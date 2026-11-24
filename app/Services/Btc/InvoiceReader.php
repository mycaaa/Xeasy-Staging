<?php

namespace App\Services\Btc;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

/**
 * BTC-READER-01 (18 Nov 2026): read a vendor invoice inside Xeasy instead of waiting for Nova.
 *
 * Uses the same bill-reading service as the expense claims (api_list: lodging, hired vehicle, airfare, rail,
 * miscellaneous ...). The service fetches the file from a short-lived signed link to this app (15 minutes,
 * route btc.reader.file), so the file never leaves our storage for longer than the read. The answer is turned into
 * the BTC bill fields: invoice no., date, vendor, GSTIN, taxable, GST, total, PNR / sector, pax.
 * Nothing is saved on a case from here; the caller shows the fields for the user to check.
 */
class InvoiceReader
{
    /** BTC category => reader endpoint (same service the claim screens use) */
    // BTC-SCAN-01 (19 Nov 2026, "Data Punching Form & Finance approval screens" tab): the scan_ocr endpoints of the
    // reading service - hired vehicle, air (also used for bus), rail, lodging, meals, miscellaneous
    public const ENDPOINTS = [
        'hotel' => 'scan_ocr/lodging', 'vehicle' => 'scan_ocr/hiredVehicle', 'air' => 'scan_ocr/air', 'train' => 'scan_ocr/rail',
        'bus' => 'scan_ocr/air', 'meals' => 'scan_ocr/meals', 'meeting' => 'scan_ocr/miscellaneous', 'other' => 'scan_ocr/miscellaneous',
        'boarding_pass' => 'boardingPass',
    ];

    public static function endpoint(string $category): string
    {
        $base = rtrim((string) config('btc.reader.base_url', 'https://api-gf4tdduqha-uc.a.run.app/api/v1'), '/');

        return $base.'/'.(self::ENDPOINTS[$category] ?? 'scan_ocr/miscellaneous').'/extractFields';
    }

    /** A 15-minute signed link the reading service can fetch the stored file from (local disk path under btc/). */
    public static function fileUrl(string $path): string
    {
        // the reader expects a file-like address (ending in .pdf / .jpg ...)
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $ext = in_array($ext, ['pdf', 'jpg', 'png', 'webp', 'heic'], true) ? $ext : 'pdf';
        // BTC-READER-02 (24 Nov 2026): the link is signed on its path only (signed:relative), so it stays valid whatever
        // scheme / host the reading service uses to fetch it. On xeasy the reader got "403" for every bill: the link was
        // signed for one address (APP_URL, http) and arrived as another (https), so the signature check refused it.
        $rel = URL::temporarySignedRoute('btc.reader.file', now()->addMinutes(15), ['n' => 'invoice.'.$ext, 'f' => self::token($path)], false);
        // the reading service must reach this app from the internet: a public base address may differ from APP_URL
        $base = rtrim((string) config('btc.reader.public_base', ''), '/');
        if ($base === '') {
            $base = (app()->runningInConsole() || ! request()->getHost()) ? rtrim((string) config('app.url'), '/') : request()->getSchemeAndHttpHost();
        }

        return $base.$rel;
    }

    /**
     * BTC-READER-02: the encrypted file path as URL-safe characters only (A-Z a-z 0-9 - _). The plain encrypted text
     * has "+", "/" and "=", which a fetching service may decode or re-encode differently, so the signed address no
     * longer matched and xeasy answered 403.
     */
    public static function token(string $path): string
    {
        return rtrim(strtr(base64_encode(Crypt::encryptString($path)), '+/', '-_'), '=');
    }

    public static function untoken(string $t): string
    {
        $raw = base64_decode(strtr($t, '-_', '+/'), true);

        return Crypt::decryptString($raw !== false && str_starts_with($raw, 'eyJ') ? $raw : $t);   // older links: plain
    }

    /**
     * @return array{ok:bool, fields:array, raw:array, error:?string}
     */
    public function read(string $path, string $category): array
    {
        if (! config('btc.reader.enabled', true)) {
            return ['ok' => false, 'fields' => [], 'raw' => [], 'error' => 'Invoice reading is switched off (BTC Settings).'];
        }
        try {
            $link = self::fileUrl($path);
            $res = Http::timeout((int) config('btc.reader.timeout', 60))
                ->withOptions(['verify' => \App\Support\TlsVerify::option()])
                ->post(self::endpoint($category), ['fileUrl' => $link]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'fields' => [], 'raw' => [], 'error' => 'The invoice reader could not be reached. Enter the details by hand.'];
        }
        $j = $res->json() ?: [];
        $data = is_array($j['data'] ?? null) ? $j['data'] : (is_array($j['response'] ?? null) ? $j['response'] : []);
        if (! $res->successful() || ! $data) {
            // BTC-READER-02: say which address the reader was given, so a link it cannot open (403 / 404) can be traced
            $at = parse_url($link, PHP_URL_SCHEME).'://'.parse_url($link, PHP_URL_HOST).(parse_url($link, PHP_URL_PORT) ? ':'.parse_url($link, PHP_URL_PORT) : '');

            return ['ok' => false, 'fields' => [], 'raw' => $j, 'error' => 'The invoice could not be read ('.($j['message'] ?? 'HTTP '.$res->status()).'; bill fetched from '.$at.'). Enter the details by hand.'];
        }
        $f = self::normalise($data);

        return ['ok' => true, 'fields' => $f, 'raw' => $data, 'error' => null];
    }

