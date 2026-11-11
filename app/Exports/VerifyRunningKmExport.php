<?php

namespace App\Exports;

use App\Http\Controllers\admin\RateController;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Enumerable;

class VerifyRunningKmExport implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle, WithStyles, WithEvents
{
    protected $records;
    protected $yearId;
    protected $basePath;
    protected $disk;
    protected $isOnlyEmployee;

    public function __construct($records, $yearId, $basePath, $disk, bool $isOnlyEmployee = false)
    {
        $this->records = $records;
        $this->yearId = $yearId;
        $this->basePath = $basePath;
        $this->disk = $disk;
        $this->isOnlyEmployee = $isOnlyEmployee;
    }

    public function title(): string
    {
        return 'Verify Running KM';
    }

    public function headings(): array
    {
        return [
            'Sn',
            'Emp Code',
            'Emp Name',
            'Department',
            'ExpId',
            'Upload Date',
            'Bill Date',
            'Opn. Img',
            'Opn.',
            'Cls. Img',
            'Cls.',
            'Remark',
            'Total KM',
            'Cum. KM',
            'Rate',
            'Amount',
            '2W/4W',
            'Status',
        ];
    }

    public function collection(): Enumerable
    {
        $data = [];
        $sn = 1;
        $cumulativePerEmp = [];
        $rateCache = [];
        $rateController = new RateController;

        foreach ($this->records as $row) {
            $empId = $row->CrBy ?? $row->EmpCode;
            $totKm = (float) ($row->TotKm ?? 0);

            if (!isset($cumulativePerEmp[$empId])) {
                $cumulativePerEmp[$empId] = 0;
            }
            $cumulativePerEmp[$empId] += $totKm;
            $cumKm = $cumulativePerEmp[$empId];

            // Image URLs
            $openingImgUrl = 'Upload Pending';
            if (!empty($row->opening_filepath)) {
                $path = "{$this->basePath}/{$this->yearId}/{$row->CrBy}/{$row->opening_filepath}";
                try {
                    $openingImgUrl = $this->disk->url($path);
                } catch (\Exception $e) {
                    $openingImgUrl = $row->opening_filepath;
                }
            }

            $closingImgUrl = 'Upload Pending';
            if (!empty($row->closing_filepath)) {
                $path = "{$this->basePath}/{$this->yearId}/{$row->CrBy}/{$row->closing_filepath}";
                try {
                    $closingImgUrl = $this->disk->url($path);
                } catch (\Exception $e) {
                    $closingImgUrl = $row->closing_filepath;
                }
            }

            // Status resolution matching the UI screen
            if ((int) ($row->FilledOkay ?? 0) === 2) {
                $status = 'Denied' . (!empty($row->FilOkDenyRemark) ? ' - ' . $row->FilOkDenyRemark : '');
            } elseif (($row->v_verify ?? '') === 'Y') {
                $status = 'Verified';
            } elseif (($row->Backdate_Odometer_Status ?? '') === 'P') {
                $status = 'Pending';
            } elseif (($row->Backdate_Odometer_Status ?? '') === 'A') {
                $status = 'Approved';
            } elseif (($row->Backdate_Odometer_Status ?? '') === 'R') {
                $status = 'Rejected';
            } else {
                $status = 'Pending';
            }

            $vehicleType = '';
            if (($row->WType ?? null) == 2) {
                $vehicleType = '2W';
            } elseif (($row->WType ?? null) == 4) {
                $vehicleType = '4W';
            } elseif (!empty($row->WType)) {
                $vehicleType = (string) $row->WType;
            }

            $rate = (float) ($row->RatePerKM ?? 0);
            if ($rate <= 0) {
                $wheelType = (int) ($row->WType ?: 2);
                $billDate = $row->BillDate ? Carbon::parse($row->BillDate)->format('Y-m-d') : date('Y-m-d');
                $cacheKey = "{$row->CrBy}_{$billDate}_{$wheelType}_" . ($row->With_Driver ?? '');
                if (!isset($rateCache[$cacheKey])) {
                    $rateCache[$cacheKey] = (float) $rateController->getRatePerKM($row->CrBy, $billDate, $wheelType, 0, $row->With_Driver ?? null);
                }
                $rate = $rateCache[$cacheKey];
            }

            $amount = (float) ($row->FilledTAmt ?? 0);
            if ($amount <= 0 && $totKm > 0 && $rate > 0) {
                $amount = round($totKm * $rate, 2);
            }

            $data[] = [
                $sn++,
                $row->EmpCode ?? '',
                $row->employee_name ?? '',
                $row->department_name ?? '',
                $row->ExpId ?? '',
                $row->CrDate ? Carbon::parse($row->CrDate)->format('d-m-Y') : '',
                $row->BillDate ? Carbon::parse($row->BillDate)->format('d-m-Y') : '',
                $openingImgUrl,
                $row->odomtr_opening !== null ? (string) $row->odomtr_opening : '',
                $closingImgUrl,
                $row->odomtr_closing !== null ? (string) $row->odomtr_closing : '',
                $row->verifyremark ?? '',
                $totKm,
                $cumKm,
                number_format($rate, 2),
                number_format($amount, 2),
                $vehicleType,
                $status,
            ];
        }

        return collect($data);
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF15648C'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Header row height
                $sheet->getRowDimension(1)->setRowHeight(28);

                // Freeze header row
                $sheet->freezePane('A2');

                // Enable auto filter for all headers
                $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");

                // Center align specific columns
                $centerCols = ['A', 'B', 'E', 'F', 'G', 'I', 'K', 'M', 'N', 'O', 'P', 'Q', 'R'];
                foreach ($centerCols as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$highestRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Left align text columns
                $leftCols = ['C', 'D', 'H', 'J', 'L'];
                foreach ($leftCols as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$highestRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // Set hyperlink styling for image URL cells
                for ($row = 2; $row <= $highestRow; $row++) {
                    // Set row height for neat display
                    $sheet->getRowDimension($row)->setRowHeight(20);

                    // Opening image link
                    $openCell = $sheet->getCell("H{$row}");
                    $openVal = (string) $openCell->getValue();
                    if (str_starts_with($openVal, 'http')) {
                        $openCell->getHyperlink()->setUrl($openVal);
                        $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FF0D6EFD');
                        $sheet->getStyle("H{$row}")->getFont()->setUnderline(true);
                    }

                    // Closing image link
                    $closeCell = $sheet->getCell("J{$row}");
                    $closeVal = (string) $closeCell->getValue();
                    if (str_starts_with($closeVal, 'http')) {
                        $closeCell->getHyperlink()->setUrl($closeVal);
                        $sheet->getStyle("J{$row}")->getFont()->getColor()->setARGB('FF0D6EFD');
                        $sheet->getStyle("J{$row}")->getFont()->setUnderline(true);
                    }
                }

                // Add thin border to all cells
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFD0D5DD'],
                        ],
                    ],
                ]);
            },
        ];
    }
}
