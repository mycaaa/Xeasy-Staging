/**
 * PAX Details Editor for Data Punch Modal
 * Handles loading, displaying, and editing PAX details for claims
 * Supports: Lodging (1), Rail Fare (14), Group Meal (17), General Expense (42)
 */

// Store PAX data globally for the current claim
let currentPaxData = null;

/**
 * Load and display PAX details in the data punch modal
 */
function loadPaxDetailsInModal(expId, claimId, yearId, comId) {
    console.log('🏨 Loading PAX details for modal - ExpId:', expId, 'ClaimId:', claimId);

    const baseUrl = window.location.origin;
    const url = new URL(`${baseUrl}/api/pax-details/get-claim-pax`, window.location.origin);
    url.searchParams.append('exp_id', expId);
    url.searchParams.append('claim_id', claimId);
    // PAX-YEAR-01 (11 Nov 2026): only a numeric year / company id is sent; otherwise the server uses the session's
    // (a text such as "2026-27" was refused by the id guard and the PAX details did not load)
    if (/^\d+$/.test(String(yearId || ''))) url.searchParams.append('year_id', yearId);
    if (/^\d+$/.test(String(comId || ''))) url.searchParams.append('com_id', comId);

    fetch(url, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Accept': 'application/json'
        }
    })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            console.log('🏨 PAX data received:', data);

            const hasEmployeePax = data.participants && data.participants.length > 0;
            const hasOtherParsed = data.other_participants_parsed && data.other_participants_parsed.length > 0;
            let hasOtherRaw = false;
            if (data.other_participants) {
                if (typeof data.other_participants === 'string' && data.other_participants.trim() !== '') hasOtherRaw = true;
                else if (Array.isArray(data.other_participants) && data.other_participants.length > 0) hasOtherRaw = true;
            }
            const hasPax = hasEmployeePax || hasOtherParsed || hasOtherRaw || (data.pax_count && parseInt(data.pax_count) > 0);

            if (data.success && hasPax) {
                currentPaxData = data;
                displayPaxEditorInModal(data, claimId);
            } else {
                console.log('No PAX data to display');
                removePaxEditorFromModal();
            }
        })
        .catch(error => {
            console.error('❌ Error loading PAX details:', error);
            removePaxEditorFromModal();
        });
}

/**
 * Display PAX editor in the modal
 */
// function displayPaxEditorInModal(data, claimId) {
//     let container = document.getElementById('paxDetailsEditorContainer');

//     // Create container if it doesn't exist
//     if (!container) {
//         const form = document.getElementById('dataEntryForm');
//         container = document.createElement('div');
//         container.id = 'paxDetailsEditorContainer';
//         form.appendChild(container);
//     }

//     let html = '<div class="card border-info mt-3 pax-editor-card">';
//     html += '<div class="card-header bg-info text-white d-flex justify-content-between align-items-center">';
//     html += '<h6 class="mb-0"><i class="fa fa-users"></i> ' + (data.claim_type_name || 'Participant') + ' Details</h6>';
//     html += '</div>';
//     html += '<div class="card-body">';

//     // Image Upload Section - Added at the top
//     html += '<div class="mb-3 p-3 border border-dashed rounded bg-light">';
//     html += '<div class="d-flex justify-content-between align-items-center mb-2">';
//     html += '<div>';
//     html += '<label class="form-label fw-bold mb-0"><i class="fa fa-upload text-primary me-2"></i>Upload PAX Supporting Document</label>';
//     html += '<div class="text-muted small">Upload images or documents related to participant details</div>';
//     html += '</div>';
//     html += '<label for="paxDocumentUpload" class="btn btn-sm btn-primary mb-0" style="cursor:pointer;">';
//     html += '<i class="fa fa-cloud-upload me-1"></i>Choose File';
//     html += '</label>';
//     html += '<input type="file" id="paxDocumentUpload" accept="image/*,application/pdf" style="display:none;" onchange="handlePaxDocumentUpload(this)">';
//     html += '</div>';
//     html += '<div id="paxUploadPreview" class="mt-2" style="display:none;">';
//     html += '<div class="alert alert-success py-2 mb-0 small">';
//     html += '<i class="fa fa-check-circle me-1"></i><span id="paxUploadFileName">No file selected</span>';
//     html += '</div>';
//     html += '</div>';
//     html += '</div>';

//     // Summary section
//     html += '<div class="alert alert-light border mb-3">';
//     html += '<div class="row">';
//     html += '<div class="col-md-4"><strong>Total Participants:</strong> <span class="badge badge-info">' + (data.total_participants || 0) + '</span></div>';
//     if (data.pax_count) {
//         html += '<div class="col-md-4"><strong>PAX Count:</strong> <span class="badge badge-info">' + data.pax_count + '</span></div>';
//     }
//     if (data.quota) {
//         html += '<div class="col-md-4"><strong>Current Quota:</strong> <span class="badge badge-success">' + data.quota + '</span></div>';
//     }
//     html += '</div>';
//     html += '</div>';

