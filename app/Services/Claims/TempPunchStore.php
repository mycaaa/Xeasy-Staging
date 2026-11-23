<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * OCR-STORE-01 (23 Nov 2026, "Decision for You" testing point 5): what the bill reader read is kept for punching.
 *
 * The old extract-data.php stored every read bill in temp_punch_{claim} (storeData_N) and answered its id as
 * temp_punch_id; the upload then queued it and the punch mover (PunchQueueMover) filled the punch form - hotel name,
 * check-in / check-out, address, bill no. and so on. The Laravel port answered the read fields but stored nothing
 * and gave no temp_punch_id, so since the switch every bill reached Data Punching empty and was typed by hand.
 * This stores the read bill in y{year}_temp_punch_{claim} with the same field names as before (only columns the table
 * has), plus the expense lines in y{year}_temp_expenses_detail, and returns the id.
 */
class TempPunchStore
{
    /** claim id => [bill reader key => temp_punch column], from the old storeData_N functions */
    public const MAPS = [
        1 => ['Billing Person' => 'billing_person', 'Billing address' => 'billing_address', 'Hotel Name' => 'hotel_name',
            'Hotel Contact' => 'hotel_contact', 'Hotel Email' => 'hotel_email', 'Hotel Address' => 'hotel_address', 'Bill date' => 'bill_date',
            'Bill No.' => 'bill_no', 'Arr. date/time' => 'arr_date_time', 'Dept. date/time' => 'dept_date_time', 'Duration' => 'duration',
            'Payment Mode' => 'payment_mode', 'Total Amount' => 'total_amount', 'Remark' => 'remark', 'City' => 'city_category',
            'Meal Plan' => 'meal_plan', 'GST' => 'gst_tax_rate', 'No. of PAX' => 'no_of_pax'],
        2 => ['Passenger' => 'passenger', 'Mode' => 'mode', 'Agent Name' => 'agent_name', 'PNR Number' => 'pnr_number',
            'Flight Name' => 'flight_name', 'Class' => 'class', 'Booking Date' => 'booking_date',
            'Period: (Between Booking & Journey Date)' => 'period', 'Journey from' => 'journey_from', 'Journey Upto' => 'journey_upto',
            'Travel Insurance' => 'travel_insurance', 'Total Amount' => 'total_amount', 'Remark' => 'remark',
            'Journey Date' => 'journey_date'],
        3 => ['Mode' => 'mode', 'Bill date' => 'bill_date', 'Trip From' => 'trip_from', 'Trip To' => 'trip_to', 'Total Amount' => 'total_amount', 'Remark' => 'remark'],
        4 => ['Billing Person' => 'billing_person', 'Billing address' => 'billing_address', 'Agency Name' => 'agency_name',
            'Agency Address' => 'agency_address', 'Invoice No.' => 'invoice_no', 'Date of Travel' => 'date_of_travel',
            'Journey_Start' => 'journey_start', 'Journey_End' => 'journey_end', 'Vehicle class /Registration Number' => 'vehicle_class_reg_no',
            'Charges Type' => 'charges_type', 'Total Amount' => 'total_amount', 'Remark' => 'remark'],
        5 => ['Bill Date' => 'bill_date', 'Service' => 'service', 'Bill Amount' => 'amount', 'Payment Mode' => 'payment_mode',
            'Total Amount' => 'total_amount', 'Remark' => 'remark', 'Billing Person' => 'billing_person', 'Billing Address' => 'billing_address',
            'Mobile No' => 'mobile_no', 'If Other Mobile No' => 'other_mobile_no', 'Billing Cycle' => 'billing_cycle', 'Due Date' => 'due_date',
            'Payment Detail' => 'payment_detail', 'Last Payment Detail' => 'last_payment_detail'],
        6 => ['Sender Name' => 'sender_name', 'Sender_Address' => 'sender_address', 'Provider Name' => 'provider_name',
            'Weight_Charged' => 'weight_charged', 'Docket No.' => 'docket_no', 'Booked Date' => 'booked_date', 'Receiver_Name' => 'receiver_name',
            'Receiver Address' => 'receiver_address', 'Source City' => 'source_city', 'Destination City' => 'destination_city',
            'Total Amount' => 'total_amount', 'Remark' => 'remark'],
        8 => ['Person' => 'person', 'Person Address' => 'person_address', 'Bill Date' => 'bill_date', 'Total Amount' => 'total_amount', 'Remark' => 'remark'],
        9 => ['Person' => 'person', 'Person Address' => 'person_address', 'Bill Date' => 'bill_date', 'Total Amount' => 'total_amount', 'Remark' => 'remark'],
        14 => ['Mode' => 'mode', 'Agent Name' => 'agent_name', 'PNR Number' => 'pnr_number', 'Train Name' => 'train_name', 'Class' => 'class',
            'Booking Date' => 'booking_date', 'Journey Date' => 'journey_date', 'Journey from' => 'journey_from', 'Journey Upto' => 'journey_upto',
            'Quota' => 'quota', 'Total Amount' => 'total_amount', 'Remark' => 'remark'],
        15 => ['Mode' => 'mode', 'Agent Name' => 'agent_name', 'Booking ID' => 'booking_id', 'Bus Name' => 'bus_name', 'Class' => 'class',
            'Booking Date' => 'booking_date', 'Journey Date' => 'journey_date', 'Period: (Between Booking & Journey Date)' => 'journey_period',
            'Journey from' => 'journey_from', 'Journey Upto' => 'journey_upto', 'Travel Insurance' => 'travel_insurance',
            'Total Amount' => 'total_amount', 'Remark' => 'remark'],
        16 => ['Bill Date' => 'bill_date', 'Total Amount' => 'total_amount', 'Remark' => 'remark'],
        17 => ['Bill Date' => 'bill_date', 'Total Amount' => 'total_amount', 'Remark' => 'overall_remark'],
        18 => ['Trip_Started_On' => 'trip_started_on', 'Trip_Ended_On' => 'trip_ended_on', 'Journey From' => 'journey_from', 'Journey Upto' => 'journey_upto', 'Remark' => 'remark'],
        22 => ['Bill Date' => 'bill_date', 'Crop' => 'crop', 'No. of Retailer' => 'no_of_retailer', 'Location' => 'location', 'Total Amount' => 'total_amount', 'Remark' => 'remark'],
    ];

