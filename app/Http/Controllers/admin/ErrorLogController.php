<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * ERRLOG-01 (15 Nov 2026): latest application errors for a Super Admin on test servers, so a "Server Error" seen
 * on xeasy can be traced without shell access. Only the first line of each ERROR / CRITICAL entry, newest first,
 * with passwords, tokens and keys masked. Off when APP_ENV is production unless ERROR_LOG_VIEWER=true.
 */
class ErrorLogController extends Controller
{
    public function index(Request $request)
    {
        abort_if(app()->environment('production') && ! filter_var(env('ERROR_LOG_VIEWER', false), FILTER_VALIDATE_BOOLEAN), 404);
        $q = trim((string) $request->input('q', ''));
        $rows = [];
        foreach ($this->logFiles() as $file) {
            foreach ($this->tail($file, 3 * 1024 * 1024) as $line) {
                if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/', $line, $m)) {
                    continue;
                }
                $msg = $this->mask($m[3]);
                $where = '';
                if (preg_match('/ at (\/[^\s"\)]+:\d+)\)?/', $msg, $w)) {
                    $where = preg_replace('#^.*?/(app|resources|routes|config|vendor)/#', '$1/', $w[1]);
                }
                $msg = trim(preg_replace('/\s*\{"(userId|exception)".*$/s', '', $msg));
                if ($q !== '' && stripos($msg.' '.$where, $q) === false) {
                    continue;
                }
                $rows[] = ['at' => $m[1], 'level' => $m[2], 'message' => mb_substr($msg, 0, 600), 'where' => $where, 'file' => basename($file)];
            }
        }
        usort($rows, fn ($a, $b) => strcmp($b['at'], $a['at']));

        return view('admin.error-log', ['rows' => array_slice($rows, 0, 200), 'q' => $q]);
    }

    private function logFiles(): array
    {
        $dir = storage_path('logs');
        $files = array_merge(glob($dir.'/laravel.log') ?: [], glob($dir.'/laravel-*.log') ?: []);
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return array_slice($files, 0, 3);
    }

    /** Last $bytes of a file, as lines. */
    private function tail(string $file, int $bytes): array
    {
        $size = @filesize($file) ?: 0;
        $h = @fopen($file, 'rb');
        if (! $h) {
            return [];
        }
        fseek($h, max(0, $size - $bytes));
        $data = (string) stream_get_contents($h);
        fclose($h);

        return explode("\n", $data);
    }

    private function mask(string $s): string
    {
        $s = preg_replace('/(password|passwd|pwd|secret|token|api[_-]?key|authorization|bearer)(["\']?\s*[:=]\s*["\']?|\s+)[^\s,"\'&)]+/i', '$1$2***', $s);
        $s = preg_replace('/AKIA[0-9A-Z]{16}/', 'AKIA***', $s);

        return preg_replace('/\b[A-Za-z0-9+\/]{40,}={0,2}/', '***', $s);
    }
}