//     // Participants table - only if there are participants
//     if (data.participants && data.participants.length > 0) {
//         html += '<div class="table-responsive mb-3">';
//         html += '<table class="table table-striped table-sm table-hover">';
//         html += '<thead class="table-light">';
//         html += '<tr>';
//         html += '<th>#</th>';
//         html += '<th>Employee Code</th>';
//         html += '<th>Name</th>';
//         html += '<th>Status</th>';
//         if (data.participants[0] && data.participants[0].eligibility) {
//             html += '<th>Max Amount</th>';
//             html += '<th>Max Days</th>';
//             html += '<th>Policy</th>';
//         }
//         html += '</tr>';
//         html += '</thead>';
//         html += '<tbody>';

//         data.participants.forEach((participant, index) => {
//             const validClass = participant.is_valid ? '' : ' table-warning';
//             const statusBadge = participant.is_valid
//                 ? '<span class="badge bg-success"><i class="fa fa-check"></i> Valid</span>'
//                 : '<span class="badge bg-warning"><i class="fa fa-exclamation"></i> Invalid</span>';

//             html += '<tr class="' + validClass + '">';
//             html += '<td><small>' + (index + 1) + '</small></td>';
//             html += '<td><code class="bg-light p-1">' + participant.emp_code + '</code></td>';
//             html += '<td><strong>' + participant.emp_name + '</strong></td>';
//             html += '<td>' + statusBadge + '</td>';

//             if (participant.eligibility) {
//                 html += '<td>₹' + parseFloat(participant.eligibility.max_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 }) + '</td>';
//                 html += '<td>' + participant.eligibility.max_days + ' days</td>';
//                 html += '<td><span class="badge bg-secondary">Policy #' + participant.eligibility.policy_id + '</span></td>';
//             }

//             html += '</tr>';
//         });

//         html += '</tbody>';
//         html += '</table>';
//         html += '</div>';
//     } // Close the if (participants && length > 0) block

//     // Show other participants/notes - even if no regular participants
//     if (data.other_participants && data.other_participants.trim() !== '') {
//         html += '<div class="alert alert-secondary mb-3">';
//         html += '<strong><i class="fa fa-sticky-note"></i> Other Participants / Notes:</strong>';
//         html += '<p class="mb-0 mt-2">' + data.other_participants + '</p>';
//         html += '</div>';
//     }

//     // Edit buttons based on claim type
//     html += '<div class="d-flex gap-2 flex-wrap">';

//     // Rail Fare - Show quota editor
//     if (data.claim_id === 14) {
//         html += '<button type="button" class="btn btn-sm btn-warning" onclick="showQuotaEditor(this)">';
//         html += '<i class="fa fa-edit me-1"></i>Edit Quota';
//         html += '</button>';
//     }

//     // Lodging, Meal, General - Show participant editor
//     if ([1, 17, 42].includes(data.claim_id)) {
//         html += '<button type="button" class="btn btn-sm btn-warning" onclick="showParticipantEditor(this)">';
//         html += '<i class="fa fa-edit me-1"></i>Edit Participants';
//         html += '</button>';
//     }

//     html += '</div>';

//     html += '</div>';
//     html += '</div>';

//     container.innerHTML = html;
//     console.log('✅ PAX editor displayed in modal');
// }


// function displayPaxEditorInModal(data, claimId) {
//     let container = document.getElementById('paxDetailsEditorContainer');

//     // Create container if it doesn't exist
//     if (!container) {
//         const form = document.getElementById('dataEntryForm');
//         container = document.createElement('div');
//         container.id = 'paxDetailsEditorContainer';
//         form.appendChild(container);
//     }

//     let html = `
//         <div class="card border-success mb-3" id="paxEditorSection">
//             <div class="card-header bg-success-subtle text-success fw-bold d-flex justify-content-between align-items-center">
//                 <span><i class="ri-user-line"></i> ${data.claim_type_name || 'Participant'} Details</span>
//                 <span class="badge bg-success" id="paxParticipantCount">Participants: ${data.total_participants || 0}</span>
//             </div>
//             <div class="card-body">

//                 <!-- 1. PAX Number Input (like Group Meal PAX No.) -->
//                 <div class="row mb-3">
//                     <div class="col-md-4">
//                         <label class="form-label fw-semibold">PAX No. <span class="text-danger">*</span></label>
//                         <input type="number" name="PaxDetails" id="paxNumberInput" class="form-control form-control-sm" 
//                                min="1" max="100" placeholder="Enter PAX (max 100)" value="${data.pax_count || ''}">
//                     </div>
//                     <div class="col-md-8 d-flex align-items-end">
//                         <small class="text-muted"><i class="ri-information-line"></i> Maximum PAX allowed is 100. Claimant is excluded automatically.</small>
//                     </div>
//                 </div>

