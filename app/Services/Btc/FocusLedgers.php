<?php

namespace App\Services\Btc;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * BTC-FOCUS-02 (20 Nov 2026, tracker tab "Decision for You", item 7): the Focus ledger / sub-ledger codes are kept
 * in the database and maintained by Finance through Excel (BTC > BTC Settings > Focus ledgers: download, edit,
 * upload) - no code change when a code changes. VNR and VVNR codes sit side by side on one row; Meeting and
 * Training rows (with their VVNR codes) are rows like any other. Seeded with the codes written in the tracker.
 *
 * Sheet "Ledgers":        Type of Activity | Type of Expense | Ledger Head | Sub Ledger | VNR Ledger Code |
 *                         VNR Sub Ledger Code | VVNR Ledger Code | VVNR Sub Ledger Code
 * Sheet "Activity Heads": Activity Head | Focus Code
 */
class FocusLedgers
{
    public const COLS = ['Type of Activity', 'Type of Expense', 'Ledger Head', 'Sub Ledger', 'VNR Ledger Code', 'VNR Sub Ledger Code', 'VVNR Ledger Code', 'VVNR Sub Ledger Code'];

    public const HEAD_COLS = ['Activity Head', 'Focus Code'];

    private static ?array $cache = null;

    public static function ready(): bool
    {
        return Schema::hasTable('btc_focus_ledgers');
    }

    private static function key(?string $activity, string $expense): string
    {
        return mb_strtolower(trim((string) $activity)).'|'.mb_strtolower(trim($expense));
    }

    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $out = ['rows' => [], 'heads' => []];
        if (self::ready() && DB::table('btc_focus_ledgers')->exists()) {
            foreach (DB::table('btc_focus_ledgers')->orderBy('id')->get() as $r) {
                $out['rows'][self::key($r->activity_type, $r->expense_type)] = (array) $r;
            }
            foreach (Schema::hasTable('btc_focus_heads') ? DB::table('btc_focus_heads')->get() : [] as $h) {
                $out['heads'][mb_strtolower(trim($h->head))] = $h->focus_code;
            }
        } else {
            // not loaded yet: the codes written in the tracker (config btc.focus_export)
            foreach ((array) config('btc.focus_export.ledgers', []) as $exp => $e) {
                $out['rows'][self::key('', $exp)] = ['activity_type' => '', 'expense_type' => $exp, 'ledger_head' => '', 'sub_ledger' => '',
                    'vnr_ledger' => $e['VNR'][0] ?? null, 'vnr_sub' => $e['VNR'][1] ?? null, 'vvnr_ledger' => $e['VVNR'][0] ?? null, 'vvnr_sub' => $e['VVNR'][1] ?? null];
            }
            foreach ((array) config('btc.focus_export.activity_ledgers', []) as $k => $e) {
                [$act, $exp] = explode('|', $k, 2);
                $out['rows'][self::key($act, $exp)] = ['activity_type' => $act, 'expense_type' => $exp, 'ledger_head' => '', 'sub_ledger' => '',
                    'vnr_ledger' => $e['VNR'][0] ?? null, 'vnr_sub' => $e['VNR'][1] ?? null, 'vvnr_ledger' => $e['VVNR'][0] ?? null, 'vvnr_sub' => $e['VVNR'][1] ?? null];
            }
        }

        return self::$cache = $out;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    /** [ledger, sub-ledger ('N/A' when blank), error]. */
    public static function lookup(?string $activity, string $expense, string $entity): array
    {
        $rows = self::load()['rows'];
        $act = trim((string) $activity);
        $r = $rows[self::key($act, $expense)] ?? null;
        if (! $r) {
            return [null, null, $act !== ''
                ? "No row for activity \"{$act}\" with expense \"{$expense}\" in the Focus ledgers (BTC Settings) - add it and upload."
                : "No row for expense \"{$expense}\" in the Focus ledgers (BTC Settings) - add it and upload."];
        }
        $p = $entity === 'VVNR' ? 'vvnr' : 'vnr';
        $ledger = trim((string) ($r[$p.'_ledger'] ?? ''));
        if ($ledger === '') {
            return [null, null, "The Focus ledgers have no {$entity} ledger code for \"".($act !== '' ? $act.' / ' : '')."{$expense}\" - fill it in and upload."];
        }
        $sub = trim((string) ($r[$p.'_sub'] ?? ''));

        return [$ledger, $sub !== '' ? $sub : 'N/A', null];
    }

    /** Focus code of an Activity Head (OSA / PSA ...); the text itself when it is already a code or not listed. */
    public static function headCode(?string $head): ?string
    {
        $h = trim((string) $head);
        if ($h === '') {
            return null;
        }

        return self::load()['heads'][mb_strtolower($h)] ?? $h;
    }

