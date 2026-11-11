{{-- resources/views/expense-payments/partials/modals.blade.php --}}
<!-- Custom Modal Structure -->
<div id="customModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <span class="close-btn" id="closeModal">✕</span>
        <h3>Edit Remark</h3>
        <form id="editRemarkForm">
            @csrf
            <input type="hidden" id="remarkId" name="remarkId" value="">
            <label for="finPayRemark">Remark:</label>
            <textarea id="finPayRemark" name="finPayRemark" class="input-field"></textarea>
            <div id="previousRemarks" class="previous-remarks"></div>
            <button type="button" id="saveRemarkBtn" class="save-btn">Save</button>
        </form>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}
.modal-content {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 6px;
    width: 450px;
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
    position: relative;
    text-align: center;
}
.close-btn {
    position: absolute;
    top: 8px;
    right: 10px;
    cursor: pointer;
    font-size: 18px;
    color: #333;
}
.input-field {
    width: 90%;
    padding: 6px;
    margin-top: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.save-btn {
    margin-top: 12px;
    padding: 6px 12px;
    background-color: #0078d7;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
.save-btn:hover {
    background-color: #005a9e;
}
</style>

<script>
// Modal functionality
$(document).ready(function() {
    // Edit remark button handler
    $(document).on('click', '.edit-btn', function() {
        var id = $(this).data('id');
        var remark = $(this).data('remark');
        var previousRemark1 = $(this).data('remark1');
        var previousRemark2 = $(this).data('remark2');
        
        $('#remarkId').val(id);
        $('#finPayRemark').val(remark);
        
        let remarksHtml = "";
        if (previousRemark1 || previousRemark2) {
            remarksHtml += "<h4 style='font-size: 14px; margin-top: 20px;'>Previous Remarks:</h4>";
            remarksHtml += "<ul style='text-align: left; font-size: 14px;'>";
            if (previousRemark1) remarksHtml += `<li style='font-size: 14px;'>${$('<div>').text(String(previousRemark1)).html()}</li>`; // S-09
            if (previousRemark2) remarksHtml += `<li style='font-size: 14px;'>${$('<div>').text(String(previousRemark2)).html()}</li>`;
            remarksHtml += "</ul>";
        }
        $('#previousRemarks').html(remarksHtml);
        $('#customModal').css('display', 'flex');
    });
    
    // Close modal
    $('#closeModal').on('click', function() {
        $('#customModal').css('display', 'none');
    });
    
    // Save remark
    $('#saveRemarkBtn').on('click', function() {
        var id = $('#remarkId').val();
        var updatedRemark = $('#finPayRemark').val();
        
        $.ajax({
            url: '{{ route("update-remark") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                id: id,
                finPayRemark: updatedRemark
            },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    alert('Remark updated successfully!');
                    location.reload();
                } else {
                    alert('Failed to update remark: ' + (data.message || 'An error occurred.'));
                }
            },
            error: function() {
                alert('An error occurred while updating the remark.');
            }
        });
    });
});
</script>