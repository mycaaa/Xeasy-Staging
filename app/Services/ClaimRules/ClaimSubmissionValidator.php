<?php

namespace App\Services\ClaimRules;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Server-side claim rules from the client's implementation-point tracker.
 *
 * One place for the rules so that every save path (web claimant relay,
 * legacy claim.php / extract-data.php handlers used by the mobile app,
 * mobile REST endpoints and the punch / verifier save) applies the same
 * checks. Each method returns a list of error messages (empty = OK);
 * callers wrap the first message in their own response format.
 */
class ClaimSubmissionValidator
{
    /** UploadSequence code for an online payment receipt (head-wise files use 101-109). */
    public const RECEIPT_UPLOAD_SEQUENCE = 110;

    public const RECEIPT_FILE_FIELD = 'File_Payment_Receipt';

    /** Fields the legacy upload_bill flow accepts as bill files. */
    private const BILL_FILE_FIELDS = [
        'upload_img', 'upload_pdf', 'NewFile',
        'File_Meeting_Hall', 'File_Lodging', 'File_Meals', 'File_Business_Entertainment', 'File_Others',
        'File_Toll_Pass', 'File_Toll_Tax', 'File_International_Recharge', 'File_Printing_Stationery',
        self::RECEIPT_FILE_FIELD,
    ];

    private const OFFLINE_FILE_FIELDS = ['Img1_offline', 'Img2_offline', 'Img3_offline', 'Pdf1_offline', 'Pdf2_offline'];

    public function enabled(): bool
    {
        return (bool) config('claim_rules.enabled', true);
    }

    /** First message of a list, or null. */
    public static function first(array $errors): ?string
    {
        return $errors[0] ?? null;
    }

    /**
     * All bill-claim (upload_bill) rules for one claim.
     *
     * $claim keys: claim_id, amount, files (see fileCounts()).
     */
    public function validateBill(array $claim): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $claimId = (int) ($claim['claim_id'] ?? 0);
        $errors = $this->billDate($claim['bill_date'] ?? null, isset($claim['year_id']) ? (int) $claim['year_id'] : null);
        if ($errors === []) {
            $errors = $this->claimYearOpen(isset($claim['year_id']) ? (int) $claim['year_id'] : null);
        }
        if ($errors === []) {
            $errors = $this->billAgeAndCutoff($claim['bill_date'] ?? null);
        }

        if (in_array($claimId, $this->ids('claim_rules.lodging.claim_ids', [1]), true)) {
            $errors = array_merge($errors, $this->lodgingReceipt((float) ($claim['amount'] ?? 0), $claim['files'] ?? []));
        }
        if (in_array($claimId, $this->ids('claim_rules.rail.claim_ids', [14]), true)) {
            $errors = array_merge($errors, $this->railTicketCategory($claim['ticket_category'] ?? null));
        }
        if (in_array($claimId, $this->ids('claim_rules.participants.team_meeting_claim_ids', [42]), true)) {
            $errors = array_merge($errors, $this->teamMeetingParticipants($claim['pax_details'] ?? null));
        }
        if (array_key_exists($claimId, (array) config('claim_rules.pax.claims', []))) {
            $errors = array_merge($errors, $this->participantsWithinPax($claim['pax_details'] ?? null, $claim['employee_participants'] ?? null, $claim['other_participants'] ?? null));
        }