//                 <!-- 2. Employee Search Section (like Group Meal) -->
//                 <div id="paxEmployeeSection" class="mb-3">
//                     <label class="form-label fw-semibold small text-primary mb-1">
//                         <i class="ri-user-search-line"></i> Select Employee Participants
//                     </label>
//                     <div class="row mb-2">
//                         <div class="col-md-8">
//                             <div class="input-group input-group-sm">
//                                 <input type="text" id="paxEmployeeSearch" class="form-control" placeholder="Search by Employee Code, Name, or Grade">
//                                 <button class="btn btn-outline-success" type="button" id="paxSearchBtn">
//                                     <i class="ri-search-line"></i> Search
//                                 </button>
//                             </div>
//                         </div>
//                     </div>
//                     <div id="paxSearchResults" class="list-group mb-2" style="max-height:160px;overflow-y:auto;"></div>
//                     <div id="paxSelectedList" class="mb-2"></div>
//                     <input type="hidden" name="pax_participants" id="paxParticipantsInput">
//                 </div>

//                 <!-- 3. Other Participants Section (like Group Meal) -->
//                 <div class="card border border-dashed p-3 mb-2 bg-light">
//                     <div class="d-flex justify-content-between align-items-center mb-2">
//                         <div>
//                             <label class="form-label fw-bold mb-0 text-dark small">
//                                 <i class="ri-user-add-line text-success me-1"></i> Other Participants (Non-Employee / External Categories)
//                             </label>
//                             <div class="text-muted" style="font-size: 11px;">Add participants not available in employee list with their specific category/role.</div>
//                         </div>
//                         <button type="button" class="btn btn-sm btn-success" id="addOtherPaxParticipantBtn">
//                             <i class="ri-add-line"></i> Add Other Participant
//                         </button>
//                     </div>
//                     <div id="otherPaxParticipantsContainer">
//                         <!-- Dynamic participant rows inserted here -->
//                     </div>
//                     <input type="hidden" name="pax_other_participants" id="paxOtherParticipants">
//                 </div>

//                 <!-- 4. Document Upload Section (like Group Meal style) -->
//                 <div class="card border border-dashed p-3 mb-3 bg-light">
//                     <div class="d-flex justify-content-between align-items-center mb-2">
//                         <div>
//                             <label class="form-label fw-bold mb-0 text-dark small">
//                                 <i class="ri-upload-2-line text-primary me-1"></i>Upload PAX Supporting Document
//                             </label>
//                             <div class="text-muted" style="font-size: 11px;">Upload images or documents related to participant details</div>
//                         </div>
//                         <label for="paxDocumentUpload" class="btn btn-sm btn-primary mb-0" style="cursor:pointer;">
//                             <i class="ri-cloud-upload-line me-1"></i>Choose File
//                         </label>
//                         <input type="file" id="paxDocumentUpload" accept="image/*,application/pdf" style="display:none;" onchange="handlePaxDocumentUpload(this)">
//                     </div>
//                     <div id="paxUploadPreview" class="mt-2" style="display:none;">
//                         <div class="alert alert-success py-2 mb-0 small">
//                             <i class="ri-checkbox-circle-fill me-1"></i><span id="paxUploadFileName">No file selected</span>
//                         </div>
//                     </div>
//                 </div>

//                 <!-- 5. Summary / Current Participants Table -->
//                 ${data.participants && data.participants.length > 0 ? `
//                 <div class="mt-3">
//                     <label class="form-label fw-semibold small text-secondary">
//                         <i class="ri-group-line"></i> Current Participants List
//                     </label>
//                     <div class="table-responsive">
//                         <table class="table table-striped table-sm table-hover">
//                             <thead class="table-light">
//                                 <tr>
//                                     <th>#</th>
//                                     <th>Employee Code</th>
//                                     <th>Name</th>
//                                     <th>Status</th>
//                                     ${data.participants[0] && data.participants[0].eligibility ? `
//                                     <th>Max Amount</th>
//                                     <th>Max Days</th>
//                                     <th>Policy</th>` : ''}
//                                 </tr>
//                             </thead>
//                             <tbody>
//                                 ${data.participants.map((participant, index) => {
//                                     const validClass = participant.is_valid ? '' : ' table-warning';
//                                     const statusBadge = participant.is_valid
//                                         ? '<span class="badge bg-success"><i class="ri-check-line"></i> Valid</span>'
//                                         : '<span class="badge bg-warning"><i class="ri-error-warning-line"></i> Invalid</span>';

