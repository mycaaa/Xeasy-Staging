<?php

namespace App\Http\Controllers\admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ExpenseDetailsController extends Controller
{
    public function generatePdf()
    {
        // Sample data for the PDF
        $data = [
            'claimer' => '0148-Prakash Kumar Kapri',
            'period' => 'April/2025-2026',
            'hq' => 'Bandamailaram',
            'state' => 'Telangana',
            'total_amount' => 2501.5,
            'submitted_date' => '14-05-2025',
            'expense_type' => 'TRV (Travel)',
            'expenses' => [
                ['date' => '01-04-2025', 'description' => 'Medchal to plant to kokanda (2time)', 'from_km' => 80127, 'to_km' => 80174, 'total_km' => 47, '2w' => '', '4w' => 470, 'ldg' => '', 'mls' => '', 'msc' => '', 'total' => 470],
                ['date' => '02-04-2025', 'description' => 'Medchal to plant to kokanda to medc', 'from_km' => 80174, 'to_km' => 80220, 'total_km' => 46, '2w' => '', '4w' => 460, 'ldg' => '', 'mls' => '', 'msc' => '', 'total' => 460],
                ['date' => '03-04-2025', 'description' => 'Medchal to plant to kokanda to medc', 'from_km' => 80220, 'to_km' => 80269, 'total_km' => 49, '2w' => '', '4w' => 490, 'ldg' => '', 'mls' => '', 'msc' => '', 'total' => 490],
                ['date' => '04-04-2025', 'description' => 'Medchal to plant to kokanda, kistap', 'from_km' => 80271, 'to_km' => 80324, 'total_km' => 53, '2w' => '', '4w' => 530, 'ldg' => '', 'mls' => '', 'msc' => '', 'total' => 530],
                // Add more rows as per the image data
            ],
        ];
        return view('admin.expense-details', $data);

        // // Load the Blade view and pass data
        // $pdf = Pdf::loadView('admin.expense-details', $data);

        // // Set paper size and orientation
        // $pdf->setPaper('A4', 'portrait');

        // // Stream the PDF in the browser
        // return $pdf->stream('expense-details.pdf');
    }
}
