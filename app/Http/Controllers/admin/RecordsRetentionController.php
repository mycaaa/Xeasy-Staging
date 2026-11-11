<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\Retention\RecordRetention;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** RET-01: retention status of each financial year (newest first). */
class RecordsRetentionController extends Controller
{
    public function index()
    {
        $co = (int) session('company_id', 1);
        $conn = (string) (session('expense_connection') ?: 'expense');
        $years = RecordRetention::years($conn);
        $arch = Schema::connection($conn)->hasTable('record_archives')
            ? DB::connection($conn)->table('record_archives')->where('company_id', $co)->get()->keyBy('year_id') : collect();
        foreach ($years as $id => &$y) {
            $y += RecordRetention::counts($conn, $id);
            $y['archive'] = $arch[$id] ?? null;
        }
        unset($y);

        return view('admin.records-retention', ['years' => $years, 'keep' => config('retention.keep_years'), 'after' => config('retention.archive_after_years')]);
    }
}
