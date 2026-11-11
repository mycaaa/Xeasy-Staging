<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** FOCUS-LOG-01: read-only list of Focus exports (newest first). No edit or delete. */
class FocusExportLogController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('focus_export_logs')->orderByDesc('id');
        if ($c = (int) $request->get('company')) {
            $q->where('company_id', $c);
        } elseif (session('company_id')) {
            $q->where('company_id', (int) session('company_id'));
        }
        if ($t = $request->get('type')) {
            $q->where('export_type', $t);
        }
        $logs = $q->limit(500)->get();
        $types = DB::table('focus_export_logs')->distinct()->orderBy('export_type')->pluck('export_type');

        return view('reports.focus-export-log', compact('logs', 'types'));
    }
}
