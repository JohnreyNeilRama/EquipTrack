@extends('layouts.admin')

@section('title', 'EquipTrack - Audit Trail')

@push('css')
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminaudit.css') }}">
@endpush

@section('content')
<!-- Main Body Container -->
        <div style="padding: 30px; max-width: 1400px; margin: 0 auto; width: 100%;">
            <!-- Header Section -->
            <div class="audit-header-section">
                <h1 class="audit-page-title">Audit Trail</h1>
                <p class="audit-page-subtitle">Monitor user and administrator activities and system changes for accountability and security.</p>
            </div>

            <!-- Summary Statistic Cards Row -->
            <div class="audit-stats-row">
                <div class="audit-stat-card">
                    <div class="audit-stat-header">
                        <div class="audit-stat-value" id="statToday">{{ $stats['today'] }}</div>
                        <div class="audit-stat-icon-box blue">
                            <i class="fa-solid fa-bolt"></i>
                        </div>
                    </div>
                    <div class="audit-stat-info">
                        <div class="audit-stat-label">Today's Activities</div>
                        <div class="audit-stat-caption">Actions performed today</div>
                    </div>
                </div>

                <div class="audit-stat-card">
                    <div class="audit-stat-header">
                        <div class="audit-stat-value" id="statWeek">{{ $stats['week'] }}</div>
                        <div class="audit-stat-icon-box green">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                    </div>
                    <div class="audit-stat-info">
                        <div class="audit-stat-label">This Week</div>
                        <div class="audit-stat-caption">Activities this week</div>
                    </div>
                </div>

                <div class="audit-stat-card">
                    <div class="audit-stat-header">
                        <div class="audit-stat-value" id="statMonth">{{ $stats['month'] }}</div>
                        <div class="audit-stat-icon-box orange">
                            <i class="fa-solid fa-chart-column"></i>
                        </div>
                    </div>
                    <div class="audit-stat-info">
                        <div class="audit-stat-label">This Month</div>
                        <div class="audit-stat-caption">Monthly activity logs</div>
                    </div>
                </div>

                <div class="audit-stat-card">
                    <div class="audit-stat-header">
                        <div class="audit-stat-value" id="statActive">{{ $stats['activeToday'] }}</div>
                        <div class="audit-stat-icon-box purple">
                            <i class="fa-solid fa-users-gear"></i>
                        </div>
                    </div>
                    <div class="audit-stat-info">
                        <div class="audit-stat-label">Active Users Today</div>
                        <div class="audit-stat-caption">Accounts with activity today</div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="white-card">
                <div class="filter-toolbar">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" id="auditSearchInput" placeholder="Search by user, module, action, or details..." maxlength="100">
                    </div>
                    <div class="filter-dropdowns">
                        <select id="filterAction" class="filter-select">
                            <option value="">All Actions</option>
                            @foreach ($actions as $action)
                                <option value="{{ $action }}">{{ $action }}</option>
                            @endforeach
                        </select>

                        <select id="filterModule" class="filter-select">
                            <option value="">All Modules</option>
                            @foreach ($modules as $module)
                                <option value="{{ $module }}">{{ $module }}</option>
                            @endforeach
                        </select>

                        <select id="filterDateRange" class="filter-select">
                            <option value="all">All Dates</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="custom">Custom Date Range</option>
                        </select>
                    </div>
                    <button type="button" id="btnExportLogs" class="btn-export-logs">
                        <i class="fa-solid fa-download"></i> Export Logs
                    </button>
                </div>

                <!-- Custom Date Range Picker Container -->
                <div class="custom-date-container" id="customDateContainer" style="display: none;">
                    <div class="custom-date-group">
                        <label>Start Date</label>
                        <input type="date" id="customStartDate">
                    </div>
                    <div class="custom-date-group">
                        <label>End Date</label>
                        <input type="date" id="customEndDate">
                    </div>
                    <button type="button" id="btnApplyCustomDate" class="btn btn-primary" style="margin-top: auto; padding: 10px 18px; font-size: 13px; height: 38px; border-radius: 8px;">Apply Filter</button>
                </div>
            </div>

            <!-- Audit Log Table & Empty State Wrapper -->
            <div class="table-container card admin-table-card audit-table-card" style="margin-top: 24px;">
                <div id="tableContainer" style="display: none;">
                    <div class="audit-table-scroll">
                        <table class="audit-table" style="width: 100%;">
                            {{-- Fixed column widths keep every header aligned with its data; Details takes the remaining space. --}}
                            <colgroup>
                                <col style="width: 190px;">
                                <col style="width: 180px;">
                                <col style="width: 130px;">
                                <col style="width: 170px;">
                                <col style="width: 170px;">
                                <col>
                                <col style="width: 140px;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Date &amp; Time</th>
                                    <th>User</th>
                                    <th>Module</th>
                                    <th>Action</th>
                                    <th>Affected Record</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody id="auditTableBody"></tbody>
                        </table>
                    </div>

                    <!-- Pagination Footer -->
                    <div class="pagination-container">
                        <span class="pagination-info" id="paginationInfo"></span>
                        <div class="pagination-buttons" id="paginationButtons"></div>
                    </div>
                </div>

                <!-- Loading State (first load only) -->
                <div id="loadingState" class="empty-state-container">
                    <i class="fa-solid fa-spinner fa-spin empty-state-icon"></i>
                    <h4>Loading audit records...</h4>
                </div>

                <!-- Empty State Container -->
                <div id="emptyState" class="empty-state-container" style="display: none;">
                    <i class="fa-solid fa-shield-halved empty-state-icon"></i>
                    <h4 id="emptyTitle">No audit records found.</h4>
                    <p id="emptySubtitle">Activities will appear here once actions are performed within the system.</p>
                </div>
            </div>
        </div>