    private const NUMERIC = ['total_amount', 'amount', 'weight_charged', 'duration', 'no_of_pax'];

    /** Store the read bill; returns the temp_punch id or null when there is nothing to store / no table. */
    public static function store(string $conn, int $yearId, int $claimId, int $empId, array $data): ?int
    {
        $table = "y{$yearId}_temp_punch_{$claimId}";
        try {
            $schema = Schema::connection($conn);
            if (! $schema->hasTable($table)) {
                return null;
            }
            $cols = array_flip($schema->getColumnListing($table));
            $types = [];
            foreach ($schema->getColumns($table) as $c) {
                $types[$c['name']] = strtolower((string) ($c['type_name'] ?? $c['type'] ?? ''));
            }
            $map = self::MAPS[$claimId] ?? ['Bill Date' => 'bill_date', 'Bill date' => 'bill_date', 'Total Amount' => 'total_amount', 'Remark' => 'remark'];
            $flat = [];
            foreach ($data as $k => $v) {
                if (is_scalar($v) || $v === null) {
                    $flat[self::key((string) $k)] = $v;
                }
            }
            $row = [];
            foreach ($map as $apiKey => $col) {
                if (! isset($cols[$col]) || ! array_key_exists(self::key($apiKey), $flat)) {
                    continue;
                }
                $v = self::value($flat[self::key($apiKey)], $col, $types[$col] ?? '');
                if ($v !== null) {
                    $row[$col] = $v;
                }
            }
            if (! $row) {
                return null;
            }
            foreach (['emp_id' => $empId, 'created_at' => now(), 'move_main' => 'N'] as $k => $v) {
                if (isset($cols[$k])) {
                    $row[$k] = $v;
                }
            }
            if (isset($cols['particulars']) && ! empty($data['Expenses'][0]['Particulars'])) {   // meals: one line per row
                $row['particulars'] = mb_substr((string) $data['Expenses'][0]['Particulars'], 0, 250);
            }
            $id = (int) DB::connection($conn)->table($table)->insertGetId($row);

            $lines = is_array($data['Expenses'] ?? null) ? $data['Expenses'] : [];
            if ($id && $lines && $schema->hasTable("y{$yearId}_temp_expenses_detail")) {
                $ins = [];
                foreach ($lines as $l) {
                    if (! is_array($l)) {
                        continue;
                    }
                    $ins[] = ['punch_id' => $id, 'claim_id' => $claimId,
                        'title' => mb_substr((string) ($l['Title'] ?? $l['Particulars'] ?? ''), 0, 250),
                        'remark' => mb_substr((string) ($l['Remark'] ?? ''), 0, 500),
                        'amount' => (float) str_replace(',', '', (string) ($l['Amount'] ?? 0))];
                }
                if ($ins) {
                    DB::connection($conn)->table("y{$yearId}_temp_expenses_detail")->insert($ins);
                }
            }

            return $id ?: null;
        } catch (\Throwable $e) {
            Log::warning('OCR-STORE-01: read bill not stored', ['claim' => $claimId, 'table' => $table, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private static function key(string $k): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $k));
    }

    private static function value($v, string $col, string $type)
    {
        if ($v === null) {
            return null;
        }
        $v = trim((string) $v);
        if ($v === '' || strtolower($v) === 'null' || strtolower($v) === 'n/a') {
            return null;
        }
        if (in_array($col, self::NUMERIC, true) || in_array($type, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'decimal', 'double', 'float'], true)) {
            $n = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $v));

            return is_numeric($n) ? ($type === 'int' || $type === 'integer' || $col === 'duration' ? (int) $n : (float) $n) : null;
        }
        if (in_array($type, ['date', 'datetime', 'timestamp'], true) || str_contains($col, 'date') || str_ends_with($col, '_on')) {
            $d = self::date($v);
            if (! $d) {
                return null;
            }

            return $type === 'date' ? substr($d, 0, 10) : $d;
        }

        return mb_substr($v, 0, 250);
    }

    /** Indian bills: day first (dd/mm/yyyy, dd-mm-yyyy, 22 Oct 2026); ISO dates as they are. */
    public static function date(string $v): ?string
    {
        $v = trim(preg_replace('/\s+/', ' ', $v));
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?/', $v, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d %02d:%02d:%02d', $m[1], $m[2], $m[3], $m[4] ?? 0, $m[5] ?? 0, $m[6] ?? 0) : null;
        }
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})(?:[ ,]+(\d{1,2})[:.](\d{2})(?::\d{2})?\s*([AaPp][Mm])?)?/', $v, $m)) {
            $y = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
            $h = (int) ($m[4] ?? 0);
            if (! empty($m[6])) {
                $h = $h % 12 + (strtolower($m[6]) === 'pm' ? 12 : 0);
            }

            return checkdate((int) $m[2], (int) $m[1], $y) ? sprintf('%04d-%02d-%02d %02d:%02d:00', $y, $m[2], $m[1], $h, $m[5] ?? 0) : null;
        }
        $t = strtotime($v);

        return $t && $t > strtotime('2000-01-01') ? date('Y-m-d H:i:s', $t) : null;
    }
}
