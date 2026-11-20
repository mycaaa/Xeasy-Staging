<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ODO-PUNCH-01 (20 Nov 2026, tracker tab "Decision for You" #3): an odometer reading read by the system from the
 * photo (OCR, y{N}_temp_punch_7) goes straight on to verification, as before. When the employee changes the
 * reading by hand (it differs from what the system read, or the system could not read it) the claim first comes
 * to the punching team (Data Punch > Odometer readings changed by employee); it cannot be verified until punched.
 * Bills are unchanged: every bill is punched by the team before verification.
 *
 * Kept in odo_manual_readings (main database) by connection + year + ExpId, so the year tables are not altered.
 */
class OdoManualPunch
{
    public static function ready(): bool
    {
        try {
            return Schema::connection(self::home())->hasTable('odo_manual_readings') && (bool) config('claim_process.odo_manual_to_punch', true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function home(): string
    {
        return (string) config('database.default');
    }

    /**
     * After the employee saves the closing reading: compare with what the system read from the photos.
     * Returns true when the claim was sent to the punching team.
     */
    public static function check(string $conn, int $yearId, int $expId, int $empId, string $billDate, int $tempPunchId, $opening, $closing): bool
    {
        if (! self::ready() || $expId <= 0) {
            return false;
        }
        try {
            $ocr = $tempPunchId > 0 ? DB::connection($conn)->table("y{$yearId}_temp_punch_7")->where('id', $tempPunchId)->first(['opening', 'closeing']) : null;
            $oOpen = $ocr ? (int) round((float) $ocr->opening) : 0;
            $oClose = $ocr ? (int) round((float) $ocr->closeing) : 0;
            $eOpen = (int) round((float) $opening);
            $eClose = (int) round((float) $closing);
            $reasons = [];
            if (! $ocr) {
                $reasons[] = 'readings typed without the system reading the photos';
            } else {
                if ($oOpen <= 0 && $eOpen > 0) {
                    $reasons[] = 'opening could not be read from the photo';
                } elseif ($oOpen > 0 && $eOpen !== $oOpen) {
                    $reasons[] = "opening changed from {$oOpen} to {$eOpen}";
                }
                if ($oClose <= 0) {
                    $reasons[] = 'closing could not be read from the photo';
                } elseif ($eClose !== $oClose) {
                    $reasons[] = "closing changed from {$oClose} to {$eClose}";
                }
            }
            $db = DB::connection(self::home());
            $key = ['conn' => $conn, 'year_id' => $yearId, 'exp_id' => $expId];
            $row = $db->table('odo_manual_readings')->where($key)->first();
            if (! $reasons) {
                // read by the system and not changed: nothing to punch (an earlier pending entry is closed)
                if ($row && $row->status === 'pending') {
                    $db->table('odo_manual_readings')->where('id', $row->id)->update(['status' => 'not_needed', 'updated_at' => now(),
                        'remark' => 'Employee re-saved the readings as read by the system']);
                }

                return false;
            }
            $data = ['emp_id' => $empId, 'bill_date' => $billDate, 'ocr_opening' => $ocr ? $oOpen : null, 'ocr_closing' => $ocr ? $oClose : null,
                'entered_opening' => $eOpen, 'entered_closing' => $eClose, 'reason' => mb_substr(implode('; ', $reasons), 0, 255),
                'status' => 'pending', 'punched_by' => null, 'punched_at' => null, 'updated_at' => now()];
            if ($row) {
                $db->table('odo_manual_readings')->where('id', $row->id)->update($data);
            } else {
                $db->table('odo_manual_readings')->insert($key + $data + ['created_at' => now()]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('odo manual punch check failed', ['exp' => $expId, 'e' => $e->getMessage()]);

            return false;
        }
    }

    /** ExpIds (of these) waiting for the punching team. */
    public static function pendingIds(string $conn, int $yearId, array $expIds): array
    {
        if (! self::ready() || ! $expIds) {
            return [];
        }

        return DB::connection(self::home())->table('odo_manual_readings')->where('conn', $conn)->where('year_id', $yearId)
            ->whereIn('exp_id', array_map('intval', $expIds))->where('status', 'pending')->pluck('exp_id')->map(fn ($v) => (int) $v)->all();
    }
}
