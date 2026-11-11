$(document).ready(function () {
    // State management
    let loginData = [];
    let filteredData = [];
    let charts = {};
    let currentFilters = {
        dateFrom: null,
        dateTo: null,
        userIds: [],
        filterType: 'all',
    };

    // Initialize Flatpickr
    const today = new Date();
    const datePicker = flatpickr('#dateRange', {
        mode: 'range',
        dateFormat: 'd M, Y',
        maxDate: new Date(today.setHours(23, 59, 59, 999)),
        defaultDate: [
            new Date(today.getFullYear(), today.getMonth() - 1, 1),
            today,
        ],
        onClose: function (selectedDates) {
            currentFilters.dateFrom = selectedDates[0] ? formatDateForAPI(selectedDates[0]) : null;
            currentFilters.dateTo = selectedDates[1] ? formatDateForAPI(selectedDates[1]) : null;
            fetchDashboardData();
        },
    });

    // Format date for API
    function formatDateForAPI(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    // Set initial date filters
    if (datePicker.selectedDates.length === 2) {
        currentFilters.dateFrom = formatDateForAPI(datePicker.selectedDates[0]);
        currentFilters.dateTo = formatDateForAPI(datePicker.selectedDates[1]);
    }

    // Event listeners
    $('#filterType').on('change', function () {
        currentFilters.filterType = $(this).val();
        fetchDashboardData();
    });

    $('#sortBy').on('change', sortData);

    // User selection (if present in the UI)
    if ($('#selectAllUsers').length) {
        $('#selectAllUsers').on('change', function () {
            $('.user-checkbox').prop('checked', this.checked);
            updateSelectedUsersText();
            updateCurrentUserFilters();
            fetchDashboardData();
        });

        $(document).on('change', '.user-checkbox', function () {
            updateSelectedUsersText();
            updateCurrentUserFilters();
            fetchDashboardData();
        });
    }

    function updateCurrentUserFilters() {
        if (!$('#selectAllUsers').length) return;

        const checkedUsers = $('.user-checkbox:checked');
        const selectAllUsers = $('#selectAllUsers');

        if (checkedUsers.length === 0 || selectAllUsers.prop('checked')) {
            currentFilters.userIds = [];
            selectAllUsers.prop('checked', true);
        } else {
            currentFilters.userIds = checkedUsers.map(function () {
                return $(this).val();
            }).get();
            selectAllUsers.prop('checked', false);
        }
    }

    function updateSelectedUsersText() {
        if (!$('#selectedUsers').length) return;

        const checkedUsers = $('.user-checkbox:checked');
        const selectAllUsers = $('#selectAllUsers');
        const selectedUsersSpan = $('#selectedUsers');
        const totalUsers = $('.user-checkbox').length;

        if (checkedUsers.length === 0 || checkedUsers.length === totalUsers) {
            selectedUsersSpan.text('All Users');
            selectAllUsers.prop('checked', true);
        } else if (checkedUsers.length === 1) {
            const userName = checkedUsers.closest('li').find('label').text().split(' (')[0].trim();
            selectedUsersSpan.text(userName);
        } else {
            selectedUsersSpan.text(`${checkedUsers.length} Users Selected`);
        }
    }

    // Fetch dashboard data
    function fetchDashboardData() {
        const params = new URLSearchParams();

        if (currentFilters.dateFrom && currentFilters.dateTo) {
            params.append('date_from', currentFilters.dateFrom);
            params.append('date_to', currentFilters.dateTo);
        }

        if (currentFilters.userIds.length > 0) {
            currentFilters.userIds.forEach((id) => params.append('user_ids[]', id));
        }

        if (currentFilters.filterType !== 'all') {
            if (currentFilters.filterType === 'multiple_ip') {
                params.append('filter_type', 'multiple_ip');
            } else {
                params.append('login_method', currentFilters.filterType);
            }
        }

        $.ajax({
            url: `/user-activity/data?${params.toString()}`,
            method: 'GET',
            dataType: 'json',
            beforeSend: function () {
                $('#loginTable tbody').html(
                    '<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>'
                );
            },
            success: function (response) {
                if (response.success) {
                    loginData = response.data.activities || [];
                    filteredData = [...loginData];
                    updateDashboard(response.data.stats || {});
                    createCharts();
                    sortData();
                } else {
                    console.error('Error in response:', response.message);
                    $('#loginTable tbody').html(
                        '<tr><td colspan="8" class="text-center text-muted py-4">Error fetching data: ' + (response.message || 'Unknown error') + '</td></tr>'
                    );
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching dashboard data:', error);
                $('#loginTable tbody').html(
                    '<tr><td colspan="8" class="text-center text-muted py-4">Error fetching data: ' + (xhr.responseJSON?.message || 'Server error') + '</td></tr>'
                );
            },
        });
    }

    // Update dashboard stats
    function updateDashboard(stats) {
        $('#totalLogins').text(stats.total_logins || 0);
        $('#uniqueUsers').text(stats.unique_users || 0);
        $('#tokenLogins').text(stats.token_logins || 0);
        $('#uniqueIPs').text(stats.unique_ips || 0);
        $('#multiIPUsers').text(stats.multiple_ip_users || 0);
        $('#peakHourDisplay').text(stats.peak_hour ? `${stats.peak_hour}:00` : '-');

        // Update method chart stats
        const total = (stats.token_logins || 0) + (stats.normal_logins || 0);
        $('#tokenCount').text(stats.token_logins || 0);
        $('#normalCount').text(stats.normal_logins || 0);
        $('#tokenPercentage').text(total ? ((stats.token_logins / total) * 100).toFixed(1) + '%' : '0%');
        $('#normalPercentage').text(total ? ((stats.normal_logins / total) * 100).toFixed(1) + '%' : '0%');

        // Update hourly chart stats
        const morningLogins = stats.hourly_counts ? Object.values(stats.hourly_counts).slice(6, 12).reduce((sum, val) => sum + val, 0) : 0;
        const afternoonLogins = stats.hourly_counts ? Object.values(stats.hourly_counts).slice(12, 18).reduce((sum, val) => sum + val, 0) : 0;
        const eveningLogins = stats.hourly_counts ? Object.values(stats.hourly_counts).slice(18, 24).reduce((sum, val) => sum + val, 0) : 0;
        $('#morningLogins').text(morningLogins);
        $('#afternoonLogins').text(afternoonLogins);
        $('#eveningLogins').text(eveningLogins);

        // Update daily chart stats
        const dailyCounts = stats.daily_counts || {};
        const dates = Object.keys(dailyCounts);
        const totalLogins = Object.values(dailyCounts).reduce((sum, val) => sum + val, 0);
        const highestDay = dates.length ? dates.reduce((a, b) => dailyCounts[a] > dailyCounts[b] ? a : b) : '--';
        $('#highestDay').text(highestDay !== '--' ? new Date(highestDay).toLocaleDateString() : '--');
        $('#totalDays').text(dates.length);
        $('#averagePerDay').text(dates.length ? (totalLogins / dates.length).toFixed(1) : 0);
        $('#growthTrend').text(calculateGrowthTrend(dailyCounts));
    }

    // Calculate growth trend
    function calculateGrowthTrend(dailyCounts) {
        const dates = Object.keys(dailyCounts).sort((a, b) => new Date(a) - new Date(b));
        if (dates.length < 2) return '-';
        const firstHalf = dates.slice(0, Math.floor(dates.length / 2)).reduce((sum, date) => sum + dailyCounts[date], 0);
        const secondHalf = dates.slice(Math.floor(dates.length / 2)).reduce((sum, date) => sum + dailyCounts[date], 0);
        const trend = ((secondHalf - firstHalf) / firstHalf) * 100;
        return isFinite(trend) ? (trend > 0 ? `+${trend.toFixed(1)}%` : `${trend.toFixed(1)}%`) : '-';
    }

    // Sort and update table
    function sortData() {
        const sortBy = $('#sortBy').val();

        filteredData.sort(function (a, b) {
            switch (sortBy) {
                case 'timestamp':
                    return new Date(b.timestamp) - new Date(a.timestamp);
                case 'user_id':
                    return (a.user_name || '').localeCompare(b.user_name || '');
                case 'login_method':
                    return (a.login_method || '').localeCompare(b.login_method || '');
                default:
                    return 0;
            }
        });

        updateTable();
    }

    function updateTable() {
        const tbody = $('#loginTable tbody');
        tbody.empty();

        if (!filteredData.length) {
            tbody.append(
                '<tr><td colspan="8" class="text-center text-muted py-4">No login data found for the selected filters</td></tr>'
            );
            return;
        }

        filteredData.forEach((login, index) => {
            const employeeId = login.employee_id && login.employee_id !== '-' ? login.employee_id : '--';
            const row = `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <div class="d-flex flex-column">
                            <span class="fw-bold">${login.user_name || '-'}</span>
                            <small class="text-muted">${login.user_email || '-'}</small>
                        </div>
                    </td>
                    <td>${employeeId}</td>
                    <td><code>${login.ip_address || '-'}</code></td>
                    <td>
                        <span class="badge ${
                            login.login_method === 'token'
                                ? 'bg-primary-subtle text-primary'
                                : 'bg-secondary-subtle text-secondary'
                        }">
                            ${
                                login.login_method
                                    ? login.login_method.charAt(0).toUpperCase() + login.login_method.slice(1)
                                    : '-'
                            }
                        </span>
                    </td>
                    <td>${
                        login.timestamp
                            ? new Date(login.timestamp).toLocaleString()
                            : '-'
                    }</td>
                    <td class="small text-muted" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;" title="${
                        login.user_agent || ''
                    }">
                        ${login.user_agent || '-'}
                    </td>
                    <td class="text-center">
                        <span class="badge ${login.is_success ? 'bg-success' : 'bg-danger'}">
                            ${login.is_success ? 'Success' : 'Failed'}
                        </span>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        // Update user ranking table
        updateUserRankingTable();
    }

    function updateUserRankingTable() {
        const tbody = $('#userRankingTable');
        tbody.empty();

        const userCounts = {};
        const userLastLogin = {};
        const userIPs = {};

        filteredData.forEach((login) => {
            const key = login.user_name || `User ${login.user_id || 'Unknown'}`;
            userCounts[key] = (userCounts[key] || 0) + 1;
            userLastLogin[key] = login.timestamp && (!userLastLogin[key] || new Date(login.timestamp) > new Date(userLastLogin[key]))
                ? login.timestamp
                : userLastLogin[key];
            userIPs[key] = userIPs[key] || new Set();
            if (login.ip_address) userIPs[key].add(login.ip_address);
        });

        const sortedUsers = Object.entries(userCounts)
            .sort(([, a], [, b]) => b - a)
            .slice(0, 10);

        sortedUsers.forEach(([user, count], index) => {
            const row = `
                <tr>
                    <td>${index + 1}</td>
                    <td>${user}</td>
                    <td>${count}</td>
                    <td>${userLastLogin[user] ? new Date(userLastLogin[user]).toLocaleString() : '-'}</td>
                    <td>${userIPs[user].size}</td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    // Chart creation
    function createCharts() {
        Object.values(charts).forEach((chart) => chart.destroy());
        charts = {};

        createHourlyChart();
        createMethodChart();
        createUserChart();
        createDailyChart();
    }

    function createHourlyChart() {
        const hourCounts = Array(24).fill(0);
        filteredData.forEach((login) => {
            if (login.timestamp) {
                const hour = new Date(login.timestamp).getHours();
                hourCounts[hour]++;
            }
        });

        const ctx = document.getElementById('hourlyChart').getContext('2d');
        charts.hourlyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: Array.from({ length: 24 }, (_, i) => `${i}:00`),
                datasets: [
                    {
                        label: 'Logins per Hour',
                        data: hourCounts,
                        borderColor: '#4BC0C0',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.4,
                        fill: true,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 },
                    },
                },
                plugins: {
                    legend: { display: true },
                    datalabels: { display: false },
                },
            },
        });
    }

    function createMethodChart() {
        const tokenCount = filteredData.filter((login) => login.login_method === 'token').length;
        const normalCount = filteredData.filter((login) => login.login_method === 'normal').length;

        const ctx = document.getElementById('methodChart').getContext('2d');
        charts.methodChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Token', 'Normal'],
                datasets: [
                    {
                        data: [tokenCount, normalCount],
                        backgroundColor: ['#36A2EB', '#FF6384'],
                        borderColor: ['#ffffff', '#ffffff'],
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    datalabels: {
                        color: '#fff',
                        formatter: (value, context) => {
                            const total = context.dataset.data.reduce((sum, val) => sum + val, 0);
                            return total ? `${((value / total) * 100).toFixed(1)}%` : '0%';
                        },
                    },
                },
            },
        });
    }

    function createUserChart() {
        const userCounts = {};
        filteredData.forEach((login) => {
            const key = login.user_name || `User ${login.user_id || 'Unknown'}`;
            userCounts[key] = (userCounts[key] || 0) + 1;
        });

        const sortedUsers = Object.entries(userCounts)
            .sort(([, a], [, b]) => b - a)
            .slice(0, 10);

        const ctx = document.getElementById('userChart').getContext('2d');
        charts.userChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: sortedUsers.map(([name]) => name),
                datasets: [
                    {
                        label: 'Logins',
                        data: sortedUsers.map(([, count]) => count),
                        backgroundColor: '#FFCE56',
                        borderColor: '#ffffff',
                        borderWidth: 1,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                },
                plugins: {
                    legend: { display: true },
                    datalabels: { display: false },
                },
            },
        });
    }

    function createDailyChart() {
        const dailyCounts = {};
        filteredData.forEach((login) => {
            if (login.timestamp) {
                const date = new Date(login.timestamp).toDateString();
                dailyCounts[date] = (dailyCounts[date] || 0) + 1;
            }
        });
        const sortedDates = Object.keys(dailyCounts).sort((a, b) => new Date(a) - new Date(b));

        const ctx = document.getElementById('dailyChart').getContext('2d');
        charts.dailyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: sortedDates.map((date) => new Date(date).toLocaleDateString()),
                datasets: [
                    {
                        label: 'Logins per Day',
                        data: sortedDates.map((date) => dailyCounts[date]),
                        borderColor: '#9966FF',
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        tension: 0.3,
                        fill: true,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                },
                plugins: {
                    legend: { display: true },
                    datalabels: { display: false },
                },
            },
        });
    }

    // Initialize
    updateSelectedUsersText();
    fetchDashboardData();
});