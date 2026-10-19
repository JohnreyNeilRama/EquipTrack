@extends('layouts.admin')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
{{-- Recent Requests: same table, badges, buttons and details modal as the Requests page --}}
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminrequests.css') }}">
@include('partials.requests.styles')
@endpush

@section('content')
<!-- Welcome Banner -->
        <div class="welcome-banner card" style="margin-top: 24px;">
            <div class="welcome-text">
                <span class="banner-date">Today</span>
                <h3>Welcome back, {{ auth('admin')->user()->name ?? '' }}!</h3>
                <p>Here's an overview of equipment requests, borrowings, and system activity.</p>
            </div>
            <img src="{{ asset('images/admin_design1.png') }}" alt="Admin Illustration" class="welcome-banner-img">
        </div>

        <!-- Quick Stats Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">Quick Stats</h4>
            <div class="stats-scroll-container">
                <!-- Card 1 – Total Users -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Total Users</span>
                            <span class="quick-stat-card-value" id="statTotalUsers">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-blue">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 2 – Total Equipment -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Total Equipment</span>
                            <span class="quick-stat-card-value" id="statTotalEq">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-indigo">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 3 – Pending Requests -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Pending Requests</span>
                            <span class="quick-stat-card-value" id="statPending">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-orange">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 4 – Borrowed Equipment -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Borrowed Equipment</span>
                            <span class="quick-stat-card-value" id="statBorrowed">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-green">
                            <i class="fa-solid fa-box"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 5 – Overdue Items -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Overdue Items</span>
                            <span class="quick-stat-card-value" id="statOverdue">0</span>
                        </div>
                        <div class="quick-stat-icon-wrapper color-red">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Requests Section -->
        <div class="admin-section">
            <div class="dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-title">
                        <i class="fa-solid fa-clipboard-list" style="color: var(--primary-color);"></i> Recent Requests
                    </div>
                </div>
                {{-- Same compact table as the Requests page (Request ID, user with role
                     underneath); rendered, reviewed and kept current by the shared
                     Requests script below. --}}
                <div class="table-container admin-table-card">
                    <table class="requests-table-compact" style="width: 100%;">
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
                            <!-- Loaded dynamically via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Overdue Alerts Section -->
        <div class="admin-section">
            <div class="dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-title">
                        <i class="fa-solid fa-triangle-exclamation warning-icon" style="color: #fbbf24;"></i> Overdue Alerts
                    </div>
                </div>
                <div class="table-container admin-table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Equipment</th>
                                <th>Due Date</th>
                                <th>Days Late</th>
                            </tr>
                        </thead>
                        <tbody id="overdueTableBody">
                            <!-- Loaded dynamically via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Two Column Bottom Section: Low Stock & System Summary -->
        <div class="bottom-grid">
            <!-- Left Column: Low Stock -->
            <div class="admin-section flex-section">
                <h4 class="admin-section-heading">Low Stock</h4>
                <div class="low-stock-card card flex-table" id="lowStockCard">
                    <div style="text-align: center; color: var(--text-muted); padding: 24px 16px;">
                        <i class="fa-solid fa-box-open" style="color: var(--text-muted); font-size: 20px; margin-bottom: 8px; display: block;"></i>
                        No low stock equipment.
                    </div>
                </div>
            </div>

            <!-- Right Column: System Summary -->
            <div class="admin-section flex-section">
                <h4 class="admin-section-heading">System Summary</h4>
                <div class="system-summary-card card">
                    <div class="summary-hero">
                        <div class="summary-hero-meta">
                            <span class="summary-hero-label">Total Requests</span>
                            <p class="summary-hero-desc">Total borrowing activity processed</p>
                        </div>
                        <span class="summary-hero-value" id="summaryTotalCount">0</span>
                    </div>
                    
                    <div class="summary-segment-bar" id="summarySegmentBar">
                        <div class="segment-fill approved" style="width: 0%;"></div>
                        <div class="segment-fill rejected" style="width: 0%;"></div>
                        <div class="segment-fill pending" style="width: 0%;"></div>
                    </div>
                    
                    <div class="summary-details-grid">
                        <div class="summary-detail-item">
                            <span class="detail-dot approved"></span>
                            <div class="detail-meta">
                                <span class="detail-label">Approved</span>
                                <span class="detail-value" id="summaryApprovedCount">0</span>
                            </div>
                        </div>
                        <div class="summary-detail-item">
                            <span class="detail-dot rejected"></span>
                            <div class="detail-meta">
                                <span class="detail-label">Rejected</span>
                                <span class="detail-value" id="summaryRejectedCount">0</span>
                            </div>
                        </div>
                        <div class="summary-detail-item">
                            <span class="detail-dot pending"></span>
                            <div class="detail-meta">
                                <span class="detail-label">Pending</span>
                                <span class="detail-value" id="summaryPendingCount">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<!-- Success Toast Notification -->
    {{-- Details / Reject modals shared with the Requests page --}}
    @include('partials.requests.modals')

    <div class="toast-notification" id="toast">
        <div class="toast-content">
            <i class="fa-solid fa-circle-check toast-icon" id="toastIcon"></i>
            <div class="toast-message">
                <span class="toast-title" id="toastTitle">Success</span>
                <span class="toast-desc" id="toastMsg">Action processed successfully!</span>
            </div>
        </div>
    </div>

    <!-- Scripting for Toggles and Interactivity -->
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

        // ---------------------------------------------------------------------
        // Live dashboard data
        //
        // Every figure and table below is rendered from the database through the
        // admin dashboard endpoints (equipment, borrow requests, loan
        // transactions and accounts). Nothing is read from localStorage, so new
        // requests, approvals, borrows, returns and stock changes are always
        // reflected. `showNotification` comes from the shared admin shell.
        // ---------------------------------------------------------------------
        const URL_DATA = @json(route('admin.dashboard.data'));
        const REFRESH_MS = 30000;

        // Rendered by the server on first paint so the page never flashes empty
        // states, then kept current by refreshDashboard().
        let dashboard = @json($dashboard);

        function applyDashboard(payload) {
            dashboard = payload;
            renderStats(payload.stats || {});
            renderSummary(payload.summary || {});
            // Recent Requests is rendered and refreshed by the shared Requests script
            renderOverdue(payload.overdue || []);
            renderLowStock(payload.lowStock || []);
        }

        // Keeps the dashboard in step with the system without a manual reload.
        function refreshDashboard() {
            return fetch(URL_DATA, { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        applyDashboard(data);
                    }
                    return data;
                })
                .catch(err => console.error('Error loading dashboard data:', err));
        }

        function setText(id, value) {
            const el = document.getElementById(id);
            if (el) el.textContent = (value === null || value === undefined) ? 0 : value;
        }

        // Quick Stats cards
        function renderStats(stats) {
            setText('statTotalUsers', stats.totalUsers);
            setText('statTotalEq', stats.totalEquipment);
            setText('statPending', stats.pendingRequests);
            setText('statBorrowed', stats.borrowedEquipment);
            setText('statOverdue', stats.overdueItems);

            // The card shows the number of registered equipment records; the
            // tooltip carries the unit totals so both figures stay available.
            const eqCard = document.getElementById('statTotalEq');
            if (eqCard) {
                eqCard.title = (stats.totalEquipmentUnits || 0) + ' units in total, ' +
                    (stats.totalAvailableUnits || 0) + ' currently available.';
            }
        }

        // System Summary card
        function renderSummary(summary) {
            setText('summaryTotalCount', summary.total);
            setText('summaryApprovedCount', summary.approved);
            setText('summaryRejectedCount', summary.rejected);
            setText('summaryPendingCount', summary.pending);

            const total = summary.total || 0;
            const pct = value => total > 0 ? ((value || 0) / total) * 100 : 0;
            const bar = document.getElementById('summarySegmentBar');
            if (bar) {
                bar.innerHTML = `
                    <div class="segment-fill approved" style="width: ${pct(summary.approved)}%;"></div>
                    <div class="segment-fill rejected" style="width: ${pct(summary.rejected)}%;"></div>
                    <div class="segment-fill pending" style="width: ${pct(summary.pending)}%;"></div>
                `;
            }
        }

        // Overdue Alerts table
        function renderOverdue(rows) {
            const tableBody = document.getElementById('overdueTableBody');
            if (!tableBody) return;

            tableBody.innerHTML = '';

            if (!rows.length) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            <i class="fa-solid fa-check-circle" style="color: #10b981; font-size: 20px; margin-bottom: 8px; display: block;"></i>
                            No overdue items recorded.
                        </td>
                    </tr>
                `;
                return;
            }

            rows.forEach(item => {
                const tr = document.createElement('tr');
                tr.className = 'admin-table-row';
                tr.innerHTML = `
                    <td>${escapeHTML(item.user)}</td>
                    <td>${escapeHTML(item.equipment)}</td>
                    <td>${escapeHTML(item.dueDate)}</td>
                    <td><span class="days-late">${escapeHTML(item.daysLate)}</span></td>
                `;
                tableBody.appendChild(tr);
            });
        }

        // Low Stock list
        function renderLowStock(rows) {
            const container = document.getElementById('lowStockCard');
            if (!container) return;

            if (!rows.length) {
                container.innerHTML = `
                    <div style="text-align: center; color: var(--text-muted); padding: 24px 16px;">
                        <i class="fa-solid fa-box-open" style="color: var(--text-muted); font-size: 20px; margin-bottom: 8px; display: block;"></i>
                        No low stock equipment.
                    </div>
                `;
                return;
            }

            container.innerHTML = rows.map((item, index) => `
                ${index > 0 ? '<div class="stock-item-divider"></div>' : ''}
                <div class="stock-item-row">
                    <div class="stock-item-left">
                        <div class="stock-item-icon-box ${item.critical ? 'critical' : 'warning'}">
                            <i class="fa-solid ${item.critical ? 'fa-circle-exclamation' : 'fa-triangle-exclamation'}"></i>
                        </div>
                        <div class="stock-item-meta">
                            <span class="stock-item-name">${escapeHTML(item.name)}</span>
                            <span class="stock-status-label ${item.critical ? 'text-critical' : 'text-warning'}">${escapeHTML(item.label)} &middot; ${escapeHTML(item.category)}</span>
                        </div>
                    </div>
                    <span class="stock-pill ${item.critical ? 'pill-critical' : 'pill-warning'}">${item.available} / ${item.total}</span>
                </div>
            `).join('');
        }


        // Helper to escape HTML values
        function escapeHTML(value) {
            if (value === null || value === undefined) return '';
            return String(value).replace(/[&<>'"]/g,
                tag => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                }[tag] || tag)
            );
        }

        // First paint from the server payload, then stay live: poll in the
        // background and refresh whenever the tab regains focus.
        applyDashboard(dashboard);

        setInterval(refreshDashboard, REFRESH_MS);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                refreshDashboard();
            }
        });
</script>
{{-- Recent Requests: the Requests table script in compact mode --}}
@include('partials.requests.script', [
    'party' => 'admin',
    'dataUrl' => route('admin.requests.data'),
    'updateUrl' => route('admin.requests.update'),
    'limit' => $recentRequestsLimit,
    'showRequestId' => true,
    'combineUserRole' => true,
    'stackEquipmentTag' => true,
])
@endpush
