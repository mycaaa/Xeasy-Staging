<?php

namespace App\Exports\Concerns;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Font;

/**
 * REQ-004: Report Enhancement — Generation Timestamp
 * 
 * Add this trait to any Export class that uses WithEvents to automatically
 * append a "Generated on: DD-MM-YYYY HH:MM:SS by [User]" row at the top.
 *
 * Usage in an Export class:
 *   use WithGeneratedTimestamp;
 *   // Then in your registerEvents() method, call:
 *   AfterSheet::class => function(AfterSheet $event) {
 *       $this->insertTimestampRow($event->sheet->getDelegate());
 *       // ... rest of your styling
 *   }
 *
 * Or if you want it standalone (no other AfterSheet events):
 *   public function registerEvents(): array {
 *       return [AfterSheet::class => fn($e) => $this->insertTimestampRow($e->sheet->getDelegate())];
 *   }
 */
trait WithGeneratedTimestamp
{
    /**
     * Insert a timestamp row at the top of the sheet.
     * Shifts existing rows down by $rowsToInsert rows.
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param int $rowsToInsert Number of rows to insert at the top (default 1)
     * @param string|null $note Optional extra note to show beside the timestamp
     */
    protected function insertTimestampRow(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $rowsToInsert = 1,
        ?string $note = null
    ): void {
        // Shift everything down
        $sheet->insertNewRowBefore(1, $rowsToInsert);

        $generatedBy = session('emp_name', auth()->user()?->name ?? 'System');
        $timestamp   = now()->format('d-m-Y H:i:s');
        $text        = "Generated on: {$timestamp}  |  By: {$generatedBy}";
        if ($note) {
            $text .= "  |  {$note}";
        }

        $lastCol = $sheet->getHighestColumn();
        $sheet->setCellValue('A1', $text);
        $sheet->mergeCells("A1:{$lastCol}1");

        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => false, 'size' => 9, 'italic' => true, 'color' => ['argb' => 'FF6B7280']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF9FAFB']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(16);
    }
}