//                                     // Employee tooltip info
//                                     const tooltipInfo = [
//                                         participant.email ? `Email: ${participant.email}` : '',
//                                         participant.mobile ? `Mobile: ${participant.mobile}` : '',
//                                         participant.department && participant.department.name ? `Dept: ${participant.department.name}` : '',
//                                         participant.designation && participant.designation.name ? `Desig: ${participant.designation.name}` : '',
//                                         participant.grade && participant.grade.name ? `Grade: ${participant.grade.name}` : ''
//                                     ].filter(Boolean).join(' | ');

//                                     return `
//                                         <tr class="${validClass}" title="${tooltipInfo}">
//                                             <td><small>${index + 1}</small></td>
//                                             <td><code class="bg-light p-1">${participant.emp_code}</code></td>
//                                             <td>
//                                                 <div>
//                                                     <strong>${participant.emp_name}</strong>
//                                                     ${participant.department && participant.department.name ? `<br><small class="text-muted"><i class="ri-building-line"></i> ${participant.department.name}</small>` : ''}
//                                                     ${participant.designation && participant.designation.name ? `<br><small class="text-muted"><i class="ri-briefcase-line"></i> ${participant.designation.name}</small>` : ''}
//                                                     ${participant.grade && participant.grade.name ? `<br><small class="badge bg-info">${participant.grade.name}</small>` : ''}
//                                                 </div>
//                                             </td>
//                                             <td>${statusBadge}</td>
//                                             ${participant.eligibility ? `
//                                             <td>₹${parseFloat(participant.eligibility.max_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
//                                             <td>${participant.eligibility.max_days} days</td>
//                                             <td><span class="badge bg-secondary">Policy #${participant.eligibility.policy_id}</span></td>` : ''}
//                                         </tr>
//                                     `;
//                                 }).join('')}
//                             </tbody>
//                         </table>
//                     </div>
//                 </div>` : ''}

//                 <!-- 6. Other Participants Display -->
//                 ${(() => {
//                     let otherList = [];
//                     if (data.other_participants_parsed && Array.isArray(data.other_participants_parsed) && data.other_participants_parsed.length > 0) {
//                         otherList = data.other_participants_parsed;
//                     } else if (data.other_participants) {
//                         if (typeof data.other_participants === 'string') {
//                             let str = data.other_participants.trim();
//                             const txt = document.createElement('textarea');
//                             txt.innerHTML = str;
//                             str = txt.value.trim();
//                             if ((str.startsWith('"') && str.endsWith('"')) || (str.startsWith("'") && str.endsWith("'"))) {
//                                 const inner = str.substring(1, str.length - 1).trim();
//                                 if (inner.startsWith('[') || inner.startsWith('{')) str = inner;
//                             }

//                             try {
//                                 const parsed = JSON.parse(str);
//                                 if (Array.isArray(parsed)) otherList = parsed;
//                             } catch (e) {
//                                 if (!str.includes('[{') && !str.includes('{"') && !str.includes('"name"')) {
//                                     str.split(';').forEach(item => {
//                                         const parts = item.split(':');
//                                         if (parts.length >= 2) {
//                                             otherList.push({ category: parts[0].trim(), name: parts[1].trim(), details: (parts[2] || '').trim() });
//                                         } else if (item.trim()) {
//                                             otherList.push({ name: item.trim(), category: 'Guest', details: '' });
//                                         }
//                                     });
//                                 }
//                             }
//                         } else if (Array.isArray(data.other_participants)) {
//                             otherList = data.other_participants;
//                         }
//                     }

//                     if (otherList.length > 0) {
//                         return `
//                         <div class="card border border-secondary mb-3 mt-2">
//                             <div class="card-header bg-secondary-subtle text-dark fw-bold py-1 px-2 small">
//                                 <i class="ri-user-tag-line me-1"></i> Other Participants (Non-Employees / External Categories)
//                             </div>
//                             <div class="table-responsive">
//                                 <table class="table table-sm table-hover mb-0" style="font-size:12px;">
//                                     <thead class="table-light">
//                                         <tr>
//                                             <th>#</th>
//                                             <th>Participant Name</th>
//                                             <th>Role / Category</th>
//                                             <th>Details / Remarks</th>
//                                         </tr>
//                                     </thead>
//                                     <tbody>
//                                         ${otherList.map((o, idx) => {
//                                             const name = typeof o === 'object' ? (o.name || '-') : o;
//                                             const cat = typeof o === 'object' ? (o.category || 'Guest') : 'Guest';
//                                             const det = typeof o === 'object' ? (o.details || '-') : '-';
//                                             return `
//                                                 <tr>
//                                                     <td>${idx + 1}</td>
//                                                     <td><strong>${name}</strong></td>
//                                                     <td><span class="badge bg-secondary">${cat}</span></td>
//                                                     <td>${det}</td>
//                                                 </tr>`;
//                                         }).join('')}
//                                     </tbody>
//                                 </table>
//                             </div>
//                         </div>`;
//                     } else if (data.other_participants && typeof data.other_participants === 'string' && data.other_participants.trim() !== '') {
//                         return `
//                         <div class="alert alert-secondary mb-3 mt-2">
//                             <strong><i class="ri-sticky-note-line"></i> Other Participants / Notes:</strong>
//                             <p class="mb-0 mt-2">${data.other_participants}</p>
//                         </div>`;
//                     }
//                     return '';
//                 })()}

