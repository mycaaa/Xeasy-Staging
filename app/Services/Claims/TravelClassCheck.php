<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;

/**
 * POL-08: travel class checked against the employee's eligibility in HRIMS.
 *
 * Rail fare (ClaimId 14): the class on the bill (SL, 3AC, 2AC, 1AC, CC, ...) is ranked and compared
 * with Train_Class (or TravelClass_Outside_Hq) of the active eligibility row.
 * Air fare (ClaimId 2): flagged when the eligibility does not allow flights, when flights are
 * "approval based" (the approval is not in Xeasy, so the verifier checks it), or when the class is
 * above Economy.
 * Upgraded tickets need no prior approval at present (management, 7 Nov 2026), so nothing is
 * blocked: punching gets a warning and the claim view a panel for the verifier.
 */
class TravelClassCheck
{
    public const RAIL = 14;

    public const AIR = 2;

    /** Rank of a rail class written on a bill or in HRIMS (higher = costlier). */
    public static function railRank(?string $c): ?int
    {
        $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $c));
        if ($c === '') {
            return null;
        }
        $map = [
            'GENERAL' => 0, 'GEN' => 0, 'GN' => 0, '2S' => 0, 'SECONDSITTING' => 0, 'UR' => 0,
            'SL' => 1, 'SLEEPER' => 1, 'SLEEPERNONAC' => 1, 'NONAC' => 1,
            'CC' => 2, '3E' => 2, '3A' => 2, '3AC' => 2, 'AC3' => 2, 'ACIII' => 2, 'ACIIIAC' => 2, 'AC' => 2, 'IIIAC' => 2, 'THIRDAC' => 2,
            '2A' => 3, '2AC' => 3, 'AC2' => 3, 'ACII' => 3, 'IIAC' => 3, 'ECONOMYACII' => 3,
            '1A' => 4, '1AC' => 4, 'AC1' => 4, 'ACI' => 4, 'EC' => 4, 'EA' => 4, 'FC' => 4, 'FIRSTAC' => 4, 'ECONOMYACI' => 4,
        ];

        return $map[$c] ?? null;
    }

    public static function railName(int $rank): string
    {
        return ['General', 'Sleeper', 'AC-III / Chair car', 'AC-II', 'AC-I / Executive'][$rank] ?? (string) $rank;
    }

    public static function eligibility(int $employeeId): ?object
    {
        try {
            return DB::connection(session('hrims_connection', 'hrims'))->table('hrm_employee_eligibility')
                ->where('EmployeeID', $employeeId)->where('Status', 'A')->orderByDesc('EligibilityId')
                ->first(['Train_Class', 'TravelClass_Outside_Hq', 'Flight_Allow', 'Flight_Class', 'Mode_Travel_Outside_Hq']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return string[] issues (empty when the class is within the eligibility or cannot be judged) */
    public static function issues(string $conn, int $yearId, int $expId): array
    {
        $c = DB::connection($conn)->table("y{$yearId}_expenseclaims as e")
            ->leftJoin("y{$yearId}_g1_expensefilldata as f", 'f.ExpId', '=', 'e.ExpId')
            ->where('e.ExpId', $expId)->first(['e.CrBy', 'e.ClaimId', 'f.Class', 'f.Mode']);
        if (! $c || ! in_array((int) $c->ClaimId, [self::RAIL, self::AIR], true)) {
            return [];
        }
        $el = self::eligibility((int) $c->CrBy);
        if (! $el) {
            return [];
        }
        $out = [];
        if ((int) $c->ClaimId === self::RAIL) {
            $billed = self::railRank($c->Class);
            $allowed = null;
            foreach ([$el->Train_Class, $el->TravelClass_Outside_Hq] as $src) {
                foreach (preg_split('#/#', (string) $src) as $part) {
                    $r = self::railRank($part);
                    if ($r !== null) {
                        $allowed = max($allowed ?? 0, $r);
                    }
                }
                if ($allowed !== null) {
                    break;
                }
            }
            if ($billed !== null && $allowed !== null && $billed > $allowed) {
                $out[] = 'Rail class '.$c->Class.' is above the eligible class ('.self::railName($allowed).', HRIMS: '.trim(($el->Train_Class ?: $el->TravelClass_Outside_Hq) ?? '').'). Pay the eligible class fare unless the upgrade was approved.';
            }
        } else {
            $allow = strtoupper(trim((string) $el->Flight_Allow));
            $mode = strtolower((string) $el->Mode_Travel_Outside_Hq);
            if (in_array($allow, ['N', 'NO'], true) && ! str_contains($mode, 'flight')) {
                $out[] = 'Air travel is not in this employee\'s eligibility (HRIMS: flight not allowed).';
            } elseif (str_contains($mode, 'approval')) {
                $out[] = 'Air travel is approval based for this employee (HRIMS: '.$el->Mode_Travel_Outside_Hq.'): check the approval.';
            }
            $cls = strtolower((string) $c->Class);
            if ($cls !== '' && preg_match('/business|first|premium/', $cls)) {
                $out[] = 'Air class '.$c->Class.' is above Economy'.($el->Flight_Class ? ' (HRIMS: '.$el->Flight_Class.')' : '').'.';
            }
        }

        return $out;
    }

    public static function warning(string $conn, int $yearId, int $expId): ?string
    {
        try {
            $i = self::issues($conn, $yearId, $expId);
        } catch (\Throwable $e) {
            return null;
        }

        return $i ? 'Travel class check: '.implode(' ', $i) : null;
    }

    public static function panelHtml(string $conn, int $yearId, int $expId): string
    {
        $w = self::warning($conn, $yearId, $expId);

        return $w ? '<div class="alert alert-warning mt-3 mb-0 small" id="travel-class-alert"><strong>Travel class:</strong> '.e(substr($w, strlen('Travel class check: '))).'</div>' : '';
    }
}
