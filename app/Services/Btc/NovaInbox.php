<?php

namespace App\Services\Btc;

use App\Models\BtcCase;
use App\Models\BtcCaseLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-REQ-07..09 (16 Nov 2026): invoices received from Nova (DMS) and their link to the BTC request.
 *
 * Six conditions are told apart (sheet "BTC requirements", page 7):
 *   1 not_at_nova     Nova has not received the invoice: an open request whose service ended (config
 *                     btc.nova.expected_after_days ago) and has no bill and no Nova invoice yet;
 *   2 received        Nova has received it but not digitised it        (nova_status = received)
 *   3 digitised       Nova has digitised it but not transferred it      (nova_status = digitised)
 *   4 import_failed   Xeasy received it but could not import it         (import_status = failed)
 *   5 unmatched       imported but not matched to the originating request
 *   6 linked          available and linked to its request               (match_status = matched)
 *
 * Nova sends records (one or a list) with: document_id, invoice_no, invoice_date, vendor_name, vendor_gstin,
 * taxable_amount, tax_amount, gross_amount, document_url, received_on, status (received / digitised / transferred),
 * and optionally request_id (the BTC case number) and company_id. The CSV import takes the same column names.
 */
class NovaInbox
{
    public const STATES = [
        'not_at_nova' => 'Nova has not received the invoice',
        'received' => 'Received by Nova, not digitised',
        'digitised' => 'Digitised by Nova, not transferred',
        'import_failed' => 'Received by Xeasy, not imported',
        'unmatched' => 'Imported, not matched to its request',
        'linked' => 'Available and linked',
    ];

    private const ALIASES = [
        'nova_document_id' => ['document_id', 'nova_document_id', 'doc_id', 'documentid', 'dms_doc_no'],
        'invoice_no' => ['invoice_no', 'invoice_number', 'bill_no', 'invoiceno'],
        'invoice_date' => ['invoice_date', 'bill_date', 'invoicedate'],
        'vendor_name' => ['vendor_name', 'vendor', 'supplier', 'supplier_name'],
        'vendor_gstin' => ['vendor_gstin', 'gstin', 'gst_no', 'supplier_gstin'],
        'taxable_amount' => ['taxable_amount', 'taxable', 'taxable_value'],
        'tax_amount' => ['tax_amount', 'tax', 'gst', 'gst_amount'],
        'gross_amount' => ['gross_amount', 'gross', 'total', 'invoice_amount', 'amount'],
        'document_url' => ['document_url', 'document_link', 'url', 'link'],
        'received_on' => ['received_on', 'received_date', 'receivedon'],
        'nova_status' => ['status', 'nova_status'],
        'request_id' => ['request_id', 'btc_request_id', 'case_no', 'btc_case_no'],
        'company_id' => ['company_id', 'comid'],
    ];

