<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\SystemAlertsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemNotificationController extends Controller
{
    protected SystemAlertsService $alertsService;

    public function __construct(SystemAlertsService $alertsService)
    {
        $this->alertsService = $alertsService;
    }

    /**
     * Display the System Alerts & Notifications Details page.
     */
    public function index(Request $request): View
    {
        if (!SystemAlertsService::isAllowedUser(auth()->user())) {
            abort(403, 'Unauthorized access. System Notifications are restricted to Verifiers, Admins, and Super Admins.');
        }

        $data = $this->alertsService->getNotificationSummary();
        $activeTab = $request->get('tab', 'transfers');

        return view('admin.notifications.index', array_merge($data, [
            'activeTab' => $activeTab,
        ]));
    }

    /**
     * AJAX endpoint to return JSON alert summary for header notification bell.
     */
    public function getSummary(Request $request): JsonResponse
    {
        if (!SystemAlertsService::isAllowedUser(auth()->user())) {
            return response()->json(['total_count' => 0, 'transfers' => [], 'joinings' => [], 'missing_imprest' => []], 403);
        }

        $data = $this->alertsService->getNotificationSummary();
        return response()->json($data);
    }
}
