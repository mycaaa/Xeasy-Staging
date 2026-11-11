{{-- Reusable Organogram & Policy Filter JavaScript --}}
<script>
    (function($) {
        'use strict';

        const ORGANOGRAM_SELECTORS = [
            '#functionSelect',
            '#verticalSelect',
            '#departmentSelect',
            '#subDepartmentSelect',
            '#buSelect',
            '#zoneSelect',
            '#regionSelect',
            '#territorySelect',
            '#hqSelect',
            '#policySelect'
        ];

        $(document).ready(function() {
            // Initialize Select2 on all Organogram selects inside drawer
            ORGANOGRAM_SELECTORS.forEach(function(sel) {
                if ($(sel).length) {
                    $(sel).select2({
                        dropdownParent: $('#additionalFiltersCanvas'),
                        placeholder: function() {
                            return $(this).data('placeholder') || 'Select options';
                        },
                        allowClear: true,
                        width: '100%'
                    });
                }
            });

            // Cascading: Function -> Vertical
            $(document).on('change', '#functionSelect', function() {
                const selectedFunctions = $(this).val() || [];
                $.ajax({
                    url: "{{ url('verticals/by-function') }}",
                    type: 'POST',
                    data: JSON.stringify({ function_ids: selectedFunctions }),
                    contentType: 'application/json',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response && response.success && response.data) {
                            let options = '';
                            response.data.forEach(function(v) {
                                options += `<option value="${v.id}">${v.vertical_name}</option>`;
                            });
                            $('#verticalSelect').html(options).trigger('change.select2');
                        }
                    }
                });
            });

            // Cascading: Vertical -> Department
            $(document).on('change', '#verticalSelect', function() {
                const selectedVerticals = $(this).val() || [];
                $.ajax({
                    url: "{{ url('departments/by-vertical') }}",
                    type: 'POST',
                    data: JSON.stringify({ vertical_ids: selectedVerticals }),
                    contentType: 'application/json',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response && response.success && response.data) {
                            let options = '';
                            response.data.forEach(function(d) {
                                options += `<option value="${d.id}">${d.department_name}</option>`;
                            });
                            $('#departmentSelect').html(options).trigger('change.select2');
                            $('#subDepartmentSelect').html('').trigger('change.select2');
                        }
                    }
                });
            });

            // Cascading: Department -> Sub-Department
            $(document).on('change', '#departmentSelect', function() {
                const selectedDepartments = $(this).val() || [];
                $.ajax({
                    url: "{{ url('sub-departments/by-department') }}",
                    type: 'POST',
                    data: JSON.stringify({ department_ids: selectedDepartments }),
                    contentType: 'application/json',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response && response.success && response.data) {
                            let options = '';
                            response.data.forEach(function(sd) {
                                options += `<option value="${sd.id}">${sd.sub_department_name}</option>`;
                            });
                            $('#subDepartmentSelect').html(options).trigger('change.select2');
                        }
                    }
                });
            });

            // Apply Filters Click
            $(document).on('click', '#applyFiltersBtn', function() {
                window.updateActiveOrganogramFilterCount();
                const canvasEl = document.getElementById('additionalFiltersCanvas');
                if (canvasEl && typeof bootstrap !== 'undefined') {
                    const bsOffcanvas = bootstrap.Offcanvas.getInstance(canvasEl) || new bootstrap.Offcanvas(canvasEl);
                    bsOffcanvas.hide();
                }
                $(document).trigger('organogramFiltersApplied', [window.getOrganogramFilterValues()]);
                if (typeof window.onOrganogramFilterApply === 'function') {
                    window.onOrganogramFilterApply(window.getOrganogramFilterValues());
                }
            });

            // Reset Filters Click
            $(document).on('click', '#resetFiltersBtn', function() {
                ORGANOGRAM_SELECTORS.forEach(function(sel) {
                    if ($(sel).length) {
                        $(sel).val(null).trigger('change.select2');
                    }
                });
                window.updateActiveOrganogramFilterCount();
                const canvasEl = document.getElementById('additionalFiltersCanvas');
                if (canvasEl && typeof bootstrap !== 'undefined') {
                    const bsOffcanvas = bootstrap.Offcanvas.getInstance(canvasEl) || new bootstrap.Offcanvas(canvasEl);
                    bsOffcanvas.hide();
                }
                $(document).trigger('organogramFiltersReset');
                if (typeof window.onOrganogramFilterReset === 'function') {
                    window.onOrganogramFilterReset();
                }
            });

            // Initial count check
            window.updateActiveOrganogramFilterCount();
        });

        // Compute active count
        window.updateActiveOrganogramFilterCount = function() {
            let count = 0;
            ORGANOGRAM_SELECTORS.forEach(function(sel) {
                const $el = $(sel);
                if ($el.length) {
                    const val = $el.val();
                    if (Array.isArray(val)) {
                        count += val.filter(v => v !== '' && v !== 'all').length;
                    } else if (val && val !== '' && val !== 'all') {
                        count += 1;
                    }
                }
            });
            $('#activeFilterCount').text(count);
            return count;
        };

        // Extract values
        window.getOrganogramFilterValues = function() {
            return {
                function_ids:       $('#functionSelect').val() || [],
                vertical_ids:       $('#verticalSelect').val() || [],
                department_ids:     $('#departmentSelect').val() || [],
                sub_department_ids: $('#subDepartmentSelect').val() || [],
                bu_ids:             $('#buSelect').val() || [],
                zone_ids:           $('#zoneSelect').val() || [],
                region_ids:         $('#regionSelect').val() || [],
                territory_ids:      $('#territorySelect').val() || [],
                hq_ids:             $('#hqSelect').val() || [],
                policy_ids:         $('#policySelect').val() || []
            };
        };

        // Generate URL query parameters string
        window.getOrganogramFilterQueryParams = function() {
            const vals = window.getOrganogramFilterValues();
            let qs = '';
            for (const key in vals) {
                if (Array.isArray(vals[key]) && vals[key].length > 0) {
                    qs += `&${encodeURIComponent(key)}=${encodeURIComponent(vals[key].join(','))}`;
                }
            }
            return qs;
        };
    })(jQuery);
</script>
