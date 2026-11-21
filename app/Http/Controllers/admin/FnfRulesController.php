<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\Fnf\FnfRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FNF-RULES-01 (21 Nov 2026, "Decision for You" testing point 3b): Payment > F&F Rules. Shows every rule of the F&F
 * working with the value in force and its history; a change is saved as a new value with an effective-from date
 * (and optional effective-to date), so workings of employees whose last working day is before it are not changed.
 */
class FnfRulesController extends Controller
{
    public function index()
    {
        abort_unless(FnfRules::ready(), 503, 'Run the database update (Admin > DB setup) first.');
        $hist = DB::table('fnf_rules')->orderByDesc('effective_from')->orderByDesc('id')->get()->groupBy('rule_key');
        $reminder = \Illuminate\Support\Facades\Schema::hasTable('eml_resigned_reminder_settings') ? DB::table('eml_resigned_reminder_settings')->where('id', 1)->first() : null;

        return view('fnf.rules', ['rules' => FnfRules::RULES, 'now' => FnfRules::all(), 'hist' => $hist, 'reminder' => $reminder]);
    }

    public function save(Request $request)
    {
        abort_unless(FnfRules::ready(), 503, 'Run the database update (Admin > DB setup) first.');
        $v = $request->validate([
            'rule_key' => 'required|in:'.implode(',', array_keys(FnfRules::RULES)),
            'value' => 'required|integer',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'remark' => 'required|string|min:5|max:300',
        ], ['remark.required' => 'Give the reason for the change.']);
        $def = FnfRules::RULES[$v['rule_key']];
        if ((int) $v['value'] < $def[4] || (int) $v['value'] > $def[5]) {
            return back()->withInput()->withErrors(['value' => "The value must be between {$def[4]} and {$def[5]}."]);
        }
        DB::table('fnf_rules')->insert(['rule_key' => $v['rule_key'], 'value' => (int) $v['value'], 'effective_from' => substr($v['effective_from'], 0, 10),
            'effective_to' => ! empty($v['effective_to']) ? substr($v['effective_to'], 0, 10) : null, 'remark' => $v['remark'],
            'updated_by' => auth()->id(), 'updated_by_name' => (string) (auth()->user()->name ?? ''), 'created_at' => now(), 'updated_at' => now()]);
        FnfRules::forget();

        return back()->with('success', '"'.$def[0].'" set to '.$v['value'].' from '.date('d-m-Y', strtotime($v['effective_from'])).'. F&F workings of employees whose last working day is on or after that date use it; cleared workings stay as they were.');
    }
}
