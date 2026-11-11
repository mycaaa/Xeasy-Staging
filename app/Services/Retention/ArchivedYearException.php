<?php

namespace App\Services\Retention;

/** RET-01: a write into an archived (read-only) financial year. */
class ArchivedYearException extends \RuntimeException
{
    public function render($request)
    {
        if ($request->expectsJson() || $request->ajax() || $request->is('api/*')) {
            return response()->json(['success' => false, 'status' => 'error', 'Code' => '100', 'msg' => $this->getMessage(), 'message' => $this->getMessage()], 423);
        }

        return back()->with('error', $this->getMessage());
    }
}
