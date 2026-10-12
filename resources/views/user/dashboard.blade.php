@extends('layouts.user')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('user/css/usertable.css') }}?v={{ filemtime(public_path('user/css/usertable.css')) }}">
<link rel="stylesheet" href="{{ asset('user/css/userdashboard.css') }}?v={{ filemtime(public_path('user/css/userdashboard.css')) }}">
@endpush

@php
    $stats = $dashboard['stats'] ?? [];
    $activeBorrows = $dashboard['activeBorrows'] ?? [];
    $recentRequests = $dashboard['recentRequests'] ?? [];
    $reminders = $dashboard['reminders'] ?? [];

    $activeCount = (int) ($stats['activeBorrows'] ?? 0);
    $pendingCount = (int) ($stats['pendingRequests'] ?? 0);
    $overdueCount = (int) ($stats['overdueItems'] ?? 0);

    // Status badge of the My Requests table ("Pending Confirmation" -> status-pending-confirmation).
    $badgeClass = fn (string $status) => 'detail-status-badge status-' . \Illuminate\Support\Str::slug($status);

    // One-line activity summary under the greeting (the script keeps it in sync).
    $plural = fn (int $n, string $word) => ($n === 0 ? 'no' : $n) . ' ' . \Illuminate\Support\Str::plural($word, $n);
    if ($overdueCount > 0) {
        $summary = $plural($overdueCount, 'item') . ($overdueCount === 1 ? ' is' : ' are') . ' past the due date. Please return ' . ($overdueCount === 1 ? 'it' : 'them') . ' as soon as possible.';
    } elseif ($activeCount > 0 || $pendingCount > 0) {
        $summary = 'You have ' . $plural($activeCount, 'item') . ' on loan and ' . $plural($pendingCount, 'request') . ' waiting for approval.';
    } else {
        $summary = 'You have no equipment on loan. Browse the catalogue to request an item.';
    }
@endphp

