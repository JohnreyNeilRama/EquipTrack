@extends('layouts.user')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('user/css/userdashboard.css') }}">
@endpush

@php
    $stats = $dashboard['stats'] ?? [];
    $activeBorrows = $dashboard['activeBorrows'] ?? [];
    $recentRequests = $dashboard['recentRequests'] ?? [];
    $reminders = $dashboard['reminders'] ?? [];
@endphp

@section('content')
<div class="dashboard-container">

    <!-- Welcome Banner -->
    <div class="welcome-banner card">
        <div class="banner-text">
            <span class="banner-date">Today</span>
            <h2>Welcome back, {{ auth('user')->user()?->student?->first_name ?? auth('user')->user()?->facultyMember?->first_name ?? 'User' }}!</h2>
            <p>Here's your equipment activity overview for today.</p>
        </div>
        <img src="{{ asset('images/user_design1.png') }}" alt="User Illustration" class="banner-img">
    </div>

    <!-- Shown only when a background refresh fails; the last loaded data stays on screen -->
    <div class="dash-refresh-error" id="dashRefreshError" role="status" hidden>
        <i class="fa-solid fa-circle-exclamation"></i>
        <span>Couldn't refresh your dashboard. Showing the last loaded data; we'll keep trying.</span>
    </div>

    <!-- Quick Stats -->
    <div class="dashboard-section">
        <div class="section-header">
            <h3 class="section-title">Quick Stats</h3>
        </div>
        <div class="stats-grid">
            <div class="stat-card card">
                <div class="stat-details">
                    <div class="stat-title">Active Borrows</div>
                    <div class="stat-value" id="statActiveBorrows">{{ (int) ($stats['activeBorrows'] ?? 0) }}</div>
                    <div class="stat-desc">Items currently with you</div>
                </div>
                <div class="stat-icon info"><i class="fa-solid fa-box-open"></i></div>
            </div>
            <div class="stat-card card">
                <div class="stat-details">
                    <div class="stat-title">Pending Requests</div>
                    <div class="stat-value" id="statPendingRequests">{{ (int) ($stats['pendingRequests'] ?? 0) }}</div>
                    <div class="stat-desc">Waiting for approval</div>
                </div>
                <div class="stat-icon warning"><i class="fa-solid fa-clock-rotate-left"></i></div>
            </div>
            <div class="stat-card card">
                <div class="stat-details">
                    <div class="stat-title">Overdue Items</div>
                    <div class="stat-value" id="statOverdueItems">{{ (int) ($stats['overdueItems'] ?? 0) }}</div>
                    <div class="stat-desc">Past their return date</div>
                </div>
                <div class="stat-icon danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
            </div>
        </div>
    </div>

    <div class="dashboard-body">
        <!-- Main column: activity tables -->
        <div class="dashboard-main">
            <div class="dashboard-section">
                <div class="section-header">
                    <h3 class="section-title">Active Borrow Items</h3>
                    <a href="{{ route('user.returns') }}" class="view-all">View All</a>
                </div>
                <div class="table-container card">
                    <table>
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Borrow Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="activeBorrowsBody">
                            @forelse ($activeBorrows as $row)
                                <tr>
                                    <td>{{ $row['equipment'] }}</td>
                                    <td>{{ $row['borrowDate'] }}</td>
                                    <td>{{ $row['dueDate'] }}</td>
                                    <td><span class="dash-badge dash-badge-{{ strtolower($row['status']) }}">{{ $row['status'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="table-empty">
                                        <div class="empty-state">
                                            <i class="fa-solid fa-box-open"></i>
                                            <span>No active borrow items.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="section-header">
                    <h3 class="section-title">Recent Requests</h3>
                    <a href="{{ route('user.requests') }}" class="view-all">View All</a>
                </div>
                <div class="table-container card">
                    <table>
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="recentRequestsBody">
                            @forelse ($recentRequests as $row)
                                <tr>
                                    <td>{{ $row['equipment'] }}</td>
                                    <td>{{ $row['date'] }}</td>
                                    <td><span class="dash-badge dash-badge-{{ strtolower($row['status']) }}">{{ $row['status'] }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="table-empty">
                                        <div class="empty-state">
                                            <i class="fa-solid fa-clipboard-list"></i>
                                            <span>No recent requests found.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Side column: shortcuts and reminders -->
        <div class="dashboard-aside">
            <div class="dashboard-section">
                <div class="section-header">
                    <h3 class="section-title">Quick Actions</h3>
                </div>
                <div class="action-card card">
                    <div class="action-grid">
                        <a href="{{ route('user.equipment') }}" class="action-btn">
                            <div class="action-icon">
                                <i class="fa-solid fa-desktop"></i>
                            </div>
                            <div class="action-text">
                                <span class="action-label">Browse Equipment</span>
                                <span class="action-desc">Explore available items</span>
                            </div>
                            <i class="fa-solid fa-chevron-right action-arrow"></i>
                        </a>
                        <a href="{{ route('user.equipment') }}" class="action-btn">
                            <div class="action-icon">
                                <i class="fa-solid fa-cart-plus"></i>
                            </div>
                            <div class="action-text">
                                <span class="action-label">Request Equipment</span>
                                <span class="action-desc">Borrow items for your use</span>
                            </div>
                            <i class="fa-solid fa-chevron-right action-arrow"></i>
                        </a>
                        <a href="{{ route('user.requests') }}" class="action-btn">
                            <div class="action-icon">
                                <i class="fa-solid fa-list-check"></i>
                            </div>
                            <div class="action-text">
                                <span class="action-label">View My Requests</span>
                                <span class="action-desc">Check your current status</span>
                            </div>
                            <i class="fa-solid fa-chevron-right action-arrow"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="section-header">
                    <h3 class="section-title">Reminders</h3>
                </div>
                <div class="reminder-card card">
                    <ul class="reminder-list" id="reminderList">
                        @forelse ($reminders as $reminder)
                            <li class="reminder-item {{ $reminder['type'] }}">
                                <div class="reminder-icon">
                                    <i class="fa-solid {{ $reminder['type'] === 'danger' ? 'fa-triangle-exclamation' : 'fa-clock' }}"></i>
                                </div>
                                <div class="reminder-content">
                                    <span class="reminder-title">{{ $reminder['title'] }}</span>
                                    <span class="reminder-desc">{{ $reminder['message'] }}</span>
                                </div>
                            </li>
                        @empty
                            <li class="reminder-item reminder-empty">
                                <i class="fa-regular fa-bell"></i>
                                <span>No active reminders at this time.</span>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
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

    (function () {
        const URL_DATA = @json(route('user.dashboard.data'));
        const URL_LOGIN = @json(route('login'));
        const REFRESH_MS = 10000;

        const errorBox = document.getElementById('dashRefreshError');
        let inFlight = false;

        function escapeHTML(value) {
            if (value === null || value === undefined) return '';
            return String(value).replace(/[&<>'"]/g, tag => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            }[tag] || tag));
        }

        function badge(status) {
            const label = String(status || 'Pending');
            return '<span class="dash-badge dash-badge-' + escapeHTML(label.toLowerCase()) + '">' + escapeHTML(label) + '</span>';
        }

        function setText(id, value) {
            const el = document.getElementById(id);
            if (el) el.textContent = Number.isFinite(Number(value)) ? Number(value) : 0;
        }

        function emptyRow(cols, icon, text) {
            return '<tr><td colspan="' + cols + '" class="table-empty"><div class="empty-state">'
                + '<i class="fa-solid ' + icon + '"></i><span>' + text + '</span></div></td></tr>';
        }

        function renderStats(stats) {
            setText('statActiveBorrows', stats.activeBorrows);
            setText('statPendingRequests', stats.pendingRequests);
            setText('statOverdueItems', stats.overdueItems);
        }

        function renderActiveBorrows(rows) {
            const body = document.getElementById('activeBorrowsBody');
            if (!body) return;

            body.innerHTML = rows.length
                ? rows.map(row => '<tr>'
                    + '<td>' + escapeHTML(row.equipment) + '</td>'
                    + '<td>' + escapeHTML(row.borrowDate) + '</td>'
                    + '<td>' + escapeHTML(row.dueDate) + '</td>'
                    + '<td>' + badge(row.status) + '</td>'
                    + '</tr>').join('')
                : emptyRow(4, 'fa-box-open', 'No active borrow items.');
        }

        function renderRecentRequests(rows) {
            const body = document.getElementById('recentRequestsBody');
            if (!body) return;

            body.innerHTML = rows.length
                ? rows.map(row => '<tr>'
                    + '<td>' + escapeHTML(row.equipment) + '</td>'
                    + '<td>' + escapeHTML(row.date) + '</td>'
                    + '<td>' + badge(row.status) + '</td>'
                    + '</tr>').join('')
                : emptyRow(3, 'fa-clipboard-list', 'No recent requests found.');
        }

        function renderReminders(rows) {
            const list = document.getElementById('reminderList');
            if (!list) return;

            list.innerHTML = rows.length
                ? rows.map(row => {
                    const type = row.type === 'danger' ? 'danger' : 'warning';
                    const icon = type === 'danger' ? 'fa-triangle-exclamation' : 'fa-clock';
                    return '<li class="reminder-item ' + type + '">'
                        + '<div class="reminder-icon"><i class="fa-solid ' + icon + '"></i></div>'
                        + '<div class="reminder-content">'
                        + '<span class="reminder-title">' + escapeHTML(row.title) + '</span>'
                        + '<span class="reminder-desc">' + escapeHTML(row.message) + '</span>'
                        + '</div></li>';
                }).join('')
                : '<li class="reminder-item reminder-empty"><i class="fa-regular fa-bell"></i>'
                    + '<span>No active reminders at this time.</span></li>';
        }

        function applyDashboard(payload) {
            renderStats(payload.stats || {});
            renderActiveBorrows(payload.activeBorrows || []);
            renderRecentRequests(payload.recentRequests || []);
            renderReminders(payload.reminders || []);
        }

        function showError(show) {
            if (errorBox) errorBox.hidden = !show;
        }

        function refreshDashboard() {
            if (inFlight) return Promise.resolve();
            inFlight = true;

            return fetch(URL_DATA, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store'
            })
                .then(res => {
                    // Session expired or the account was deactivated: go back to login.
                    if (res.status === 401 || res.status === 419 || res.redirected) {
                        window.location.href = URL_LOGIN;
                        return null;
                    }
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(data => {
                    if (!data) return;
                    if (!data.success) throw new Error('Unsuccessful response');
                    applyDashboard(data);
                    showError(false);
                })
                .catch(err => {
                    console.error('Error refreshing dashboard:', err);
                    showError(true);
                })
                .finally(() => { inFlight = false; });
        }

        // The first paint is rendered by the server; keep it fresh in the background.
        setInterval(() => {
            if (document.visibilityState === 'visible') refreshDashboard();
        }, REFRESH_MS);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') refreshDashboard();
        });
    })();
</script>
@endpush