<!-- Audit Record Details Modal -->
    <div class="modal-overlay" id="auditDetailsModal" role="dialog" aria-modal="true" aria-labelledby="auditDetailsTitle">
        <div class="modal-card eq-modal-card" style="max-width: 680px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Complete audit record</p>
            </div>
            <div class="modal-inner-card">
                <button type="button" class="modal-close" id="auditDetailsClose" aria-label="Close details">&times;</button>
                <h3 class="modal-title-center" id="auditDetailsTitle">Audit Record Details</h3>

                <div class="audit-detail-grid">
                    <div class="audit-detail-field">
                        <span class="audit-detail-label">Date &amp; Time</span>
                        <div class="audit-detail-value" id="detailDate"></div>
                    </div>
                    <div class="audit-detail-field">
                        <span class="audit-detail-label">Record ID</span>
                        <div class="audit-detail-value" id="detailId"></div>
                    </div>
                    <div class="audit-detail-field">
                        <span class="audit-detail-label">User</span>
                        <div class="audit-detail-value" id="detailUser"></div>
                    </div>
                    <div class="audit-detail-field">
                        <span class="audit-detail-label">Role</span>
                        <div class="audit-detail-value" id="detailRole"></div>
                    </div>
                    <div class="audit-detail-field">
                        <span class="audit-detail-label">Module</span>
                        <div class="audit-detail-value" id="detailModule"></div>
                    </div>
                    <div class="audit-detail-field">
                        <span class="audit-detail-label">Action</span>
                        <div class="audit-detail-value"><span class="status-badge" id="detailAction"></span></div>
                    </div>
                    <div class="audit-detail-field">
                        <span class="audit-detail-label">IP Address</span>
                        <div class="audit-detail-value" id="detailIp"></div>
                    </div>
                    <div class="audit-detail-field">
                        <span class="audit-detail-label">Affected Record</span>
                        <div class="audit-detail-value" id="detailEntity"></div>
                    </div>
                    <div class="audit-detail-field audit-detail-full">
                        <span class="audit-detail-label">Description</span>
                        <div class="audit-detail-value audit-detail-text" id="detailDescription"></div>
                    </div>
                    <div class="audit-detail-field audit-detail-full">
                        <span class="audit-detail-label">Details / Notes</span>
                        <div class="audit-detail-value audit-detail-text" id="detailNote"></div>
                    </div>
                </div>

                <div class="audit-detail-footer">
                    <button type="button" class="audit-detail-close-btn" id="auditDetailsDone">Close</button>
                </div>
            </div>
        </div>
    </div>

