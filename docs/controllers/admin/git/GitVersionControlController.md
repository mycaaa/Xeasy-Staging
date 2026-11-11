# GitVersionControlController

## Overview
The GitVersionControlController provides a comprehensive web-based Git interface for managing version control operations. It supports all major Git operations including repository management, branching, merging, stashing, and advanced Git features through a Laravel application.

## Namespace
`App\Http\Controllers\admin\git`

## Routes

### Version Control Routes (Defined in `routes/backend.php`)
- **GET** `/version-control` - Main version control dashboard (index)
- **GET** `/version-control/status` - Get repository status (gitStatus)
- **POST** `/version-control/add` - Stage files (gitAdd)
- **POST** `/version-control/commit` - Commit changes (gitCommit)
- **POST** `/version-control/push` - Push to remote (gitPush)
- **POST** `/version-control/pull` - Pull from remote (gitPull)
- **POST** `/version-control/fetch` - Fetch from remote (gitFetch)
- **POST** `/version-control/checkout` - Checkout branch (gitCheckout)
- **GET** `/version-control/log` - Get commit history (gitLog)
- **GET** `/version-control/diff` - Get working directory diff (gitDiff)
- **POST** `/version-control/stash` - Manage stashes (gitStash)
- **POST** `/version-control/create-branch` - Create new branch (gitCreateBranch)
- **POST** `/version-control/delete-branch` - Delete branch (gitDeleteBranch)
- **POST** `/version-control/merge` - Merge branches (gitMerge)
- **POST** `/version-control/tag` - Manage tags (gitTag)
- **POST** `/version-control/reset` - Reset repository state (gitReset)
- **POST** `/version-control/revert` - Revert commits (gitRevert)
- **POST** `/version-control/init` - Initialize repository (gitInit)
- **POST** `/version-control/clone` - Clone repository (gitClone)
- **GET** `/version-control/conflicts` - Get merge conflicts (gitConflicts)
- **POST** `/version-control/remote` - Manage remotes (gitRemote)

**Route Names**: All routes are prefixed with `version-control.`
**Middleware**: Requires authentication (`auth` middleware)

## Dependencies
- `Illuminate\Support\Facades\File`
- `Illuminate\Support\Facades\Process`

## Configuration
Uses environment variables for Git configuration:
- `GIT_REPOSITORY_URL`: Remote repository URL
- `GIT_USERNAME`: Git username for authentication
- `GIT_PASSWORD`: Git password for authentication
- `GIT_BRANCH`: Default branch (defaults to 'main')
- `GIT_KEY_PATH`: SSH key path for authentication

## Core Methods

### index()
**Purpose**: Main dashboard displaying Git repository status and configuration.

**Returns**: View with comprehensive Git information

**Data Provided**:
- Git configuration settings
- Repository initialization status
- Remote origin status
- Current Git status
- Available branches
- Current branch
- Recent commit logs

### gitStatus()
**Purpose**: Returns current Git repository status.

**Returns**: JSON response with repository status

**Information Included**:
- Modified files
- Staged changes
- Current branch
- Ahead/behind commit counts

## Basic Git Operations

### gitAdd(Request $request)
**Purpose**: Stages files for commit.

**Parameters**:
- `files` (nullable, string): Files to add (defaults to '.' for all files)

**Returns**: JSON response with operation status

### gitCommit(Request $request)
**Purpose**: Commits staged changes.

**Parameters**:
- `message` (required, string, min:3): Commit message

**Returns**: JSON response with commit status

### gitPush(Request $request)
**Purpose**: Pushes commits to remote repository.

**Parameters**:
- `branch` (nullable, string): Branch to push (defaults to configured branch)
- `force` (nullable, boolean): Force push flag

**Returns**: JSON response with push status

**Features**:
- Automatic authentication setup
- URL restoration after operation
- Force push support

### gitPull(Request $request)
**Purpose**: Pulls changes from remote repository.

**Parameters**:
- `branch` (nullable, string): Branch to pull from

**Returns**: JSON response with pull status

### gitFetch()
**Purpose**: Fetches changes from remote without merging.

**Returns**: JSON response with fetch status

## Branch Management

### gitCheckout(Request $request)
**Purpose**: Switches to a different branch.

**Parameters**:
- `branch` (required, string): Branch name to checkout

**Returns**: JSON response with checkout status

### gitCreateBranch(Request $request)
**Purpose**: Creates a new branch.

**Parameters**:
- `branch_name` (required, string): New branch name
- `checkout` (nullable, boolean): Whether to checkout new branch (default: true)

