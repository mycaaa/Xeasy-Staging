<?php

namespace App\Http\Controllers\admin\git;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class GitDiagnosticsController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $empId = (string) (auth()->user()?->employee_id ?? session('employee_id'));
            if (!in_array($empId, ['2081', '1729'], true)) {
                abort(403, 'Unauthorized. Version Control Diagnostics is restricted to authorized personnel only.');
            }
            return $next($request);
        });
    }

    public function diagnose(): \Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
    {
        $results = [];

        // Test 1: Check if git command works
        try {
            $result = Process::run('git --version');
            $results['git_version'] = [
                'status' => $result->successful() ? 'success' : 'failed',
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ];
        } catch (\Exception $e) {
            $results['git_version'] = [
                'status' => 'error',
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }

        // Test 2: Check common Git paths on Linux servers
        $commonPaths = [
            '/usr/bin/git',
            '/usr/local/bin/git',
            '/bin/git',
            '/opt/git/bin/git',
            'git', // Default PATH
        ];

        $results['path_tests'] = [];
        foreach ($commonPaths as $path) {
            try {
                $result = Process::run("{$path} --version");
                $results['path_tests'][$path] = [
                    'status' => $result->successful() ? 'success' : 'failed',
                    'output' => $result->output(),
                    'error' => $result->errorOutput(),
                ];
            } catch (\Exception $e) {
                $results['path_tests'][$path] = [
                    'status' => 'error',
                    'output' => '',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Test 3: Check PHP functions availability
        $results['php_functions'] = [
            'proc_open' => function_exists('proc_open'),
            'exec' => function_exists('exec'),
            'shell_exec' => function_exists('shell_exec'),
            'system' => function_exists('system'),
        ];

        // Test 4: Try using which/whereis command to find git
        try {
            $whichResult = Process::run('which git');
            $results['which_git'] = [
                'status' => $whichResult->successful() ? 'success' : 'failed',
                'output' => $whichResult->output(),
                'error' => $whichResult->errorOutput(),
            ];
        } catch (\Exception $e) {
            $results['which_git'] = [
                'status' => 'error',
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }

        // Test 5: Server information
        $results['server_info'] = [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'sapi' => php_sapi_name(),
            'user' => function_exists('posix_getpwuid') && function_exists('posix_geteuid')
                ? posix_getpwuid(posix_geteuid())['name']
                : 'unknown',
            'disabled_functions' => ini_get('disable_functions'),
        ];

        // Test 6: Check if .git directory exists
        $results['git_repo'] = [
            'exists' => file_exists(base_path('.git')),
            'is_dir' => is_dir(base_path('.git')),
            'readable' => is_readable(base_path('.git')),
        ];

        if (request()->wantsJson()) {
            return response()->json($results);
        }

        return view('admin.utility.git_diagnostics', compact('results'));
    }
}
