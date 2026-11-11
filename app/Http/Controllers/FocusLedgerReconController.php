<?php

namespace App\Http\Controllers;

use App\Services\Focus\FocusLedgerParser;
use App\Services\Focus\FocusLedgerReconciler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * FOCUS-RECON-01 (10 Nov 2026): upload an employee's Focus ledger (the Focus "Ledger of <employee>" Excel)
 * and see where and why it differs from Xeasy: totals, a bridge that adds up to the closing difference,
 * debit and credit matching line by line, and the month-end balances side by side.
 * A file with several employee ledgers gives one result per employee.
 */
class FocusLedgerReconController extends Controller
{
    public function index(Request $request)
    {
        $token = (string) $request->query('r', '');
        $result = $token !== '' ? Cache::get('focus_recon:'.$token) : null;
        $history = Schema::hasTable('focus_ledger_recon_logs')
            ? DB::table('focus_ledger_recon_logs')->where('company_id', (int) session('company_id', 1))->orderByDesc('id')->limit(25)->get()
            : collect();

        return view('reports.focus-ledger-recon', ['result' => $result, 'token' => $token, 'history' => $history]);
    }

    public function upload(Request $request)
    {
        $request->validate(['ledger' => 'required|file|mimes:xlsx,xls|max:10240']);
        $file = $request->file('ledger');
        try {
            $doc = (new FocusLedgerParser())->parse($file->getRealPath());
        } catch (\Throwable $e) {
            return back()->withErrors(['ledger' => 'Could not read the file: '.$e->getMessage()]);
        }
        if (! $doc['sections']) {
            return back()->withErrors(['ledger' => 'No employee ledger found in the file.']);
        }
        $conn = (string) (session('expense_connection') ?: 'expense');
        $company = (int) session('company_id', 1);
        $rec = new FocusLedgerReconciler($conn, $company);
        $results = [];
        foreach ($doc['sections'] as $s) {
            $r = $rec->reconcile($s, $doc['from'], $doc['to']);
            $results[] = $r;
            if (! isset($r['error']) && Schema::hasTable('focus_ledger_recon_logs')) {
                DB::table('focus_ledger_recon_logs')->insert([
                    'user_id' => auth()->id(), 'company_id' => $company, 'employee_id' => $r['employee']['id'], 'emp_code' => $r['employee']['code'],
                    'file_name' => mb_substr($file->getClientOriginalName(), 0, 190), 'period_from' => $r['from'], 'period_to' => $r['to'],
                    'focus_closing' => $r['summary']['focus']['closing'], 'xeasy_closing' => $r['summary']['xeasy']['closing'],
                    'difference' => $r['difference'], 'items' => count($r['bridge']), 'created_at' => now(),
                ]);
            }
        }
        $token = Str::random(24);
        Cache::put('focus_recon:'.$token, ['file' => $file->getClientOriginalName(), 'company' => $doc['company'], 'from' => $doc['from'], 'to' => $doc['to'], 'results' => $results, 'user' => auth()->id()], now()->addHours(4));

        return redirect()->route('focus.ledger.recon', ['r' => $token]);
    }

    public function export(Request $request)
    {
        $data = Cache::get('focus_recon:'.(string) $request->query('r', ''));
        abort_unless($data, 404, 'This result has expired; upload the ledger again.');
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);
        foreach ($data['results'] as $i => $r) {
            if (isset($r['error'])) {
                continue;
            }
            $tag = substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $r['employee']['code']), 0, 10);
            $this->sheet($book, "{$tag} Summary", array_merge(
                [['Employee', $r['employee']['name'].' ('.$r['employee']['code'].')'], ['Period', $r['from'].' to '.$r['to']], [], ['', 'Focus', 'Xeasy', 'Difference']],
                array_map(fn ($k) => [ucfirst($k), $r['summary']['focus'][$k], $r['summary']['xeasy'][$k], round($r['summary']['focus'][$k] - $r['summary']['xeasy'][$k], 2)], ['opening', 'debit', 'credit', 'closing']),
                [[], ['Where the difference comes from', 'Effect on the Focus balance'], ...array_map(fn ($b) => [$b['what'].': '.$b['detail'], $b['effect']], $r['bridge']), ['Total', $r['bridge_total']], [], ['Notes'], ...array_map(fn ($n) => [$n], $r['notes'])]
            ));
            $this->sheet($book, "{$tag} Debits", array_merge([['Status', 'Focus date', 'Focus voucher', 'Focus narration', 'Focus amount', 'Xeasy date', 'Xeasy reference', 'Xeasy entry', 'Xeasy amount', 'Difference', 'Note']],
                array_map(fn ($d) => [$d['status'], $d['focus_date'], $d['focus_voucher'], $d['focus_narration'], $d['focus_amount'], $d['xeasy_date'], $d['xeasy_ref'], $d['xeasy_label'], $d['xeasy_amount'], $d['diff'], $d['note']], $r['debits'])));
            $this->sheet($book, "{$tag} Credits", array_merge([['Status', 'Expense month', 'Account', 'Head', 'Focus vouchers', 'Focus amount', 'Xeasy reference', 'Xeasy amount', 'Difference', 'Focus lines', 'Xeasy lines', 'Note']],
                array_map(fn ($c) => [$c['status'], $c['month'], $c['account'], $c['head'], $c['vouchers'], $c['focus_amount'], $c['xeasy_ref'], $c['xeasy_amount'], $c['diff'], implode('; ', $c['focus_lines']), implode('; ', $c['xeasy_lines']), $c['note']], $r['credits'])));
            $this->sheet($book, "{$tag} Months", array_merge([['Month end', 'Focus balance', 'Xeasy balance', 'Difference', 'Difference changed this month']],
                array_map(fn ($m) => [$m['month_end'], $m['focus'], $m['xeasy'], $m['diff'], $m['changed'] ? 'yes' : ''], $r['months'])));
        }
        if ($book->getSheetCount() === 0) {
            $this->sheet($book, 'Result', [['No employee could be reconciled.']]);
        }
        $path = storage_path('app/focus_recon_'.Str::random(8).'.xlsx');
        (new Xlsx($book))->save($path);

        return response()->download($path, 'Focus_vs_Xeasy_'.date('Ymd_His').'.xlsx')->deleteFileAfterSend(true);
    }

    private function sheet(Spreadsheet $book, string $title, array $rows): void
    {
        $s = $book->createSheet();
        $s->setTitle(substr($title, 0, 31));
        $s->fromArray($rows, null, 'A1', true);
        $s->getStyle('A1:Z1')->getFont()->setBold(true);
        foreach (range('A', 'L') as $c) {
            $s->getColumnDimension($c)->setAutoSize(true);
        }
    }
}
