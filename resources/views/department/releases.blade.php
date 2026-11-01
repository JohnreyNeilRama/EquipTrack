@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
{{-- Same table, badge and modal design as the Borrow Requests / Return Confirmation pages --}}
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}?v={{ filemtime(public_path('admin/css/admindashboard.css')) }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminrequests.css') }}?v={{ filemtime(public_path('admin/css/adminrequests.css')) }}">
@include('partials.requests.styles')
<link rel="stylesheet" href="{{ asset('departments/css/requests.css') }}">
<style>
    .releases-table th,
    .releases-table td.col-date,
    .releases-table .status-badge {
        white-space: nowrap;
    }
    /* Seven columns: tighter cells (same as the Return Confirmation table) so
       the Action column stays in view on laptop screens. */
    .admin-table-card .releases-table th,
    .admin-table-card .releases-table td {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
    .admin-table-card .releases-table th:first-child,
    .admin-table-card .releases-table td:first-child {
        padding-left: 20px !important;
    }
    /* Borrower and Equipment share the remaining width; their text ends in "…" */
    .releases-table .col-user,
    .releases-table .col-equipment {
        width: 24%;
        max-width: 0;
        min-width: 120px;
    }
    /* When / by whom it was handled: wraps under the badge instead of widening the column */
    .releases-table td.col-status .cell-sub {
        margin-top: 6px;
        white-space: normal;
        line-height: 1.4;
    }
    #filterStatus {
        min-width: 180px;
        padding-right: 36px;
    }
    .releases-table .cell-main {
        display: block;
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .releases-table .cell-sub {
        display: block;
        margin-top: 2px;
        font-size: var(--fs-small);
        color: var(--text-muted);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .releases-table .cell-sub.is-due {
        color: #b45309;
        font-weight: 600;
    }
    .releases-table .cell-sub.is-late {
        color: #ef4444;
        font-weight: 600;
    }
    .releases-table .action-buttons {
        justify-content: center;
    }
    .releases-table .btn-approve,
    .releases-table .btn-reject {
        white-space: nowrap;
    }

    /* Release statuses, in the shared badge design */
    .status-badge.status-ready {
        background-color: rgba(245, 158, 11, 0.12);
        color: #b45309;
    }
    .status-badge.status-released {
        background-color: rgba(16, 185, 129, 0.08);
        color: #10b981;
    }
    .status-badge.status-not-claimed {
        background-color: rgba(71, 85, 105, 0.08);
        color: #475569;
    }
    .dark-theme .status-badge.status-ready { background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; }
    .dark-theme .status-badge.status-released { background-color: rgba(16, 185, 129, 0.15); color: #34d399; }
    .dark-theme .status-badge.status-not-claimed { background-color: rgba(226, 232, 240, 0.1); color: #cbd5e1; }

    .summary-card-icon.icon-orange { background-color: #fef3c7; color: #d97706; }
    .dark-theme .summary-card-icon.icon-orange { background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; }

    /* Release / Not Claimed window */
    .release-summary {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: 8px 16px;
        margin-bottom: 20px;
        padding: 16px;
        font-size: var(--fs-body);
        background: var(--primary-light);
        border-radius: 12px;
    }
    .release-summary dt {
        color: var(--text-muted);
        font-weight: 500;
    }
    .release-summary dd {
        margin: 0;
        color: var(--text-main);
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .id-check {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 14px 16px;
        font-size: var(--fs-body);
        color: var(--text-main);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        cursor: pointer;
    }
    .id-check input {
        margin-top: 3px;
        width: 16px;
        height: 16px;
        flex-shrink: 0;
        accent-color: #385585;
    }
    .release-help {
        margin: 0 0 16px;
        font-size: var(--fs-label);
        color: var(--text-muted);
        line-height: 1.5;
    }
    .btn-submit-request:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
</style>
@endpush

@section('content')
    <div class="requests-container">
        <div class="equipment-header-section">
            <h2>Release Equipment</h2>
            <p>Hand approved equipment to borrowers when they come to the lab. Check their school ID, then release the item, or mark it Not Claimed if they never came.</p>
        </div>

        <!-- Summary Cards -->
        <div class="requests-summary-section">
            <h4 class="section-subtitle">Summary Cards</h4>
            <div class="summary-cards-grid">
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumReady">0</span>
                        <div class="summary-card-icon icon-orange">
                            <i class="fa-solid fa-box"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Ready for Pickup</span>
                </div>
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumReleased">0</span>
                        <div class="summary-card-icon icon-green">
                            <i class="fa-solid fa-hand-holding"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Released (last 30 days)</span>
                </div>
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumNotClaimed">0</span>
                        <div class="summary-card-icon icon-red">
                            <i class="fa-solid fa-user-xmark"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Not Claimed (last 30 days)</span>
                </div>
            </div>
        </div>

        <!-- Search & filter -->
        <div class="controls-bar">
            <div class="controls-left">
                <div class="search-box-wrapper">
                    <input type="text" id="searchReleases" placeholder="Search by user, ID number, equipment or request ID...">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <div class="filter-select-wrapper">
                    <select id="filterStatus">
                        <option value="all">All Status</option>
                        <option value="Ready for Pickup" selected>Ready for Pickup</option>
                        <option value="Released">Released</option>
                        <option value="Not Claimed">Not Claimed</option>
                    </select>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
            </div>
        </div>

        <div class="table-container card admin-table-card">
            <table class="releases-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th class="col-user">Borrower</th>
                        <th class="col-equipment">Equipment</th>
                        <th>Pickup Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th class="action-column" style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="releasesTableBody">
                    {{-- Rendered by the script below --}}
                </tbody>
            </table>
        </div>
    </div>

    {{-- Release / Not Claimed window --}}
    <div class="modal-overlay" id="releaseModal">
        <div class="modal-card eq-modal-card" style="max-width: 500px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top" id="releaseModalSubtitle">Hand the equipment to the borrower</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeReleaseBtn" aria-label="Close">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;" id="releaseModalTitle">Release Equipment</h3>

                <dl class="release-summary">
                    <dt>Borrower</dt><dd id="relUser">—</dd>
                    <dt>ID Number</dt><dd id="relIdNumber">—</dd>
                    <dt>Equipment</dt><dd id="relEquipment">—</dd>
                    <dt>Pickup Date</dt><dd id="relPickup">—</dd>
                    <dt>Due Date</dt><dd id="relDue">—</dd>
                </dl>

                <form id="releaseForm">
                    <input type="hidden" id="relTransactionId">

                    {{-- Release --}}
                    <div id="releaseFields">
                        <label class="id-check">
                            <input type="checkbox" id="relIdVerified">
                            <span>I checked the borrower's school ID and it matches the name and ID number above.</span>
                        </label>
                        <div class="form-submit-container" style="margin-top: 20px;">
                            <button type="submit" class="btn-submit-request" id="relSubmitRelease" disabled>Release Equipment</button>
                        </div>
                    </div>

                    {{-- Not Claimed --}}
                    <div id="notClaimedFields" style="display: none;">
                        <p class="release-help">The held unit goes back to stock. The borrower is not penalized and can request the item again.</p>
                        <label class="detail-form-label" for="relNote" style="text-align: left;">Note (optional)</label>
                        <textarea id="relNote" class="detail-form-control textarea-control" rows="3" maxlength="255" placeholder="e.g. Borrower did not come on the pickup date."></textarea>
                        <div class="form-submit-container" style="margin-top: 20px;">
                            <button type="submit" class="btn-submit-reject">Mark as Not Claimed</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
    (() => {
        const CSRF_TOKEN = @json(csrf_token());
        const URL_DATA = @json(route('department.releases.data'));
        const URL_RELEASE = @json(route('department.releases.release'));
        const URL_NOT_CLAIMED = @json(route('department.releases.not-claimed'));
        const REFRESH_MS = 15000;

        let releases = @json($releases);
        let mode = 'release';

        const tableBody = document.getElementById('releasesTableBody');
        const searchInput = document.getElementById('searchReleases');
        const filterSelect = document.getElementById('filterStatus');
        const modal = document.getElementById('releaseModal');
        const form = document.getElementById('releaseForm');
        const idCheck = document.getElementById('relIdVerified');
        const releaseBtn = document.getElementById('relSubmitRelease');

        function escapeHTML(value) {
            if (value === null || value === undefined) return '';
            return String(value).replace(/[&<>'"]/g, tag => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            }[tag] || tag));
        }

        const badgeClass = status => ({
            'Ready for Pickup': 'status-ready',
            'Released': 'status-released',
            'Not Claimed': 'status-not-claimed',
        }[status] || 'status-pending');

        function renderSummary() {
            document.getElementById('sumReady').textContent = releases.filter(r => r.status === 'Ready for Pickup').length;
            document.getElementById('sumReleased').textContent = releases.filter(r => r.status === 'Released').length;
            document.getElementById('sumNotClaimed').textContent = releases.filter(r => r.status === 'Not Claimed').length;
        }

        // Second line of the Status / date cells: when it was handled, or how soon pickup is.
        function statusDetail(r) {
            if (r.status === 'Released') return `${escapeHTML(r.releasedAt)}<br>by ${escapeHTML(r.releasedBy)}`;
            if (r.status === 'Not Claimed') return escapeHTML(r.unclaimedAt);
            return '';
        }

        function renderTable() {
            const query = searchInput.value.toLowerCase().trim();
            const filter = filterSelect.value;

            const rows = releases.filter(r => {
                const text = [r.user, r.idNumber, r.equipment, r.serial, '#' + r.requestId, String(r.requestId)].join(' ').toLowerCase();
                return (filter === 'all' || r.status === filter) && (query === '' || text.includes(query));
            });

            if (!rows.length) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="no-requests-message">
                            <i class="fa-solid fa-hand-holding"></i>
                            ${filter === 'Ready for Pickup' && query === '' ? 'No equipment is waiting for pickup.' : 'No records match your search.'}
                        </td>
                    </tr>`;
                return;
            }

            tableBody.innerHTML = rows.map(r => `
                <tr class="admin-table-row">
                    <td style="font-weight: 600;">#${r.requestId}</td>
                    <td class="col-user">
                        <span class="cell-main" title="${escapeHTML(r.user)}">${escapeHTML(r.user)}</span>
                        <span class="cell-sub">${escapeHTML(r.role)} · ${escapeHTML(r.idNumber)}</span>
                    </td>
                    <td class="col-equipment">
                        <span class="cell-main" title="${escapeHTML(r.equipment)}">${escapeHTML(r.equipment)}</span>
                        <span class="cell-sub">${escapeHTML(r.serial)}${r.requiresBothApprovals ? ' · Expensive' : ''}</span>
                    </td>
                    <td class="col-date">
                        ${escapeHTML(r.pickupDate)}
                        ${r.pickupNote ? `<span class="cell-sub ${r.pickupPassed ? 'is-late' : (r.pickupNote === 'Pickup today' ? 'is-due' : '')}">${escapeHTML(r.pickupNote)}</span>` : ''}
                    </td>
                    <td class="col-date">${escapeHTML(r.dueDate)}</td>
                    <td class="col-status">
                        <span class="status-badge ${badgeClass(r.status)}">${escapeHTML(r.status)}</span>
                        ${statusDetail(r) ? `<span class="cell-sub">${statusDetail(r)}</span>` : ''}
                    </td>
                    <td class="action-cell">
                        ${r.canAct ? `
                            <div class="action-buttons">
                                <button type="button" class="btn-approve" data-release="${r.id}">Release</button>
                                <button type="button" class="btn-reject" data-not-claimed="${r.id}">Not Claimed</button>
                            </div>` : '<span class="cell-sub" style="text-align: center;">—</span>'}
                    </td>
                </tr>`).join('');
        }

        function render() {
            renderSummary();
            renderTable();
        }

        function openModal(id, nextMode) {
            const r = releases.find(item => item.id === id);
            if (!r) return;

            mode = nextMode;
            const releasing = mode === 'release';

            document.getElementById('relTransactionId').value = r.id;
            document.getElementById('relUser').textContent = `${r.user} (${r.role})`;
            document.getElementById('relIdNumber').textContent = r.idNumber;
            document.getElementById('relEquipment').textContent = `${r.equipment} · ${r.serial}${r.quantity > 1 ? ' · ×' + r.quantity : ''}`;
            document.getElementById('relPickup').textContent = r.pickupDate + (r.pickupNote ? ` (${r.pickupNote})` : '');
            document.getElementById('relDue').textContent = r.dueDate;
            document.getElementById('releaseModalTitle').textContent = releasing ? 'Release Equipment' : 'Mark as Not Claimed';
            document.getElementById('releaseModalSubtitle').textContent = releasing
                ? 'Hand the equipment to the borrower'
                : 'The borrower did not pick up the equipment';
            document.getElementById('releaseFields').style.display = releasing ? 'block' : 'none';
            document.getElementById('notClaimedFields').style.display = releasing ? 'none' : 'block';
            idCheck.checked = false;
            releaseBtn.disabled = true;
            document.getElementById('relNote').value = '';

            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        tableBody.addEventListener('click', event => {
            const release = event.target.closest('[data-release]');
            const notClaimed = event.target.closest('[data-not-claimed]');
            if (release) openModal(parseInt(release.dataset.release, 10), 'release');
            if (notClaimed) openModal(parseInt(notClaimed.dataset.notClaimed, 10), 'not-claimed');
        });

        idCheck.addEventListener('change', () => {
            releaseBtn.disabled = !idCheck.checked;
        });

        form.addEventListener('submit', event => {
            event.preventDefault();

            const formData = new FormData();
            formData.append('_token', CSRF_TOKEN);
            formData.append('transaction_id', document.getElementById('relTransactionId').value);

            if (mode === 'release') {
                if (!idCheck.checked) return;
                formData.append('id_verified', '1');
            } else {
                formData.append('note', document.getElementById('relNote').value.trim());
            }

            fetch(mode === 'release' ? URL_RELEASE : URL_NOT_CLAIMED, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData,
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showNotification(mode === 'release' ? 'Equipment Released' : 'Marked Not Claimed', data.message, mode === 'release' ? 'success' : 'error');
                        releases = data.releases || releases;
                        closeModal();
                        render();
                    } else {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
                        showNotification('Action Failed', firstError || data.message || 'The action could not be completed.', 'error');
                        refresh();
                    }
                })
                .catch(() => showNotification('Error', 'Network error occurred.', 'error'));
        });

        document.getElementById('closeReleaseBtn').addEventListener('click', closeModal);
        modal.addEventListener('click', event => {
            if (event.target === modal) closeModal();
        });
        searchInput.addEventListener('input', renderTable);
        filterSelect.addEventListener('change', renderTable);

        function refresh() {
            return fetch(URL_DATA, { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        releases = data.releases;
                        render();
                    }
                })
                .catch(err => console.error('Error loading releases:', err));
        }

        render();
        setInterval(() => {
            // Don't redraw the table under an open window.
            if (!modal.classList.contains('show')) refresh();
        }, REFRESH_MS);
    })();
</script>
@endpush