        return $errors;
    }

    /**
     * Tracker #38: employee + other participants must not exceed the PAX.
     * Only applies when a PAX number was entered (group meal / group lodging).
     */
    public function participantsWithinPax($pax, $employees, $others): array
    {
        $paxNumber = is_numeric(trim((string) $pax)) ? (int) $pax : 0;
        if ($paxNumber <= 0) {
            return [];
        }
        $employeeCount = $this->countEmployeeParticipants($employees);
        $otherCount = count(\App\Helpers\CommonHelper::parseOtherParticipants(is_array($others) ? json_encode($others) : (string) $others));
        $total = $employeeCount + $otherCount;
        if ($total > $paxNumber) {
            return ["Total participants ({$total} = {$employeeCount} employees + {$otherCount} others) exceed PAX ({$paxNumber}). Remove ".($total - $paxNumber).' participant(s) or correct the PAX.'];
        }

        return [];
    }

    /** Distinct employee participants from an array, a JSON array or a comma list. */
    public function countEmployeeParticipants($employees): int
    {
        if (is_string($employees)) {
            $decoded = json_decode($employees, true);
            $employees = is_array($decoded) ? $decoded : explode(',', $employees);
        }
        $list = array_filter(array_map(fn ($e) => trim((string) (is_array($e) ? ($e['id'] ?? $e['code'] ?? '') : $e)), (array) $employees), fn ($e) => $e !== '');

        return count(array_unique($list));
    }

    /**
     * Tracker #17: a DOS (DA outstation) claim must carry a proof attachment.
     * $offlineReference = Img1_offline name the app syncs later (offline mode).
     */
    public function dosProof($daType, int $attachedFiles, $offlineReference = null): array
    {
        if (! $this->enabled() || ! in_array((int) $daType, $this->ids('claim_rules.dos.claim_ids', [20]), true)) {
            return [];
        }
        if ($attachedFiles > 0 || trim((string) $offlineReference) !== '') {
            return [];
        }

        return ['DA Outstation (DOS) claim: please upload the night-halt proof (hotel bill or halt certificate) before submitting.'];
    }

    /**
     * dosProof() for a DA_Update request (field upload_img, or the web form's uploadedFile).
     *
     * UAT R-01: pass $storedFiles (files actually stored) once the save path has
     * stored them, so a proof that failed to store does not count.
     *
     * UAT E-12: $allowOfflineReference = false for the web DA form. Img1_offline is the
     * mobile app's placeholder for a photo it took offline and uploads on its next sync;
     * a browser has no such sync, so on the web the reference would never become a file.
     */
    public function validateDaRequest(Request $request, ?int $storedFiles = null, bool $allowOfflineReference = true): array
    {
        $dateErrors = $this->billDate($request->input('Billdate', $request->input('bill_date', $request->input('da_date'))), $request->input('YearId') ? (int) $request->input('YearId') : null);
        if ($dateErrors) {
            return $dateErrors;
        }

        return $this->dosProof(
            $request->input('DAtype', $request->input('mclaimtype')),
            $storedFiles ?? ($this->countFiles($request->file('upload_img')) + $this->countFiles($request->file('uploadedFile'))),
            $allowOfflineReference ? $request->input('Img1_offline') : null
        );
    }

    /** Tracker #21: Team Meeting claims must name the participants (PaxDetails). */
    public function teamMeetingParticipants($paxDetails): array
    {
        if (trim((string) $paxDetails) === '') {
            return ['Participant details (Pax Details) are mandatory for Team Meeting claims.'];
        }

        return [];
    }

    /**
     * Tracker #21: activity claims that need a participant list must carry
     * participant names, an uploaded participant list, or (on edit) an
     * already-stored participant list file.
     *
     * $activity keys: claim_id, participant_names, participant_files (int),
     * existing_participant_files (int).
     */
    public function validateActivity(array $activity, ?string $connection = null): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $claimId = (int) ($activity['claim_id'] ?? 0);
        if (! $this->activityRequiresParticipants($claimId, $connection)) {
            return [];
        }
        $hasNames = trim((string) ($activity['participant_names'] ?? '')) !== '';
        $hasFiles = (int) ($activity['participant_files'] ?? 0) + (int) ($activity['existing_participant_files'] ?? 0) > 0;
        if (! $hasNames && ! $hasFiles) {
            return ['Participant list is mandatory for this activity claim: enter the participant names or upload the participant list.'];
        }

        return [];
    }

    /** validateActivity() for an upload_activity_bill request (web relay or extract-data.php). */
    public function validateActivityRequest(Request $request, int $existingParticipantFiles = 0, ?string $connection = null, ?int $storedParticipantFiles = null): array
    {
        // BILLDATE-03: activity bills get the same date rule as other bills (real date, not in the future)
        $dateErrors = $this->billDate($request->input('bill_date', $request->input('Billdate', $request->input('BillDate'))), (int) $request->input('YearId', $request->input('yearid', 0)) ?: null, $connection);
        if ($dateErrors) {
            return $dateErrors;
        }

        return $this->validateActivity([
            'claim_id' => (int) $request->input('claim_id', $request->input('activity_claim', 0)),
            'participant_names' => $request->input('participant_names'),
            // UAT R-01: after storing, count the participant-list files actually stored.
            'participant_files' => $storedParticipantFiles ?? ($this->countFiles($request->file('file_participant_list')) + $this->countFiles($request->file('participant_list_file'))),
            'existing_participant_files' => $existingParticipantFiles,
        ], $connection);
    }

    /** All activity ClaimIds that need a participant list (for the claimant screen JS). */
    public function participantActivityClaimIds(?string $connection = null): array
    {
        $ids = $this->ids('claim_rules.participants.activity_extra_claim_ids', []);
        try {
            $mapped = DB::connection($connection)->table('adv_expense_head_name_mapping as m')
                ->join('adv_expense_head_category as c', 'c.id', '=', 'm.expense_head_id')
                ->join('adv_activity_names as n', 'n.id', '=', 'm.activity_id')
                ->where('m.checked', 1)
                ->where('c.expense_head_name', (string) config('claim_rules.participants.activity_head_name', 'Participant list'))
                ->pluck('n.activity_name')->map(fn ($v) => (int) $v)->all();
            $ids = array_merge($ids, $mapped);
        } catch (\Throwable $e) {
            // keep the configured ids
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** Whether an activity ClaimId needs a participant list (head mapping or config). */
    public function activityRequiresParticipants(int $claimId, ?string $connection = null): bool
    {
        if ($claimId <= 0) {
            return false;
        }
        if (in_array($claimId, $this->ids('claim_rules.participants.activity_extra_claim_ids', []), true)) {
            return true;
        }
        try {
            return DB::connection($connection)->table('adv_expense_head_name_mapping as m')
                ->join('adv_expense_head_category as c', 'c.id', '=', 'm.expense_head_id')
                ->join('adv_activity_names as n', 'n.id', '=', 'm.activity_id')
                ->where('n.activity_name', $claimId)
                ->where('m.checked', 1)
                ->where('c.expense_head_name', (string) config('claim_rules.participants.activity_head_name', 'Participant list'))
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * validateBill() for a legacy-format upload_bill request (mobile app,
     * claim.php, and the web claimant form, which uses the same field names
     * except claimtype for claim_id).
     *
     * UAT R-01: pass $storedCounts (field => files actually stored, from
     * ClaimFileBatch::countsByField()) to check the #16 receipt rule against the
     * stored files instead of the request.
     */
    public function validateBillRequest(Request $request, ?array $storedCounts = null): array
    {
        return $this->validateBill([
            'claim_id' => (int) $request->input('claim_id', $request->input('claimtype', 0)),
            'amount' => (float) $request->input('amount', 0),
            'files' => $this->fileCounts($request, $storedCounts),
            'bill_date' => $request->input('uploadBillDate', $request->input('bill_date', $request->input('Billdate', $request->input('BillDate')))),
            'year_id' => $request->input('YearId', $request->input('yearid')),
            'ticket_category' => $request->input('rail_quota', $request->input('ticket_category')), // APP-RAIL-01
            'pax_details' => $request->input('PaxDetails'),
            'employee_participants' => $this->paxField($request, 0),
            'other_participants' => $this->paxField($request, 1),
        ]);
    }

    /** Employee (0) or other (1) participant field for the request's claim type. */
    private function paxField(Request $request, int $which)
    {
        $claimId = (int) $request->input('claim_id', $request->input('claimtype', 0));
        $fields = (array) (config('claim_rules.pax.claims', [])[$claimId] ?? []);

        return isset($fields[$which]) ? $request->input($fields[$which]) : null;
    }

    /** Tracker #18: rail fare ticket category must be given and be a known category. */
    public function railTicketCategory($category): array
    {
        if (trim((string) $category) === '') {
            return ['Please select the ticket category (General / Tatkal / Premium Tatkal) for the Rail Fare claim.'];
        }
        if ($this->normalizeTicketCategory($category) === null) {
            return ['Unknown rail ticket category "'.trim((string) $category).'". Allowed: '.implode(', ', array_keys($this->ticketCategories())).'.'];
        }

        return [];
    }

    /** Canonical ticket category (e.g. "TATKAL" -> "Tatkal"), or null if unknown/blank. */
    public function normalizeTicketCategory($category): ?string
    {
        $key = strtolower(preg_replace('/\s+/', ' ', trim((string) $category)));
        if ($key === '') {
            return null;
        }
        foreach ($this->ticketCategories() as $canonical => $aliases) {
            if ($key === strtolower($canonical) || in_array($key, (array) $aliases, true)) {
                return $canonical;
            }
        }

        return null;
    }

    private function ticketCategories(): array
    {
        return (array) config('claim_rules.rail.ticket_categories', [
            'General' => ['general', 'normal', 'gn'], 'Tatkal' => ['tatkal', 'ck'], 'Premium Tatkal' => ['premium tatkal', 'premium', 'pt'],
        ]);
    }

    /**
     * Rules for the punch / verifier save (ClaimViewController::uploadClaimFiles),
     * which posts the claim's form fields as $form.
     */
    public function validatePunch(int $cgId, int $claimId, array $form): array
    {
        if (! $this->enabled() || $cgId !== 1) {
            return [];
        }
        $errors = $this->billDate($form['bill_date'] ?? $form['booking_date'] ?? $form['journey_date'] ?? null);
        // GST-01: a GSTIN typed on the punch form must pass the offline check (empty is allowed)
        if (config('claim_rules.gstin.enabled', true) && trim((string) ($form['gstin'] ?? '')) !== '') {
            $g = \App\Support\Gstin::check($form['gstin']);
            if ($g['status'] !== 'valid') {
                $errors[] = $g['message'];
            }
        }
        // Q-06: arrival before departure, journey start before journey end
        $errors = array_merge($errors, $this->dateOrder($form['arrival_datetime'] ?? null, $form['departure_datetime'] ?? null, 'Departure', 'arrival'));
        $errors = array_merge($errors, $this->dateOrder($form['journey_start'] ?? null, $form['journey_end'] ?? null, 'Journey end date', 'journey start date'));
        if (in_array($claimId, $this->ids('claim_rules.rail.claim_ids', [14]), true)) {
            $errors = array_merge($errors, $this->railTicketCategory($form['rail_quota'] ?? $form['quota'] ?? $form['ticket_category'] ?? null));
        }
        // Group meal punch: only when the form carries the participant fields (verifier view keeps hidden copies).
        if ($claimId === 17 && (isset($form['meal_participants']) || isset($form['meal_other_participants']))) {
            $errors = array_merge($errors, $this->participantsWithinPax($form['PaxDetails'] ?? $form['pax_count'] ?? null, $form['meal_participants'] ?? null, $form['meal_other_participants'] ?? null));
        }

        return $errors;
    }

    /**
     * Tracker #16: lodging bill above the threshold needs an online payment receipt.
     *
     * $files = ['total' => int, 'receipt' => int, 'receipt_supported' => bool].
     * A client that can tag files (web screen, or any request that sends
     * File_Payment_Receipt) must send at least one receipt. A legacy client
     * that cannot tag files passes with legacy_min_files files (bill + receipt).
     */
    public function lodgingReceipt(float $amount, array $files): array
    {
        $threshold = (float) config('claim_rules.lodging.receipt_threshold', 10000);
        if ($amount <= $threshold) {
            return [];
        }
        if ((int) ($files['receipt'] ?? 0) >= 1) {
            return [];
        }
        $limit = number_format($threshold, 0, '.', ',');
        $legacyMin = (int) config('claim_rules.lodging.legacy_min_files', 2);
        if (empty($files['receipt_supported']) && $legacyMin > 0) {
            if ((int) ($files['total'] ?? 0) >= $legacyMin) {
                return [];
            }

            return ["Lodging bill above Rs {$limit}: please upload the online payment receipt as well as the hotel bill (at least {$legacyMin} files)."];
        }

        return ["Lodging bill above Rs {$limit}: please upload the online payment receipt before submitting."];
    }

    /**
     * Tracker #20: courier (dispatch) date for an employee's claim month.
     * Loads the month's DateOfSubmit when min_basis = 'submission'.
     */
    public function courierDateForMonth($date, int $employeeId, int $month, int $yearId, ?string $connection = null): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $submittedOn = null;
        if (config('claim_rules.courier.min_basis', 'month_start') === 'submission' && $employeeId > 0) {
            try {
                $submittedOn = DB::connection($connection)->table("y{$yearId}_monthexpensefinal")
                    ->where('EmployeeID', $employeeId)->where('Month', $month)->where('YearId', $yearId)
                    ->value('DateOfSubmit');
            } catch (\Throwable $e) {
                $submittedOn = null;
            }
        }

        return $this->courierDate($date, $month, $this->fyStartYear($yearId, $connection), $submittedOn);
    }

    /**
     * Courier date must parse, must not be after today and must not be before
     * the claim month's first day (or the submission date, see config).
     */
    public function courierDate($date, int $month, int $fyStartYear, $submittedOn = null): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $parsed = self::parseDate($date);
        if ($parsed === null) {
            return ['Please enter a valid courier (dispatch) date.'];
        }
        $today = date('Y-m-d');
        if ($parsed > $today) {
            return ['Courier date cannot be in the future. Enter the actual dispatch date.'];
        }
        if ($month >= 1 && $month <= 12 && $fyStartYear > 0) {
            $minDate = sprintf('%04d-%02d-01', $month >= 4 ? $fyStartYear : $fyStartYear + 1, $month);
            $basis = 'the start of the claim month';
            $submitted = self::parseDate($submittedOn);
            if ($submitted !== null && $submitted > $minDate) {
                $minDate = $submitted;
                $basis = 'the claim submission date';
            }
            if ($parsed < $minDate) {
                return ['Courier date cannot be before '.$basis.' ('.date('d-m-Y', strtotime($minDate)).'). Enter the actual dispatch date.'];
            }
        }

        return [];
    }

    /** Hard-copy receiving date: valid, not in the future, not before the courier date. */
    public function receivingDate($date, $courierDate = null): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $parsed = self::parseDate($date);
        if ($parsed === null) {
            return ['Please enter a valid receiving date.'];
        }
        if ($parsed > date('Y-m-d')) {
            return ['Receiving date cannot be in the future.'];
        }
        $posted = self::parseDate($courierDate);
        if ($posted !== null && $parsed < $posted) {
            return ['Receiving date cannot be before the courier date ('.date('d-m-Y', strtotime($posted)).').'];
        }

        return [];
    }

    /** Y-m-d for Y-m-d, d-m-Y or d/m/Y input; null for blank, zero or invalid dates. */
    /** Q-06: the second date/time may not be before the first (both optional). */
    public function dateOrder($first, $second, string $secondLabel, string $firstLabel): array
    {
        $a = trim((string) $first);
        $b = trim((string) $second);
        if ($a === '' || $b === '') {
            return [];
        }
        try {
            $ta = \Carbon\Carbon::parse(str_replace('/', '-', $a));
            $tb = \Carbon\Carbon::parse(str_replace('/', '-', $b));
        } catch (\Throwable $e) {
            return [];
        }

        return $tb->lt($ta) ? ["{$secondLabel} ({$b}) cannot be before the {$firstLabel} ({$a})."] : [];
    }

    public static function parseDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }
        $value = substr($value, 0, 10);
        // Day first always (Indian bills): 05/08/2026 is 5 August, never 8 May.
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd.m.Y', 'Y/m/d', 'j/n/Y', 'j-n-Y', 'j.n.Y'] as $format) {
            $d = \DateTime::createFromFormat('!'.$format, $value);
            $errors = \DateTime::getLastErrors();
            if ($d !== false && (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $d->format($format) === $value) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * DATE-01: a bill date must be a real date, not in the future, and inside the financial
     * year the claim is filed in (a bill from another year cannot be stored in this year's
     * tables). Empty dates are left to the form's own required-field check.
     */
    /** ODO-FY-01: first day an odometer reading may be dated on the web form (1 April of the session's year), d-m-Y. */
    public static function odometerMinDate(): string
    {
        try {
            $start = app(self::class)->fyStartYear((int) session('year_id', 0), session('expense_connection'));
        } catch (\Throwable $e) {
            $start = 0;
        }
        if ($start <= 0) {
            $start = (int) date('n') >= 4 ? (int) date('Y') : (int) date('Y') - 1;
        }

        return sprintf('01-04-%04d', $start);
    }

    public function billDate($date, ?int $yearId = null, ?string $connection = null): array
    {
        if (! $this->enabled() || ! config('claim_rules.bill_date.enabled', true)) {
            return [];
        }
        $raw = trim((string) $date);
        if ($raw === '') {
            return [];
        }
        $iso = self::parseDate($raw);
        if ($iso === null) {
            return ["Bill date \"{$raw}\" is not a valid date (use DD/MM/YYYY)."];
        }
        $today = now('Asia/Kolkata')->toDateString();
        if ($iso > $today) {
            return ['Bill date '.date('d/m/Y', strtotime($iso)).' is in the future. Please enter the date printed on the bill.'];
        }
        $yearId = $yearId ?: (int) session('year_id', 0);
        if ($yearId > 0) {
            $start = $this->fyStartYear($yearId, $connection);
            if ($start > 0) {
                $from = sprintf('%04d-04-01', $start);
                $to = sprintf('%04d-03-31', $start + 1);
                if ($iso < $from || $iso > $to) {
                    return ['Bill date '.date('d/m/Y', strtotime($iso))." is outside the financial year {$start}-".substr((string) ($start + 1), 2)
                        .'. Bills from another year cannot be claimed here; please contact Finance.'];
                }
            }
        }

        return [];
    }

    /**
     * Q-03 / year from the bill date (audit): a new claim may only be saved into an open year.
     * Open = the year that contains today; the year before stays open for
     * claim_rules.year.previous_year_grace_days after it ends (March bills claimed in April-May).
     * VNR also needs financialyear.Status = Active. VVNR uses the date only (its Active flag is
     * wrong until the go-live script runs). Staff may override with year_override_reason (logged).
     */
    public function claimYearOpen(?int $yearId, ?string $connection = null): array
    {
        if (! $this->enabled() || ! config('claim_rules.year.enabled', true)) {
            return [];
        }
        $yearId = $yearId ?: (int) session('year_id', 0);
        if ($yearId <= 0) {
            return [];
        }
        $conn = $connection ?: (string) session('expense_connection', '');
        $isVvnr = (int) session('company_id') === 11 || str_contains($conn, 'vvnr');
        $start = $this->fyStartYear($yearId, $connection);
        if ($start <= 0) {
            return [];
        }
        $today = now('Asia/Kolkata');
        $curStart = $today->month >= 4 ? $today->year : $today->year - 1;
        $grace = (int) config('claim_rules.year.previous_year_grace_days', 60);
        $open = $start === $curStart
            || ($start === $curStart - 1 && $today->toDateString() <= $today->copy()->setDate($curStart, 4, 1)->addDays($grace)->toDateString());
        if ($open && ! $isVvnr) {
            try {
                $status = DB::connection($connection)->table('financialyear')->where('YearId', $yearId)->value('Status');
                // an inactive previous year is still open during the grace period
                if ($status !== null && strcasecmp((string) $status, 'Active') !== 0 && $start === $curStart) {
                    $open = false;
                }
            } catch (\Throwable $e) {
                // no financialyear table on this connection: the date rule alone decides
            }
        }
        if ($open) {
            return [];
        }
        $reason = trim((string) request()?->input('year_override_reason', ''));
        $user = auth()->user();
        if ($reason !== '' && $user && \App\Support\RecordAccess::isStaff($user)) {
            \Illuminate\Support\Facades\Log::warning('Claim saved into a closed year (override)', ['year_id' => $yearId, 'user' => $user->id ?? null, 'reason' => mb_substr($reason, 0, 200)]);

            return [];
        }

        return ["The financial year {$start}-".substr((string) ($start + 1), 2).' is closed for new claims. Please file the bill in the current year, or ask Finance.'];
    }

    /**
     * Maximum bill age (audit). Off until Finance sets claim_rules.bill_age.max_days.
     */
    public function billAgeAndCutoff($date): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $iso = self::parseDate(trim((string) $date));
        if ($iso === null) {
            return [];
        }
        $maxDays = (int) config('claim_rules.bill_age.max_days', 0);
        if ($maxDays <= 0) {
            return [];
        }
        $bill = \Illuminate\Support\Carbon::parse($iso, 'Asia/Kolkata')->startOfDay();
        if ($bill->diffInDays(now('Asia/Kolkata')->startOfDay()) > $maxDays) {
            return ['Bill date '.$bill->format('d/m/Y')." is more than {$maxDays} days old. Bills older than that cannot be claimed; please contact Finance."];
        }

        return [];
    }

    /**
     * Submission cut-off (audit): a month may be submitted until its last submission day in the
     * following month (submission_settings.last_submission_day for the company, or
     * claim_rules.cutoff.day when set), unless the month has SubPermission = 'Y' (allowed by admin).
     * Off when there is no active setting for the company and no config value.
     */
    public function submissionCutoff(int $companyId, int $yearId, int $month, ?string $subPermission, ?string $connection = null): array
    {
        if (! $this->enabled() || ! config('claim_rules.cutoff.enforce_submit', true) || strtoupper((string) $subPermission) === 'Y') {
            return [];
        }
        $day = (int) config('claim_rules.cutoff.day', 0);
        if ($day <= 0) {
            try {
                $day = (int) (\App\Models\SubmissionSetting::where('company_id', $companyId)->where('is_active', true)->value('last_submission_day') ?? 0);
            } catch (\Throwable $e) {
                $day = 0;
            }
        }
        if ($day < 1 || $day > 28 || $month < 1 || $month > 12) {
            return [];
        }
        $start = $this->fyStartYear($yearId, $connection);
        if ($start <= 0) {
            return [];
        }
        $calYear = $month >= 4 ? $start : $start + 1;
        $deadline = \Illuminate\Support\Carbon::create($calYear, $month, 1, 0, 0, 0, 'Asia/Kolkata')->addMonthNoOverflow()->setDay($day)->endOfDay();
        if (now('Asia/Kolkata')->greaterThan($deadline)) {
            return ['The last date to submit '.\Illuminate\Support\Carbon::create($calYear, $month, 1)->format('F Y').' claims was '.$deadline->format('d/m/Y')
                .'. Please ask your admin to allow a late submission.'];
        }

        return [];
    }

    /** First calendar year of a financial year id (financialyear.FromDate; YearId 8 = 2026-27). */
    public function fyStartYear(int $yearId, ?string $connection = null): int
    {
        try {
            $from = DB::connection($connection)->table('financialyear')->where('YearId', $yearId)->value('FromDate');
            if ($from) {
                return (int) substr((string) $from, 0, 4);
            }
        } catch (\Throwable $e) {
            // fall through to the fixed mapping used by every company DB
        }

        return $yearId > 0 ? 2018 + $yearId : 0;
    }

    /**
     * Count bill files on a legacy-format request (uploads plus offline
     * placeholders the app syncs later). With $storedCounts the uploads are
     * counted from what was actually stored (UAT R-01).
     */
    public function fileCounts(Request $request, ?array $storedCounts = null): array
    {
        $count = fn (string $field) => $storedCounts !== null ? (int) ($storedCounts[$field] ?? 0) : $this->countFiles($request->file($field));
        $total = 0;
        foreach (self::BILL_FILE_FIELDS as $field) {
            $total += $count($field);
        }
        foreach (self::OFFLINE_FILE_FIELDS as $field) {
            if (trim((string) $request->input($field, '')) !== '') {
                $total++;
            }
        }
        $receipt = $count(self::RECEIPT_FILE_FIELD);

        return [
            'total' => $total,
            'receipt' => $receipt,
            'receipt_supported' => $receipt > 0 || $request->boolean('receipt_upload_supported'),
        ];
    }

    private function countFiles($files): int
    {
        if ($files instanceof UploadedFile) {
            return $files->isValid() ? 1 : 0;
        }
        if (! is_array($files)) {
            return 0;
        }
        $n = 0;
        array_walk_recursive($files, function ($f) use (&$n) {
            if ($f instanceof UploadedFile && $f->isValid()) {
                $n++;
            }
        });

        return $n;
    }

    private function ids(string $key, array $default): array
    {
        return array_map('intval', (array) config($key, $default));
    }
}
