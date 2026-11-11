<div class="modal fade" id="apiFeildsMapping" tabindex="-1" aria-labelledby="apiLabel">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="progress-container" style="display: none;">
                <div class="progres" style="height: 5px;">
                    <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                </div>
            </div>
            <div class="modal-header">
                <h5 class="modal-title" id="">Fields Mapping</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
               <div class="table-responsive">
                 <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Temp Column</th>
                            <th>Input Type</th>
                            <th>Select Table</th>
                            <th>Search Column</th>
                            <th>Return Column</th>
                            <th>Punch Table</th>
                            <th>Punch Column</th>
                            <th>Condition</th>
                        </tr>
                    </thead>
                    <tbody id="mapping-rows">
                        <!-- Rows will be dynamically added here -->
                    </tbody>
                </table>
               </div>
                <button type="button" class="btn btn-primary" id="save-mapping">Save Mapping</button>
            </div>
        </div>
    </div>
</div>