    public static function expenseTypes(): array
    {
        $t = array_values(array_unique(array_merge((array) config('btc.focus_export.expense_types', []), array_map(fn ($r) => $r['expense_type'], self::load()['rows']))));

        return array_values(array_filter($t, fn ($x) => trim((string) $x) !== ''));
    }

    public static function activityTypes(): array
    {
        $t = array_values(array_unique(array_merge((array) config('btc.focus_export.activity_types', []), array_map(fn ($r) => (string) $r['activity_type'], self::load()['rows']))));

        return array_values(array_filter($t, fn ($x) => trim((string) $x) !== ''));
    }

    public static function heads(): array
    {
        return array_keys(self::load()['heads']) ? array_map('strtoupper', array_keys(self::load()['heads'])) : (array) config('btc.focus_export.activity_heads', []);
    }

    /** Seed the table with the tracker codes (only when empty). */
    public static function seed(): int
    {
        if (! self::ready() || DB::table('btc_focus_ledgers')->exists()) {
            return 0;
        }
        self::$cache = null;
        $rows = array_map(fn ($r) => $r + ['created_at' => now(), 'updated_at' => now()], array_values(self::load()['rows']));
        DB::table('btc_focus_ledgers')->insert($rows);
        self::$cache = null;

        return count($rows);
    }

