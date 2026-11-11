/**
 * Eligibility Validation Panel
 * Shared JavaScript for displaying claim eligibility comparison across all views.
 * Shows policy checks as a visible table at the bottom of claim detail.
 */

/**
 * Build eligibility validation panel HTML from eligibility data returned by the API
 * @param {Object} eligibility - Eligibility data from claim-detail API response
 * @returns {string} HTML string for the eligibility panel
 */
function buildEligibilityPanel(eligibility) {
    if (!eligibility || !eligibility.status || eligibility.status === 'unavailable') {
        return '';
    }

    var statusClass = 'success';
    var statusText = 'Within Limit';
    var statusIcon = '&#10004;';

    if (eligibility.status === 'exceeded') {
        statusClass = 'danger';
        statusText = 'Exceeds Limit';
        statusIcon = '&#10060;';
    } else if (eligibility.status === 'no_limit') {
        statusClass = 'secondary';
        statusText = 'No Limit Defined';
        statusIcon = '&#8505;';
    }

    var html = '<div class="mt-3 border-top pt-3">' +
        '<h6 class="mb-2" style="font-size:13px;color:#15648C;">' +
            '<i class="ri-shield-check-line me-1"></i>Policy Check ' +
            '<span class="badge bg-' + statusClass + '" style="font-size:11px;">' + statusText + '</span>' +
        '</h6>' +
        '<table class="table table-sm table-bordered mb-0" style="font-size:12px;">';

    // Header
    html += '<thead class="table-light"><tr>' +
                '<th style="width:40%;">Parameter</th>' +
                '<th style="width:30%;">Value</th>' +
                '<th style="width:30%;">Status</th>' +
            '</tr></thead><tbody>';

    // Claim Type
    html += '<tr>' +
                '<td class="text-muted">Claim Type</td>' +
                '<td><strong>' + (eligibility.claim_type || '') + '</strong></td>' +
                '<td></td>' +
            '</tr>';

    // Claimed Amount
    html += '<tr>' +
                '<td class="text-muted">Claimed Amount</td>' +
                '<td><strong>\u20B9' + eligibilityFormatNum(eligibility.claimed_amount) + '</strong></td>' +
                '<td></td>' +
            '</tr>';

    // Eligible Limit
    if (eligibility.eligible_limit > 0) {
        html += '<tr class="' + (eligibility.exceeds_limit ? 'table-danger' : 'table-success') + '">' +
                    '<td class="text-muted">Eligible Limit</td>' +
                    '<td><strong>\u20B9' + eligibilityFormatNum(eligibility.eligible_limit) + '</strong></td>' +
                    '<td>' + (eligibility.exceeds_limit ? '<span class="text-danger fw-bold">Exceeded</span>' : '<span class="text-success fw-bold">OK</span>') + '</td>' +
                '</tr>';
    }

    // Excess Amount
    if (eligibility.exceeds_limit && eligibility.excess_amount > 0) {
        html += '<tr class="table-danger">' +
                    '<td class="text-muted">Excess Amount</td>' +
                    '<td><strong class="text-danger">\u20B9' + eligibilityFormatNum(eligibility.excess_amount) + '</strong></td>' +
                    '<td></td>' +
                '</tr>';
    }

    // Basis
    html += '<tr>' +
                '<td class="text-muted">Validation Basis</td>' +
                '<td colspan="2">' + (eligibility.validation_basis || '') + '</td>' +
            '</tr>';

    // Grade & Policy from base_info
    if (eligibility.base_info) {
        if (eligibility.base_info.grade && eligibility.base_info.grade !== 'N/A') {
            html += '<tr>' +
                        '<td class="text-muted">Employee Grade</td>' +
                        '<td colspan="2">' + eligibility.base_info.grade + '</td>' +
                    '</tr>';
        }
        if (eligibility.base_info.policy_name && eligibility.base_info.policy_id > 0) {
            html += '<tr>' +
                        '<td class="text-muted">Vehicle Policy</td>' +
                        '<td colspan="2">' + eligibility.base_info.policy_name + ' (ID: ' + eligibility.base_info.policy_id + ')</td>' +
                    '</tr>';
        }
    }

    // Claim-specific details
    if (eligibility.details) {
        // Travel/KM specific (ClaimId 7)
        if (eligibility.claim_id === 7 && eligibility.details.calculated_rate) {
            html += '<tr class="table-light"><td colspan="3"><strong><em>Travel / KM Details</em></strong></td></tr>';
            html += '<tr>' +
                        '<td class="text-muted">Total KM</td>' +
                        '<td colspan="2">' + (eligibility.details.total_km || 0) + ' km</td>' +
                    '</tr>';
            html += '<tr>' +
                        '<td class="text-muted">Vehicle Type</td>' +
                        '<td colspan="2">' + (eligibility.details.wheel_type || '') + '</td>' +
                    '</tr>';
            html += '<tr>' +
                        '<td class="text-muted">Calculated Rate/KM</td>' +
                        '<td colspan="2">\u20B9' + parseFloat(eligibility.details.calculated_rate || 0).toFixed(2) + '</td>' +
                    '</tr>';
            html += '<tr>' +
                        '<td class="text-muted">Month Running KM</td>' +
                        '<td colspan="2">' + (eligibility.details.month_running_km || 0) + ' km</td>' +
                    '</tr>';
            if (eligibility.details.base_rate_2w > 0) {
                html += '<tr>' +
                            '<td class="text-muted">Base Rate (2W)</td>' +
                            '<td colspan="2">\u20B9' + parseFloat(eligibility.details.base_rate_2w).toFixed(2) + '</td>' +
                        '</tr>';
            }
            if (eligibility.details.base_rate_4w > 0) {
                html += '<tr>' +
                            '<td class="text-muted">Base Rate (4W)</td>' +
                            '<td colspan="2">\u20B9' + parseFloat(eligibility.details.base_rate_4w).toFixed(2) + '</td>' +
                        '</tr>';
            }
        }

        // Lodging specific (ClaimId 1) - show all category limits
        if (eligibility.claim_id === 1 && eligibility.details.all_limits) {
            html += '<tr class="table-light"><td colspan="3"><strong><em>Lodging Limits by City Category</em></strong></td></tr>';
            var limits = eligibility.details.all_limits;
            var cityCategory = eligibility.details.city_category;
            for (var cat in limits) {
                if (limits.hasOwnProperty(cat)) {
                    var isActive = cat.replace('Category ', '') === cityCategory;
                    html += '<tr' + (isActive ? ' class="table-info"' : '') + '>' +
                                '<td class="text-muted">' + cat + '</td>' +
                                '<td>\u20B9' + eligibilityFormatNum(limits[cat]) + '</td>' +
                                '<td>' + (isActive ? '<span class="badge bg-info">Applied</span>' : '') + '</td>' +
                            '</tr>';
                }
            }
        }

        // Group Meal specific (ClaimId 17)
        if (eligibility.claim_id === 17 && eligibility.details) {
            html += '<tr class="table-light"><td colspan="3"><strong><em>DA Eligibility Limits</em></strong></td></tr>';
            if (eligibility.details.da_inside_hq !== undefined) {
                html += '<tr>' +
                            '<td class="text-muted">DA Inside HQ Eligibility</td>' +
                            '<td colspan="2">\u20B9' + eligibilityFormatNum(eligibility.details.da_inside_hq) + '</td>' +
                        '</tr>';
            }
            if (eligibility.details.da_outside_hq !== undefined) {
                html += '<tr>' +
                            '<td class="text-muted">DA Outside HQ Eligibility</td>' +
                            '<td colspan="2">\u20B9' + eligibilityFormatNum(eligibility.details.da_outside_hq) + '</td>' +
                        '</tr>';
            }
        }

        // DA specific (ClaimId 19 or 20)
        if ((eligibility.claim_id === 19 || eligibility.claim_id === 20) && eligibility.details && eligibility.details.raw_limit_value) {
            html += '<tr>' +
                        '<td class="text-muted">Raw Eligibility Value</td>' +
                        '<td colspan="2">' + eligibility.details.raw_limit_value + '</td>' +
                    '</tr>';
        }
    }

    html += '</tbody></table></div>';

    return html;
}

/**
 * Format number in Indian locale style
 * @param {number} num - Number to format
 * @returns {string} Formatted number string
 */
function eligibilityFormatNum(num) {
    if (!num && num !== 0) return '0';
    return parseFloat(num).toLocaleString('en-IN', {maximumFractionDigits: 0});
}
