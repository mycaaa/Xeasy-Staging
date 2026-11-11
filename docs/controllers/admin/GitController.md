# GitController Documentation

## Overview
The `GitController` provides a secure web-based interface for executing Git operations on the application repository. This controller enables authorized users to perform common Git commands through a web panel, facilitating deployment and version control management without direct server access.

## Class Information
- **Namespace**: `App\Http\Controllers\admin`
- **Extends**: `App\Http\Controllers\Controller`
- **Location**: `app/Http/Controllers/admin/GitController.php`

## Methods

### runCommand(Request $request)
Executes authorized Git commands with password protection and command validation.

**Route**: `POST /admin/git/run-command`

**Purpose**: Provides secure execution of predefined Git operations through web interface.

**Parameters**:
- `password` (string, required): Authentication password for Git operations
- `command` (string, required): Git command identifier to execute

**Request Example**:
```json
{
    "password": "MyPassword",
    "command": "status"
}
```

**Response Examples**:

**Success Response**:
```json
{
    "success": true,
    "message": "Status executed successfully.",
    "data": "Working directory clean. No pending changes."
}
```

**Error Response**:
```json
{
    "success": false,
    "message": "Invalid password. Access denied.",
    "status": 403
}
```

**Business Logic**:

#### Security Validation
1. **Password Authentication**: Validates against hardcoded password 'MyPassword'
2. **Command Whitelist**: Only allows predefined safe commands
3. **Environment Checks**: Configures Git credentials for production environment

#### Supported Commands
```php
$allowedCommands = [
    'status'       => 'git status',
    'pull'         => 'git pull',
    'add'          => 'git add .',
    'commit'       => 'git commit -m "Auto commit from web panel"',
    'push'         => 'git push',
    'fetch'        => 'git fetch',
    'branch'       => 'git branch',
    'log'          => 'git log -5 --oneline',
    'abort-merge'  => 'git merge --abort',
    'stash'        => 'git stash',
    'stash-pop'    => 'git stash pop',
    'force-push'   => 'git push --force',
    'force-pull'   => 'git fetch --all && git reset --hard origin/main',
    'reset-local'  => 'git fetch origin main && git reset --hard origin/main',
    'refresh-ignore' => 'git rm -r --cached . && git add . && git commit -m "Apply .gitignore changes"'
];
```

#### Pre-execution Checks
- **Uncommitted Changes**: Validates clean working directory for pull/push operations
- **Remote Configuration**: Sets up GitHub authentication for production environment
- **Pull Configuration**: Configures merge strategy to prevent rebase conflicts

#### Output Formatting
The controller formats command output for better user experience:

**Status Command**:
```php
if (str_contains($output, 'nothing to commit')) {
    return "Working directory clean. No pending changes.";
}
return "Current Git Status:\n" . $output;
```

**Pull Command**:
```php
if (str_contains($output, 'Already up to date')) {
    return "Your local code is already up to date.";
}
return "Changes pulled successfully:\n" . $output;
```

### formatOutput($command, $output)
Formats raw Git command output for user-friendly display.

**Purpose**: Transforms technical Git output into readable messages for web interface.

**Parameters**:
- `$command` (string): The executed command identifier
- `$output` (string): Raw Git command output

**Returns**: Formatted string with user-friendly message

**Implementation Details**:
- Detects common Git states and provides contextual messages
- Simplifies technical output for non-technical users
- Maintains important information while improving readability

### interpretGitError($output)
Analyzes Git error output and provides actionable error messages.

**Purpose**: Converts technical Git errors into user-understandable solutions.

**Parameters**:
- `$output` (string): Raw Git error output

**Returns**: Human-readable error message with suggested actions

**Error Interpretations**:
```php
// Authentication issues
if (str_contains($output, 'Authentication failed')) {
    return 'Authentication failed. Check GitHub username/token.';
}

// Push conflicts
if (str_contains($output, 'non-fast-forward')) {
    return 'Push rejected (non-fast-forward). Try pull first or use "force-push".';
}

// Merge conflicts
if (str_contains($output, 'Merge conflict')) {
    return 'Merge conflict detected. Use "abort-merge" or "reset-local" to fix.';
}
```

## Security Considerations

### Authentication & Authorization
- **Password Protection**: Hardcoded password validation (should be moved to environment variables)
- **Command Whitelist**: Only predefined commands are allowed
- **Environment Restrictions**: Production-specific configurations

### Security Recommendations
```php
// Recommended improvements
class GitController extends Controller
{
    private function validateAccess(Request $request)
    {
        // Use environment variable instead of hardcoded password
        $password = env('GIT_PANEL_PASSWORD');
        
        // Implement rate limiting
        if (RateLimiter::tooManyAttempts('git-commands:' . $request->ip(), 5)) {
            throw new TooManyRequestsHttpException(60, 'Too many attempts');
        }
        
        // Log all access attempts
        Log::info('Git command attempted', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'command' => $request->input('command')
        ]);
        
        return hash_equals($password, $request->input('password'));
    }
}
```

## Frontend Integration

