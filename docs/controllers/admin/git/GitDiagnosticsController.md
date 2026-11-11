# GitDiagnosticsController

## Overview
The GitDiagnosticsController provides comprehensive diagnostic capabilities for Git installation and configuration. It performs various tests to verify Git availability, check common installation paths, validate PHP functions, and assess the Git repository status.

## Namespace
`App\Http\Controllers\admin\git`

## Dependencies
- `Illuminate\Support\Facades\Process`

## Routes

### Version Control Routes
- **GET** `/version-control/diagnostics` - Run Git diagnostics (diagnose)

**Route Name**: `version-control.diagnostics`

## Methods

### diagnose()
**Purpose**: Performs comprehensive Git diagnostics and returns results.

**Parameters**: None

**Returns**: 
- View (`admin.utility.git_diagnostics`) for web requests
- JSON response for API requests

**Diagnostic Tests Performed**:

#### 1. Git Version Test
- **Purpose**: Verifies if Git command is available and working
- **Command**: `git --version`
- **Results**: Status, output, and error information

#### 2. Path Tests
- **Purpose**: Tests Git availability at common installation paths
- **Paths Tested**:
  - `/usr/bin/git` (Standard Linux path)
  - `/usr/local/bin/git` (Custom installation path)
  - `/bin/git` (Alternative Linux path)
  - `/opt/git/bin/git` (Optional installation path)
  - `git` (Default PATH lookup)
- **Results**: Status and output for each path

#### 3. PHP Functions Availability
- **Purpose**: Checks if required PHP functions are available
- **Functions Tested**:
  - `proc_open`: Process execution
  - `exec`: Command execution
  - `shell_exec`: Shell command execution
  - `system`: System command execution
- **Results**: Boolean availability for each function

#### 4. Git Location Discovery
- **Purpose**: Uses system commands to locate Git installation
- **Command**: `which git`
- **Results**: Git executable location or error

#### 5. Server Information
- **Purpose**: Gathers system information relevant to Git operations
- **Information Collected**:
  - PHP version
  - Operating system
  - Server API (SAPI)
  - Current user (if POSIX functions available)
  - Disabled PHP functions

#### 6. Git Repository Check
- **Purpose**: Verifies if current directory is a Git repository
- **Checks Performed**:
  - `.git` directory exists
  - `.git` is a directory (not a file)
  - `.git` directory is readable
- **Results**: Boolean status for each check

## Response Structure

### JSON Response Format
```json
{
  "git_version": {
    "status": "success|failed|error",
    "output": "command output",
    "error": "error message"
  },
  "path_tests": {
    "/usr/bin/git": {
      "status": "success|failed|error",
      "output": "command output", 
      "error": "error message"
    }
    // ... other paths
  },
  "php_functions": {
    "proc_open": true|false,
    "exec": true|false,
    "shell_exec": true|false,
    "system": true|false
  },
  "which_git": {
    "status": "success|failed|error",
    "output": "git path",
    "error": "error message"
  },
  "server_info": {
    "php_version": "8.x.x",
    "os": "Linux|Windows|Darwin",
    "sapi": "apache2handler|cli|fpm",
    "user": "username|unknown",
    "disabled_functions": "function1,function2"
  },
  "git_repo": {
    "exists": true|false,
    "is_dir": true|false,
    "readable": true|false
  }
}
```

## Use Cases
This controller is essential for:
- **Troubleshooting Git Issues**: Identify why Git commands might be failing
- **Environment Validation**: Ensure Git is properly installed and configured
- **Security Assessment**: Check which PHP functions are available for process execution
- **System Information**: Gather details about the server environment
- **Repository Validation**: Verify Git repository status

## Error Handling
- All Git commands are wrapped in try-catch blocks
- Failed commands return status information rather than throwing exceptions
- Graceful degradation when functions or commands are unavailable
- Comprehensive error reporting for each diagnostic test

## Security Considerations
- Checks for disabled PHP functions that might affect Git operations
- Identifies current user context for permission troubleshooting
- Does not expose sensitive system information unnecessarily

## Troubleshooting Support
The diagnostics help identify common issues:
- Git not installed or not in PATH
- PHP functions disabled by security policies
- Permission issues with Git repository
- Incorrect Git installation paths
- Server configuration problems

## View Integration
- Returns structured data for web interface display
- Supports both JSON API and web view responses
- Provides comprehensive diagnostic information for administrators