<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmailTemplateRequest;
use App\Http\Requests\UpdateEmailTemplateRequest;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;

/**
 * This controller handles everything related to email templates in the admin area.
 */
class EmailTemplateController extends Controller
{
    /**
     * Shows a page with a list of all email templates.
     */
    public function index()
    {

        $status = request('status', 'active');
        $templatesQuery = EmailTemplate::query();
        if ($status === 'active') {
            $templatesQuery->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $templatesQuery->where('is_active', 0);
        }
        $templates = $templatesQuery->get();
        return view('admin.email_templates', compact('templates', 'status'));
    }

    /**
     * Shows a form to create a new email template.
     * Not used right now.
     */
    public function create()
    {
        //
    }

    /**
     * Saves a new email template to the database.
     */
    public function store(StoreEmailTemplateRequest $request)
    {
        $validated = $request->validated();
        $template = EmailTemplate::create([
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'body_html' => $validated['body_html'],
            'body_text' => strip_tags($validated['body_html']),
            'is_active' => $validated['is_active'],
            'category' => $validated['category'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return $this->jsonSuccess($template, 'Email template created successfully.');
    }

    /**
     * Shows details of a specific email template.
     * Not used right now.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Gets an email template to edit it.
     */
    public function edit(string $id)
    {
        $template = EmailTemplate::findOrFail($id);

        return $this->jsonSuccess($template, 'Email template fetched successfully.');
    }

    /**
     * Updates an email template in the database.
     */
    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $template)
    {
        $validated = $request->validated();
        $template->update([
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'body_html' => $validated['body_html'],
            'body_text' => strip_tags($validated['body_html']),
            'is_active' => $validated['is_active'],
            'category' => $validated['category'] ?? null,
            'updated_by' => auth()->id(),
        ]);

        return $this->jsonSuccess($template, 'Email template updated successfully.');
    }

    /**
     * Deletes an email template from the database.
     */
    public function destroy(string $id)
    {
        $template = EmailTemplate::findOrFail($id);
        $template->delete();

        return $this->jsonSuccess($template, 'Email template deleted successfully.');
    }

    /**
     * Gets a list of all active email templates.
     */
    public function templateList()
    {
        $query = EmailTemplate::select('id', 'name', 'subject', 'category', 'is_active', 'created_at', 'updated_at')
            ->where('is_active', 1);
        $data = $query->get();

        return response()->json($data);
    }

    /**
     * Gets activity logs for a specific email template.
     */
    public function getLogs(EmailTemplate $template)
    {
        $logs = $template->activities()
            ->with('causer')
            ->latest()
            ->get()
            ->map(function ($log) {
                return [
                    'event' => $log->event,
                    'description' => $log->description,
                    'properties' => $log->properties->toArray(),
                    'causer' => $log->causer ? ['name' => $log->causer->name] : null,
                    'created_at' => $log->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $logs,
            'title' => $template->name,
        ]);
    }

    public function getVariables()
    {
        $variables = DB::table('eml_template_variables')->select('variable_name')->get();

        return response()->json($variables);
    }
}
