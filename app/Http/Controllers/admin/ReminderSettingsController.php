<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Support\ReminderSettings;
use Illuminate\Http\Request;

/** REM-SET-01: one screen for the reminder mail settings. */
class ReminderSettingsController extends Controller
{
    public function index()
    {
        return view('admin.reminder-settings', [
            'fields' => ReminderSettings::FIELDS,
            'values' => ReminderSettings::current(),
            'mailActive' => filter_var(config('xeasy_notifications.mail_active', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function update(Request $request)
    {
        $changed = ReminderSettings::save($request->all(), auth()->id());

        return redirect()->route('reminder.settings')->with('success', $changed ? count($changed).' setting(s) saved.' : 'Nothing changed.');
    }
}
