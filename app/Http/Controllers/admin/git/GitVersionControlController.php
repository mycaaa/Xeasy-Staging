<?php

namespace App\Http\Controllers\admin\git;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;


class GitVersionControlController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $empId = (string) (auth()->user()?->employee_id ?? session('employee_id'));
            if (!in_array($empId, ['2081', '1729'], true)) {
                abort(403, 'Unauthorized. Version Control is restricted to authorized personnel only.');
            }
            return $next($request);
        });
    }

    private function getProjectRoot(): string
    {
        return base_path();
    }

    private function runGitCommand(string $command): \Illuminate\Process\ProcessResult
    {
        // Ensure we're running Git commands from the project root
        $projectRoot = $this->getProjectRoot();

        // Get git executable path from config (or use default 'git')
        $gitPath = config('git.executable_path', 'git');

        // Replace 'git' at the start of the command with the full path
        if (str_starts_with($command, 'git ')) {
            $command = $gitPath.' '.substr($command, 4);
        } elseif ($command === 'git') {
            $command = $gitPath;
        }

        return Process::path($projectRoot)->run($command);
    }

    private function getGitConfig(): array
    {
        return [
            'repository_url' => env('GIT_REPOSITORY_URL', ''),
            'git_username' => env('GIT_USERNAME', ''),
            'git_password' => env('GIT_PASSWORD', ''),
            'git_branch' => env('GIT_BRANCH', 'main'),
            'git_key_path' => env('GIT_KEY_PATH', ''),
        ];
    }

    public function index(): \Illuminate\Contracts\View\View
    {
        $config = $this->getGitConfig();

        // Check if Git is initialized
        $isGitRepo = $this->isGitRepository();
        $hasRemote = $this->hasRemoteOrigin();

        // Get the actual remote URL from git if available
        if ($isGitRepo && $hasRemote) {
            $remoteResult = $this->runGitCommand('git remote get-url origin');
            if ($remoteResult->successful() && ! empty(trim($remoteResult->output()))) {
                $config['repository_url'] = trim($remoteResult->output());
            }
        }

        // If not initialized or no remote, try to set it up
        if ($isGitRepo && ! $hasRemote && ! empty($config['repository_url'])) {
            $this->setupRemoteOrigin($config['repository_url']);
            $hasRemote = $this->hasRemoteOrigin();
        }

        $status = $this->getGitStatus();
        $branches = $this->getBranches();
        $currentBranch = $this->getCurrentBranch();
        $logs = $this->getRecentLogs(25);

        return view('admin.utility.version_control', compact('config', 'status', 'branches', 'currentBranch', 'logs', 'isGitRepo', 'hasRemote'));
    }

    public function gitStatus(): \Illuminate\Http\JsonResponse
    {
        try {
            $status = $this->getGitStatus();

            return response()->json([
                'status' => 200,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitAdd(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'files' => 'nullable|string',
        ]);

        try {
            $files = $request->input('files', '.');
            $command = "git add {$files}";
            $result = $this->runGitCommand($command);

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Files added successfully.',
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to add files.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitCommit(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'message' => 'required|string|min:3',
        ]);

        try {
            $message = escapeshellarg($request->input('message'));
            $command = "git commit -m {$message}";
            $result = $this->runGitCommand($command);

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Changes committed successfully.',
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to commit changes.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitPush(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'branch' => 'nullable|string',
            'force' => 'nullable|boolean',
        ]);

        try {
            $config = $this->getGitConfig();
            $branch = $request->input('branch', $config['git_branch'] ?? 'main');
            $force = $request->input('force', false);
            $originalUrl = $config['repository_url'];

            // Set up authentication if credentials are provided
            $this->setupGitAuth($config);

            $forceFlag = $force ? ' --force' : '';
            $command = "git push origin {$branch}{$forceFlag}";
            $result = $this->runGitCommand($command);

            // Restore original URL if it was modified
            if (! empty($originalUrl)) {
                $this->runGitCommand("git remote set-url origin {$originalUrl}");
            }

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Changes pushed successfully.',
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to push changes.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            // Restore original URL on error
            $config = $this->getGitConfig();
            if (! empty($config['repository_url'])) {
                Process::run("git remote set-url origin {$config['repository_url']}");
            }

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitPull(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'branch' => 'nullable|string',
        ]);

        try {
            $config = $this->getGitConfig();
            $branch = $request->input('branch', $config['git_branch'] ?? 'main');
            $originalUrl = $config['repository_url'];

            // Set up authentication if credentials are provided
            $this->setupGitAuth($config);

            $command = "git pull origin {$branch}";
            $result = $this->runGitCommand($command);

            // Restore original URL if it was modified
            if (! empty($originalUrl)) {
                $this->runGitCommand("git remote set-url origin {$originalUrl}");
            }

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Changes pulled successfully.',
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to pull changes.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            // Restore original URL on error
            $config = $this->getGitConfig();
            if (! empty($config['repository_url'])) {
                $this->runGitCommand("git remote set-url origin {$config['repository_url']}");
            }

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitFetch(): \Illuminate\Http\JsonResponse
    {
        try {
            $config = $this->getGitConfig();
            $originalUrl = $config['repository_url'];
            $this->setupGitAuth($config);

            $command = 'git fetch';
            $result = $this->runGitCommand($command);

            // Restore original URL if it was modified
            if (! empty($originalUrl)) {
                $this->runGitCommand("git remote set-url origin {$originalUrl}");
            }

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Fetch completed successfully.',
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to fetch.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            // Restore original URL on error
            $config = $this->getGitConfig();
            if (! empty($config['repository_url'])) {
                $this->runGitCommand("git remote set-url origin {$config['repository_url']}");
            }

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitCheckout(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'branch' => 'required|string',
        ]);

        try {
            $branch = $request->input('branch');
            $command = "git checkout {$branch}";
            $result = $this->runGitCommand($command);

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => "Switched to branch: {$branch}",
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to checkout branch.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitLog(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        try {
            $limit = (int) $request->input('limit', 25);
            $logs = $this->getRecentLogs($limit);

            return response()->json([
                'status' => 200,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitDiff(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $file = $request->input('file');
            $gitPath = config('git.executable_path', 'git');

            $args = [$gitPath, 'diff'];
            if (! empty($file)) {
                $args[] = '--';
                $args[] = $file;
            }

            $result = Process::path($this->getProjectRoot())->run($args);
            $diffOutput = $result->output();

            // If empty working-tree diff and a specific file was requested, check staged diff
            if (empty(trim($diffOutput)) && ! empty($file)) {
                $stagedResult = Process::path($this->getProjectRoot())->run([$gitPath, 'diff', '--staged', '--', $file]);
                $diffOutput = $stagedResult->output();
            }

            return response()->json([
                'status' => 200,
                'data' => [
                    'file' => $file,
                    'diff' => $diffOutput,
                    'has_changes' => ! empty(trim($diffOutput)),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitShow(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'commit' => 'required|string',
        ]);

        try {
            $commit = $request->input('commit');
            $gitPath = config('git.executable_path', 'git');

            $result = Process::path($this->getProjectRoot())
                ->run([$gitPath, 'show', '--stat', '-p', $commit]);

            return response()->json([
                'status' => 200,
                'data' => [
                    'commit' => $commit,
                    'output' => $result->output(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitDiscard(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'file' => 'required|string',
        ]);

        try {
            $file = $request->input('file');
            $gitPath = config('git.executable_path', 'git');

            // Unstage if staged
            Process::path($this->getProjectRoot())->run([$gitPath, 'restore', '--staged', '--', $file]);
            // Restore working copy
            $result = Process::path($this->getProjectRoot())->run([$gitPath, 'restore', '--', $file]);

            // Fallback for older git
            if (! $result->successful()) {
                $result = Process::path($this->getProjectRoot())->run([$gitPath, 'checkout', '--', $file]);
            }

            // If untracked file, clean it
            if (! $result->successful()) {
                $result = Process::path($this->getProjectRoot())->run([$gitPath, 'clean', '-f', '--', $file]);
            }

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => "Discarded changes in '{$file}' successfully.",
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to discard changes.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    private function setupGitAuth(array $config): bool
    {
        if (! empty($config['git_username']) && ! empty($config['git_password'])) {
            $repoUrl = $config['repository_url'];
            if (! empty($repoUrl)) {
                // For HTTPS authentication, embed credentials in URL
                if (preg_match('/^https?:\/\//', $repoUrl)) {
                    // Extract protocol and URL parts
                    $urlParts = parse_url($repoUrl);
                    if ($urlParts && isset($urlParts['host'])) {
                        $scheme = $urlParts['scheme'] ?? 'https';
                        $host = $urlParts['host'];
                        $path = $urlParts['path'] ?? '';

                        // Build authenticated URL
                        $username = urlencode($config['git_username']);
                        $password = urlencode($config['git_password']);
                        $authenticatedUrl = "{$scheme}://{$username}:{$password}@{$host}{$path}";

                        // Temporarily update remote URL
                        $result = $this->runGitCommand("git remote set-url origin \"{$authenticatedUrl}\"");

                        return $result->successful();
                    }
                }
            }
        }

        // If SSH key is provided, configure SSH
        if (! empty($config['git_key_path']) && File::exists($config['git_key_path'])) {
            $keyPath = $config['git_key_path'];
            putenv("GIT_SSH_COMMAND=ssh -i {$keyPath} -o StrictHostKeyChecking=no");

            return true;
        }

        return false;
    }

    private function getGitStatus(): array
    {
        try {
            $command = 'git status --porcelain';
            $result = $this->runGitCommand($command);
            $output = $result->output();
            $files = [];

            if (! empty(trim($output))) {
                $lines = explode("\n", trim($output));
                foreach ($lines as $line) {
                    if (! empty(trim($line))) {
                        $status = substr($line, 0, 2);
                        $filename = trim(substr($line, 2));
                        $files[] = [
                            'status' => $status,
                            'filename' => $filename,
                            'staged' => $status[0] !== ' ',
                            'modified' => in_array($status[0], ['M', 'A', 'D', 'R', 'C']),
                        ];
                    }
                }
            }

            // Get branch info
            $branchResult = $this->runGitCommand('git branch --show-current');
            $currentBranch = trim($branchResult->output());

            // Get ahead/behind info
            $aheadBehind = $this->getAheadBehind($currentBranch);

            return [
                'has_changes' => ! empty($files),
                'files' => $files,
                'current_branch' => $currentBranch,
                'ahead' => $aheadBehind['ahead'],
                'behind' => $aheadBehind['behind'],
            ];
        } catch (\Exception $e) {
            return [
                'has_changes' => false,
                'files' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getBranches(): array
    {
        try {
            $command = 'git branch -a';
            $result = $this->runGitCommand($command);
            $output = $result->output();
            $branches = [];

            if (! empty(trim($output))) {
                $lines = explode("\n", trim($output));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (! empty($line)) {
                        $isCurrent = strpos($line, '*') === 0;
                        $branchName = trim($line, '* ');
                        $isRemote = strpos($branchName, 'remotes/') === 0;
                        if ($isRemote) {
                            $branchName = str_replace('remotes/origin/', '', $branchName);
                        }

                        if (! in_array($branchName, array_column($branches, 'name'))) {
                            $branches[] = [
                                'name' => $branchName,
                                'current' => $isCurrent,
                                'remote' => $isRemote,
                            ];
                        }
                    }
                }
            }

            return $branches;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getCurrentBranch(): string
    {
        try {
            $result = $this->runGitCommand('git branch --show-current');

            return trim($result->output());
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    private function getAheadBehind(string $branch): array
    {
        try {
            $result = $this->runGitCommand("git rev-list --left-right --count origin/{$branch}...HEAD 2>&1");
            $output = trim($result->output());

            if (preg_match('/(\d+)\s+(\d+)/', $output, $matches)) {
                return [
                    'behind' => (int) $matches[1],
                    'ahead' => (int) $matches[2],
                ];
            }

            return ['behind' => 0, 'ahead' => 0];
        } catch (\Exception $e) {
            return ['behind' => 0, 'ahead' => 0];
        }
    }

    private function getRecentLogs(int $limit = 25): array
    {
        try {
            $gitPath = config('git.executable_path', 'git');
            $result = Process::path($this->getProjectRoot())
                ->run([$gitPath, 'log', '-n', (string) $limit, '--pretty=format:%h|%an|%ae|%ad|%s', '--date=iso']);
            $output = $result->output();
            $logs = [];

            if (! empty(trim($output))) {
                $lines = explode("\n", trim($output));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (! empty($line)) {
                        $parts = explode('|', $line, 5);
                        if (count($parts) === 5) {
                            $logs[] = [
                                'hash' => trim($parts[0]),
                                'author' => trim($parts[1]),
                                'email' => trim($parts[2]),
                                'date' => trim($parts[3]),
                                'message' => trim($parts[4]),
                            ];
                        }
                    }
                }
            }

            return $logs;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function isGitRepository(): bool
    {
        try {
            // Check if .git directory exists (more reliable than command)
            $gitDir = $this->getProjectRoot().'/.git';
            if (File::isDirectory($gitDir)) {
                return true;
            }

            // Fallback to git command
            $result = $this->runGitCommand('git rev-parse --git-dir');

            return $result->successful() && ! empty(trim($result->output()));
        } catch (\Exception $e) {
            return false;
        }
    }

    private function hasRemoteOrigin(): bool
    {
        try {
            // First check if git repo exists
            if (! $this->isGitRepository()) {
                return false;
            }

            // Check git config file directly for remote origin (most reliable method)
            $gitConfigPath = $this->getProjectRoot().'/.git/config';
            if (File::exists($gitConfigPath)) {
                $configContent = File::get($gitConfigPath);
                // Check if [remote "origin"] section exists
                if (preg_match('/\[remote\s+"origin"\]/', $configContent)) {
                    return true;
                }
            }

            // Fallback: Use git remote command to list remotes
            $result = $this->runGitCommand('git remote');
            $output = trim($result->output());

            // Check if 'origin' exists in the list of remotes
            if (! empty($output)) {
                $remotes = explode("\n", $output);
                foreach ($remotes as $remote) {
                    if (trim($remote) === 'origin') {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function setupRemoteOrigin(string $url): bool
    {
        try {
            // Check if remote exists
            $checkResult = $this->runGitCommand('git remote get-url origin 2>&1');
            if ($checkResult->successful()) {
                // Remote exists, update it
                $result = $this->runGitCommand("git remote set-url origin {$url}");
            } else {
                // Remote doesn't exist, add it
                $result = $this->runGitCommand("git remote add origin {$url}");
            }

            return $result->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    // ==================== ADVANCED GIT FEATURES ====================

    public function gitStash(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'action' => 'required|in:save,list,apply,pop,drop,clear',
            'stash_id' => 'nullable|string',
            'message' => 'nullable|string',
        ]);

        try {
            $action = $request->input('action');

            switch ($action) {
                case 'save':
                    $message = $request->input('message', 'WIP');
                    $command = 'git stash save '.escapeshellarg($message);
                    break;
                case 'list':
                    $command = 'git stash list';
                    break;
                case 'apply':
                    $stashId = $request->input('stash_id', 'stash@{0}');
                    $command = "git stash apply {$stashId}";
                    break;
                case 'pop':
                    $stashId = $request->input('stash_id', 'stash@{0}');
                    $command = "git stash pop {$stashId}";
                    break;
                case 'drop':
                    $stashId = $request->input('stash_id', 'stash@{0}');
                    $command = "git stash drop {$stashId}";
                    break;
                case 'clear':
                    $command = 'git stash clear';
                    break;
                default:
                    return response()->json(['status' => 400, 'message' => 'Invalid action'], 400);
            }

            $result = $this->runGitCommand($command);

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => ucfirst($action).' completed successfully.',
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => "Failed to {$action} stash.",
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitCreateBranch(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'branch_name' => 'required|string',
            'checkout' => 'nullable|boolean',
        ]);

        try {
            $branchName = $request->input('branch_name');
            $checkout = $request->input('checkout', true);

            $command = $checkout
                ? "git checkout -b {$branchName}"
                : "git branch {$branchName}";

            $result = $this->runGitCommand($command);

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => "Branch '{$branchName}' created successfully.",
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to create branch.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitDeleteBranch(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'branch_name' => 'required|string',
            'force' => 'nullable|boolean',
        ]);

        try {
            $branchName = $request->input('branch_name');
            $force = $request->input('force', false);

            $flag = $force ? '-D' : '-d';
            $command = "git branch {$flag} {$branchName}";

            $result = $this->runGitCommand($command);

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => "Branch '{$branchName}' deleted successfully.",
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to delete branch.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitMerge(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'branch_name' => 'required|string',
            'no_ff' => 'nullable|boolean',
        ]);

        try {
            $branchName = $request->input('branch_name');
            $noFf = $request->input('no_ff', false);

            $flag = $noFf ? '--no-ff' : '';
            $command = "git merge {$flag} {$branchName}";

            $result = $this->runGitCommand($command);

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => "Merged '{$branchName}' successfully.",
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to merge branch.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitTag(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'action' => 'required|in:create,list,delete',
            'tag_name' => 'required_if:action,create,delete|string',
            'message' => 'nullable|string',
        ]);

        try {
            $action = $request->input('action');

            switch ($action) {
                case 'create':
                    $tagName = $request->input('tag_name');
                    $message = $request->input('message');
                    if ($message) {
                        $command = "git tag -a {$tagName} -m ".escapeshellarg($message);
                    } else {
                        $command = "git tag {$tagName}";
                    }
                    break;
                case 'list':
                    $command = 'git tag -l';
                    break;
                case 'delete':
                    $tagName = $request->input('tag_name');
                    $command = "git tag -d {$tagName}";
                    break;
                default:
                    return response()->json(['status' => 400, 'message' => 'Invalid action'], 400);
            }

            $result = $this->runGitCommand($command);

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => ucfirst($action).' completed successfully.',
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => "Failed to {$action} tag.",
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitReset(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'type' => 'required|in:soft,mixed,hard',
            'commit' => 'nullable|string',
            'files' => 'nullable|string',
        ]);

        try {
            $type = $request->input('type');
            $commit = $request->input('commit', 'HEAD');
            $files = $request->input('files');

            if ($files) {
                // Reset specific files
                $command = "git checkout {$commit} -- {$files}";
            } else {
                // Reset to commit
                $command = "git reset --{$type} {$commit}";
            }

            $result = $this->runGitCommand($command);

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Reset completed successfully.',
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to reset.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitRevert(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'commit' => 'required|string',
        ]);

        try {
            $commit = $request->input('commit');
            $command = "git revert {$commit} --no-edit";

            $result = $this->runGitCommand($command);

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Commit reverted successfully.',
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to revert commit.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitInit(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $command = 'git init';
            $result = $this->runGitCommand($command);

            if ($result->successful()) {
                // Set up remote if configured
                $config = $this->getGitConfig();
                if (! empty($config['repository_url'])) {
                    $this->setupRemoteOrigin($config['repository_url']);
                }

                return response()->json([
                    'status' => 200,
                    'message' => 'Git repository initialized successfully.',
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to initialize repository.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitClone(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'repository_url' => 'required|url',
            'branch' => 'nullable|string',
        ]);

        try {
            $repoUrl = $request->input('repository_url');
            $branch = $request->input('branch');
            $config = $this->getGitConfig();

            // Add authentication to URL if credentials are provided
            if (! empty($config['git_username']) && ! empty($config['git_password'])) {
                $urlParts = parse_url($repoUrl);
                if ($urlParts && isset($urlParts['host']) && preg_match('/^https?:\/\//', $repoUrl)) {
                    $scheme = $urlParts['scheme'] ?? 'https';
                    $host = $urlParts['host'];
                    $path = $urlParts['path'] ?? '';
                    $username = urlencode($config['git_username']);
                    $password = urlencode($config['git_password']);
                    $repoUrl = "{$scheme}://{$username}:{$password}@{$host}{$path}";
                }
            }

            $branchFlag = $branch ? "-b {$branch}" : '';
            $targetDir = $this->getProjectRoot();
            $command = "git clone {$branchFlag} {$repoUrl} {$targetDir}";

            $result = $this->runGitCommand($command);

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Repository cloned successfully.',
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => 'Failed to clone repository.',
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitConflicts(): \Illuminate\Http\JsonResponse
    {
        try {
            $command = 'git diff --name-only --diff-filter=U';
            $result = $this->runGitCommand($command);
            $conflicts = [];

            if (! empty(trim($result->output()))) {
                $files = explode("\n", trim($result->output()));
                foreach ($files as $file) {
                    if (! empty(trim($file))) {
                        $conflicts[] = trim($file);
                    }
                }
            }

            return response()->json([
                'status' => 200,
                'data' => [
                    'has_conflicts' => ! empty($conflicts),
                    'files' => $conflicts,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function gitRemote(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'action' => 'required|in:list,add,remove,set-url',
            'name' => 'required_if:action,add,remove,set-url|string',
            'url' => 'required_if:action,add,set-url|string',
        ]);

        try {
            $action = $request->input('action');

            switch ($action) {
                case 'list':
                    $command = 'git remote -v';
                    break;
                case 'add':
                    $name = $request->input('name');
                    $url = $request->input('url');
                    $command = "git remote add {$name} {$url}";
                    break;
                case 'remove':
                    $name = $request->input('name');
                    $command = "git remote remove {$name}";
                    break;
                case 'set-url':
                    $name = $request->input('name');
                    $url = $request->input('url');
                    $command = "git remote set-url {$name} {$url}";
                    break;
                default:
                    return response()->json(['status' => 400, 'message' => 'Invalid action'], 400);
            }

            $result = $this->runGitCommand($command);

            if ($result->successful()) {
                return response()->json([
                    'status' => 200,
                    'message' => ucfirst($action).' completed successfully.',
                    'output' => $result->output(),
                ]);
            }

            return response()->json([
                'status' => 400,
                'message' => "Failed to {$action} remote.",
                'error' => $result->errorOutput(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