    /** Normalise one record (any of the column spellings above). */
    public static function normalise(array $r): array
    {
        $low = [];
        foreach ($r as $k => $v) {
            $low[strtolower(trim(str_replace([' ', '-'], '_', (string) $k)))] = is_string($v) ? trim($v) : $v;
        }
        $out = [];
        foreach (self::ALIASES as $field => $names) {
            foreach ($names as $n) {
                if (array_key_exists($n, $low) && $low[$n] !== '' && $low[$n] !== null) {
                    $out[$field] = $low[$n];
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * Import records from Nova (API) or a CSV export. Every call is logged (Integration Monitoring).
     *
     * @return array{log_id:int, received:int, imported:int, updated:int, duplicates:int, failed:int, errors:string[]}
     */
    public function ingest(array $records, string $channel, ?string $rawBody = null, int $defaultCompany = 1): array
    {
        $res = ['received' => count($records), 'imported' => 0, 'updated' => 0, 'duplicates' => 0, 'failed' => 0, 'errors' => []];
        $logId = DB::table('btc_integration_logs')->insertGetId([
            'channel' => $channel, 'status' => 'success', 'received' => count($records), 'request_body' => $rawBody ? mb_substr($rawBody, 0, 200000) : null,
            'ip' => request()?->ip(), 'user_id' => auth()->id(), 'created_at' => now(),
        ]);
        foreach ($records as $i => $raw) {
            $r = self::normalise((array) $raw);
            $doc = mb_substr((string) ($r['nova_document_id'] ?? ''), 0, 80);
            if ($doc === '') {
                $res['failed']++;
                $res['errors'][] = 'Record '.($i + 1).': no document id';
                continue;
            }
            $status = strtolower((string) ($r['nova_status'] ?? 'transferred'));
            $status = in_array($status, ['received', 'digitised', 'digitized', 'transferred'], true) ? str_replace('digitized', 'digitised', $status) : 'transferred';
            $num = fn ($k) => isset($r[$k]) && $r[$k] !== '' ? (is_numeric(str_replace(',', '', (string) $r[$k])) ? round((float) str_replace(',', '', (string) $r[$k]), 2) : 'bad') : null;
            $date = function ($k) use ($r) {
                if (empty($r[$k])) {
                    return null;
                }
                $t = strtotime(str_replace('/', '-', (string) $r[$k]));

                return $t ? date('Y-m-d', $t) : 'bad';
            };
            $row = [
                'company_id' => (int) ($r['company_id'] ?? $defaultCompany) ?: $defaultCompany,
                'source' => $channel === 'csv_import' ? 'csv' : 'nova',
                'nova_document_id' => $doc,
                'invoice_no' => isset($r['invoice_no']) ? mb_substr((string) $r['invoice_no'], 0, 60) : null,
                'invoice_date' => $date('invoice_date'),
                'vendor_name' => isset($r['vendor_name']) ? mb_substr((string) $r['vendor_name'], 0, 200) : null,
                'vendor_gstin' => isset($r['vendor_gstin']) ? strtoupper(mb_substr(preg_replace('/\s+/', '', (string) $r['vendor_gstin']), 0, 20)) : null,
                'taxable_amount' => $num('taxable_amount'), 'tax_amount' => $num('tax_amount'), 'gross_amount' => $num('gross_amount'),
                'document_url' => isset($r['document_url']) ? mb_substr((string) $r['document_url'], 0, 500) : null,
                'received_on' => $date('received_on'),
                'nova_status' => $status,
                'payload' => json_encode($raw, JSON_UNESCAPED_UNICODE),
                'log_id' => $logId,
            ];
            // a transferred invoice must carry what Finance needs; otherwise it is "received by Xeasy, not imported"
            $problems = [];
            foreach (['taxable_amount', 'tax_amount', 'gross_amount', 'invoice_date', 'received_on'] as $k) {
                if ($row[$k] === 'bad') {
                    $problems[] = "{$k} is not valid";
                    $row[$k] = null;
                }
            }
            if ($status === 'transferred') {
                if (! $row['invoice_no']) {
                    $problems[] = 'invoice number missing';
                }
                if (! $row['gross_amount']) {
                    $problems[] = 'gross amount missing';
                }
                if (! $row['document_url']) {
                    $problems[] = 'document link missing';
                }
            }
            $row['import_status'] = $problems ? 'failed' : 'imported';
            $row['import_error'] = $problems ? mb_substr(implode('; ', $problems), 0, 500) : null;

            $existing = DB::table('btc_invoices')->where('nova_document_id', $doc)->first();
            if ($existing) {
                $same = true;
                foreach (['invoice_no', 'invoice_date', 'vendor_gstin', 'gross_amount', 'document_url', 'nova_status', 'import_status'] as $k) {
                    if ((string) $existing->$k !== (string) $row[$k] && ! ($k === 'gross_amount' && (float) $existing->$k === (float) $row[$k])) {
                        $same = false;
                    }
                }
                if ($same && ! $problems) {
                    $res['duplicates']++;
                    continue;
                }
                if ($same && $problems) {
                    // sent again (or retried) with the same faults: still not imported
                    $res['failed']++;
                    $res['errors'][] = "{$doc}: ".implode('; ', $problems);
                    continue;
                }
                DB::table('btc_invoices')->where('id', $existing->id)->update($row + ['updated_at' => now()]);
                $id = (int) $existing->id;
                $res['updated']++;
            } else {
                $id = (int) DB::table('btc_invoices')->insertGetId($row + ['match_status' => 'unmatched', 'created_at' => now(), 'updated_at' => now()]);
                $res['imported']++;
            }
            if ($problems) {
                $res['failed']++;
                $res['errors'][] = "{$doc}: ".implode('; ', $problems);
                continue;
            }
            $inv = DB::table('btc_invoices')->where('id', $id)->first();
            if ($inv->nova_status === 'transferred' && $inv->match_status !== 'matched') {
                $this->autoMatch($inv, $r['request_id'] ?? null);
            }
        }
        $status = $res['failed'] === 0 ? 'success' : ($res['failed'] < $res['received'] ? 'partial' : 'failure');
        DB::table('btc_integration_logs')->where('id', $logId)->update([
            'status' => $status, 'imported' => $res['imported'], 'updated' => $res['updated'], 'duplicates' => $res['duplicates'],
            'failed' => $res['failed'], 'error' => $res['errors'] ? mb_substr(implode(' | ', $res['errors']), 0, 1000) : null,
        ]);

        return $res + ['log_id' => $logId];
    }

    /** Re-import the failed invoices of a log (or all failed ones) from their stored payload. */
    public function retry(?int $logId = null): array
    {
        $q = DB::table('btc_invoices')->where('import_status', 'failed');
        if ($logId) {
            $q->where('log_id', $logId);
        }
        $records = $q->pluck('payload')->map(fn ($p) => json_decode((string) $p, true) ?: [])->all();

        return $records ? $this->ingest($records, 'retry', null) : ['log_id' => 0, 'received' => 0, 'imported' => 0, 'updated' => 0, 'duplicates' => 0, 'failed' => 0, 'errors' => []];
    }

    /** Link straight away when Nova names the request, or when one case scores at the auto-link level. */
    private function autoMatch(object $inv, ?string $requestId): void
    {
        if ($requestId) {
            $case = BtcCase::where('case_no', $requestId)->first();
            if ($case) {
                $this->link($inv->id, $case->id, null, 100, 'Request id sent by Nova');

                return;
            }
        }
        $best = $this->suggest($inv)[0] ?? null;
        $auto = (int) config('btc.nova.auto_link', 90);
        $from = (int) config('btc.nova.suggest_from', 40);
        if ($best && $best['score'] >= $auto) {
            $this->link($inv->id, $best['case']->id, null, $best['score'], 'Auto-linked: '.$best['why']);
        } elseif ($best && $best['score'] >= $from) {
            DB::table('btc_invoices')->where('id', $inv->id)->update(['match_status' => 'suggested', 'suggested_case_id' => $best['case']->id, 'match_confidence' => $best['score'], 'updated_at' => now()]);
        } else {
            DB::table('btc_invoices')->where('id', $inv->id)->update(['match_status' => 'unmatched', 'suggested_case_id' => null, 'match_confidence' => $best['score'] ?? null, 'updated_at' => now()]);
        }
    }

    /**
     * Candidate requests for an invoice, best first, each with a 0-100 score and the reasons:
     * GSTIN 35, vendor name up to 20, amount up to 30 (within 1 / 5 / 15 %), invoice no = bill no 10, date in the
     * activity window 5.
     *
     * @return array<int, array{case: BtcCase, score: int, why: string}>
     */
    public function suggest(object $inv, int $limit = 5): array
    {
        $linked = DB::table('btc_invoices')->where('match_status', 'matched')->whereNotNull('btc_case_id')->pluck('btc_case_id')->all();
        $cases = BtcCase::where('company_id', (int) $inv->company_id)
            ->whereNotIn('status', ['cancelled', 'rejected', 'draft', 'closed'])
            ->whereNotIn('id', $linked ?: [0])
            ->orderByDesc('id')->limit(2000)->get();
        $name = fn ($s) => trim(preg_replace('/\b(pvt|private|ltd|limited|llp|the|and|&)\b|[^a-z0-9 ]/', ' ', strtolower((string) $s)));
        $out = [];
        foreach ($cases as $c) {
            $score = 0;
            $why = [];
            if ($inv->vendor_gstin && $c->vendor_gstin && strtoupper($c->vendor_gstin) === strtoupper($inv->vendor_gstin)) {
                $score += 35;
                $why[] = 'GSTIN';
            }
            if ($inv->vendor_name && $c->vendor_name) {
                similar_text($name($inv->vendor_name), $name($c->vendor_name), $pct);
                if ($pct >= 60) {
                    $score += (int) round(min(20, $pct / 5));
                    $why[] = 'vendor '.round($pct).'%';
                }
            }
            $gross = (float) $inv->gross_amount;
            $ref = (float) ($c->bill_amount ?: $c->booking_amount ?: $c->quotation_amount ?: $c->estimated_amount);
            if ($gross > 0 && $ref > 0) {
                $d = abs($gross - $ref) / $ref;
                $pts = $d <= 0.01 ? 30 : ($d <= 0.05 ? 20 : ($d <= 0.15 ? 10 : 0));
                if ($pts) {
                    $score += $pts;
                    $why[] = 'amount '.($d <= 0.01 ? 'equal' : '±'.round($d * 100).'%');
                }
            }
            if ($inv->invoice_no && $c->bill_no && strcasecmp(trim($inv->invoice_no), trim($c->bill_no)) === 0) {
                $score += 10;
                $why[] = 'bill no.';
            }
            if ($inv->invoice_date && $c->service_from) {
                $from = $c->service_from->copy()->subDays(7);
                $to = ($c->service_to ?: $c->service_from)->copy()->addDays(30);
                if ($inv->invoice_date >= $from->toDateString() && $inv->invoice_date <= $to->toDateString()) {
                    $score += 5;
                    $why[] = 'date';
                }
            }
            if ($score > 0) {
                $out[] = ['case' => $c, 'score' => min(100, $score), 'why' => implode(', ', $why)];
            }
        }
        usort($out, fn ($a, $b) => $b['score'] <=> $a['score'] ?: $b['case']->id <=> $a['case']->id);

        return array_slice($out, 0, $limit);
    }

    /** Link an invoice to a request; fills the bill on the request where it is still empty. */
    public function link(int $invoiceId, int $caseId, $user, ?int $confidence = null, ?string $why = null): void
    {
        $inv = DB::table('btc_invoices')->where('id', $invoiceId)->first();
        $c = BtcCase::findOrFail($caseId);
        DB::transaction(function () use ($inv, $c, $user, $confidence, $why) {
            DB::table('btc_invoices')->where('id', $inv->id)->update([
                'btc_case_id' => $c->id, 'match_status' => 'matched', 'match_confidence' => $confidence, 'suggested_case_id' => null,
                'mismatch_note' => null, 'matched_by' => $user->id ?? null, 'matched_at' => now(), 'updated_at' => now(),
            ]);
            $c->nova_document_id = $inv->nova_document_id;
            $c->dms_doc_no = $c->dms_doc_no ?: $inv->nova_document_id;
            if (! $c->bill_no) {
                $c->bill_no = $inv->invoice_no;
                $c->bill_date = $inv->invoice_date;
                $c->taxable_amount = $inv->taxable_amount;
                $c->gst_amount = $inv->tax_amount ?? 0;
                $c->bill_amount = $inv->gross_amount;
            }
            if (! $c->vendor_gstin && $inv->vendor_gstin) {
                $c->vendor_gstin = $inv->vendor_gstin;
            }
            $c->save();
            BtcCaseLog::create(['btc_case_id' => $c->id, 'action' => 'invoice_linked', 'from_status' => $c->status, 'to_status' => $c->status,
                'remark' => "Nova invoice {$inv->nova_document_id} ({$inv->invoice_no}, Rs ".number_format((float) $inv->gross_amount, 2).') linked'.($why ? " - {$why}" : ''),
                'user_id' => $user->id ?? null, 'user_name' => $user->name ?? 'Nova sync']);
        });
    }

    public function unlink(int $invoiceId, $user, string $note): void
    {
        $inv = DB::table('btc_invoices')->where('id', $invoiceId)->first();
        DB::transaction(function () use ($inv, $user, $note) {
            if ($inv->btc_case_id && ($c = BtcCase::find($inv->btc_case_id))) {
                if ($c->nova_document_id === $inv->nova_document_id) {
                    $c->nova_document_id = null;
                    $c->save();
                }
                BtcCaseLog::create(['btc_case_id' => $c->id, 'action' => 'invoice_unlinked', 'from_status' => $c->status, 'to_status' => $c->status,
                    'remark' => "Nova invoice {$inv->nova_document_id} unlinked: {$note}", 'user_id' => $user->id ?? null, 'user_name' => $user->name ?? null]);
            }
            DB::table('btc_invoices')->where('id', $inv->id)->update(['btc_case_id' => null, 'match_status' => 'mismatch', 'mismatch_note' => mb_substr($note, 0, 500),
                'matched_by' => $user->id ?? null, 'matched_at' => now(), 'updated_at' => now()]);
        });
    }

    /** Requests waiting for an invoice that Nova has not received (state 1). */
    public static function notAtNova(int $companyId)
    {
        $days = (int) config('btc.nova.expected_after_days', 7);
        $withInvoice = Schema::hasTable('btc_invoices') ? DB::table('btc_invoices')->whereNotNull('btc_case_id')->pluck('btc_case_id')->all() : [];

        return BtcCase::where('company_id', $companyId)->whereIn('status', ['booked', 'advance_paid'])
            ->whereNull('bill_no')->whereNotIn('id', $withInvoice ?: [0])
            ->where(fn ($q) => $q->whereDate('service_to', '<=', now()->subDays($days))
                ->orWhere(fn ($w) => $w->whereNull('service_to')->whereDate('service_from', '<=', now()->subDays($days))))
            ->orderBy('service_to')->get();
    }

    /** Counts for the six states. */
    public static function counts(int $companyId): array
    {
        $q = fn () => DB::table('btc_invoices')->where('company_id', $companyId);

        return [
            'not_at_nova' => self::notAtNova($companyId)->count(),
            'received' => $q()->where('nova_status', 'received')->count(),
            'digitised' => $q()->where('nova_status', 'digitised')->count(),
            'import_failed' => $q()->where('nova_status', 'transferred')->where('import_status', 'failed')->count(),
            'unmatched' => $q()->where('nova_status', 'transferred')->where('import_status', 'imported')->where('match_status', '!=', 'matched')->count(),
            'linked' => $q()->where('match_status', 'matched')->count(),
        ];
    }
}