    /** The reader's field names (they differ per bill type) to the BTC bill fields. */
    public static function normalise(array $d): array
    {
        $low = [];
        foreach ($d as $k => $v) {
            $low[strtolower(trim((string) $k))] = $v;
        }
        $pick = function (array $keys) use ($low) {
            foreach ($keys as $k) {
                $v = $low[strtolower($k)] ?? null;
                if (is_scalar($v) && trim((string) $v) !== '') {
                    return trim((string) $v);
                }
            }

            return null;
        };
        $num = function (?string $s): ?float {
            if ($s === null) {
                return null;
            }
            $s = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $s));

            return is_numeric($s) ? round((float) $s, 2) : null;
        };
        $date = function (?string $s): ?string {
            if (! $s) {
                return null;
            }
            $s = trim(preg_replace('/\s+\d{1,2}:\d{2}.*$/', '', $s));
            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m)) {
                return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
            }
            if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2,4})$/', $s, $m)) {   // Indian bills: day first
                $y = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];

                return checkdate((int) $m[2], (int) $m[1], $y) ? sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]) : null;
            }
            $t = strtotime($s);

            return $t ? date('Y-m-d', $t) : null;
        };

        $gross = $num($pick(['Grand Total', 'Total Amount', 'Total Fare', 'Invoice Total', 'Net Amount', 'Total', 'Amount']));
        $tax = $num($pick(['GST Amount', 'Tax Amount', 'Total Tax', 'Total GST', 'IGST', 'GST']));
        $rateRaw = $pick(['GST/Tax Rate', 'GST Rate', 'Tax Rate', 'GST (%)']);
        $rate = $rateRaw !== null && preg_match('/(\d+(?:\.\d+)?)/', $rateRaw, $m) ? (float) $m[1] : null;
        $taxable = $num($pick(['Taxable Amount', 'Taxable Value', 'Sub Total', 'Subtotal', 'Base Fare', 'Basic Amount']));
        if ($gross !== null) {
            if ($taxable === null && $tax !== null && $tax < $gross) {
                $taxable = round($gross - $tax, 2);
            } elseif ($taxable === null && $rate !== null && $rate > 0 && $rate <= 28) {
                $taxable = round($gross / (1 + $rate / 100), 2);
            }
            if ($taxable !== null && $tax === null) {
                $tax = round($gross - $taxable, 2);
            }
        }
        $gstin = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $pick(['GST Number', 'GSTIN', 'Vendor GSTIN', 'Supplier GSTIN', 'GST No'])));
        if (! preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/', $gstin)) {
            $gstin = null;
        }
        $from = $pick(['From', 'Journey From', 'Origin', 'Journey_Start', 'Source']);
        $to = $pick(['To', 'Journey Upto', 'Journey To', 'Destination', 'Journey_End']);
        $emps = is_array($low['employees'] ?? null) ? count(array_filter($low['employees'])) : 0;
        $sector = $pick(['Sector', 'Route']) ?: ($from && $to ? $from.' - '.$to : null);

        return array_filter([
            'bill_no' => ($n = $pick(['Bill No.', 'Bill No', 'Invoice No.', 'Invoice No', 'Invoice Number', 'Bill Number', 'Voucher No', 'Ticket No.', 'Ticket Number', 'PNR', 'PNR No.', 'PNR Number', 'Booking ID'])) ? mb_substr($n, 0, 60) : null,
            'bill_date' => $date($pick(['Bill date', 'Invoice Date', 'Bill Date', 'Voucher Date', 'Booking Date', 'Date of Booking', 'Date of Issue', 'Date of Travel', 'Date of Journey', 'Journey Date', 'Travel Date', 'Date'])),
            'vendor_name' => ($v = $pick(['Hotel Name', 'Agency Name', 'Agent Name', 'Vendor Name', 'Vendor', 'Supplier Name', 'Seller Name', 'Airline', 'Airline Name', 'Operator', 'Service Provider', 'Company Name', 'Company'])) ? mb_substr($v, 0, 200) : null,
            'vendor_gstin' => $gstin,
            'taxable_amount' => $taxable,
            'gst_amount' => $tax,
            'bill_amount' => $gross,
            'pnr' => ($p = $pick(['PNR', 'PNR No.', 'PNR Number', 'Booking Reference'])) ? mb_substr($p, 0, 20) : null,
            'travel_sector' => $sector ? mb_substr($sector, 0, 100) : null,
            'travel_date' => $date($pick(['Date of Travel', 'Date of Journey', 'Journey Date', 'Travel Date', 'Departure Date', 'Chk. In Date', 'Arr. date/time'])),
            'pax_count' => ($x = (int) $num($pick(['No. of pax', 'No. of Pax', 'Pax', 'No. of Passengers']))) > 0 ? $x : ($emps ?: null),
        ], fn ($v) => $v !== null && $v !== '');
    }
}