    /** The current codes as an Excel workbook (also the template to fill). */
    public static function workbook(): Spreadsheet
    {
        $book = new Spreadsheet();
        $s = $book->getActiveSheet()->setTitle('Ledgers');
        $s->fromArray(self::COLS, null, 'A1');
        $i = 2;
        foreach (self::load()['rows'] as $r) {
            $s->fromArray([$r['activity_type'] ?: '', $r['expense_type'], $r['ledger_head'] ?? '', $r['sub_ledger'] ?? '', $r['vnr_ledger'] ?? '', $r['vnr_sub'] ?? '',
                $r['vvnr_ledger'] ?? '', $r['vvnr_sub'] ?? ''], null, 'A'.$i++);
        }
        $s->getStyle('A1:H1')->getFont()->setBold(true);
        foreach (range('A', 'H') as $c) {
            $s->getColumnDimension($c)->setAutoSize(true);
        }
        $s->freezePane('A2');
        $h = $book->createSheet()->setTitle('Activity Heads');
        $h->fromArray(self::HEAD_COLS, null, 'A1');
        $i = 2;
        foreach (self::load()['heads'] as $k => $v) {
            $h->fromArray([strtoupper($k), $v], null, 'A'.$i++);
        }
        $h->getStyle('A1:B1')->getFont()->setBold(true);
        $h->getColumnDimension('A')->setWidth(18);
        $h->getColumnDimension('B')->setWidth(32);
        $n = $book->createSheet()->setTitle('How to fill');
        $lines = [
            'Focus ledgers for BTC - how to fill',
            '',
            'Sheet "Ledgers": one row per Type of Expense (travel, stay, vehicle ...) with Type of Activity left blank,',
            'and one row per Type of Activity + Type of Expense for meeting / market / training expenses (e.g. Farmer Meeting + Fooding).',
            'Type of Expense and Type of Activity must be written exactly as Finance chooses them in the Focus entry of a BTC bill.',
            'VNR codes are used for VNR employees, VVNR codes for VVNR employees. Leave a code blank only when it does not exist:',
            '  - a blank Sub Ledger Code is exported as N/A;',
            '  - a blank Ledger Code stops the export for that bill with the reason (nothing is guessed).',
            'Ledger Head and Sub Ledger are the names, for reading only.',
            '',
            'Sheet "Activity Heads": the Focus code of each Activity Head (OSA / PSA / PDA / PLA ...).',
            '',
            'Upload: BTC > BTC Settings > Focus ledgers > Upload. The upload replaces all rows; the previous version stays in the history.',
            'Keep the column names and their order as they are.',
        ];
        foreach ($lines as $k => $l) {
            $n->setCellValue('A'.($k + 1), $l);
        }
        $n->getStyle('A1')->getFont()->setBold(true);
        $n->getColumnDimension('A')->setWidth(120);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * Replace the codes from an uploaded workbook. Nothing changes unless the whole file is valid.
     * @return array{ok:bool, message:string, errors?:array}
     */
    public static function import(UploadedFile $file, ?int $userId, string $userName): array
    {
        if (! self::ready()) {
            return ['ok' => false, 'message' => 'Run the BTC database update first.'];
        }
        try {
            $book = IOFactory::load($file->getRealPath());
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'The file could not be read as Excel.'];
        }
        $sheet = $book->getSheetByName('Ledgers') ?? $book->getSheet(0);
        $data = $sheet->toArray(null, true, false, false);
        $head = array_map(fn ($v) => trim((string) $v), $data[0] ?? []);
        if (array_slice($head, 0, count(self::COLS)) !== self::COLS) {
            return ['ok' => false, 'message' => 'The "Ledgers" sheet must have these columns in this order: '.implode(' | ', self::COLS).'. Download the current file and edit that.'];
        }
        $rows = [];
        $errors = [];
        $seen = [];
        $code = fn ($v) => strtoupper(preg_replace('/\s+/', '', (string) $v));
        foreach (array_slice($data, 1) as $i => $r) {
            $line = $i + 2;
            $r = array_map(fn ($v) => trim((string) $v), array_pad($r, 8, ''));
            if (implode('', $r) === '') {
                continue;
            }
            [$act, $exp, $lh, $sl, $vl, $vs, $wl, $ws] = $r;
            if ($exp === '') {
                $errors[] = "Row {$line}: Type of Expense is empty.";
                continue;
            }
            foreach (['VNR Ledger Code' => $vl, 'VNR Sub Ledger Code' => $vs, 'VVNR Ledger Code' => $wl, 'VVNR Sub Ledger Code' => $ws] as $n => $v) {
                if ($v !== '' && strtoupper($v) !== 'N/A' && ! preg_match('/^[A-Za-z0-9][A-Za-z0-9_\-\/. ]{1,39}$/', $v)) {
                    $errors[] = "Row {$line}: {$n} \"{$v}\" does not look like a Focus code.";
                }
            }
            if ($vl === '' && $wl === '') {
                $errors[] = "Row {$line}: no ledger code for VNR or VVNR.";
            }
            $k = self::key($act, $exp);
            if (isset($seen[$k])) {
                $errors[] = "Row {$line}: \"".($act !== '' ? $act.' / ' : '')."{$exp}\" is already on row {$seen[$k]}.";
                continue;
            }
            $seen[$k] = $line;
            $na = fn ($v) => ($v === '' || strtoupper($v) === 'N/A') ? null : $code($v);
            $rows[] = ['activity_type' => mb_substr($act, 0, 80), 'expense_type' => mb_substr($exp, 0, 60), 'ledger_head' => mb_substr($lh, 0, 150), 'sub_ledger' => mb_substr($sl, 0, 150),
                'vnr_ledger' => $na($vl), 'vnr_sub' => $na($vs), 'vvnr_ledger' => $na($wl), 'vvnr_sub' => $na($ws), 'created_at' => now(), 'updated_at' => now()];
        }
        if (! $rows) {
            $errors[] = 'No rows found in the "Ledgers" sheet.';
        }
        $heads = [];
        if ($hs = $book->getSheetByName('Activity Heads')) {
            foreach (array_slice($hs->toArray(null, true, false, false), 1) as $i => $r) {
                $h = trim((string) ($r[0] ?? ''));
                $c = trim((string) ($r[1] ?? ''));
                if ($h === '' && $c === '') {
                    continue;
                }
                if ($h === '' || $c === '') {
                    $errors[] = 'Activity Heads row '.($i + 2).': both the head and its Focus code are needed.';
                    continue;
                }
                $heads[mb_strtolower($h)] = ['head' => mb_substr($h, 0, 40), 'focus_code' => mb_substr($c, 0, 80), 'created_at' => now(), 'updated_at' => now()];
            }
        }
        if ($errors) {
            return ['ok' => false, 'message' => 'Nothing was changed. Fix these and upload again:', 'errors' => array_slice($errors, 0, 40)];
        }
        $before = self::workbook();
        $dir = storage_path('app/btc/focus_ledgers');
        @mkdir($dir, 0775, true);
        $stamp = now()->format('Ymd_His');
        try {
            IOFactory::createWriter($before, 'Xlsx')->save("{$dir}/before_{$stamp}.xlsx");
        } catch (\Throwable $e) {
            report($e);
        }
        DB::transaction(function () use ($rows, $heads, $file, $userId, $userName, $stamp) {
            DB::table('btc_focus_ledgers')->delete();
            foreach (array_chunk($rows, 200) as $ch) {
                DB::table('btc_focus_ledgers')->insert($ch);
            }
            if ($heads && Schema::hasTable('btc_focus_heads')) {
                DB::table('btc_focus_heads')->delete();
                DB::table('btc_focus_heads')->insert(array_values($heads));
            }
            if (Schema::hasTable('btc_setting_logs')) {
                DB::table('btc_setting_logs')->insert(['key' => 'focus_ledgers', 'old_value' => 'before_'.$stamp.'.xlsx',
                    'new_value' => count($rows).' ledger rows, '.count($heads).' activity heads from '.mb_substr($file->getClientOriginalName(), 0, 100),
                    'user_id' => $userId, 'user_name' => $userName, 'created_at' => now()]);
            }
        });
        try {
            $file->move($dir, "uploaded_{$stamp}.xlsx");
        } catch (\Throwable $e) {
        }
        self::$cache = null;

        return ['ok' => true, 'message' => 'Focus ledgers updated: '.count($rows).' rows'.($heads ? ', '.count($heads).' activity heads' : '').'.'];
    }
}
