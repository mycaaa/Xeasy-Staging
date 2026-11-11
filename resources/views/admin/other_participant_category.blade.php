@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex" style="justify-content: space-between;">
                            <h5 class="card-title mb-0"><i class="ri-user-shared-line me-1"></i> Other Participant Roles / Categories</h5>
                            <div style="display: flex;gap:8px;align-items: baseline;">
                                <div class="dropdown card-header-dropdown">
                                    <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <span class="fw-semibold fs-12">Status: </span>
                                        <span class="text-muted">
                                            @if ($status === 'active')
                                                Active
                                            @elseif($status === 'inactive')
                                                Inactive
                                            @else
                                                All
                                            @endif
                                            <i class="mdi mdi-chevron-down"></i>
                                        </span>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ url()->current() }}?status=all">All</a>
                                        <a class="dropdown-item" href="{{ url()->current() }}?status=active">Active</a>
                                        <a class="dropdown-item" href="{{ url()->current() }}?status=inactive">Inactive</a>
                                    </div>
                                </div>
                                <div class="flex-shrink-0">
                                    <button type="button"
                                        class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                        data-bs-toggle="modal" data-bs-target="#otherParticipantCategoryModal"
                                        id="addParticipantCategoryBtn">
                                        <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i>
                                        Add New Role / Category
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="alertMessageContainer"></div>
                            <table id="otherParticipantCategoryTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                style="width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;">S No.</th>
                                        <th style="text-align: left">Category Name</th>
                                        <th>Code</th>
                                        <th>Sort Order</th>
                                        <th>Status</th>
                                        <th style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($categories as $key => $category)
                                        <tr>
                                            <td>{{ $key + 1 }}</td>
                                            <td style="text-align: left">
                                                <strong>{{ $category->name }}</strong>
                                                @if($category->description)
                                                    <br><small class="text-muted">{{ $category->description }}</small>
                                                @endif
                                            </td>
                                            <td><code>{{ $category->code ?? '-' }}</code></td>
                                            <td>{{ $category->sort_order }}</td>
                                            <td>
                                                @if ($category->status)
                                                    <span class="badge bg-success-subtle text-success badge-border toggle-status" data-id="{{ $category->id }}" style="cursor:pointer;" title="Click to disable">Active</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger badge-border toggle-status" data-id="{{ $category->id }}" style="cursor:pointer;" title="Click to enable">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <button type="button"
                                                    class="btn btn-sm btn-primary edit-category-btn"
                                                    data-id="{{ $category->id }}"
                                                    data-name="{{ $category->name }}"
                                                    data-code="{{ $category->code }}"
                                                    data-sort_order="{{ $category->sort_order }}"
                                                    data-description="{{ $category->description }}"
                                                    data-status="{{ $category->status }}"
                                                    title="Edit Category">
                                                    <i class="ri-edit-2-fill"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-category-btn"
                                                    data-id="{{ $category->id }}" data-name="{{ $category->name }}" title="Delete Category">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                    @if(count($categories) === 0)
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-3">No categories found. Click "Add New Role / Category" to create one.</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Add/Edit Category -->
    <div class="modal fade" id="otherParticipantCategoryModal" tabindex="-1" aria-labelledby="otherParticipantCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="otherParticipantCategoryForm">
                    @csrf
                    <input type="hidden" name="_method" id="formMethod" value="POST">
                    <input type="hidden" id="categoryId" name="id">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title" id="otherParticipantCategoryModalLabel">Add Other Participant Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modalMessageContainer"></div>
                        <div class="mb-3">
                            <label for="categoryName" class="form-label fw-semibold">Category / Role Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="categoryName" name="name" placeholder="e.g. Retailer, Dealer, Consultant" required>
                        </div>
                        <div class="mb-3">
                            <label for="categoryCode" class="form-label fw-semibold">Code / Short Identifier</label>
                            <input type="text" class="form-control" id="categoryCode" name="code" placeholder="e.g. RETAILER, DEALER (Optional)">
                            <small class="text-muted">Will be auto-generated if left blank.</small>
                        </div>
                        <div class="mb-3">
                            <label for="categorySortOrder" class="form-label fw-semibold">Sort Order</label>
                            <input type="number" class="form-control" id="categorySortOrder" name="sort_order" value="0" min="0">
                        </div>
                        <div class="mb-3">
                            <label for="categoryDescription" class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" id="categoryDescription" name="description" rows="2" placeholder="Optional notes..."></textarea>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="categoryStatus" name="status" value="1" checked>
                            <label class="form-check-label fw-semibold" for="categoryStatus">Active Status</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveCategoryBtn">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        $(document).ready(function() {
            // Open modal for add
            $("#addParticipantCategoryBtn").click(function() {
                $("#otherParticipantCategoryForm")[0].reset();
                $("#categoryId").val("");
                $("#formMethod").val("POST");
                $("#categoryName").val("");
                $("#categoryCode").val("");
                $("#categorySortOrder").val(0);
                $("#categoryDescription").val("");
                $("#otherParticipantCategoryModalLabel").text("Add Other Participant Category");
                $("#categoryStatus").prop("checked", true);
                $("#modalMessageContainer").html("");
                $("#otherParticipantCategoryModal").modal("show");
            });

            // Open modal for edit
            $(document).on("click", ".edit-category-btn", function() {
                const btn = $(this);
                const id = btn.attr("data-id") || btn.data("id");
                const name = btn.attr("data-name") || btn.data("name") || "";
                const code = btn.attr("data-code") || btn.data("code") || "";
                const sortOrder = btn.attr("data-sort_order") !== undefined ? btn.attr("data-sort_order") : (btn.data("sort_order") || 0);
                const description = btn.attr("data-description") || btn.data("description") || "";
                const status = btn.attr("data-status") !== undefined ? btn.attr("data-status") : btn.data("status");

                $("#modalMessageContainer").html("");
                $("#categoryId").val(id);
                $("#formMethod").val("PUT");
                $("#categoryName").val(name);
                $("#categoryCode").val(code);
                $("#categorySortOrder").val(sortOrder);
                $("#categoryDescription").val(description);
                $("#categoryStatus").prop("checked", status == 1 || status === "1" || status === true);
                $("#otherParticipantCategoryModalLabel").text("Edit Other Participant Category - " + name);

                $("#otherParticipantCategoryModal").modal("show");

                // Also fetch fresh record via AJAX to ensure up-to-date values
                $.ajax({
                    url: "{{ url('other-participant-categories') }}/" + id + "/edit",
                    type: "GET",
                    success: function(res) {
                        if (res.status === 200 && res.data) {
                            const cat = res.data;
                            $("#categoryId").val(cat.id);
                            $("#categoryName").val(cat.name);
                            $("#categoryCode").val(cat.code || "");
                            $("#categorySortOrder").val(cat.sort_order);
                            $("#categoryDescription").val(cat.description || "");
                            $("#categoryStatus").prop("checked", cat.status == 1);
                            $("#otherParticipantCategoryModalLabel").text("Edit Other Participant Category - " + cat.name);
                        }
                    }
                });
            });

            // Submit form
            $("#otherParticipantCategoryForm").submit(function(e) {
                e.preventDefault();
                const id = $("#categoryId").val();
                const method = $("#formMethod").val();
                const url = method === "PUT" 
                    ? "{{ url('other-participant-categories') }}/" + id
                    : "{{ url('other-participant-categories') }}";

                const formData = {
                    _token: "{{ csrf_token() }}",
                    _method: method,
                    name: $("#categoryName").val(),
                    code: $("#categoryCode").val(),
                    sort_order: $("#categorySortOrder").val(),
                    description: $("#categoryDescription").val(),
                    status: $("#categoryStatus").is(":checked") ? 1 : 0
                };

                $.ajax({
                    url: url,
                    type: "POST",
                    data: formData,
                    beforeSend: function() {
                        $("#saveCategoryBtn").prop("disabled", true).text("Saving...");
                    },
                    success: function(res) {
                        $("#saveCategoryBtn").prop("disabled", false).text("Save Category");
                        if (res.status === 200) {
                            $("#otherParticipantCategoryModal").modal("hide");
                            location.reload();
                        } else {
                            $("#modalMessageContainer").html('<div class="alert alert-danger py-2">' + (res.message || 'Validation error') + '</div>');
                        }
                    },
                    error: function(xhr) {
                        $("#saveCategoryBtn").prop("disabled", false).text("Save Category");
                        let errStr = "Error saving category.";
                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            errStr = Object.values(xhr.responseJSON.errors).flat().join("<br>");
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errStr = xhr.responseJSON.message;
                        }
                        $("#modalMessageContainer").html('<div class="alert alert-danger py-2">' + errStr + '</div>');
                    }
                });
            });

            // Toggle status
            $(document).on("click", ".toggle-status", function() {
                const id = $(this).data("id");
                $.ajax({
                    url: "{{ url('other-participant-categories/toggle-status') }}/" + id,
                    type: "POST",
                    data: { _token: "{{ csrf_token() }}" },
                    success: function(res) {
                        if (res.status === 200) {
                            location.reload();
                        }
                    }
                });
            });

            // Delete
            $(document).on("click", ".delete-category-btn", function() {
                const id = $(this).data("id");
                const name = $(this).data("name");
                if (confirm(`Are you sure you want to delete category "${name}"?`)) {
                    $.ajax({
                        url: "{{ url('other-participant-categories') }}/" + id,
                        type: "POST",
                        data: { _token: "{{ csrf_token() }}", _method: "DELETE" },
                        success: function(res) {
                            location.reload();
                        }
                    });
                }
            });
        });
    </script>
    @endpush
@endsection
