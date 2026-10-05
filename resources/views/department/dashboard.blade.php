@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('departments/css/departmentdashboard.css') }}">
@endpush

@section('content')
<!-- Welcome Banner -->
        <div class="welcome-banner card" style="margin-top: 24px;">
            <div class="banner-text">
                <span class="banner-date">Today</span>
                <h2>Welcome back, Department Personnel! 👋</h2>
                <p>Manage your department's equipment, monitor borrowing activities, and review requests efficiently.</p>
            </div>
            <!-- Styled Icon Graphic instead of PNG/JPG image -->
            <div class="welcome-banner-graphic" style="position: absolute; right: 40px; top: 50%; transform: translateY(-50%); font-size: 80px; color: rgba(56, 85, 133, 0.08); pointer-events: none;">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
        </div>

        <!-- Quick Stats Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">Quick Stats</h4>
            <div class="stats-scroll-container">
                <!-- Total Equipment -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Total Equipment</span>
                            <span class="quick-stat-card-value" id="statTotalEquipment">{{ $dashboard['stats']['totalEquipment'] ?? 0 }}</span>
                            <p class="quick-stat-card-desc">Items assigned to department</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-blue">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                    </div>
                </div>

                <!-- Pending Requests -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Pending Requests</span>
                            <span class="quick-stat-card-value" id="statPendingRequests">{{ $dashboard['stats']['pendingRequests'] ?? 0 }}</span>
                            <p class="quick-stat-card-desc">Awaiting review</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-orange">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                    </div>
                </div>

                <!-- Currently Borrowed -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Currently Borrowed</span>
                            <span class="quick-stat-card-value" id="statBorrowed">{{ $dashboard['stats']['borrowedEquipment'] ?? 0 }}</span>
                            <p class="quick-stat-card-desc">Equipment on active loan</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-green">
                            <i class="fa-solid fa-box"></i>
                        </div>
                    </div>
                </div>

                <!-- Overdue Items -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Overdue Items</span>
                            <span class="quick-stat-card-value" id="statOverdue">{{ $dashboard['stats']['overdueItems'] ?? 0 }}</span>
                            <p class="quick-stat-card-desc">Exceeded return date</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-red">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                </div>

                <!-- Department Users -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Department Users</span>
                            <span class="quick-stat-card-value" id="statDepartmentUsers">{{ $dashboard['stats']['departmentUsers'] ?? 0 }}</span>
                            <p class="quick-stat-card-desc">Registered students & faculty</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-indigo">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Borrow Requests Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">
                Recent Borrow Requests
                <a href="{{ route('department.requests') }}" class="view-all" style="margin-left: auto; font-size: 13px; font-weight: 600; color: var(--primary-color);">View All Requests &rarr;</a>
            </h4>
            <div class="table-container card admin-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Borrower Name</th>
                            <th>Equipment</th>
                            <th>Request Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="requestsTableBody">
                        @forelse ($dashboard['recentRequests'] ?? [] as $req)
                            <tr class="admin-table-row">
                                <td>#{{ $req['id'] }}</td>
                                <td>{{ $req['user'] }}</td>
                                <td>{{ $req['equipment'] }}</td>
                                <td>{{ $req['date'] }}</td>
                                <td>{{ $req['status'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                                    No recent borrow requests found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Overdue Alerts Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">
                <i class="fa-solid fa-triangle-exclamation warning-icon" style="color: #ef4444;"></i> Overdue Alerts
                <a href="{{ route('department.monitoring') }}" class="view-all" style="margin-left: auto; font-size: 13px; font-weight: 600; color: #ef4444;">View Monitoring &rarr;</a>
            </h4>
            <div class="table-container card admin-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Borrower Name</th>
                            <th>Equipment</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                        </tr>
                    </thead>
                    <tbody id="overdueTableBody">
                        @forelse ($dashboard['overdue'] ?? [] as $row)
                            <tr class="admin-table-row">
                                <td>{{ $row['user'] }}</td>
                                <td>{{ $row['equipment'] }}</td>
                                <td>{{ $row['dueDate'] }}</td>
                                <td><span class="days-late">{{ $row['daysLateText'] }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                                    No overdue items.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Upcoming Returns Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">
                Upcoming Returns
                <a href="{{ route('department.monitoring') }}" class="view-all" style="margin-left: auto; font-size: 13px; font-weight: 600; color: var(--success-color);">View Equipment Monitoring &rarr;</a>
            </h4>
            <div class="table-container card admin-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Borrower Name</th>
                            <th>Equipment</th>
                            <th>Due Date</th>
                            <th>Days Remaining</th>
                        </tr>
                    </thead>
                    <tbody id="upcomingTableBody">
                        @forelse ($dashboard['upcoming'] ?? [] as $row)
                            <tr class="admin-table-row">
                                <td>{{ $row['user'] }}</td>
                                <td>{{ $row['equipment'] }}</td>
                                <td>{{ $row['dueDate'] }}</td>
                                <td>
                                    @if ($row['statusType'] === 'today')
                                        <span class="text-danger"><i class="fa-solid fa-circle" style="font-size: 8px; margin-right: 6px; color: #ef4444;"></i>{{ $row['daysRemainingText'] }}</span>
                                    @elseif ($row['statusType'] === 'tomorrow')
                                        <span style="color: #f59e0b; font-weight: 600;"><i class="fa-solid fa-circle" style="font-size: 8px; margin-right: 6px; color: #f59e0b;"></i>{{ $row['daysRemainingText'] }}</span>
                                    @else
                                        <span style="color: #10b981; font-weight: 600;"><i class="fa-solid fa-circle" style="font-size: 8px; margin-right: 6px; color: #10b981;"></i>{{ $row['daysRemainingText'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                                    No upcoming returns scheduled.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
<!-- Interactivity -->
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const bannerDate = document.querySelector('.banner-date');
        if (bannerDate) {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            bannerDate.textContent = new Date().toLocaleDateString('en-US', options);
        }
    });

    const URL_DATA = @json(route('department.dashboard.data'));
    const REFRESH_MS = 10000;

    let dashboard = @json($dashboard ?? []);

    function escapeHTML(str) {
        if (!str && str !== 0) return '';
        return String(str).replace(/[&<>'"]/g,
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = (value === null || value === undefined) ? 0 : value;
    }

    function renderStats(stats) {
        setText('statTotalEquipment', stats.totalEquipment);
        setText('statPendingRequests', stats.pendingRequests);
        setText('statBorrowed', stats.borrowedEquipment);
        setText('statOverdue', stats.overdueItems);
        setText('statDepartmentUsers', stats.departmentUsers);

        const eqCard = document.getElementById('statTotalEquipment');
        if (eqCard) {
            eqCard.title = (stats.totalEquipmentUnits || 0) + ' units in total, ' +
                (stats.availableUnits || 0) + ' currently available.';
        }
    }

    function renderRecentRequests(rows) {
        const tableBody = document.getElementById('requestsTableBody');
        if (!tableBody) return;

        tableBody.innerHTML = '';

        if (!rows.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                        No recent borrow requests found.
                    </td>
                </tr>
            `;
            return;
        }

        rows.forEach(req => {
            const tr = document.createElement('tr');
            tr.className = 'admin-table-row';
            tr.innerHTML = `
                <td>#${escapeHTML(req.id)}</td>
                <td>${escapeHTML(req.user)}</td>
                <td>${escapeHTML(req.equipment)}</td>
                <td>${escapeHTML(req.date)}</td>
                <td>${escapeHTML(req.status)}</td>
            `;
            tableBody.appendChild(tr);
        });
    }

    function renderOverdue(rows) {
        const tableBody = document.getElementById('overdueTableBody');
        if (!tableBody) return;

        tableBody.innerHTML = '';

        if (!rows.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                        No overdue items.
                    </td>
                </tr>
            `;
            return;
        }

        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.className = 'admin-table-row';
            tr.innerHTML = `
                <td>${escapeHTML(row.user)}</td>
                <td>${escapeHTML(row.equipment)}</td>
                <td>${escapeHTML(row.dueDate)}</td>
                <td><span class="days-late">${escapeHTML(row.daysLateText)}</span></td>
            `;
            tableBody.appendChild(tr);
        });
    }

    function renderUpcoming(rows) {
        const tableBody = document.getElementById('upcomingTableBody');
        if (!tableBody) return;

        tableBody.innerHTML = '';

        if (!rows.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                        No upcoming returns scheduled.
                    </td>
                </tr>
            `;
            return;
        }

        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.className = 'admin-table-row';

            let daysBadge = '';
            if (row.statusType === 'today') {
                daysBadge = `<span class="text-danger"><i class="fa-solid fa-circle" style="font-size: 8px; margin-right: 6px; color: #ef4444;"></i>${escapeHTML(row.daysRemainingText)}</span>`;
            } else if (row.statusType === 'tomorrow') {
                daysBadge = `<span style="color: #f59e0b; font-weight: 600;"><i class="fa-solid fa-circle" style="font-size: 8px; margin-right: 6px; color: #f59e0b;"></i>${escapeHTML(row.daysRemainingText)}</span>`;
            } else {
                daysBadge = `<span style="color: #10b981; font-weight: 600;"><i class="fa-solid fa-circle" style="font-size: 8px; margin-right: 6px; color: #10b981;"></i>${escapeHTML(row.daysRemainingText)}</span>`;
            }

            tr.innerHTML = `
                <td>${escapeHTML(row.user)}</td>
                <td>${escapeHTML(row.equipment)}</td>
                <td>${escapeHTML(row.dueDate)}</td>
                <td>${daysBadge}</td>
            `;
            tableBody.appendChild(tr);
        });
    }

    function applyDashboard(payload) {
        if (!payload) return;
        renderStats(payload.stats || {});
        renderRecentRequests(payload.recentRequests || []);
        renderOverdue(payload.overdue || []);
        renderUpcoming(payload.upcoming || []);
    }

    function refreshDashboard() {
        return fetch(URL_DATA, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    applyDashboard(data);
                }
                return data;
            })
            .catch(err => console.error('Error loading department dashboard data:', err));
    }

    // First paint from server payload, then poll in background and on visibility change
    applyDashboard(dashboard);

    setInterval(refreshDashboard, REFRESH_MS);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshDashboard();
        }
    });

    // Notification bell (placeholder)
    const notifBtn = document.getElementById('notifBtn');
    if (notifBtn) {
        notifBtn.addEventListener('click', () => {
            alert('No new notifications yet.');
        });
    }
    
</script>
@endpush
