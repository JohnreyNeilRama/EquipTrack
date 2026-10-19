@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
{{-- Recent Borrow Requests: same table, badges, buttons and details modal as the Borrow Requests page --}}
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminrequests.css') }}">
@include('partials.requests.styles')
<link rel="stylesheet" href="{{ asset('departments/css/departmentdashboard.css') }}">
<style>
    /* Recent Borrow Requests: long user names end in "…" (full name on hover
       and in the details modal), so one name can't stretch the table. */
    .recent-requests-table .col-user {
        width: 22%;
        min-width: 120px;
        max-width: 0;
    }
    .recent-requests-table .requester-name {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .recent-requests-table .col-request-id {
        font-weight: 600;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    /* Seven columns: tighter side padding (same as the Return Confirmation
       table) so Approve + Reject fit without pushing the table off the card. */
    .admin-table-card .recent-requests-table th,
    .admin-table-card .recent-requests-table td {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
    .admin-table-card .recent-requests-table th:first-child,
    .admin-table-card .recent-requests-table td:first-child {
        padding-left: 20px !important;
    }
    /* View button: same light / dark styling as the other Department tables */
    .recent-requests-table .btn-view-only {
        background-color: rgba(56, 85, 133, 0.08);
        border: 1px solid rgba(56, 85, 133, 0.35);
        color: #385585;
    }
    .recent-requests-table .btn-view-only:hover {
        background-color: #385585;
        border-color: #385585;
        color: #ffffff;
    }
    .recent-requests-table .btn-view-only:focus-visible {
        outline: 2px solid #385585;
        outline-offset: 2px;
    }
    .dark-theme .recent-requests-table .btn-view-only {
        background-color: #334155;
        border-color: #475569;
        color: #93c5fd;
    }
    .dark-theme .recent-requests-table .btn-view-only:hover {
        background-color: #2563eb;
        border-color: #2563eb;
        color: #ffffff;
    }
    .dark-theme .recent-requests-table .btn-view-only:focus-visible {
        outline-color: #60a5fa;
    }
    /* Short fixed-format values stay on one line: the headers and the Date */
    .recent-requests-table th,
    .recent-requests-table td.col-date {
        white-space: nowrap;
    }
</style>
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
            {{-- Same columns as the Borrow Requests table, plus Request ID, with the
                 role under the user's name (Return Confirmation style); rendered,
                 reviewed and kept current by the shared Requests script below. --}}
            <div class="table-container card admin-table-card">
                <table class="recent-requests-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th class="col-user">User</th>
                            <th>Equipment</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="action-column" style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="requestsTableBody">
                        {{-- Loaded dynamically via JavaScript --}}
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
                            <th>User</th>
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
                            <th>User</th>
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

    {{-- Details / Reject modals shared with the Borrow Requests page --}}
    @include('partials.requests.modals')

    {{-- Toast for approve / reject results (used by showNotification) --}}
    <div class="toast-notification" id="toast">
        <div class="toast-content">
            <i class="fa-solid fa-circle-check toast-icon" id="toastIcon"></i>
            <div class="toast-message">
                <span class="toast-title" id="toastTitle">Success</span>
                <span class="toast-desc" id="toastMsg">Action processed successfully!</span>
            </div>
        </div>
    </div>
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
        // Recent Borrow Requests is rendered and refreshed by the shared Requests script
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
{{-- Recent Borrow Requests: the Borrow Requests table script in compact mode --}}
@include('partials.requests.script', [
    'party' => 'dept',
    'dataUrl' => route('department.requests.data'),
    'updateUrl' => route('department.requests.update'),
    'limit' => $recentRequestsLimit,
    'showRequestId' => true,
    'combineUserRole' => true,
    'stackEquipmentTag' => true,
])
@endpush