**Returns**: JSON response with creation status

### gitDeleteBranch(Request $request)
**Purpose**: Deletes a branch.

**Parameters**:
- `branch_name` (required, string): Branch name to delete
- `force` (nullable, boolean): Force deletion flag

**Returns**: JSON response with deletion status

### gitMerge(Request $request)
**Purpose**: Merges a branch into current branch.

**Parameters**:
- `branch_name` (required, string): Branch to merge
- `no_ff` (nullable, boolean): No fast-forward flag

**Returns**: JSON response with merge status

## Advanced Git Features

### gitStash(Request $request)
**Purpose**: Manages Git stash operations.

**Parameters**:
- `action` (required, in:save,list,apply,pop,drop,clear): Stash action
- `stash_id` (nullable, string): Stash identifier
- `message` (nullable, string): Stash message for save action

**Returns**: JSON response with stash operation status

**Supported Actions**:
- `save`: Create new stash
- `list`: List all stashes
- `apply`: Apply stash without removing
- `pop`: Apply and remove stash
- `drop`: Remove stash
- `clear`: Remove all stashes

### gitTag(Request $request)
**Purpose**: Manages Git tags.

**Parameters**:
- `action` (required, in:create,list,delete): Tag action
- `tag_name` (required_if:action,create,delete): Tag name
- `message` (nullable, string): Tag message for annotated tags

**Returns**: JSON response with tag operation status

### gitReset(Request $request)
**Purpose**: Resets repository state.

**Parameters**:
- `type` (required, in:soft,mixed,hard): Reset type
- `commit` (nullable, string): Target commit (default: HEAD)
- `files` (nullable, string): Specific files to reset

**Returns**: JSON response with reset status

**Reset Types**:
- `soft`: Keep changes in staging area
- `mixed`: Keep changes in working directory
- `hard`: Discard all changes

### gitRevert(Request $request)
**Purpose**: Reverts a specific commit.

**Parameters**:
- `commit` (required, string): Commit hash to revert

**Returns**: JSON response with revert status

## Repository Management

### gitInit()
**Purpose**: Initializes a new Git repository.

**Returns**: JSON response with initialization status

**Features**:
- Automatic remote setup if configured
- Repository initialization in project root

### gitClone(Request $request)
**Purpose**: Clones a remote repository.

**Parameters**:
- `repository_url` (required, url): Repository URL to clone
- `branch` (nullable, string): Specific branch to clone

**Returns**: JSON response with clone status

**Features**:
- Automatic authentication integration
- Branch-specific cloning support

## Utility Methods

### gitLog(Request $request)
**Purpose**: Retrieves commit history.

**Parameters**:
- `limit` (nullable, integer, 1-100): Number of commits (default: 10)

**Returns**: JSON response with commit logs

### gitDiff()
**Purpose**: Shows differences in working directory.

**Returns**: JSON response with diff output

### gitConflicts()
**Purpose**: Lists files with merge conflicts.

**Returns**: JSON response with conflicted files

### gitRemote(Request $request)
**Purpose**: Manages remote repositories.

**Parameters**:
- `action` (required, in:list,add,remove,set-url): Remote action
- `name` (required_if): Remote name
- `url` (required_if): Remote URL

**Returns**: JSON response with remote operation status

## Private Helper Methods

### runGitCommand(string $command)
**Purpose**: Executes Git commands with proper configuration.

**Features**:
- Runs commands from project root
- Uses configured Git executable path
- Returns ProcessResult object

### setupGitAuth(array $config)
**Purpose**: Sets up authentication for Git operations.

**Authentication Methods**:
- HTTPS with username/password
- SSH with key file
- Temporary URL modification for credentials

### getGitStatus()
**Purpose**: Retrieves detailed repository status.

**Returns**: Array with file changes, branch info, and ahead/behind counts

### getBranches()
**Purpose**: Lists all local and remote branches.

**Returns**: Array of branch information

### getRecentLogs(int $limit)
**Purpose**: Retrieves recent commit history.

**Returns**: Array of formatted commit information

## Security Features
- Input validation for all parameters
- Secure credential handling
- Temporary credential injection
- Shell argument escaping
- Error message sanitization

## Error Handling
- Comprehensive try-catch blocks
- Graceful failure responses
- Detailed error reporting
- Automatic credential cleanup on errors

## Authentication Support
- HTTPS authentication with credentials
- SSH key authentication
- Temporary URL modification for security
- Automatic credential restoration

## Configuration Management
- Environment-based configuration
- Dynamic Git executable path
- Flexible authentication options
- Repository URL management