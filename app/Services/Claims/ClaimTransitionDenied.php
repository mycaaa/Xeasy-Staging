<?php

namespace App\Services\Claims;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** B-01: a claim status change the transition table or the actor check does not allow. */
class ClaimTransitionDenied extends RuntimeException
{
    public function __construct(public string $to, public array $refused)
    {
        $first = $refused[0] ?? [];
        $ids = implode(', ', array_slice(array_column($refused, 'exp_id'), 0, 10));
        parent::__construct("Status change to {$to} not allowed for claim(s) {$ids}: ".($first['why'] ?? 'not allowed'));
    }

    public function render($request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
            return response()->json(['success' => false, 'status' => 'error', 'Code' => '403', 'msg' => $this->getMessage(), 'message' => $this->getMessage()], 403);
        }

        return back()->with('error', $this->getMessage());
    }
}
