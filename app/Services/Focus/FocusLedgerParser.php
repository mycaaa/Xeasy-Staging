<?php

namespace App\Services\Focus;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

/**
 * FOCUS-RECON-01 (10 Nov 2026): reads an employee ledger exported from Focus ("Ledger of <name> E<code>",
 * columns Date, Voucher, Account Name, Account Code, Narration, ..., Debit, Credit, Balance, ..., Sub Ledger, ...).
 *
 * The file may hold one employee or several (one section per ledger account, each starting with the
 * account name row and an "Opening Balance" row and ending with a "Total" row). Amounts are as Focus
 * prints them from the employee's side: Debit = paid to the employee (advance, reimbursement),
 * Credit = expense booked (EMPJV / EJV credit-note vouchers). Balances are signed: Dr positive, Cr negative.
 */
class FocusLedgerParser
{
    private const COLS = [
        'date' => ['date'], 'voucher' => ['voucher'], 'account_name' => ['account name'], 'account_code' => ['account code'],
        'narration' => ['narration'], 'debit' => ['debit'], 'credit' => ['credit'], 'balance' => ['balance'],
        'account2_code' => ['account-2 code'], 'account2_name' => ['account-2 name'], 'sub_ledger' => ['sub ledger'],
        'activity' => ['activity name'], 'voucher_name' => ['voucher name'], 'cost_center' => ['cost center code'],
        'bill_no' => ['billno', 'bill no'],
    ];

    /** @return array{company: string, title: string, from: ?string, to: ?string, sections: array} */
    public function parse(string $path): array
    {
        $book = IOFactory::load($path);
        $rows = $book->getActiveSheet()->toArray(null, true, false, false);
        $company = '';
        $title = '';
        $from = $to = null;
        $head = null;
        $map = [];
        foreach ($rows as $i => $r) {
            $cells = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $r);
            $texts = array_values(array_filter($cells, fn ($v) => is_string($v) && $v !== ''));
            $lower = array_map(fn ($v) => is_string($v) ? strtolower($v) : '', $cells);
            if (in_array('date', $lower, true) && in_array('voucher', $lower, true) && in_array('debit', $lower, true)) {
                $head = $i;
                foreach (self::COLS as $key => $names) {
                    foreach ($lower as $c => $v) {
                        if (in_array($v, $names, true)) {
                            $map[$key] = $c;
                            break;
                        }
                    }
                }
                break;
            }
            foreach ($texts as $t) {
                if (preg_match('#(\d{2})/(\d{2})/(\d{4})\s+to\s+(\d{2})/(\d{2})/(\d{4})#i', $t, $m)) {
                    $from = "{$m[3]}-{$m[2]}-{$m[1]}";
                    $to = "{$m[6]}-{$m[5]}-{$m[4]}";
                } elseif (stripos($t, 'ledger of') === 0) {
                    $title = $t;
                } elseif ($company === '' && count($texts) === 1 && ! str_starts_with($t, '[')) {
                    $company = $t;
                }
            }
        }
        if ($head === null || ! isset($map['date'], $map['voucher'], $map['debit'], $map['credit'])) {
            throw new \RuntimeException('This is not a Focus ledger in the expected format: the header row (Date, Voucher, ..., Debit, Credit, Balance) was not found.');
        }