<!-- Toast Success Notification Container -->
    <div class="toast-notification" id="toastNotification">
        <div class="toast-content">
            <div style="width: 32px; height: 32px; border-radius: 50%; background-color: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 16px;" id="toastIconBox">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div style="display: flex; flex-direction: column; gap: 2px;">
                <span style="font-weight: 700; font-size: 14px; color: var(--text-main);" id="toastTitleText">Notification Title</span>
                <span style="font-size: 12.5px; color: var(--text-muted);" id="toastMessageText">Notification details message goes here.</span>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const DATA_URL = @json(route('admin.audit.data'));
    const EXPORT_URL = @json(route('admin.audit.export'));

    // Everything the table shows comes from the server; this is only UI state.
    const state = { q: '', action: '', module: '', range: 'all', from: '', to: '', sort: 'date', dir: 'desc', page: 1 };
    let controller = null;
    let lastTotal = 0;
    let searchTimer = null;
    // Full, untruncated rows from the current page of the server response, keyed by audit id.
    const recordsById = new Map();

    const $ = (id) => document.getElementById(id);
    const tableBody = $('auditTableBody');
    const tableContainer = $('tableContainer');
    const emptyState = $('emptyState');
    const loadingState = $('loadingState');
    const paginationInfo = $('paginationInfo');
    const paginationButtons = $('paginationButtons');

    function escapeHTML(value) {
        return String(value ?? '').replace(/[&<>'"]/g,
            tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag));
    }

    function badgeClass(action) {
        const a = action.toLowerCase();
        if (a.includes('login')) return 'badge-blue';
        if (a.includes('logout')) return 'badge-gray';
        if (a.includes('deleted') || a.includes('deactivated') || a.includes('rejected')) return 'badge-red';
        if (a.includes('added') || a.includes('registered') || a.includes('submitted') || a.includes('activated')) return 'badge-green';
        if (a.includes('updated')) return 'badge-orange';
        if (a.includes('approved')) return 'badge-purple';
        if (a.includes('returned')) return 'badge-teal';
        if (a.includes('exported') || a.includes('generated')) return 'badge-yellow';
        return 'badge-blue';
    }

    function showToast(title, message, type) {
        const box = $('toastIconBox');
        $('toastTitleText').textContent = title;
        $('toastMessageText').textContent = message;
        const ok = type === 'success';
        box.style.backgroundColor = ok ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)';
        box.style.color = ok ? '#10b981' : '#ef4444';
        box.innerHTML = ok ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-circle-xmark"></i>';
        const toast = $('toastNotification');
        toast.classList.add('show');
        clearTimeout(showToast.timer);
        showToast.timer = setTimeout(() => toast.classList.remove('show'), 4000);
    }

    function queryString(includePage) {
        const params = new URLSearchParams();
        ['q', 'action', 'module', 'sort', 'dir'].forEach(key => { if (state[key] !== '') params.set(key, state[key]); });
        if (state.range !== 'all') params.set('range', state.range);
        if (state.range === 'custom') {
            if (state.from) params.set('from', state.from);
            if (state.to) params.set('to', state.to);
        }
        if (includePage && state.page > 1) params.set('page', state.page);
        return params.toString();
    }

    async function load() {
        if (controller) controller.abort();
        controller = new AbortController();

        try {
            const response = await fetch(DATA_URL + '?' + queryString(true), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            if (response.status === 422) {
                const body = await response.json().catch(() => ({}));
                const first = body.errors ? Object.values(body.errors)[0][0] : 'Please check the selected filters.';
                showToast('Invalid Filter', first, 'error');
                return;
            }
            if (!response.ok) throw new Error('HTTP ' + response.status);

            render(await response.json());
        } catch (error) {
            if (error.name === 'AbortError') return;
            loadingState.style.display = 'none';
            showToast('Could Not Load Records', 'The audit trail could not be loaded. Please refresh the page.', 'error');
        }
    }

    function render(data) {
        $('statToday').textContent = data.stats.today;
        $('statWeek').textContent = data.stats.week;
        $('statMonth').textContent = data.stats.month;
        $('statActive').textContent = data.stats.activeToday;

        loadingState.style.display = 'none';
        lastTotal = data.total;
        state.page = data.page;

        if (data.total === 0) {
            tableContainer.style.display = 'none';
            emptyState.style.display = 'flex';
            if (data.hasAny) {
                $('emptyTitle').textContent = 'No records match your filters.';
                $('emptySubtitle').textContent = 'Try a different search or clear the filters to see all audit records.';
            } else {
                $('emptyTitle').textContent = 'No audit records found.';
                $('emptySubtitle').textContent = 'Activities will appear here once actions are performed within the system.';
            }
            return;
        }

        emptyState.style.display = 'none';
        tableContainer.style.display = 'block';

        // Long values are only clipped on screen (CSS ellipsis); the full text stays in the
        // title tooltip and in the details modal, which reads the same untouched record.
        const clip = (value, cls = '') => `<div class="audit-truncate ${cls}" title="${escapeHTML(value)}">${escapeHTML(value)}</div>`;

        recordsById.clear();
        data.records.forEach(rec => recordsById.set(String(rec.id), rec));

        tableBody.innerHTML = data.records.map(rec => `
            <tr class="audit-row" data-id="${rec.id}" tabindex="0">
                <td class="audit-col-date">${clip(rec.date)}</td>
                <td>
                    ${clip(rec.user, 'audit-user-name')}
                    ${rec.role && rec.role !== '—' ? clip(rec.role, 'audit-user-role') : ''}
                </td>
                <td>${clip(rec.module)}</td>
                <td><span class="status-badge ${badgeClass(rec.action)}" title="${escapeHTML(rec.action)}">${escapeHTML(rec.action)}</span></td>
                <td>${clip(rec.entity)}</td>
                <td>
                    ${rec.description ? clip(rec.description) : ''}
                    ${rec.note ? clip(rec.note, 'audit-note') : ''}
                    ${!rec.description && !rec.note ? '—' : ''}
                </td>
                <td><code class="audit-ip" title="${escapeHTML(rec.ip)}">${escapeHTML(rec.ip)}</code></td>
            </tr>`).join('');

        paginationInfo.textContent = `Showing ${data.from} to ${data.to} of ${data.total} entries`;
        renderPagination(data.page, data.lastPage);
    }

    function pageButton(label, page, { active = false, disabled = false } = {}) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-page' + (active ? ' active' : '');
        btn.disabled = disabled;
        btn.innerHTML = label;
        if (!disabled && !active) {
            btn.addEventListener('click', () => { state.page = page; load(); });
        }
        return btn;
    }

    function renderPagination(current, last) {
        paginationButtons.innerHTML = '';
        paginationButtons.appendChild(pageButton('<i class="fa-solid fa-angle-left"></i>', current - 1, { disabled: current === 1 }));

        const pages = new Set([1, last, current - 1, current, current + 1]);
        let previous = 0;
        [...pages].filter(p => p >= 1 && p <= last).sort((a, b) => a - b).forEach(p => {
            if (p - previous > 1) {
                const gap = document.createElement('span');
                gap.textContent = '…';
                gap.style.cssText = 'align-self: center; color: var(--text-muted);';
                paginationButtons.appendChild(gap);
            }
            paginationButtons.appendChild(pageButton(String(p), p, { active: p === current }));
            previous = p;
        });

        paginationButtons.appendChild(pageButton('<i class="fa-solid fa-angle-right"></i>', current + 1, { disabled: current === last }));
    }

    function reload() { state.page = 1; load(); }

    // Full-details modal
    const detailModal = $('auditDetailsModal');
    let detailReturnFocus = null;

    function setDetail(id, value) {
        const el = $(id);
        const text = String(value ?? '').trim();
        el.textContent = text !== '' ? text : '—';
        el.classList.toggle('is-empty', text === '' || text === '—');
    }

    function openDetails(id) {
        const rec = recordsById.get(String(id));
        if (!rec) return;

        setDetail('detailDate', rec.date);
        setDetail('detailId', '#' + rec.id);
        setDetail('detailUser', rec.user);
        setDetail('detailRole', rec.role);
        setDetail('detailModule', rec.module);
        setDetail('detailEntity', rec.entity);
        setDetail('detailDescription', rec.description);
        setDetail('detailNote', rec.note);
        setDetail('detailIp', rec.ip);

        const badge = $('detailAction');
        badge.textContent = rec.action;
        badge.className = 'status-badge ' + badgeClass(rec.action);

        detailReturnFocus = document.activeElement;
        detailModal.classList.add('show');
        $('auditDetailsClose').focus();
    }

    function closeDetails() {
        if (!detailModal.classList.contains('show')) return;
        detailModal.classList.remove('show');
        if (detailReturnFocus && typeof detailReturnFocus.focus === 'function') detailReturnFocus.focus();
        detailReturnFocus = null;
    }

    tableBody.addEventListener('click', (e) => {
        const row = e.target.closest('tr.audit-row');
        if (!row) return;
        // Let the Admin select/copy text (e.g. an IP address) without opening the modal.
        if (String(window.getSelection()).trim() !== '') return;
        openDetails(row.dataset.id);
    });

    tableBody.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const row = e.target.closest('tr.audit-row');
        if (!row || e.target !== row) return;
        e.preventDefault();
        openDetails(row.dataset.id);
    });

    $('auditDetailsClose').addEventListener('click', closeDetails);
    $('auditDetailsDone').addEventListener('click', closeDetails);
    detailModal.addEventListener('click', (e) => { if (e.target === detailModal) closeDetails(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDetails(); });

    // Controls
    $('auditSearchInput').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { state.q = e.target.value.trim(); reload(); }, 300);
    });
    $('filterAction').addEventListener('change', (e) => { state.action = e.target.value; reload(); });
    $('filterModule').addEventListener('change', (e) => { state.module = e.target.value; reload(); });

    $('filterDateRange').addEventListener('change', (e) => {
        state.range = e.target.value;
        if (state.range === 'custom') {
            $('customDateContainer').style.display = 'flex';
        } else {
            $('customDateContainer').style.display = 'none';
            reload();
        }
    });

    $('btnApplyCustomDate').addEventListener('click', () => {
        state.from = $('customStartDate').value;
        state.to = $('customEndDate').value;
        if (!state.from && !state.to) {
            showToast('Select a Date', 'Choose a start date, an end date, or both.', 'error');
            return;
        }
        reload();
    });

    $('btnExportLogs').addEventListener('click', () => {
        if (lastTotal === 0) {
            showToast('Export Failed', 'There are no audit logs available to export.', 'error');
            return;
        }
        // The server exports everything the current filters match and records the export.
        window.location.href = EXPORT_URL + '?' + queryString(false);
        showToast('Export Started', 'Your CSV download of the filtered audit logs is starting.', 'success');
        setTimeout(load, 1500);
    });

    load();
});
</script>
@endpush
