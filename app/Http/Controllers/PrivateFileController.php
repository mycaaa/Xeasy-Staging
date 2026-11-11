<?php

namespace App\Http\Controllers;

use App\Support\PrivateExport;
use App\Support\RecordAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * FILES-01 (UAT-03): authorised downloads for files that used to sit in public folders.
 *  - Claim-stage / Focus exports: login + a reports or finance permission (config/route_access.php
 *    rule for exports/download and storage/temp).
 *  - Pax supporting documents: login + RecordAccess (own claim, reporting chain, approver or staff).
 */
class PrivateFileController extends Controller
{
    public function export(string $file)
    {
        $found = PrivateExport::locate($file);
        if (! $found) {
            abort(404);
        }
        [$disk, $path] = $found;

        return Storage::disk($disk)->download($path, basename($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pax(int $year, int $claim, string $file)
    {
        if (! preg_match('#^exp_(\d+)_[A-Za-z0-9_]+\.(jpg|jpeg|png|gif|pdf|webp|heic)$#i', $file, $m)) {
            abort(404);
        }
        $user = Auth::user();
        if (! RecordAccess::canViewClaim($user, (int) $m[1], $year, (int) session('company_id') ?: null)) {
            abort(403, 'You cannot open this document.');
        }
        $rel = "pax_documents/{$year}/{$claim}/{$file}";
        $full = storage_path('app/private/'.$rel);
        if (! is_file($full)) {
            // A file not moved yet is still in public/ (the web server normally serves it itself).
            $full = public_path('uploads/'.$rel);
        }
        if (! is_file($full)) {
            abort(404);
        }

        return response()->file($full, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
