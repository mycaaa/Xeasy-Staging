<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\admin\BtcController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * BTC-APP-01 (17 Nov 2026): Bill To Company in the mobile app - the employee's own requests, raising a new one,
 * and the approvals waiting for him (reporting manager, department head). The app's legacy token is checked,
 * then the same web BTC screens' code runs as that user, so every rule and permission is the same as on the web.
 *
 *   {base}/api/{ComId}/btc.php?value=list|detail|act|create|meta|vendors&empid=..&tokenid=..&ComId=..
 */
class BtcApiController extends BaseMobileController
{
    /** Steps the app offers; everything else stays on the web (desks). */
    public const APP_ACTIONS = ['submit', 'cancel', 'manager_approve', 'manager_reject', 'complete_activity'];   // BTC-V2: no department head step

    public function handle(Request $request): JsonResponse
    {
        $emp = $this->validateToken($request);
        if (! $emp) {
            return $this->error('Invalid Employee!');
        }
        $user = User::where('employee_id', (int) $emp->EmployeeID)->first();
        if (! $user) {
            return $this->error('No Xeasy web user for this employee.');
        }
        Auth::setUser($user);
        try {
            $request->setLaravelSession(app('session')->driver());
            session(['company_id' => (int) ($request->input('ComId') ?: $request->route('comId') ?: 1)]);
        } catch (\Throwable $e) {
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('btc_cases')) {
            return $this->error('BTC is not available.');
        }
        $web = app(BtcController::class);
        try {
            switch ((string) $request->input('value')) {
                case 'list':
                    $tab = $request->input('tab') === 'approvals' ? 'approvals' : 'mine';
                    $rows = json_decode($web->data(new Request(['tab' => $tab, 'search' => (string) $request->input('q')]))->getContent(), true)['data'] ?? [];

                    return $this->ok(['rows' => array_values($rows), 'tab' => $tab]);
                case 'detail':
                    $d = json_decode($web->show((int) $request->input('id'))->getContent(), true);
                    $d['actions'] = array_values(array_intersect($d['actions'] ?? [], self::APP_ACTIONS));
                    $d['files'] = collect($d['files'] ?? [])->map(fn ($f) => ['kind' => $f['kind'], 'name' => $f['name']])->all();   // files open on the web

                    return $this->ok(['data' => array_intersect_key($d, array_flip(['case', 'status_label', 'category_label', 'route_label', 'manager', 'dept_approver',
                        'pending', 'actions', 'files', 'logs', 'advance', 'pending_with', 'stages', 'payments', 'exceptions']))]);
                case 'act':
                    $action = (string) $request->input('action');
                    if (! in_array($action, self::APP_ACTIONS, true)) {
                        return $this->error('This step is done on the Xeasy website.');
                    }
                    $r = $web->act($request, (int) $request->input('id'));
                    $j = json_decode($r->getContent(), true);

                    return ($j['success'] ?? false) ? $this->ok(['message' => $j['message']]) : $this->error($j['message'] ?? 'Not done');
                case 'create':
                    $request->merge(['submit' => $request->boolean('submit')]);
                    $r = $web->store($request);
                    $j = json_decode($r->getContent(), true);

                    return ($j['success'] ?? false) ? $this->ok(['message' => $j['message'], 'id' => $j['id'] ?? null]) : $this->error($j['message'] ?? 'Not saved');
                case 'meta':
                    $routes = (array) config('btc.employee_routes');   // BTC-REQ-23

                    return $this->ok(['routes' => collect($routes)->map(fn ($k) => ['key' => $k, 'title' => config("btc.route_choices.{$k}.0", $k), 'help' => config("btc.route_choices.{$k}.1", '')])->values(),
                        'categories' => collect(config('btc.categories'))->filter(fn ($c) => $c['new_case'] ?? true)->map(fn ($c, $k) => ['key' => $k, 'label' => $c['label']])->values(),   // BTC-REQ-25
                        'purposes' => (array) config('btc.purposes', []),
                        'vendor_types' => config('btc.vendor_types_by_category'),
                        // BTC-TRIP-01 (25 Nov 2026): Air / Train - travel_from, travel_to, trip_type (one_way / round_trip),
                        // travel_date, return_date; the manager sends booking_type with manager_approve of an admin booking
                        'trip_categories' => (array) config('btc.trip_categories', []), 'booking_types' => (array) config('btc.booking_types', []),
                        'booking_type_categories' => (array) config('btc.booking_type_categories', ['air'])]);
                case 'vendors':
                    $q = trim((string) $request->input('q'));
                    if (\App\Services\Btc\CoreVendors::active()) {   // BTC-VENDOR-CORE-01: vendor list from the core application
                        $cat = (string) $request->input('category');
                        $served = in_array($cat, collect((array) config('btc.core_vendors.category_map'))->flatten()->all(), true);   // Air / Train: no core category - all vendors, as on the web
                        $rows = collect(\App\Services\Btc\CoreVendors::forForm())
                            ->filter(fn ($v) => ! $served || ! $v['types'] || in_array($cat, $v['types'], true))
                            ->filter(fn ($v) => $q === '' || stripos($v['name'], $q) !== false || stripos($v['gst'], $q) !== false)
                            ->take(30)->map(fn ($v) => ['id' => $v['id'], 'name' => $v['name'], 'gst' => $v['gst']])->values();

                        return $this->ok(['rows' => $rows]);
                    }
                    $types = config('btc.vendor_types_by_category.'.$request->input('category'), []);

                    return $this->ok(['rows' => DB::table('vendor_list')->where('status', '1')->when($types, fn ($w) => $w->whereIn('vendor_type', $types))
                        ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('vendor_name', 'like', "%{$q}%")->orWhere('gst_no', 'like', "%{$q}%")))
                        ->orderBy('vendor_name')->limit(30)->get(['id', 'vendor_name as name', 'gst_no as gst'])]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error(collect($e->errors())->flatten()->first() ?? 'Check the entries.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->error($e->getStatusCode() === 403 ? 'You cannot open this request.' : 'Not found.');
        } catch (\Throwable $e) {
            report($e);

            return $this->error('BTC could not be loaded. Please try again.');
        }

        return $this->error('Unknown request.');
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json(['Code' => '300', 'msg' => 'OK'] + $data);
    }
}