//                 <!-- 7. Action Buttons -->
//                 <div class="d-flex gap-2 flex-wrap mt-3">
//                     ${data.claim_id === 14 ? `
//                     <button type="button" class="btn btn-sm btn-warning" onclick="showQuotaEditor(this)">
//                         <i class="ri-edit-line me-1"></i>Edit Quota
//                     </button>` : ''}

//                     ${[1, 17, 42].includes(data.claim_id) ? `
//                     <button type="button" class="btn btn-sm btn-warning" onclick="showParticipantEditor(this)">
//                         <i class="ri-edit-line me-1"></i>Edit Participants
//                     </button>` : ''}
//                 </div>

//             </div>
//         </div>
//     `;

//     container.innerHTML = html;
//     console.log('✅ PAX editor displayed in modal with Group Meal style');
// }




// Function to handle employee search for PAX
function setupPaxEmployeeSearch() {
    const searchBtn = document.getElementById('paxSearchBtn');
    const searchInput = document.getElementById('paxEmployeeSearch');
    const resultsDiv = document.getElementById('paxSearchResults');
    const selectedList = document.getElementById('paxSelectedList');
    let selectedEmployees = [];

    function searchEmployees() {
        const query = searchInput.value.trim();
        if (query.length < 2) {
            resultsDiv.innerHTML = '<div class="text-muted small p-2">Type at least 2 characters to search</div>';
            return;
        }

        // AJAX call to search employees via unrestricted search-all endpoint
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch('/employees/search-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                search: query,
                exclude_claimant: 1,
                _token: csrfToken
            })
        })
            .then(response => response.json())
            .then(res => {
                const employees = res.employees || res.data || [];
                if (employees.length === 0) {
                    resultsDiv.innerHTML = '<div class="text-muted small p-2">No employees found</div>';
                    return;
                }

                resultsDiv.innerHTML = employees.map(emp => `
                    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" 
                         onclick="addPaxEmployee('${emp.emp_code || emp.EmpCode_New || emp.EmpCode}', '${emp.emp_name || emp.EmployeeName}')">
                        <div>
                            <strong>${emp.emp_name || emp.EmployeeName}</strong>
                            <small class="text-muted d-block">${emp.emp_code || emp.EmpCode} - ${emp.grade_name || emp.grade || 'N/A'} (${emp.company_name || ''})</small>
                        </div>
                        <button class="btn btn-sm btn-success">
                            <i class="ri-add-line"></i> Add
                        </button>
                    </div>
                `).join('');
            })
            .catch(error => {
                console.error('Error searching employees:', error);
                resultsDiv.innerHTML = '<div class="text-danger small p-2">Error searching employees</div>';
            });
    }

    // Add employee to selected list
    window.addPaxEmployee = function (empCode, empName) {
        if (selectedEmployees.find(e => e.emp_code === empCode)) {
            alert('Employee already added!');
            return;
        }

        selectedEmployees.push({ emp_code: empCode, emp_name: empName });
        updatePaxSelectedList();
        document.getElementById('paxParticipantsInput').value = JSON.stringify(selectedEmployees);
        // Clear search results
        document.getElementById('paxSearchResults').innerHTML = '';
        document.getElementById('paxEmployeeSearch').value = '';

        // Update participant count
        updatePaxParticipantCount();
    };

    function updatePaxSelectedList() {
        if (selectedEmployees.length === 0) {
            selectedList.innerHTML = '<div class="text-muted small">No participants selected</div>';
            return;
        }

        selectedList.innerHTML = `
            <div class="d-flex flex-wrap gap-1">
                ${selectedEmployees.map((emp, index) => `
                    <span class="badge bg-primary d-flex align-items-center gap-1 p-2">
                        ${emp.emp_name} (${emp.emp_code})
                        <i class="ri-close-line" style="cursor:pointer;" onclick="removePaxEmployee(${index})"></i>
                    </span>
                `).join('')}
            </div>
        `;
    }

    window.removePaxEmployee = function (index) {
        selectedEmployees.splice(index, 1);
        updatePaxSelectedList();
        document.getElementById('paxParticipantsInput').value = JSON.stringify(selectedEmployees);
        updatePaxParticipantCount();
    };

    function updatePaxParticipantCount() {
        const count = selectedEmployees.length + getOtherPaxParticipantsCount();
        document.getElementById('paxParticipantCount').textContent = `Participants: ${count}`;
    }

    // Search button click
    searchBtn.addEventListener('click', searchEmployees);

    // Enter key press
    searchInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchEmployees();
        }
    });
}

