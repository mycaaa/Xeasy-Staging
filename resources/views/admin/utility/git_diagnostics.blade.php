@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bx bx-info-circle"></i> This page helps diagnose Git availability on your server.
                    Use this information to configure Git Version Control properly.
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <ul class="nav nav-tabs" id="gitDiagnosticsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-version-tab" data-bs-toggle="tab" data-bs-target="#tab-version" type="button" role="tab" aria-controls="tab-version" aria-selected="true">
                            Git Version
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-server-tab" data-bs-toggle="tab" data-bs-target="#tab-server" type="button" role="tab" aria-controls="tab-server" aria-selected="false">
                            Server Info
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-php-tab" data-bs-toggle="tab" data-bs-target="#tab-php" type="button" role="tab" aria-controls="tab-php" aria-selected="false">
                            PHP Functions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-repo-tab" data-bs-toggle="tab" data-bs-target="#tab-repo" type="button" role="tab" aria-controls="tab-repo" aria-selected="false">
                            Git Repository
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-paths-tab" data-bs-toggle="tab" data-bs-target="#tab-paths" type="button" role="tab" aria-controls="tab-paths" aria-selected="false">
                            Git Paths
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-recommendations-tab" data-bs-toggle="tab" data-bs-target="#tab-recommendations" type="button" role="tab" aria-controls="tab-recommendations" aria-selected="false">
                            Recommendations
                        </button>
                    </li>
                </ul>
                <div class="tab-content" id="gitDiagnosticsTabsContent">
                    <div class="tab-pane fade show active" id="tab-version" role="tabpanel" aria-labelledby="tab-version-tab">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Git Version Test</h5>
                            </div>
                            <div class="card-body">
                                @if($results['git_version']['status'] === 'success')
                                <div class="alert alert-success">
                                    <i class="bx bx-check-circle"></i> Git is available!
                                    <pre class="mt-2 mb-0">{{ $results['git_version']['output'] }}</pre>
                                </div>
                                @else
                                <div class="alert alert-danger">
                                    <i class="bx bx-error"></i> Git command failed
                                    @if(!empty($results['git_version']['error']))
                                    <pre class="mt-2 mb-0">{{ $results['git_version']['error'] }}</pre>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-server" role="tabpanel" aria-labelledby="tab-server-tab">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Server Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th>PHP Version:</th>
                                        <td>{{ $results['server_info']['php_version'] }}</td>
                                    </tr>
                                    <tr>
                                        <th>Operating System:</th>
                                        <td>{{ $results['server_info']['os'] }}</td>
                                    </tr>
                                    <tr>
                                        <th>Server API:</th>
                                        <td>{{ $results['server_info']['sapi'] }}</td>
                                    </tr>
                                    <tr>
                                        <th>Web Server User:</th>
                                        <td>{{ $results['server_info']['user'] }}</td>
                                    </tr>
                                    @if(!empty($results['server_info']['disabled_functions']))
                                    <tr>
                                        <th>Disabled Functions:</th>
                                        <td><span class="badge bg-warning">{{ substr($results['server_info']['disabled_functions'], 0, 50) }}...</span></td>
                                    </tr>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-php" role="tabpanel" aria-labelledby="tab-php-tab">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Required PHP Functions</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    @foreach($results['php_functions'] as $func => $available)
                                    <tr>
                                        <td>{{ $func }}()</td>
                                        <td>
                                            @if($available)
                                            <span class="badge bg-success">Available</span>
                                            @else
                                            <span class="badge bg-danger">Disabled</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </table>
                                @if(!$results['php_functions']['proc_open'])
                                <div class="alert alert-danger mt-2">
                                    <i class="bx bx-error"></i> proc_open() is required for Git commands but is disabled on this server.
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-repo" role="tabpanel" aria-labelledby="tab-repo-tab">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Git Repository Status</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <td>.git directory exists:</td>
                                        <td>
                                            @if($results['git_repo']['exists'])
                                            <span class="badge bg-success">Yes</span>
                                            @else
                                            <span class="badge bg-warning">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>.git is directory:</td>
                                        <td>
                                            @if($results['git_repo']['is_dir'])
                                            <span class="badge bg-success">Yes</span>
                                            @else
                                            <span class="badge bg-warning">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>.git is readable:</td>
                                        <td>
                                            @if($results['git_repo']['readable'])
                                            <span class="badge bg-success">Yes</span>
                                            @else
                                            <span class="badge bg-danger">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-paths" role="tabpanel" aria-labelledby="tab-paths-tab">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Git Path Detection</h5>
                            </div>
                            <div class="card-body">
                                @if($results['which_git']['status'] === 'success' && !empty(trim($results['which_git']['output'])))
                                <div class="alert alert-success">
                                    <i class="bx bx-check-circle"></i> <strong>Git found at:</strong>
                                    <code>{{ trim($results['which_git']['output']) }}</code>
                                    <div class="mt-2">
                                        <strong>Add this to your .env file:</strong>
                                        <pre class="bg-dark text-light p-2 rounded mt-1">GIT_EXECUTABLE_PATH="{{ trim($results['which_git']['output']) }}"</pre>
                                    </div>
                                </div>
                                @endif

                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Path</th>
                                            <th>Status</th>
                                            <th>Output</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($results['path_tests'] as $path => $test)
                                        <tr>
                                            <td><code>{{ $path }}</code></td>
                                            <td>
                                                @if($test['status'] === 'success')
                                                <span class="badge bg-success">Working</span>
                                                @else
                                                <span class="badge bg-danger">Failed</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($test['status'] === 'success')
                                                <small class="text-success">{{ $test['output'] }}</small>
                                                @else
                                                <small class="text-danger">{{ $test['error'] }}</small>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-recommendations" role="tabpanel" aria-labelledby="tab-recommendations-tab">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0 text-white">Recommendations</h5>
                            </div>
                            <div class="card-body">
                                @php
                                $gitWorking = false;
                                $gitPath = null;
                                foreach($results['path_tests'] as $path => $test) {
                                if($test['status'] === 'success') {
                                $gitWorking = true;
                                $gitPath = $path;
                                break;
                                }
                                }
                                if($results['which_git']['status'] === 'success' && !empty(trim($results['which_git']['output']))) {
                                $gitPath = trim($results['which_git']['output']);
                                }
                                @endphp

                                @if($gitWorking && $gitPath)
                                <div class="alert alert-success">
                                    <h6><i class="bx bx-check-circle"></i> Git is available on your server!</h6>
                                    <p class="mb-2"><strong>Next Steps:</strong></p>
                                    <ol class="mb-0">
                                        <li>Add this line to your <code>.env</code> file:
                                            <pre class="bg-dark text-light p-2 rounded mt-1 mb-2">GIT_EXECUTABLE_PATH="{{ $gitPath }}"</pre>
                                        </li>
                                        <li>Run: <code>php artisan config:clear</code></li>
                                        <li>Go to <a href="{{ route('version-control.index') }}" class="alert-link">Version Control</a> page and test!</li>
                                    </ol>
                                </div>
                                @else
                                <div class="alert alert-danger">
                                    <h6><i class="bx bx-error"></i> Git is NOT available on your server</h6>
                                    <p><strong>Possible reasons:</strong></p>
                                    <ul>
                                        <li>Git is not installed on your GoDaddy hosting account</li>
                                        <li>PHP functions like <code>proc_open()</code> are disabled</li>
                                        <li>Your hosting plan doesn't support command execution</li>
                                    </ul>

                                    <p class="mb-2"><strong>Solutions:</strong></p>
                                    <ol class="mb-0">
                                        <li><strong>Contact GoDaddy Support:</strong> Ask if Git is available and request the full path</li>
                                        <li><strong>Use SSH:</strong> If you have SSH access, you can use Git commands directly via terminal</li>
                                        <li><strong>Upgrade Hosting:</strong> Consider upgrading to VPS or dedicated hosting where you have more control</li>
                                        <li><strong>Alternative:</strong> Use Git locally and deploy files via FTP/SFTP instead of using this UI</li>
                                    </ol>
                                </div>
                                @endif

                                @if(!$results['php_functions']['proc_open'])
                                <div class="alert alert-warning mt-3">
                                    <h6><i class="bx bx-error-circle"></i> proc_open() is disabled</h6>
                                    <p class="mb-0">The <code>proc_open()</code> function is required for Git commands but is currently disabled.
                                        Contact your hosting provider to enable this function, or the Git Version Control feature will not work.</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-center">
                    <a href="{{ route('version-control.index') }}" class="btn btn-primary">
                        <i class="bx bx-arrow-back"></i> Back to Version Control
                    </a>
                    <button onclick="location.reload()" class="btn btn-secondary">
                        <i class="bx bx-refresh"></i> Re-run Diagnostics
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection