<?php

namespace App\Exports;

use App\Helpers\CommonHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Enumerable;

class FocusExportSheet implements \App\Exports\Concerns\NoReportStamp, FromCollection, WithEvents, WithTitle
{
    protected string $sheetTitle;

    protected Collection $focusData;

    public function __construct(string $sheetTitle, Collection $focusData)
    {
        $this->sheetTitle = $sheetTitle;
        $this->focusData = $focusData;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function collection(): Enumerable
    {
        return collect([]);
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function ($event) {
                $this->renderSheet($event->sheet->getDelegate());
            },
        ];
    }

    private function renderSheet($sheet)
    {
        $curRow = 1;
        $totalColumns = 30;
        $lastColumn = $this->getColumnLetter($totalColumns);
        $headers = ['VOUCHER NUMBER', 'Date', 'Time', 'Account (EC)', 'Business Entity', 'Region', 'BU', 'sNarration', 'ActMonths', 'LB', 'Crop', 'Location', 'Activity', 'Category', 'Subledger', 'Ledger', 'Ledger Name', 'Type Of Activity', 'Activity Head', 'State', 'Amount', 'Grade', 'Function', 'Function Code', 'Vertical', 'Vertical Code', 'Department', 'Department Code', 'Sub Department', 'Sub Department Code'];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}{$curRow}", $header);
            $col++;
        }
        $this->headerStyle($sheet, "A{$curRow}:{$lastColumn}{$curRow}");
        $curRow++;

        $groupedData = $this->focusData->groupBy(function ($item) {
            return $item->CrBy.'|'
                .($item->ActMonths ?? '').'|'
                .($item->Subledger ?? '').'|'
                .($item->focus_claim_name ?? '').'|'
                .($item->Title ?? '');
        });

        $index = 1;

        foreach ($groupedData as $group) {
            $firstRecord = $group->first();

            $sumAmount = $group->sum('FinancedTAmt');

            $data = [
                $this->makeVoucherNumber($firstRecord->Year, $firstRecord->VOUCHER_NUMBER),
                //   CommonHelper::toDisplayFormat($firstRecord->Date ?? ''),
                CommonHelper::currentDisplayDate(),
                $firstRecord->Time ?? '',
                $this->makeAccountEC($firstRecord->Account_EC ?? ''),
                $firstRecord->Business_Entity ?? '',
                $firstRecord->Region_Code ?? '',
                $firstRecord->BU ?? '',
                $this->makeNarration($firstRecord->claim_name, $firstRecord->ActMonths ?? '', $firstRecord->Year ?? '', $firstRecord->Category ?? ''),
                $this->formatMonth($firstRecord->ActMonths ?? ''),
                $firstRecord->LB ?? '',
                $this->formatCropWithCode($firstRecord->Crop ?? null),
                $firstRecord->Location ?? '',
                $firstRecord->Activity_Focus_Head ?? (in_array($firstRecord->focus_claim_name, ['OFD', 'CT', 'RST', 'Trialing', 'Disease']) ? $firstRecord->focus_claim_name : 'All Activity'),
                // $firstRecord->Activity ?? 'All Activity',
                // $firstRecord->Category ?? 'N/A',
                'N/A',
                $firstRecord->Subledger ?? '',
                $firstRecord->Ledger ?? '',
                $firstRecord->Ledger_Name ?? '',
                $firstRecord->focus_claim_name ?? '',
                $firstRecord->Activity_Head ?? '',
                $firstRecord->State ?? '',
                $sumAmount,
                $firstRecord->Grade ?? '',
                $firstRecord->Function ?? '',
                $firstRecord->Function_Code ?? '',
                $firstRecord->Vertical ?? '',
                $firstRecord->Vertical_Code ?? '',
                $firstRecord->department_name ?? '',
                $firstRecord->Department_Code ?? '',
                $firstRecord->Sub_Department ?? '',
                $firstRecord->Sub_Department_Code ?? '',
            ];

            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue("{$col}{$curRow}", $value);
                $col++;
            }

            $bg = ($index % 2 === 0) ? 'FFE7E6E6' : 'FFFFFFFF';
            $sheet->getStyle("A{$curRow}:{$lastColumn}{$curRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            $curRow++;
            $index++;
        }

        for ($i = 1; $i <= $totalColumns; $i++) {
            $sheet->getColumnDimension($this->getColumnLetter($i))->setAutoSize(true);
        }
    }

    private function formatCropWithCode(?string $cropValue, string $fallback = 'All Crop'): string
    {
        if (empty($cropValue)) {
            return $fallback;
        }

        $cropMap = $this->getCropCodeMap();

        // Get only the first crop
        $firstCrop = trim(explode(',', $cropValue)[0]);

        // Return the code only, or fallback to the crop name if not found in map
        return $cropMap[$firstCrop] ?? $firstCrop;
    }

    private function getCropCodeMap(): array
    {
        return [
            'Ash Gourd' => 'CRP001',
            'Bhindi' => 'CRP002',
            'Bitter Gourd' => 'CRP003',
            'Bottle Gourd' => 'CRP005',
            'Brinjal' => 'CRP006',
            'Capsicum' => 'CRP007',
            'Chilli' => 'CRP008',
            'Cluster Bean' => 'CRP009',
            'Coriander' => 'CRP010',
            'Cowpea' => 'CRP011',
            'Dolichos' => 'CRP012',
            'Pumpkin' => 'CRP013',
            'Radish' => 'CRP014',
            'Ridge Gourd' => 'CRP015',
            'Snake Gourd' => 'CRP016',
            'Sponge Gourd' => 'CRP017',
            'Tinda' => 'CRP018',
            'Tomato' => 'CRP019',
            'Sweet Corn Seed' => 'CRP020',
            'Onion' => 'CRP021',
            'Veg Crop' => 'VCRP01',
            'Carrot' => 'CRP022',
            'French Bean' => 'CRP023',
            'Pea' => 'CRP024',
            'Hot Pepper' => 'CRP025',
            'Sweet Pepper' => 'CRP026',
            'Cauliflower' => 'CRP004',
            'Cucumber' => 'CRP027',
            'Papaya' => 'CRP028',
            'Watermelon' => 'CRP029',
            'Muskmelon' => 'CRP030',
            'Guava' => 'CRP031',
            'Custard Apple' => 'CRP032',
            'Bajra Seed' => 'CRP033',
            'Maize Seed' => 'CRP034',
            'Paddy Seed' => 'CRP035',
            'Mustard Seed' => 'CRP036',
            'Wheat Seed' => 'CRP037',
            'Field Crop' => 'FCRP01',
            'Sun Hemp' => 'CRP038',
            'Root Stock' => 'Root Stock',
        ];
    }

    private function makeAccountEC($empCode)
    {
        if (empty($empCode)) {
            return '';
        }

        $firstChar = strtoupper(substr($empCode, 0, 1));

        if ($firstChar === 'E' || $firstChar === 'V') {
            return $empCode;
        }

        $padded = str_pad($empCode, 4, '0', STR_PAD_LEFT);

        return 'E'.$padded;
    }

    private function makeVoucherNumber($year, $docNo)
    {
        if (empty($docNo)) {
            return '';
        }

        if (empty($year) || strlen($year) < 9) {
            $yearId = session('year_id');
            if (! empty($yearId)) {
                try {
                    $year = DB::connection(session('expense_connection', 'expense'))
                        ->table('financialyear')
                        ->where('YearId', $yearId)
                        ->value('Year');
                } catch (\Throwable $e) {
                    $year = null;
                }
            }
            if (empty($year) || strlen($year) < 9) {
                $curYear = (int) date('Y');
                $curMonth = (int) date('n');
                $startYear = ($curMonth >= 4) ? $curYear : $curYear - 1;
                $endYear = $startYear + 1;
                $year = "{$startYear}-{$endYear}";
            }
        }

        $formattedDocNo = is_numeric($docNo) ? str_pad($docNo, 4, '0', STR_PAD_LEFT) : $docNo;
        if (preg_match('/(\d{2,4})[-_\/](\d{2,4})/', (string) $year, $m)) {
            $part1 = substr($m[1], -2);
            $part2 = substr($m[2], -2);
        } else {
            $part1 = substr((string) $year, 2, 2);
            $part2 = substr((string) $year, 7, 2);
        }

        if (session('company_id') == 11) {
            return "EMPJVV/{$part1}-{$part2}/{$formattedDocNo}";
        } else {
            return "EMPJV/{$part1}-{$part2}/{$formattedDocNo}";
        }
    }

    private function makeNarration($claimType, $claimMonth, $year, $category = null)
    {
        if (empty($year)) {
            $yearId = session('year_id');
            if (!empty($yearId)) {
                try {
                    $year = DB::connection(session('expense_connection', 'expense'))
                        ->table('financialyear')
                        ->where('YearId', $yearId)
                        ->value('Year');
                } catch (\Throwable $e) {}
            }
            if (empty($year)) {
                $year = session('year_value');
            }
        }

        if (empty($claimMonth) || empty($year)) {
            return '';
        }
        $monthName = date('F', strtotime("{$claimMonth}/01"));
        if ($category === 'Retailer') {
            return "{$claimType} Retailer Meeting Expenses {$monthName} {$year}";
        }
        if ($category === 'Farmer') {
            return "{$claimType} Farmer Meeting Expenses {$monthName} {$year}";
        }
        if ($category === 'Dealer') {
            return "{$claimType} Dealer Meeting Expenses {$monthName} {$year}";
        }
        if ($category === 'Distributor') {
            return "{$claimType} Distributor Meeting Expenses {$monthName} {$year}";
        }
        if ($category === 'Market') {
            return "{$claimType} Market Development Expenses {$monthName} {$year}";
        }
        if ($category === 'Other') {
            return "{$claimType} Expenses {$monthName} {$year}";
        }
        if ($claimType == 'Team Meeting Expenses') {
            return "Team Meeting Expenses {$monthName} {$year}";
        }

        return "Travel Expenses {$monthName} {$year}";
    }

    private function formatMonth($month)
    {
        if (empty($month)) {
            return '';
        }
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);

        return date('M', strtotime("$month/01"));
    }

    private function headerStyle($sheet, $range)
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }

    private function fmt($number): string
    {
        return ($number && $number != 0) ? (new CommonHelper)->InrCurrencyFormat($number, 0) : '';
    }

    private function getColumnLetter($columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $temp = ($columnNumber - 1) % 26;
            $letter = chr(65 + $temp).$letter;
            $columnNumber = intval(($columnNumber - $temp - 1) / 26);
        }

        return $letter;
    }
}