// Function to handle Other Participants for PAX
function setupPaxOtherParticipants() {
    const container = document.getElementById('otherPaxParticipantsContainer');
    const addBtn = document.getElementById('addOtherPaxParticipantBtn');
    let otherParticipants = [];

    function addOtherPaxParticipant() {
        const id = Date.now();
        otherParticipants.push({ id, name: '', category: '' });
        renderOtherPaxParticipants();
        updatePaxOtherParticipantsHidden();
        updatePaxParticipantCount();
    }

    function removeOtherPaxParticipant(id) {
        otherParticipants = otherParticipants.filter(p => p.id !== id);
        renderOtherPaxParticipants();
        updatePaxOtherParticipantsHidden();
        updatePaxParticipantCount();
    }

    function renderOtherPaxParticipants() {
        if (otherParticipants.length === 0) {
            container.innerHTML = '<div class="text-muted small">No other participants added</div>';
            return;
        }

        container.innerHTML = otherParticipants.map(p => `
            <div class="row mb-2" data-id="${p.id}">
                <div class="col-md-5">
                    <input type="text" class="form-control form-control-sm other-pax-name" 
                           placeholder="Name / Category" value="${p.name || ''}">
                </div>
                <div class="col-md-5">
                    <select class="form-select form-select-sm other-pax-category">
                        <option value="">Select Category</option>
                        <option value="Vendor" ${p.category === 'Vendor' ? 'selected' : ''}>Vendor</option>
                        <option value="Consultant" ${p.category === 'Consultant' ? 'selected' : ''}>Consultant</option>
                        <option value="Client" ${p.category === 'Client' ? 'selected' : ''}>Client</option>
                        <option value="Other" ${p.category === 'Other' ? 'selected' : ''}>Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-danger remove-other-pax" data-id="${p.id}">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </div>
            </div>
        `).join('');

        // Add event listeners
        container.querySelectorAll('.other-pax-name').forEach((input, index) => {
            input.addEventListener('change', function () {
                otherParticipants[index].name = this.value;
                updatePaxOtherParticipantsHidden();
            });
        });

        container.querySelectorAll('.other-pax-category').forEach((select, index) => {
            select.addEventListener('change', function () {
                otherParticipants[index].category = this.value;
                updatePaxOtherParticipantsHidden();
            });
        });

        container.querySelectorAll('.remove-other-pax').forEach(btn => {
            btn.addEventListener('click', function () {
                const id = parseInt(this.dataset.id);
                removeOtherPaxParticipant(id);
            });
        });
    }

    function updatePaxOtherParticipantsHidden() {
        document.getElementById('paxOtherParticipants').value = JSON.stringify(otherParticipants);
    }

    function getOtherPaxParticipantsCount() {
        return otherParticipants.length;
    }

    // Make functions globally accessible
    window.addOtherPaxParticipant = addOtherPaxParticipant;
    window.getOtherPaxParticipantsCount = getOtherPaxParticipantsCount;
    window.updatePaxParticipantCount = function () {
        const count = (window.getSelectedPaxEmployees ? window.getSelectedPaxEmployees().length : 0) + getOtherPaxParticipantsCount();
        document.getElementById('paxParticipantCount').textContent = `Participants: ${count}`;
    };

    addBtn.addEventListener('click', addOtherPaxParticipant);
}

// Function to handle PAX document upload
function handlePaxDocumentUpload(input) {
    const file = input.files[0];
    if (file) {
        const preview = document.getElementById('paxUploadPreview');
        const fileName = document.getElementById('paxUploadFileName');
        preview.style.display = 'block';
        fileName.textContent = file.name;

        // You can add additional logic here for file validation, upload, etc.
        console.log('File selected:', file.name);
    }
}

// Initialize PAX editor when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    // Check if PAX editor section exists
    if (document.getElementById('paxEditorSection')) {
        setupPaxEmployeeSearch();
        setupPaxOtherParticipants();

        // Add PAX number change listener
        const paxInput = document.getElementById('paxNumberInput');
        if (paxInput) {
            paxInput.addEventListener('change', function () {
                const value = parseInt(this.value);
                if (value > 100) {
                    alert('Maximum PAX allowed is 100');
                    this.value = 100;
                }
                if (value < 1) {
                    this.value = 1;
                }
            });
        }
    }
});

