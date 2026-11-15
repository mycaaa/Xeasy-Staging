<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * PAGE-CLEAN-02 (15 Nov 2026): one page per address. An old or second address of a page now redirects to the
 * page's main address, keeping its query string, so bookmarks and old links still work. The route keeps its
 * name, so route('old.name') still produces a working link.
 *
 *   Route::get('old-address', AliasRedirectController::class)->defaults('to', '/main-address');
 *   Route::get('approver', AliasRedirectController::class)->defaults('to', 'company-approvals');
 */
class AliasRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $to = (string) ($request->route()?->defaults['to'] ?? '/');
        if ($to === 'company-approvals') {
            // the old approver screen: the company approvals screen of the user's current company
            $to = match ((int) session('company_id', 1)) {
                11 => '/vvnr/approver',
                3 => '/nursery/approver',
                default => '/vnr/approver',
            };
        }
        $qs = $request->getQueryString();

        return redirect()->to('/'.ltrim($to, '/').($qs ? '?'.$qs : ''), 301);
    }
}
