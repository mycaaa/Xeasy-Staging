<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\SubmissionSetting;
use Illuminate\Http\Request;

class SubmissionSettingController extends Controller
{
    /**
     * Show the submission settings page.
     */
    public function index()
    {
        $settings = SubmissionSetting::orderBy('company_id')->get();

        // Ensure all companies have a record
        $companyIds = [1, 3, 11];
        foreach ($companyIds as $cid) {
            if (!$settings->contains('company_id', $cid)) {
                SubmissionSetting::create([
                    'company_id' => $cid,
                    'last_submission_day' => 15,
                    'is_active' => true,
                ]);
            }
        }
        $settings = SubmissionSetting::orderBy('company_id')->get();

        return view('admin.submission-settings.index', compact('settings'));
    }

    /**
     * Update submission settings.
     */
    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.company_id' => 'required|integer|in:1,3,11',
            'settings.*.last_submission_day' => 'required|integer|min:1|max:28',
        ]);

        foreach ($request->input('settings') as $data) {
            SubmissionSetting::updateOrCreate(
                ['company_id' => $data['company_id']],
                [
                    'last_submission_day' => $data['last_submission_day'],
                    'is_active' => true,
                    'updated_by' => auth()->user()->name ?? 'System',
                ]
            );
        }

        return redirect()->back()->with('success', 'Submission deadline updated successfully.');
    }
}
