@extends('layouts.user')

@section('title', 'EquipTrack - My Requests')

@push('css')
<link rel="stylesheet" href="{{ asset('user/css/userrequests.css') }}">
@endpush

@php
    $fmt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('M d, Y') : 'N/A';
    $resolveImg = function (?string $raw): string {
        $raw = trim((string) $raw);
        if ($raw === '') return asset('images/EquipTrack_logo.png');
        if (preg_match('/^(https?:\/\/|data:|\/storage\/)/i', $raw)) return $raw;
        return asset(ltrim($raw, '/'));
    };
@endphp

@section('content')
    {{-- Page Subtitle (Header in mockup) --}}
    <h3 class="track-status-header">Track the status of your equipment requests</h3>

    {{-- Search and Filter Row --}}
    <div class="search-filter-row">
        <div class="search-wrapper">
            <input type="text" id="searchRequests" placeholder="Search equipment..." autocomplete="off">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
        </div>
        <div class="filter-wrapper">
            <select id="statusFilter" class="filter-select">
                <option value="All">All</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Rejected">Rejected</option>
            </select>
        </div>
    </div>

    {{-- Table Container --}}
    <div class="table-container card requests-table-card">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Equipment</th>
                    <th>Request Date</th>
                    <th>Borrow Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody id="requestsTableBody">
                @forelse ($userRequests as $idx => $req)
                    @php
                        $eq = $req->equipment;
                        $eqName = $eq->name ?? 'Unknown equipment';
                        $eqCat = $eq->category?->category_name ?: 'General';
                        $reqDateFormatted = $fmt($req->date_requested);
                        $bDateFormatted = $fmt($req->borrow_date ?: $req->date_needed);
                        $dDateFormatted = $fmt($req->due_date ?: $req->return_date);
                        $status = $req->overall_status ?: 'Pending';
                        $imgUrl = $resolveImg($eq->image ?? '');
                    @endphp
                    <tr class="request-row"
                        data-id="{{ $req->request_id }}"
                        data-equipment="{{ $eqName }}"
                        data-category="{{ $eqCat }}"
                        data-req-date="{{ $reqDateFormatted }}"
                        data-borrow-date="{{ $bDateFormatted }}"
                        data-due-date="{{ $dDateFormatted }}"
                        data-status="{{ $status }}"
                        data-purpose="{{ $req->purpose }}"
                        data-notes="{{ $req->notes ?: 'None' }}"
                        data-img="{{ $imgUrl }}"
                        data-reject-reason="{{ $req->reject_reason }}">
                        <td>{{ $idx + 1 }}</td>
                        <td class="equipment-col">
                            <div class="eq-cell" style="display: flex; align-items: center; gap: 12px;">
                                <img src="{{ $imgUrl }}" alt="{{ $eqName }}" class="eq-thumb" style="width: 42px; height: 42px; border-radius: 8px; object-fit: cover; background: #fff;" onerror="this.onerror=null; this.src='{{ asset('images/EquipTrack_logo.png') }}';">
                                <div class="eq-info" style="display: flex; flex-direction: column;">
                                    <span class="eq-title" style="font-weight: 600; color: var(--text-main);">{{ $eqName }}</span>
                                    <span class="eq-sub" style="font-size: 12px; color: var(--text-muted);">{{ $eqCat }}</span>
                                </div>
                            </div>
                        </td>
                        <td>{{ $reqDateFormatted }}</td>
                        <td>{{ $bDateFormatted }}</td>
                        <td>{{ $dDateFormatted }}</td>
                        <td>
                            <span class="detail-status-badge status-{{ strtolower($status) }}">
                                {{ $status }}
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <button type="button" class="btn-view-request" onclick="openRequestDetails(this)">
                                <i class="fa-regular fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr id="noRequestsRow">
                        <td colspan="7" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                            No equipment requests found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination Footer --}}
        <div class="pagination-container" id="paginationContainer">
            <span class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
            <div class="pagination-buttons" id="paginationButtons">
                {{-- Buttons added dynamically --}}
            </div>
        </div>
    </div>

    {{-- Request Details Modal --}}
    <div class="modal-overlay" id="detailsModal">
        <div class="modal-card details-modal-card">
            <div class="modal-inner-card">
                <button class="modal-close" id="closeDetailsBtn">&times;</button>

                <h3 class="modal-title-center">Request Details</h3>
                <p class="modal-subtitle-center">Detailed view of your borrowing request</p>

                <div class="detail-main-content">
                    {{-- Left Side: Image Preview & Status --}}
                    <div class="detail-left-side">
                        <div class="detail-img-container">
                            <img src="" alt="Equipment Image" id="detailEqImg">
                        </div>
                        <div class="detail-form-group">
                            <label class="detail-form-label">Request Status</label>
                            <div class="status-badge-wrapper">
                                <span class="detail-status-badge" id="detailStatusBadge">Approved</span>
                            </div>
                        </div>
                    </div>

                    {{-- Right Side: Text Details Form Grid --}}
                    <div class="detail-right-side">
                        <div class="detail-form-grid">
                            <div class="detail-form-group">
                                <label class="detail-form-label">Equipment Name</label>
                                <input type="text" id="detailEqName" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Category</label>
                                <input type="text" id="detailEqCategory" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Request Date</label>
                                <input type="text" id="detailReqDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Borrow Date</label>
                                <input type="text" id="detailBorrowDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group full-width">
                                <label class="detail-form-label">Due Date</label>
                                <input type="text" id="detailDueDate" class="detail-form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Lower Section: Purpose & Notes --}}
                <div class="detail-lower-section">
                    <div class="detail-form-group">
                        <label class="detail-form-label">Borrowing Purpose</label>
                        <textarea id="detailPurpose" class="detail-form-control textarea-control" readonly rows="2"></textarea>
                    </div>
                    <div class="detail-form-group">
                        <label class="detail-form-label">Additional Notes</label>
                        <textarea id="detailNotes" class="detail-form-control textarea-control" readonly rows="2"></textarea>
                    </div>
                    <div class="detail-form-group" id="detailReasonGroup" style="display: none;">
                        <label class="detail-form-label text-danger">Reason for Rejection</label>
                        <textarea id="detailReason" class="detail-form-control textarea-control text-danger-control" readonly rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const searchInput = document.getElementById('searchRequests');
    const statusFilter = document.getElementById('statusFilter');
    const detailsModal = document.getElementById('detailsModal');
    const closeDetailsBtn = document.getElementById('closeDetailsBtn');

    // Pagination elements & state (same behavior as the Admin tables)
    const paginationContainer = document.getElementById('paginationContainer');
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationButtons = document.getElementById('paginationButtons');
    const allRows = Array.from(document.querySelectorAll('.request-row'));
    let currentPage = 1;
    const pageSize = 10;

    // Filters/search changed: go back to the first page, then re-render
    function filterRequests() {
        currentPage = 1;
        renderRequestsTable();
    }

    // Apply search + status filter, then show only the rows of the current page
    function renderRequestsTable() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const filterVal = statusFilter ? statusFilter.value : 'All';

        const filtered = allRows.filter(row => {
            const equipment = (row.getAttribute('data-equipment') || '').toLowerCase();
            const category  = (row.getAttribute('data-category') || '').toLowerCase();
            const status    = row.getAttribute('data-status') || '';

            const matchesSearch = !query || equipment.includes(query) || category.includes(query);
            const matchesFilter = (filterVal === 'All' || status === filterVal);
            return matchesSearch && matchesFilter;
        });

        const totalEntries = filtered.length;
        const totalPages = Math.ceil(totalEntries / pageSize);

        if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = Math.min(startIndex + pageSize, totalEntries);
        const pageRows = filtered.slice(startIndex, endIndex);

        allRows.forEach(row => {
            row.style.display = 'none';
            row.classList.remove('last-visible-row');
        });
        pageRows.forEach(row => { row.style.display = ''; });
        if (pageRows.length) {
            pageRows[pageRows.length - 1].classList.add('last-visible-row');
        }

        if (totalEntries === 0) {
            paginationContainer.style.display = 'none';
        } else {
            paginationContainer.style.display = 'flex';
            paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalEntries} entries`;
            renderPaginationButtons(totalPages);
        }
    }

    // Render pagination buttons dynamically (same behavior as Admin Users)
    function renderPaginationButtons(totalPages) {
        paginationButtons.innerHTML = '';

        const prevBtn = document.createElement('button');
        prevBtn.className = 'btn-page';
        prevBtn.innerHTML = '<i class="fa-solid fa-angle-left"></i>';
        prevBtn.disabled = currentPage === 1;
        prevBtn.addEventListener('click', () => {
            currentPage--;
            renderRequestsTable();
        });
        paginationButtons.appendChild(prevBtn);

        for (let i = 1; i <= totalPages; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `btn-page ${currentPage === i ? 'active' : ''}`;
            pageBtn.textContent = i;
            pageBtn.addEventListener('click', () => {
                currentPage = i;
                renderRequestsTable();
            });
            paginationButtons.appendChild(pageBtn);
        }

        const nextBtn = document.createElement('button');
        nextBtn.className = 'btn-page';
        nextBtn.innerHTML = '<i class="fa-solid fa-angle-right"></i>';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.addEventListener('click', () => {
            currentPage++;
            renderRequestsTable();
        });
        paginationButtons.appendChild(nextBtn);
    }

    // Function to open request details modal
    function openRequestDetails(button) {
        const row = button.closest('.request-row');

        const eqName = row.getAttribute('data-equipment');
        const eqCategory = row.getAttribute('data-category');
        const reqDate = row.getAttribute('data-req-date');
        const borrowDate = row.getAttribute('data-borrow-date');
        const dueDate = row.getAttribute('data-due-date');
        const status = row.getAttribute('data-status');
        const purpose = row.getAttribute('data-purpose');
        const notes = row.getAttribute('data-notes') || 'N/A';
        const imgUrl = row.getAttribute('data-img');
        const rejectReason = row.getAttribute('data-reject-reason');

        document.getElementById('detailEqName').value = eqName;
        document.getElementById('detailEqCategory').value = eqCategory;
        document.getElementById('detailReqDate').value = reqDate;
        document.getElementById('detailBorrowDate').value = borrowDate;
        document.getElementById('detailDueDate').value = dueDate;
        document.getElementById('detailPurpose').value = purpose;
        document.getElementById('detailNotes').value = notes;

        const imgElement = document.getElementById('detailEqImg');
        if (imgUrl) {
            imgElement.src = imgUrl;
            imgElement.style.display = 'block';
        } else {
            imgElement.style.display = 'none';
        }

        const badge = document.getElementById('detailStatusBadge');
        badge.textContent = status;
        badge.className = 'detail-status-badge';
        badge.classList.add('status-' + status.toLowerCase());

        const reasonGroup = document.getElementById('detailReasonGroup');
        if (status === 'Rejected' && rejectReason) {
            document.getElementById('detailReason').value = rejectReason;
            reasonGroup.style.display = 'block';
        } else {
            reasonGroup.style.display = 'none';
        }

        detailsModal.classList.add('show');
    }

    closeDetailsBtn.addEventListener('click', () => {
        detailsModal.classList.remove('show');
    });

    detailsModal.addEventListener('click', (e) => {
        if (e.target === detailsModal) {
            detailsModal.classList.remove('show');
        }
    });

    searchInput.addEventListener('input', filterRequests);
    statusFilter.addEventListener('change', filterRequests);

    // Initial render
    renderRequestsTable();
</script>
@endpush
