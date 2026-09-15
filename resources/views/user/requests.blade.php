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
                    <th>Action</th>
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
                        <td>
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

    // Function to filter requests in real-time
    function filterRequests() {
        const rows = document.querySelectorAll('.request-row');
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const filterVal = statusFilter ? statusFilter.value : 'All';

        rows.forEach(row => {
            const equipment = (row.getAttribute('data-equipment') || '').toLowerCase();
            const category  = (row.getAttribute('data-category') || '').toLowerCase();
            const status    = row.getAttribute('data-status') || '';

            const matchesSearch = !query || equipment.includes(query) || category.includes(query);
            const matchesFilter = (filterVal === 'All' || status === filterVal);

            if (matchesSearch && matchesFilter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
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
</script>
@endpush
