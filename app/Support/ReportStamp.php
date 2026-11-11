<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * STAMP-01 (tracker, 11 Aug 2026): every report carries the date and time it was generated, and by whom.
 *
 *   Generated on 05/11/2026 04:12:33 PM IST | By: <name> (<emp code>)
 *
 * - Excel exports (Laravel Excel): added to every sheet by a global listener (AppServiceProvider),
 *   two rows below the last used row, so headings, filters, freeze panes and totals are not moved.
 *   Sheets that already print a "Generated" line keep theirs.
 * - Hand-built spreadsheets and CSV downloads call sheet() / csvRow() before writing.
 * - PDFs and print pages use the <x-report-stamp /> component.
 * Files that are uploaded into another system implement App\Exports\Concerns\NoReportStamp.
 */
class ReportStamp
{
    public static function text(): string
    {
        $user = auth()->user();
        $name = trim((string) (session('emp_name') ?: ($user->name ?? '')));
        $code = (string) (session('emp_code') ?: ($user->emp_code ?? ($user->EmpCode ?? '')));
        $by = $name !== '' ? $name.($code !== '' && ! str_contains($name, $code) ? " ({$code})" : '') : (app()->runningInConsole() ? 'System' : 'User');

        return 'Generated on '.now('Asia/Kolkata')->format('d/m/Y h:i:s A').' IST | By: '.$by;
    }

    /** Add the stamp to one worksheet unless it already has one. */
    public static function sheet(Worksheet $sheet): void
    {
        if (self::alreadyStamped($sheet)) {
            return;
        }
        $last = max(1, (int) $sheet->getHighestDataRow());
        $row = $sheet->getCell('A1')->getValue() === null && $last === 1 ? 1 : $last + 2;
        $sheet->setCellValueExplicit("A{$row}", self::text(), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('FF6B7280');
        $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(false);
    }

    /** Stamp every sheet of a workbook and set its document properties. */
    public static function workbook(Spreadsheet $book): void
    {
        $book->getProperties()->setCreated(now('Asia/Kolkata')->getTimestamp())->setDescription(self::text());
        foreach ($book->getAllSheets() as $sheet) {
            self::sheet($sheet);
        }
    }

    /** The stamp as a CSV row (put an empty row before it). */
    public static function csvRow(): array
    {
        return [self::text()];
    }

    private static function alreadyStamped(Worksheet $sheet): bool
    {
        $last = (int) $sheet->getHighestDataRow();
        $rows = array_unique(array_merge(range(1, min(3, max(1, $last))), range(max(1, $last - 2), max(1, $last))));
        foreach ($rows as $r) {
            foreach (['A', 'B'] as $c) {
                $v = $sheet->getCell("{$c}{$r}")->getValue();
                if (is_string($v) && stripos($v, 'Generated') !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
