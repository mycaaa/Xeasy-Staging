@extends('layouts.app')
@section('content')
<div class="page-content">
   <div class="container-fluid">
      <div class="row">
         <div class="col-lg-12">
           <div class="card">
    <div class="card-header align-items-center d-flex">
        <h4 class="card-title mb-0 flex-grow-1">Set Permissions for {{ $user->name }}</h4>
        <div class="flex-shrink-0">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#rolesModal">Manage Roles</button>
        </div>
    </div>
    <div class="card-body">
        @foreach ($allPermissions as $groupName => $permissions)
            <div class="card ribbon-box border shadow-none mb-3">
                <div class="card-body text-muted">
                    <span class="ribbon ribbon-primary ribbon-shape">
                        <span>{{ $groupName }}</span>
                    </span>
                    <div class="row mt-3">
                        @foreach ($permissions as $permission)
                            <div class="col-md-3">
                                <div class="form-check mb-1 d-flex align-items-center">
                                    <input class="form-check-input permission-checkbox me-2" 
                                        type="checkbox" 
                                        id="permission-{{ $permission['id'] }}" 
                                        value="{{ $permission['id'] }}"
                                        {{ isset($userPermissions[$groupName]) && in_array($permission['id'], array_column($userPermissions[$groupName], 'id')) ? 'checked' : '' }}
                                        data-user-id="{{ $user->id }}">
                                    <label class="form-check-label" for="permission-{{ $permission['id'] }}">
                                        {{ $permission['name'] }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

         </div>
      </div>
   </div>
</div>
<x-modal.roles />
@endsection
@push('scripts')
<script>
$(document).ready(function () {
    // Optional: hide permissions initially
    // $('.permissions-group').hide();

    $('.toggle-permissions').on('click', function () {
        let target = $(this).data('target');
        let icon = $(this).find('i');

        // Toggle the permissions group
        $(target).slideToggle(200);

        // Switch icon
        if (icon.hasClass('ri-arrow-down-s-line')) {
            icon.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-up-s-line');
        } else {
            icon.removeClass('ri-arrow-up-s-line').addClass('ri-arrow-down-s-line');
        }
    });
});


</script>
@endpush