<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\BillToCompany;
use App\Models\BillToCompanyCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BillToCompanyController extends Controller
{
    // ── Allowed MIME types / extensions ─────────────────────────────────────
    private const ALLOWED_MIMES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    private const MAX_FILE_MB   = 10;

    // ── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $companyId  = session('company_id', 1);
        $categories = BillToCompanyCategory::active()->orderBy('name')->get();

        return view('admin.bill-to-company.index', compact('companyId', 'categories'));
    }

    // ── DataTable data ───────────────────────────────────────────────────────

    public function getData(Request $request)
    {
        $companyId  = session('company_id', 1);
        $employeeId = $request->input('employee_id');
        $category   = $request->input('category');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');

        $query = BillToCompany::forCompany($companyId)
            ->active()
            ->orderByDesc('bill_date')
            ->orderByDesc('id');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        if ($category) {
            $query->where('bill_category', $category);
        }
        if ($dateFrom) {
            $query->whereDate('bill_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('bill_date', '<=', $dateTo);
        }

        $bills = $query->get()->map(function ($bill) {
            return [
                'id'            => $bill->id,
                'emp_code'      => $bill->emp_code,
                'emp_name'      => $bill->emp_name,
                'bill_title'    => $bill->bill_title,
                'bill_category' => $bill->bill_category ?? '—',
                'category_name' => $bill->bill_category ?? '—',
                'bill_amount'   => $bill->bill_amount ? number_format($bill->bill_amount, 2) : '—',
                'bill_date'     => $bill->bill_date ? $bill->bill_date->format('d M Y') : '—',
                'file_name'     => $bill->file_name,
                'file_type'     => $bill->file_type,
                'file_url'      => $bill->file_url,
                'is_image'      => $bill->is_image,
                'is_pdf'        => $bill->is_pdf,
                'remarks'       => $bill->remarks,
                'created_at'    => $bill->created_at ? $bill->created_at->format('d M Y, h:i A') : '—',
            ];
        });

        return response()->json(['success' => true, 'data' => $bills]);
    }

    // ── Store (upload) ───────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'employee_id'      => 'required|integer',
            'emp_code'         => 'nullable|string|max:20',
            'emp_name'         => 'nullable|string|max:150',
            'bill_title'       => 'required|string|max:255',
            'bill_category'    => 'nullable|string|max:100',
            'bill_category_id' => 'nullable|integer|exists:bill_to_company_categories,id',
            'bill_amount'      => 'nullable|numeric|min:0',
            'bill_date'        => 'nullable|date',
            'remarks'          => 'nullable|string|max:1000',
            'bill_file'        => 'required|file|mimes:jpg,jpeg,png,gif,webp,pdf|max:' . (self::MAX_FILE_MB * 1024),
        ]);

        try {
            $companyId = session('company_id', 1);
            $file      = $request->file('bill_file');
            $ext       = strtolower($file->getClientOriginalExtension());
            $timestamp = now()->format('YmdHis');
            $folder    = "bill_to_company/{$companyId}/{$timestamp}";
            $fileName  = Str::random(12) . '.' . $ext;

            $path = $file->storeAs($folder, $fileName, 'public');

            // Resolve category name: prefer id lookup, fall back to raw string
            $categoryName = null;
            if ($request->filled('bill_category_id')) {
                $cat          = BillToCompanyCategory::find((int) $request->bill_category_id);
                $categoryName = $cat?->name ?? $request->bill_category;
            } else {
                $categoryName = $request->bill_category;
            }

            BillToCompany::create([
                'company_id'    => $companyId,
                'employee_id'   => $request->employee_id,
                'emp_code'      => $request->emp_code,
                'emp_name'      => $request->emp_name,
                'bill_title'    => $request->bill_title,
                'bill_category' => $categoryName,
                'bill_amount'   => $request->bill_amount,
                'bill_date'     => $request->bill_date,
                'file_path'     => $path,
                'file_name'     => $file->getClientOriginalName(),
                'file_type'     => $ext,
                'remarks'       => $request->remarks,
                'status'        => 1,
                'uploaded_by'   => auth()->id(),
            ]);

            return response()->json(['success' => true, 'message' => 'Bill uploaded successfully.']);

        } catch (\Throwable $e) {
            Log::error('BillToCompany store error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Upload failed. Please try again.'], 500);
        }
    }

    // ── Show single record ───────────────────────────────────────────────────

    public function show(int $id)
    {
        $companyId = session('company_id', 1);
        $bill = BillToCompany::forCompany($companyId)->active()->findOrFail($id);

        return response()->json([
            'success' => true,
            'bill'    => [
                'id'            => $bill->id,
                'emp_code'      => $bill->emp_code,
                'emp_name'      => $bill->emp_name,
                'bill_title'    => $bill->bill_title,
                'bill_category' => $bill->bill_category,
                'bill_amount'   => $bill->bill_amount,
                'bill_date'     => $bill->bill_date?->format('Y-m-d'),
                'file_name'     => $bill->file_name,
                'file_type'     => $bill->file_type,
                'file_url'      => $bill->file_url,
                'is_image'      => $bill->is_image,
                'is_pdf'        => $bill->is_pdf,
                'remarks'       => $bill->remarks,
                'created_at'    => $bill->created_at?->format('d M Y, h:i A'),
            ],
        ]);
    }

    // ── Update remarks ───────────────────────────────────────────────────────

    public function updateRemarks(Request $request, int $id)
    {
        $request->validate(['remarks' => 'nullable|string|max:1000']);

        $companyId = session('company_id', 1);
        $bill = BillToCompany::forCompany($companyId)->active()->findOrFail($id);
        $bill->update(['remarks' => $request->remarks]);

        return response()->json(['success' => true, 'message' => 'Remarks updated.']);
    }

    // ── Delete (soft: set status = 0) ────────────────────────────────────────

    public function destroy(int $id)
    {
        $companyId = session('company_id', 1);
        $bill = BillToCompany::forCompany($companyId)->active()->findOrFail($id);

        // Keep the physical file; just deactivate the record.
        $bill->update(['status' => 0]);

        return response()->json(['success' => true, 'message' => 'Bill deleted successfully.']);
    }

    // ── Employee search helper (reuses hrims) ────────────────────────────────

    public function searchEmployees(Request $request)
    {
        $searchTerm = $request->input('q', '');
        $companyId  = session('company_id', 1);

        $employees = DB::connection('hrims')
            ->table('hrm_employee AS emp')
            ->select(
                'emp.EmployeeID',
                'emp.EmpCode_New AS emp_code',
                DB::raw("CONCAT_WS(' ', emp.Fname, emp.Sname, emp.Lname) AS emp_name")
            )
            ->where('emp.companyid', $companyId)
            ->where(function ($q) use ($searchTerm) {
                $q->whereRaw("CONCAT_WS(' ', emp.Fname, emp.Sname, emp.Lname) LIKE ?", ["%{$searchTerm}%"])
                  ->orWhere('emp.EmpCode_New', 'LIKE', "%{$searchTerm}%");
            })
            ->orderBy('emp.EmpCode_New')
            ->limit(20)
            ->get();

        // Format for Select2
        $results = $employees->map(fn($e) => [
            'id'       => $e->EmployeeID,
            'text'     => $e->emp_code . ' — ' . $e->emp_name,
            'emp_code' => $e->emp_code,
            'emp_name' => $e->emp_name,
        ]);

        return response()->json(['results' => $results]);
    }
}