### JavaScript Implementation
```javascript
class GitManager {
    constructor() {
        this.initializeEventListeners();
        this.commandHistory = [];
    }

    initializeEventListeners() {
        // Command execution
        $('#git-command-form').on('submit', this.executeCommand.bind(this));
        
        // Quick command buttons
        $('.quick-command').on('click', this.executeQuickCommand.bind(this));
        
        // Command history
        $('#show-history').on('click', this.showCommandHistory.bind(this));
    }

    async executeCommand(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const command = formData.get('command');
        const password = formData.get('password');
        
        if (!password) {
            this.showError('Password is required');
            return;
        }
        
        if (!command) {
            this.showError('Please select a command');
            return;
        }

        this.showLoading(true);
        
        try {
            const response = await fetch('/admin/git/run-command', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const result = await response.json();
            
            if (response.ok) {
                this.showSuccess(result.message);
                this.displayOutput(result.data);
                this.addToHistory(command, result.data);
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            this.showError('Network error occurred');
        } finally {
            this.showLoading(false);
        }
    }

    executeQuickCommand(e) {
        const command = e.target.dataset.command;
        const password = document.getElementById('password').value;
        
        if (!password) {
            this.showError('Please enter password first');
            document.getElementById('password').focus();
            return;
        }
        
        // Set command and submit form
        document.getElementById('command').value = command;
        document.getElementById('git-command-form').dispatchEvent(new Event('submit'));
    }

    displayOutput(output) {
        const outputContainer = document.getElementById('command-output');
        const timestamp = new Date().toLocaleString();
        
        const outputHtml = `
            <div class="output-entry">
                <div class="output-header">
                    <span class="timestamp">${timestamp}</span>
                </div>
                <pre class="output-content">${this.escapeHtml(output)}</pre>
            </div>
        `;
        
        outputContainer.innerHTML = outputHtml + outputContainer.innerHTML;
    }

    addToHistory(command, output) {
        this.commandHistory.unshift({
            command,
            output,
            timestamp: new Date().toISOString()
        });
        
        // Keep only last 20 commands
        if (this.commandHistory.length > 20) {
            this.commandHistory = this.commandHistory.slice(0, 20);
        }
        
        // Save to localStorage
        localStorage.setItem('git_command_history', JSON.stringify(this.commandHistory));
    }

    showCommandHistory() {
        const history = JSON.parse(localStorage.getItem('git_command_history') || '[]');
        
        if (history.length === 0) {
            this.showInfo('No command history available');
            return;
        }
        
        const historyHtml = history.map(entry => `
            <div class="history-entry">
                <div class="history-header">
                    <strong>${entry.command}</strong>
                    <span class="text-muted">${new Date(entry.timestamp).toLocaleString()}</span>
                </div>
                <pre class="history-output">${this.escapeHtml(entry.output)}</pre>
            </div>
        `).join('');
        
        // Show in modal or dedicated section
        this.showModal('Command History', historyHtml);
    }

    showLoading(show) {
        const button = document.querySelector('#git-command-form button[type="submit"]');
        const spinner = button.querySelector('.spinner');
        
        if (show) {
            button.disabled = true;
            if (spinner) spinner.style.display = 'inline-block';
        } else {
            button.disabled = false;
            if (spinner) spinner.style.display = 'none';
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showInfo(message) {
        this.showNotification(message, 'info');
    }

    showNotification(message, type) {
        // Implement notification system
        console.log(`${type.toUpperCase()}: ${message}`);
    }

    showModal(title, content) {
        // Implement modal display
        console.log(`Modal: ${title}`, content);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new GitManager();
});
```

### Blade Template Example
```blade
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Git Operations Panel</h4>
                    <div class="card-tools">
                        <button type="button" class="btn btn-info btn-sm" id="show-history">
                            <i class="ri-history-line"></i> Command History
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form id="git-command-form">
                        @csrf
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-6">
                                <label for="command" class="form-label">Git Command</label>
                                <select class="form-select" id="command" name="command" required>
                                    <option value="">Select Command</option>
                                    <option value="status">Git Status</option>
                                    <option value="pull">Git Pull</option>
                                    <option value="add">Git Add All</option>
                                    <option value="commit">Git Commit</option>
                                    <option value="push">Git Push</option>
                                    <option value="fetch">Git Fetch</option>
                                    <option value="branch">Show Branches</option>
                                    <option value="log">Recent Commits</option>
                                    <option value="stash">Stash Changes</option>
                                    <option value="stash-pop">Pop Stash</option>
                                    <option value="abort-merge">Abort Merge</option>
                                    <option value="force-pull">Force Pull</option>
                                    <option value="reset-local">Reset Local</option>
                                    <option value="force-push">Force Push</option>
                                    <option value="refresh-ignore">Refresh .gitignore</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">
                                <span class="spinner" style="display: none;">
                                    <i class="ri-loader-4-line ri-spin"></i>
                                </span>
                                Execute Command
                            </button>
                        </div>
                    </form>
                    
                    <!-- Quick Command Buttons -->
                    <div class="mb-3">
                        <h6>Quick Commands:</h6>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-secondary quick-command" data-command="status">
                                Status
                            </button>
                            <button type="button" class="btn btn-outline-primary quick-command" data-command="pull">
                                Pull
                            </button>
                            <button type="button" class="btn btn-outline-success quick-command" data-command="push">
                                Push
                            </button>
                            <button type="button" class="btn btn-outline-warning quick-command" data-command="stash">
                                Stash
                            </button>
                            <button type="button" class="btn btn-outline-danger quick-command" data-command="reset-local">
                                Reset Local
                            </button>
                        </div>
                    </div>
                    
                    <!-- Command Output -->
                    <div class="mt-4">
                        <h6>Command Output:</h6>
                        <div id="command-output" class="border rounded p-3" style="max-height: 400px; overflow-y: auto; background-color: #f8f9fa;">
                            <p class="text-muted">No commands executed yet.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.output-entry {
    margin-bottom: 1rem;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 0.5rem;
}

.output-header {
    display: flex;
    justify-content-space-between;
    align-items: center;
    margin-bottom: 0.25rem;
}

.timestamp {
    font-size: 0.875rem;
    color: #6c757d;
}

.output-content {
    background-color: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    padding: 0.5rem;
    margin: 0;
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
    white-space: pre-wrap;
}

.history-entry {
    margin-bottom: 1rem;
    padding: 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
}

.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.history-output {
    background-color: #f8f9fa;
    border: none;
    padding: 0.5rem;
    margin: 0;
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
}
</style>
@endpush
```