/**
 * Remove PAX editor from modal
 */
function removePaxEditorFromModal() {
    const container = document.getElementById('paxDetailsEditorContainer');
    if (container) {
        container.remove();
    }
    currentPaxData = null;
}

/**
 * Show quota editor for rail fare
 */
function showQuotaEditor(btn) {
    if (!currentPaxData) {
        alert('No PAX data available');
        return;
    }

    const currentQuota = currentPaxData.quota || '';
    const newQuota = prompt('Enter new quota (e.g., AC, 2AC, 3AC, SL):', currentQuota);

    if (newQuota === null) return; // User cancelled
    if (newQuota.trim() === '') {
        alert('Quota cannot be empty');
        return;
    }

    saveQuotaToDatabase(btn, newQuota.trim());
}

/**
 * Save quota to database
 */
function saveQuotaToDatabase(btn, newQuota) {
    btn.disabled = true;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

    const baseUrl = window.location.origin;
    const expId = document.querySelector('[data-modal-expid]')?.getAttribute('data-modal-expid');
    const yearId = getCurrentYearId();

    if (!expId || !yearId) {
        alert('Error: Missing expense ID or year ID');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        return;
    }

    fetch(`${baseUrl}/api/pax-details/update-quota`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            exp_id: expId,
            year_id: yearId,
            quota: newQuota
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentPaxData.quota = newQuota;
                alert('✅ Quota updated successfully!');
                // Refresh PAX display
                const claimId = currentPaxData.claim_id;
                const comId = document.querySelector('[data-modal-cgid]')?.getAttribute('data-modal-cgid');
                if (comId) {
                    loadPaxDetailsInModal(expId, claimId, yearId, comId);
                }
            } else {
                alert('❌ Error: ' + (data.error || 'Failed to update quota'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Network error: ' + error.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
}

/**
 * Show participant editor
 */
function showParticipantEditor(btn) {
    if (!currentPaxData) {
        alert('No PAX data available');
        return;
    }

    const currentParticipants = currentPaxData.participants.map(p => p.emp_code).join(', ');
    const newParticipants = prompt('Enter employee codes (comma-separated):', currentParticipants);

    if (newParticipants === null) return; // User cancelled
    if (newParticipants.trim() === '') {
        alert('Participants cannot be empty');
        return;
    }

    const codes = newParticipants.split(',').map(c => c.trim()).filter(c => c.length > 0);

    if (codes.length === 0) {
        alert('No valid employee codes entered');
        return;
    }

    saveParticipantsToDatabase(btn, codes);
}

/**
 * Save participants to database
 */
function saveParticipantsToDatabase(btn, codes) {
    btn.disabled = true;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';

    const baseUrl = window.location.origin;
    const expId = document.querySelector('[data-modal-expid]')?.getAttribute('data-modal-expid');
    const yearId = getCurrentYearId();

    if (!expId || !yearId) {
        alert('Error: Missing expense ID or year ID');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        return;
    }

    fetch(`${baseUrl}/api/pax-details/update-participants`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            exp_id: expId,
            year_id: yearId,
            participants: codes
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Participants updated successfully!');
                // Refresh PAX display
                const claimId = currentPaxData.claim_id;
                const comId = document.querySelector('[data-modal-cgid]')?.getAttribute('data-modal-cgid');
                if (comId) {
                    loadPaxDetailsInModal(expId, claimId, yearId, comId);
                }
            } else {
                alert('❌ Error: ' + (data.error || 'Failed to update participants'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Network error: ' + error.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
}

/**
 * Get current year ID from session or page
 */
function getCurrentYearId() {
    // PAX-YEAR-01: the session's year from the page first; any other source counts only when it is a number
    const metaYear = document.querySelector('meta[name="xeasy-year-id"]')?.content;
    if (/^\d+$/.test(String(metaYear || ''))) return metaYear;
    const found = getCurrentYearIdLoose();
    return /^\d+$/.test(String(found || '').trim()) ? String(found).trim() : '';
}

function getCurrentYearIdLoose() {
    // Try to get from various sources
    let yearId = document.querySelector('[data-year-id]')?.getAttribute('data-year-id');
    if (!yearId) yearId = document.querySelector('input[name="YearId"]')?.value;
    if (!yearId) yearId = document.querySelector('input[name="year_id"]')?.value;
    if (!yearId) yearId = document.querySelector('select[name="YearId"]')?.value;
    if (!yearId) yearId = document.querySelector('select[name="year_id"]')?.value;
    if (!yearId) yearId = document.querySelector('[id*="year"]')?.value;
    if (!yearId && typeof fYearId !== 'undefined') yearId = fYearId;
    if (!yearId && typeof yearId_global !== 'undefined') yearId = yearId_global;

    // Try to get from localStorage or sessionStorage
    if (!yearId) yearId = sessionStorage.getItem('year_id') || localStorage.getItem('year_id');

    // Last resort - look for any element with year-like data
    if (!yearId) {
        const possibleElement = document.querySelector('[data-*="year"], [class*="year"], [id*="Year"]');
        yearId = possibleElement?.value || possibleElement?.textContent;
    }

    return yearId;
}

/**
 * Initialize PAX editor when modal is opened
 * Call this from the modal loading code
 */
function initializePaxEditor(expId, claimId, yearId, comId) {
    // Get year ID if not provided
    if (!yearId) {
        yearId = getCurrentYearId();
    }

    // Get company ID if not provided
    if (!comId) {
        comId = document.querySelector('#modalCompany')?.value ||
            document.querySelector('[data-modal-comid]')?.getAttribute('data-modal-comid') ||
            document.querySelector('input[name="com_id"]')?.value ||
            document.querySelector('input[name="company_id"]')?.value ||
            document.querySelector('input[name="ComId"]')?.value ||
            ''; // Empty string lets backend use active session company_id
    }

    if (!expId || !claimId || !yearId) {
        console.warn('⚠️ Missing parameters for PAX editor initialization', {
            expId, claimId, yearId, comId
        });
        return;
    }

    // Only load for supported claim types
    const supportedClaimIds = [1, 14, 17, 42];
    if (supportedClaimIds.includes(parseInt(claimId))) {
        setTimeout(() => {
            loadPaxDetailsInModal(expId, claimId, yearId, comId);
        }, 500); // Small delay to ensure form is loaded
    }
}

/**
 * Handle PAX document upload
 */
function handlePaxDocumentUpload(input) {
    const file = input.files[0];

    if (!file) {
        console.log('No file selected');
        return;
    }

    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
    if (!allowedTypes.includes(file.type)) {
        alert('❌ Invalid file type. Please upload an image (JPG, PNG, GIF) or PDF file.');
        input.value = '';
        return;
    }

    // Validate file size (max 10MB)
    const maxSize = 10 * 1024 * 1024; // 10MB
    if (file.size > maxSize) {
        alert('❌ File size too large. Maximum allowed size is 10MB.');
        input.value = '';
        return;
    }

    // Show file preview/name
    const previewDiv = document.getElementById('paxUploadPreview');
    const fileNameSpan = document.getElementById('paxUploadFileName');

    if (previewDiv && fileNameSpan) {
        fileNameSpan.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
        previewDiv.style.display = 'block';
    }

    console.log('✅ PAX document selected:', file.name);

    // Upload the file
    uploadPaxDocument(file, input);
}

/**
 * Upload PAX document to server
 */
function uploadPaxDocument(file, inputElement) {
    const baseUrl = window.location.origin;
    const expId = document.querySelector('[data-modal-expid]')?.getAttribute('data-modal-expid');
    const yearId = getCurrentYearId();
    const claimId = currentPaxData?.claim_id;
    const comId = document.querySelector('[data-modal-cgid]')?.getAttribute('data-modal-cgid');

    if (!expId || !yearId || !claimId) {
        alert('❌ Error: Missing required information (Expense ID, Year ID, or Claim ID)');
        return;
    }

    // Create FormData
    const formData = new FormData();
    formData.append('document', file);
    formData.append('exp_id', expId);
    formData.append('year_id', yearId);
    formData.append('claim_id', claimId);
    formData.append('com_id', comId || 1);

    // Show uploading message
    const previewDiv = document.getElementById('paxUploadPreview');
    const fileNameSpan = document.getElementById('paxUploadFileName');

    if (fileNameSpan) {
        const originalText = fileNameSpan.textContent;
        fileNameSpan.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Uploading ' + file.name + '...';
    }

    // Upload to server
    fetch(`${baseUrl}/api/pax-details/upload-document`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Accept': 'application/json'
        },
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (fileNameSpan) {
                    fileNameSpan.innerHTML = '<i class="fa fa-check-circle me-1"></i>' + file.name + ' uploaded successfully!';
                }
                console.log('✅ PAX document uploaded successfully:', data);

                // Optionally refresh the PAX details to show the uploaded document
                setTimeout(() => {
                    if (previewDiv) {
                        previewDiv.style.display = 'none';
                    }
                    inputElement.value = ''; // Reset input
                }, 3000);
            } else {
                throw new Error(data.error || 'Upload failed');
            }
        })
        .catch(error => {
            console.error('❌ Error uploading PAX document:', error);
            alert('❌ Failed to upload document: ' + error.message);

            if (previewDiv) {
                previewDiv.style.display = 'none';
            }
            inputElement.value = ''; // Reset input
        });
}

/**
 * Format file size for display
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}