@section('content')
<div class="dashboard-container">

    <!-- Welcome Banner -->
    <section class="welcome-banner card" aria-labelledby="welcomeTitle">
        <div class="banner-text">
            <span class="banner-date">Today</span>
            <h2 id="welcomeTitle">Welcome back, {{ auth('user')->user()?->student?->first_name ?? auth('user')->user()?->facultyMember?->first_name ?? 'User' }}!</h2>
            <p class="banner-summary {{ $overdueCount > 0 ? 'is-alert' : '' }}" id="dashSummary">{{ $summary }}</p>
        </div>
        <img src="{{ asset('images/user_design1.png') }}" alt="" class="banner-img">
    </section>

    <!-- Shown only when a background refresh fails; the last loaded data stays on screen -->
    <div class="dash-refresh-error" id="dashRefreshError" role="status" hidden>
        <i class="fa-solid fa-circle-exclamation"></i>
        <span>Couldn't refresh your dashboard. Showing the last loaded data; we'll keep trying.</span>
    </div>

    <!-- Quick Stats: each card opens the page with the details -->
    <section class="stats-grid" aria-label="Quick Stats">
        <a href="{{ route('user.returns') }}" class="stat-card card" data-tone="info">
            <div class="stat-head">
                <span class="stat-icon"><i class="fa-solid fa-box-open"></i></span>
                <span class="stat-title">Active Borrows</span>
            </div>
            <div class="stat-value" id="statActiveBorrows">{{ $activeCount }}</div>
            <div class="stat-foot">
                <span class="stat-desc">Items currently with you</span>
                <i class="fa-solid fa-arrow-right stat-arrow" aria-hidden="true"></i>
            </div>
        </a>
        <a href="{{ route('user.requests') }}" class="stat-card card" data-tone="warning">
            <div class="stat-head">
                <span class="stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
                <span class="stat-title">Pending Requests</span>
            </div>
            <div class="stat-value" id="statPendingRequests">{{ $pendingCount }}</div>
            <div class="stat-foot">
                <span class="stat-desc">Waiting for approval</span>
                <i class="fa-solid fa-arrow-right stat-arrow" aria-hidden="true"></i>
            </div>
        </a>
        <a href="{{ route('user.returns') }}" class="stat-card card {{ $overdueCount > 0 ? 'is-alert' : '' }}" data-tone="danger" id="statOverdueCard">
            <div class="stat-head">
                <span class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <span class="stat-title">Overdue Items</span>
            </div>
            <div class="stat-value" id="statOverdueItems">{{ $overdueCount }}</div>
            <div class="stat-foot">
                <span class="stat-desc">Past their return date</span>
                <i class="fa-solid fa-arrow-right stat-arrow" aria-hidden="true"></i>
            </div>
        </a>
    </section>

    <div class="dashboard-body">
        <!-- Main column: activity tables -->
        <div class="dashboard-main">
            <section class="dash-table-section" aria-labelledby="activeBorrowsTitle">
                <header class="panel-header">
                    <div class="panel-heading">
                        <h3 class="section-title" id="activeBorrowsTitle">Active Borrow Items</h3>
                        <span class="panel-count" id="activeBorrowsCount">{{ count($activeBorrows) }}</span>
                    </div>
                    <a href="{{ route('user.returns') }}" class="view-all">View All <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </header>
                {{-- The My Requests table component (user/css/usertable.css) --}}
                <div class="table-container card requests-table-card">
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
                                    <td class="equipment-col">
                                        <div class="eq-cell" style="display: flex; align-items: center; gap: 12px;">
                                            <img src="{{ $row['image'] }}" alt="{{ $row['equipment'] }}" class="eq-thumb" style="width: 42px; height: 42px; border-radius: 8px; object-fit: cover; background: #fff;" onerror="this.onerror=null; this.src='{{ asset('images/EquipTrack_logo.png') }}';">
                                            <div class="eq-info" style="display: flex; flex-direction: column;">
                                                <span class="eq-title" style="font-weight: 600; color: var(--text-main);">{{ $row['equipment'] }}</span>
                                                <span class="eq-sub" style="font-size: 12px; color: var(--text-muted);">{{ $row['category'] }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $row['borrowDate'] }}</td>
                                    <td>{{ $row['dueDate'] }}</td>
                                    <td><span class="{{ $badgeClass($row['status']) }}">{{ $row['status'] }}</span></td>
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
            </section>

            <section class="dash-table-section" aria-labelledby="recentRequestsTitle">
                <header class="panel-header">
                    <div class="panel-heading">
                        <h3 class="section-title" id="recentRequestsTitle">Recent Requests</h3>
                        <span class="panel-count" id="recentRequestsCount">{{ count($recentRequests) }}</span>
                    </div>
                    <a href="{{ route('user.requests') }}" class="view-all">View All <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </header>
                {{-- The My Requests table component (user/css/usertable.css) --}}
                <div class="table-container card requests-table-card">
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
                                    <td class="equipment-col">
                                        <div class="eq-cell" style="display: flex; align-items: center; gap: 12px;">
                                            <img src="{{ $row['image'] }}" alt="{{ $row['equipment'] }}" class="eq-thumb" style="width: 42px; height: 42px; border-radius: 8px; object-fit: cover; background: #fff;" onerror="this.onerror=null; this.src='{{ asset('images/EquipTrack_logo.png') }}';">
                                            <div class="eq-info" style="display: flex; flex-direction: column;">
                                                <span class="eq-title" style="font-weight: 600; color: var(--text-main);">{{ $row['equipment'] }}</span>
                                                <span class="eq-sub" style="font-size: 12px; color: var(--text-muted);">{{ $row['category'] }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $row['date'] }}</td>
                                    <td><span class="{{ $badgeClass($row['status']) }}">{{ $row['status'] }}</span></td>
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
            </section>
        </div>

        <!-- Side column: shortcuts and reminders -->
        <div class="dashboard-aside">
            <section class="dash-panel card" aria-labelledby="quickActionsTitle">
                <header class="panel-header">
                    <h3 class="section-title" id="quickActionsTitle">Quick Actions</h3>
                </header>
                <div class="action-grid">
                    <a href="{{ route('user.equipment') }}" class="action-btn">
                        <span class="action-icon"><i class="fa-solid fa-desktop"></i></span>
                        <span class="action-text">
                            <span class="action-label">Browse Equipment</span>
                            <span class="action-desc">Explore available items</span>
                        </span>
                        <i class="fa-solid fa-chevron-right action-arrow" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('user.equipment') }}" class="action-btn">
                        <span class="action-icon"><i class="fa-solid fa-cart-plus"></i></span>
                        <span class="action-text">
                            <span class="action-label">Request Equipment</span>
                            <span class="action-desc">Borrow items for your use</span>
                        </span>
                        <i class="fa-solid fa-chevron-right action-arrow" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('user.requests') }}" class="action-btn">
                        <span class="action-icon"><i class="fa-solid fa-list-check"></i></span>
                        <span class="action-text">
                            <span class="action-label">View My Requests</span>
                            <span class="action-desc">Check your current status</span>
                        </span>
                        <i class="fa-solid fa-chevron-right action-arrow" aria-hidden="true"></i>
                    </a>
                </div>
            </section>

            <section class="dash-panel card" aria-labelledby="remindersTitle">
                <header class="panel-header">
                    <div class="panel-heading">
                        <h3 class="section-title" id="remindersTitle">Reminders</h3>
                        <span class="panel-count {{ count($reminders) ? 'is-alert' : '' }}" id="remindersCount">{{ count($reminders) }}</span>
                    </div>
                </header>
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
            </section>
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

        // Status badge of the My Requests table, same as the server render
        // ("Pending Confirmation" -> status-pending-confirmation).
        function badge(status) {
            const label = String(status || 'Pending');
            const slug = label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            return '<span class="detail-status-badge status-' + escapeHTML(slug) + '">' + escapeHTML(label) + '</span>';
        }

        function plural(n, word) {
            return (n === 0 ? 'no' : n) + ' ' + word + (n === 1 ? '' : 's');
        }

        // Same sentence the server renders under the greeting.
        function summaryText(active, pending, overdue) {
            if (overdue > 0) {
                return plural(overdue, 'item') + (overdue === 1 ? ' is' : ' are')
                    + ' past the due date. Please return ' + (overdue === 1 ? 'it' : 'them') + ' as soon as possible.';
            }
            if (active > 0 || pending > 0) {
                return 'You have ' + plural(active, 'item') + ' on loan and ' + plural(pending, 'request') + ' waiting for approval.';
            }
            return 'You have no equipment on loan. Browse the catalogue to request an item.';
        }

        function setCount(id, value, alertWhenPositive) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = value;
            if (alertWhenPositive) el.classList.toggle('is-alert', value > 0);
        }

        function setText(id, value) {
            const el = document.getElementById(id);
            if (el) el.textContent = Number.isFinite(Number(value)) ? Number(value) : 0;
        }

        const FALLBACK_IMG = @json(asset('images/EquipTrack_logo.png'));

        // Photo + name + category, the same cell as the My Requests table.
        function equipmentCell(row) {
            return '<td class="equipment-col"><div class="eq-cell" style="display: flex; align-items: center; gap: 12px;">'
                + '<img src="' + escapeHTML(row.image || FALLBACK_IMG) + '" alt="' + escapeHTML(row.equipment) + '" class="eq-thumb"'
                + ' style="width: 42px; height: 42px; border-radius: 8px; object-fit: cover; background: #fff;"'
                + ' onerror="this.onerror=null; this.src=\'' + escapeHTML(FALLBACK_IMG) + '\';">'
                + '<div class="eq-info" style="display: flex; flex-direction: column;">'
                + '<span class="eq-title" style="font-weight: 600; color: var(--text-main);">' + escapeHTML(row.equipment) + '</span>'
                + '<span class="eq-sub" style="font-size: 12px; color: var(--text-muted);">' + escapeHTML(row.category || 'General') + '</span>'
                + '</div></div></td>';
        }

        function emptyRow(cols, icon, text) {
            return '<tr><td colspan="' + cols + '" class="table-empty"><div class="empty-state">'
                + '<i class="fa-solid ' + icon + '"></i><span>' + text + '</span></div></td></tr>';
        }

        function renderStats(stats) {
            const active = Number(stats.activeBorrows) || 0;
            const pending = Number(stats.pendingRequests) || 0;
            const overdue = Number(stats.overdueItems) || 0;

            setText('statActiveBorrows', active);
            setText('statPendingRequests', pending);
            setText('statOverdueItems', overdue);

            const overdueCard = document.getElementById('statOverdueCard');
            if (overdueCard) overdueCard.classList.toggle('is-alert', overdue > 0);

            const summary = document.getElementById('dashSummary');
            if (summary) {
                summary.textContent = summaryText(active, pending, overdue);
                summary.classList.toggle('is-alert', overdue > 0);
            }
        }

        function renderActiveBorrows(rows) {
            const body = document.getElementById('activeBorrowsBody');
            if (!body) return;
            setCount('activeBorrowsCount', rows.length, false);

            body.innerHTML = rows.length
                ? rows.map(row => '<tr>'
                    + equipmentCell(row)
                    + '<td>' + escapeHTML(row.borrowDate) + '</td>'
                    + '<td>' + escapeHTML(row.dueDate) + '</td>'
                    + '<td>' + badge(row.status) + '</td>'
                    + '</tr>').join('')
                : emptyRow(4, 'fa-box-open', 'No active borrow items.');
        }

        function renderRecentRequests(rows) {
            const body = document.getElementById('recentRequestsBody');
            if (!body) return;
            setCount('recentRequestsCount', rows.length, false);

            body.innerHTML = rows.length
                ? rows.map(row => '<tr>'
                    + equipmentCell(row)
                    + '<td>' + escapeHTML(row.date) + '</td>'
                    + '<td>' + badge(row.status) + '</td>'
                    + '</tr>').join('')
                : emptyRow(3, 'fa-clipboard-list', 'No recent requests found.');
        }

        function renderReminders(rows) {
            const list = document.getElementById('reminderList');
            if (!list) return;
            setCount('remindersCount', rows.length, true);

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