## Environment Configuration

### Required Environment Variables
```env
# Git Configuration
GIT_USER=your-github-username
GIT_TOKEN=your-github-token
GIT_REPO=your-repository-url
GIT_PANEL_PASSWORD=secure-password-here

# Application Environment
APP_ENV=production
```

### Git Credentials Setup
```php
// In production environment
if ($appEnv === 'production' && $user && $token && $repo) {
    $remoteUrl = "https://{$user}:{$token}@github.com/{$user}/" . basename($repo, '.git') . ".git";
    exec("cd " . base_path() . " && git remote set-url origin {$remoteUrl} 2>&1");
}
```

## Error Handling & Logging

### Comprehensive Error Handling
```php
public function runCommand(Request $request)
{
    try {
        // Validate input
        $this->validateGitRequest($request);
        
        // Execute command with timeout
        $result = $this->executeGitCommand($command, 30); // 30 second timeout
        
        // Log successful operations
        Log::info('Git command executed successfully', [
            'command' => $command,
            'user_ip' => $request->ip(),
            'execution_time' => $result['execution_time']
        ]);
        
        return $this->jsonSuccess($result['output'], $result['message']);
        
    } catch (GitCommandException $e) {
        Log::error('Git command failed', [
            'command' => $command,
            'error' => $e->getMessage(),
            'output' => $e->getOutput()
        ]);
        
        return $this->jsonError($e->getMessage(), 500);
        
    } catch (ValidationException $e) {
        return $this->jsonError($e->getMessage(), 400);
        
    } catch (Exception $e) {
        Log::critical('Unexpected error in Git controller', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return $this->jsonError('An unexpected error occurred', 500);
    }
}
```

## Performance Considerations

### Command Execution Optimization
```php
private function executeWithTimeout($command, $timeout = 30)
{
    $descriptorspec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    
    $process = proc_open($command, $descriptorspec, $pipes, base_path());
    
    if (!is_resource($process)) {
        throw new GitCommandException('Failed to start Git process');
    }
    
    // Set timeout
    stream_set_timeout($pipes[1], $timeout);
    stream_set_timeout($pipes[2], $timeout);
    
    // Read output
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    
    // Close pipes and process
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    $returnCode = proc_close($process);
    
    return [
        'output' => $output,
        'error' => $error,
        'return_code' => $returnCode
    ];
}
```

## Business Rules & Constraints

### Command Execution Rules
1. **Authentication Required**: All commands require password validation
2. **Command Whitelist**: Only predefined commands are allowed
3. **State Validation**: Some commands require clean working directory
4. **Environment Awareness**: Production commands use different configurations
5. **Conflict Prevention**: Automatic checks for uncommitted changes

### Deployment Workflow
1. **Status Check**: Verify current repository state
2. **Stash Changes**: Save local modifications if needed
3. **Pull Updates**: Fetch latest changes from remote
4. **Conflict Resolution**: Handle merge conflicts automatically
5. **Push Changes**: Deploy local changes to remote repository

## Testing Considerations

### Unit Tests
```php
public function test_git_status_command_execution()
{
    $response = $this->post('/admin/git/run-command', [
        'password' => 'MyPassword',
        'command' => 'status'
    ]);
    
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'message',
        'data'
    ]);
}

public function test_invalid_password_rejection()
{
    $response = $this->post('/admin/git/run-command', [
        'password' => 'wrong-password',
        'command' => 'status'
    ]);
    
    $response->assertStatus(403);
    $response->assertJson([
        'success' => false,
        'message' => 'Invalid password. Access denied.'
    ]);
}
```

### Security Testing
- Test password validation with various inputs
- Verify command injection prevention
- Test rate limiting functionality
- Validate audit logging accuracy

This controller provides essential Git operations management through a secure web interface, enabling deployment and version control without direct server access.