        $sections = [];
        $cur = null;
        $get = fn ($r, $k) => isset($map[$k]) ? ($r[$map[$k]] ?? null) : null;
        for ($i = $head + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $dateRaw = $get($r, 'date');
            $voucher = trim((string) $get($r, 'voucher'));
            $debit = $this->num($get($r, 'debit'));
            $credit = $this->num($get($r, 'credit'));
            $date = $this->date($dateRaw);
            $dateText = is_string($dateRaw) ? trim($dateRaw) : '';

            if ($dateText !== '' && $date === null && strcasecmp($dateText, 'total') !== 0 && $voucher === '' && $debit == 0 && $credit == 0) {
                // account (employee) section header
                if ($cur) {
                    $sections[] = $cur;
                }
                $cur = ['account' => $dateText, 'emp_code' => $this->empCode($dateText), 'opening' => null, 'lines' => [], 'total_debit' => null, 'total_credit' => null, 'closing' => null];
                continue;
            }
            if (! $cur && ($date !== null || stripos($voucher, 'opening') !== false)) {
                $cur = ['account' => $title, 'emp_code' => $this->empCode($title), 'opening' => null, 'lines' => [], 'total_debit' => null, 'total_credit' => null, 'closing' => null];
            }
            if (! $cur) {
                continue;
            }
            if (stripos($voucher, 'opening balance') !== false) {
                $bal = $this->balance($get($r, 'balance'));
                $cur['opening'] = $bal ?? round($debit - $credit, 2);
                continue;
            }
            if (strcasecmp($dateText, 'total') === 0) {
                $cur['total_debit'] = $debit;
                $cur['total_credit'] = $credit;
                $cur['closing'] = $this->balance($get($r, 'balance'));
                continue;
            }
            if ($date === null || ($debit == 0 && $credit == 0)) {
                continue;
            }
            $line = [
                'row' => $i + 1, 'date' => $date, 'voucher' => $voucher,
                'account_name' => trim((string) $get($r, 'account_name')), 'account_code' => trim((string) $get($r, 'account_code')),
                'narration' => trim((string) $get($r, 'narration')), 'debit' => $debit, 'credit' => $credit,
                'balance' => $this->balance($get($r, 'balance')), 'sub_ledger' => trim((string) $get($r, 'sub_ledger')),
                'activity' => trim((string) $get($r, 'activity')), 'voucher_name' => trim((string) $get($r, 'voucher_name')),
                'cost_center' => trim((string) $get($r, 'cost_center')), 'account2_code' => trim((string) $get($r, 'account2_code')),
            ];
            // Focus prints the sub-ledger name in "Sub Ledger" and its code (SLC-0270) in "Cost Center Code";
            // Xeasy's mapping (fc_claim_focus_mapping.focus_name_subl) holds the code.
            $line['sub_code'] = preg_match('/^SLC-\d+$/i', $line['cost_center']) ? strtoupper($line['cost_center']) : $line['sub_ledger'];
            if (! $cur['emp_code'] && $line['account2_code'] !== '') {
                $cur['emp_code'] = $this->empCode($line['account2_code']);
            }
            $line += $this->classify($line);
            $cur['lines'][] = $line;
        }
        if ($cur) {
            $sections[] = $cur;
        }
        foreach ($sections as &$s) {
            $s['opening'] = $s['opening'] ?? 0.0;
            $dr = array_sum(array_column($s['lines'], 'debit'));
            $cr = array_sum(array_column($s['lines'], 'credit'));
            $s['sum_debit'] = round($dr, 2);
            $s['sum_credit'] = round($cr, 2);
            $s['computed_closing'] = round($s['opening'] + $dr - $cr, 2);
            $s['closing'] = $s['closing'] ?? $s['computed_closing'];
            if (! $from && $s['lines']) {
                $from = min(array_column($s['lines'], 'date'));
            }
            if (! $to && $s['lines']) {
                $to = max(array_column($s['lines'], 'date'));
            }
        }

        return ['company' => $company, 'title' => $title, 'from' => $from, 'to' => $to, 'sections' => $sections];
    }

    /** kind: advance | reimbursement | bank | expense_jv | other; plus the month the line is for, when the narration says. */
    public function classify(array $l): array
    {
        $n = strtolower($l['narration']);
        $isBank = str_starts_with(strtoupper($l['account_code']), 'BANK') || preg_match('#\bBP/#', $l['voucher']) || stripos($l['voucher_name'], 'bank') !== false;
        $isJv = (bool) preg_match('#JV#i', $l['voucher']) || stripos($l['voucher_name'], 'JV') !== false;
        $kind = 'other';
        if ($isBank && $l['debit'] > 0) {
            $kind = str_contains($n, 'advance') || str_contains($n, 'imprest') ? 'advance' : (str_contains($n, 'reimburse') || str_contains($n, 'expense') ? 'reimbursement' : 'bank');
        } elseif ($isJv && $l['credit'] > 0) {
            $kind = 'expense_jv';
        }
        $month = null;
        $fyStart = null;
        $months = ['january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12];
        if (preg_match('/\b(january|february|march|april|may|june|july|august|september|october|november|december)\s+(\d{4})\s*-\s*(\d{4})/i', $l['narration'], $m)) {
            $month = $months[strtolower($m[1])];
            $fyStart = (int) $m[2];
        } elseif (preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*[\s\-\/]+(\d{2,4})\b/i', $l['narration'], $m)) {
            foreach ($months as $name => $num) {
                if (str_starts_with($name, strtolower($m[1]))) {
                    $month = $num;
                    break;
                }
            }
            $yy = (int) $m[2];
            $yy = $yy < 100 ? 2000 + $yy : $yy;
            $fyStart = $month >= 4 ? $yy : $yy - 1;
        }
        $docNo = preg_match('#EMPJV/[\d\-]+/(\d+)#i', $l['voucher'], $v) ? $v[1] : null;

        return ['kind' => $kind, 'for_month' => $month, 'for_fy_start' => $fyStart, 'jv_doc_no' => $docNo];
    }

    private function empCode(string $s): ?string
    {
        if (preg_match_all('/\b[A-Z]{0,3}(\d{1,7})\b/', strtoupper($s), $m)) {
            return ltrim((string) end($m[1]), '0') ?: null;
        }

        return null;
    }

    private function num($v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }
        if (is_numeric($v)) {
            return round((float) $v, 2);
        }

        return round((float) str_replace([',', ' '], '', (string) $v), 2);
    }

    /** "5,825.00 Dr" -> 5825.0, "2,158.00 Cr" -> -2158.0 */
    private function balance($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        $s = strtoupper(trim((string) $v));
        $n = (float) str_replace([',', ' ', 'DR', 'CR'], '', $s);

        return str_ends_with($s, 'CR') ? -$n : $n;
    }

    private function date($v): ?string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_numeric($v) && $v > 20000 && $v < 80000) {
            return XlDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }
        if (is_string($v) && preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})#', trim($v), $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (is_string($v) && preg_match('#^(\d{4})-(\d{2})-(\d{2})#', trim($v), $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return null;
    }
}
