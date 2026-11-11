<div class="modal fade" id="permissionModal" tabindex="-1" aria-labelledby="permissionLabel">
   <div class="modal-dialog">
       <div class="modal-content">
           <div class="progress-container" style="display: none;">
               <div class="progres" style="height: 5px;">
                   <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
               </div>
           </div>
           <div class="modal-header">
               <h5 class="modal-title" id="addPermissionLabel">Add New Permission</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
           </div>
           <div class="modal-body">
               <form action="javascript:void(0);" id="permissionForm" novalidate>
                   <div class="row g-3">
                       <div class="col-xxl-6">
                           <label for="permission_group" class="form-label">Group</label>
                           <div class="input-group group_message">
                               <select class="form-select" name="group_id" id="groupDropdown" required>
                                   <option value="" selected>Select a group</option>
                                   <option value="new">Add New Group</option>
                               </select>
                               <input type="text" id="newGroupInput" class="form-control d-none" placeholder="Enter new group name" aria-label="New group name" />
                               <button class="btn btn-primary btn-primary d-none" type="button" id="saveGroupBtn">
                                   <span class="loader" style="display: none;"></span>
                                   Save
                               </button>
                           </div>
                       </div>
                       <div class="col-xxl-6">
                           <label for="permission_name" class="form-label">Name</label>
                           <input type="text" class="form-control" id="permissionName" name="permission_name"  placeholder="Enter permission name" aria-label="Enter permission name" required />
                       </div>
                    
                       <div class="col-xxl-6">
                           <div class="form-check form-switch">
                               <input class="form-check-input" name="is_active" type="checkbox" checked permission="switch" id="is_active" onchange="toggleSwitchText()" />
                               <label class="form-check-label" for="is_active" id="is_active_label">Active</label>
                           </div>
                       </div>
                       <div class="col-lg-12">
                           <div class="hstack gap-2 justify-content-end">
                               <button  type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill" id="savePermissionBtn">
                                   <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2">
                                       <span class="loader" style="display: none;"></span>
                                   </i>
                                   Submit
                               </button>
                           </div>
                       </div>
                   </div>
               </form>
           </div>
       </div>
   </div>
</div>