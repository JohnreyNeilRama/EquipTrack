@extends('layouts.user')

@section('title', 'EquipTrack - Borrowing History')

@push('css')
<link rel="stylesheet" href="{{ asset('user/css/usertable.css') }}?v={{ filemtime(public_path('user/css/usertable.css')) }}">
<link rel="stylesheet" href="{{ asset('user/css/userhistory.css') }}?v={{ filemtime(public_path('user/css/userhistory.css')) }}">
@endpush

@section('content')
<!-- Page Subtitle Header -->
        <h3 class="history-header">View your past equipment transactions</h3>

        <!-- Search and Filters Bar -->
        <div class="filters-row">
            <!-- Search bar on the left -->
            <div class="search-wrapper">
                <input type="text" id="searchHistory" placeholder="Search equipment..." autocomplete="off">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
            </div>
            
            <!-- Select dropdowns on the right -->
            <div class="filters-right">
                <div class="filter-wrapper">
                    <select id="statusHistoryFilter" class="filter-select">
                        <option value="All">All</option>
                        <option value="Ready for Pickup">Ready for Pickup</option>
                        <option value="Borrowed">Borrowed</option>
                        <option value="Overdue">Overdue</option>
                        <option value="Returned">Returned</option>
                        <option value="Late Return">Late Return</option>
                        <option value="Pending Confirmation">Pending Confirmation</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="Expired">Expired</option>
                        <option value="Not Claimed">Not Claimed</option>
                        <option value="Reservation Cancelled">Reservation Cancelled</option>
                    </select>
                </div>
                <div class="filter-wrapper">
                    <select id="sortHistoryFilter" class="filter-select sort-select">
                        <option value="latest">Sort by: Latest</option>
                        <option value="oldest">Sort by: Oldest</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Table Container -->
        {{-- The My Requests table component (user/css/usertable.css) --}}
        <div class="table-container card requests-table-card history-table-card">
            {{-- Fixed column widths (same approach as the Department tables): the
                 columns keep their size and position whatever rows are shown, and
                 long text ends in "…" instead of widening a column. --}}
            <table class="history-table">
                <colgroup>
                    <col class="w-no">
                    <col class="w-equipment">
                    <col class="w-date">
                    <col class="w-date">
                    <col class="w-status">
                    <col class="w-remarks">
                    <col class="w-action">
                </colgroup>
                <thead>
                    <tr>
                        <th class="col-no">No.</th>
                        <th class="col-equipment">Equipment</th>
                        <th class="col-date">Borrow Date</th>
                        <th class="col-date">Return Date</th>
                        <th class="col-status">Status</th>
                        <th class="col-remarks">Remarks</th>
                        <th class="col-action">Action</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    @forelse ($historyItems as $idx => $item)
                        <tr class="history-row"
                            data-equipment="{{ $item['equipment'] }}"
                            data-category="{{ $item['category'] }}"
                            data-borrow-date="{{ $item['borrow_date'] }}"
                            data-return-date="{{ $item['return_date'] }}"
                            data-status="{{ $item['status'] }}"
                            data-badge="{{ $item['badge_class'] }}"
                            data-remarks="{{ $item['remarks'] }}"
                            data-condition="{{ $item['condition'] }}"
                            data-handled-by="{{ $item['handled_by'] }}"
                            data-penalties="{{ $item['penalties'] }}"
                            data-img="{{ $item['img'] }}"
                            data-timestamp="{{ $item['timestamp'] }}">
                            <td class="row-index col-no">{{ $idx + 1 }}</td>
                            <td class="equipment-col">
                                <div class="eq-cell" style="display: flex; align-items: center; gap: 12px;">
                                    <img src="{{ $item['img'] }}" alt="{{ $item['equipment'] }}" class="eq-thumb" style="width: 42px; height: 42px; border-radius: 8px; object-fit: cover; background: #fff;" onerror="this.onerror=null; this.src='{{ asset('images/EquipTrack_logo.png') }}';">
                                    <div class="eq-info" style="display: flex; flex-direction: column;">
                                        <span class="eq-title" style="font-weight: 600; color: var(--text-main);" title="{{ $item['equipment'] }}">{{ $item['equipment'] }}</span>
                                        <span class="eq-sub" style="font-size: 12px; color: var(--text-muted);">{{ $item['category'] }}{{ $item['quantity'] > 1 ? ' · ' . $item['quantity'] . ' units' : '' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="col-date" title="{{ $item['borrow_date'] }}">{{ $item['borrow_date'] }}</td>
                            <td class="col-date" title="{{ $item['return_date'] }}">{{ $item['return_date'] }}</td>
                            <td class="col-status">
                                <span class="detail-status-badge {{ $item['status_class'] }}" title="{{ $item['status'] }}">{{ $item['status'] }}</span>
                            </td>
                            {{-- One line ending in "…"; the full text is in the tooltip and the details window --}}
                            <td class="col-remarks">
                                <span class="remarks-text" @if ($item['remarks'] !== '—') title="{{ $item['remarks'] }}" @endif>{{ $item['remarks'] }}</span>
                            </td>
                            <td class="col-action">
                                <button type="button" class="btn-action-view" aria-label="View details of {{ $item['equipment'] }}">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i> View
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    {{-- No rows to show: one message row under the header (same as the
                         Department tables), so the header and columns stay in place. --}}
                    <tr class="table-empty-row" id="historyEmptyRow" @unless ($historyItems->isEmpty()) style="display: none;" @endunless>
                        <td colspan="7" class="table-empty-cell">
                            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                            <span id="historyEmptyText">No borrowing history yet. Your past borrowing transactions will appear here.</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination Footer (same design as the Department tables) -->
            <div class="table-footer-pagination" id="paginationContainer" @if ($historyItems->isEmpty()) style="display: none;" @endif>
                <span class="pagination-info" id="paginationInfo">Showing 0 of 0 transactions</span>
                <div class="pagination-controls" id="paginationButtons">
                    <!-- Buttons added dynamically -->
                </div>
            </div>
        </div>
<!-- Transaction Details Modal -->
    <div class="modal-overlay" id="historyModal">
        <div class="modal-card history-modal-card">
            <div class="modal-inner-card">
                <button class="modal-close" id="closeHistoryBtn">&times;</button>
                
                <h3 class="modal-title-center">Transaction Details</h3>
                <p class="modal-subtitle-center">Full historical log of this borrowed equipment</p>
                
                <div class="detail-main-content">
                    <!-- Left Side: Image Preview & Status -->
                    <div class="detail-left-side">
                        <div class="detail-img-container">
                            <img src="" alt="Equipment Image" id="historyEqImg">
                        </div>
                        <div class="detail-form-group">
                            <label class="detail-form-label">Return Status</label>
                            <div class="status-badge-wrapper">
                                <span class="detail-status-badge" id="historyStatusBadge">Returned</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Side: Details Form Grid -->
                    <div class="detail-right-side">
                        <div class="detail-form-grid">
                            <div class="detail-form-group">
                                <label class="detail-form-label">Equipment Name</label>
                                <input type="text" id="historyEqName" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Category</label>
                                <input type="text" id="historyEqCategory" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Borrow Date</label>
                                <input type="text" id="historyBorrowDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Return Date</label>
                                <input type="text" id="historyReturnDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Condition on Return</label>
                                <input type="text" id="historyCondition" class="detail-form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lower Section: Remarks & Handled By -->
                <div class="detail-lower-section">
                    <div class="detail-form-group">
                        <label class="detail-form-label">Transaction Remarks</label>
                        <textarea id="historyRemarks" class="detail-form-control textarea-control" readonly rows="3"></textarea>
                    </div>
                    <div class="detail-form-group">
                        <label class="detail-form-label">Received By</label>
                        <input type="text" id="historyHandledBy" class="detail-form-control" readonly>
                    </div>
                    <div class="detail-form-group">
                        <label class="detail-form-label">Penalties</label>
                        <input type="text" id="historyPenalties" class="detail-form-control" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>

        const searchInput = document.getElementById('searchHistory');
        const statusFilter = document.getElementById('statusHistoryFilter');
        const sortFilter = document.getElementById('sortHistoryFilter');
        const tableBody = document.getElementById('historyTableBody');
        const historyModal = document.getElementById('historyModal');
        const closeHistoryBtn = document.getElementById('closeHistoryBtn');

        // Pagination elements & state (same behavior as the Admin tables)
        const paginationContainer = document.getElementById('paginationContainer');
        const paginationInfo = document.getElementById('paginationInfo');
        const paginationButtons = document.getElementById('paginationButtons');
        let currentPage = 1;
        const pageSize = 10;

        // Search/filter/sort changed: go back to the first page, then re-render
        function filterHistory() {
            currentPage = 1;
            updateHistoryTable();
        }

        // Pagination buttons: same design and behavior as the Department tables
        // (previous / up to 5 page numbers around the current page / next)
        function renderPaginationButtons(totalPages) {
            paginationButtons.innerHTML = '';

            const makeBtn = (html, page, extraClass, label) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'page-btn' + (extraClass ? ' ' + extraClass : '');
                btn.innerHTML = html;
                if (label) btn.setAttribute('aria-label', label);
                if (extraClass === 'disabled') {
                    btn.disabled = true;
                } else if (extraClass !== 'active') {
                    btn.addEventListener('click', () => {
                        currentPage = page;
                        updateHistoryTable(false);
                    });
                }
                paginationButtons.appendChild(btn);
            };

            makeBtn('<i class="fa-solid fa-chevron-left"></i>', currentPage - 1, currentPage <= 1 ? 'disabled' : '', 'Previous page');

            let start = Math.max(1, currentPage - 2);
            const end = Math.min(totalPages, start + 4);
            start = Math.max(1, end - 4);
            for (let i = start; i <= end; i++) {
                makeBtn(String(i), i, i === currentPage ? 'active' : '');
            }

            makeBtn('<i class="fa-solid fa-chevron-right"></i>', currentPage + 1, currentPage >= totalPages ? 'disabled' : '', 'Next page');
        }

        // Filter and Sort function
        function updateHistoryTable(applySort = true) {
            const query = searchInput.value.toLowerCase().trim();
            const selectedStatus = statusFilter.value;
            const sortOrder = sortFilter.value;
            
            // Get all rows
            const rows = Array.from(tableBody.querySelectorAll('.history-row'));
            let visibleCount = 0;

            rows.forEach(row => {
                const equipment = row.getAttribute('data-equipment').toLowerCase();
                const status = row.getAttribute('data-status');

                const matchesSearch = equipment.includes(query);
                const matchesFilter = (selectedStatus === 'All' || status === selectedStatus);

                if (matchesSearch && matchesFilter) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Sort remaining rows
            if (applySort) rows.sort((a, b) => {
                const dateA = new Date(a.getAttribute('data-timestamp'));
                const dateB = new Date(b.getAttribute('data-timestamp'));
                return sortOrder === 'latest' ? dateB - dateA : dateA - dateB;
            });

            // Re-append sorted rows to table body
            rows.forEach(row => tableBody.appendChild(row));

            // Re-calculate visible indices
            // Paginate the matching rows; numbering continues across pages
            const matchedRows = rows.filter(row => row.style.display !== 'none');
            const totalEntries = matchedRows.length;
            const totalPages = Math.ceil(totalEntries / pageSize);

            if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, totalEntries);
            const pageRows = matchedRows.slice(startIndex, endIndex);

            rows.forEach(row => {
                row.style.display = 'none';
                row.classList.remove('last-visible-row');
            });
            pageRows.forEach((row, i) => {
                row.style.display = '';
                row.querySelector('.row-index').textContent = startIndex + i + 1;
            });
            if (pageRows.length) {
                pageRows[pageRows.length - 1].classList.add('last-visible-row');
            }

            if (totalEntries > 0) {
                paginationInfo.textContent = `Showing ${startIndex + 1}-${endIndex} of ${totalEntries} transactions`;
                renderPaginationButtons(totalPages);
            }

            // No rows: the message row inside the table (the header stays), no pagination bar
            const emptyRow = document.getElementById('historyEmptyRow');
            document.getElementById('historyEmptyText').textContent = rows.length === 0
                ? 'No borrowing history yet. Your past borrowing transactions will appear here.'
                : 'No records match your search or filters.';
            tableBody.appendChild(emptyRow);
            emptyRow.style.display = visibleCount === 0 ? '' : 'none';
            paginationContainer.style.display = visibleCount === 0 ? 'none' : '';
        }

        // Attach listeners
        searchInput.addEventListener('input', filterHistory);
        statusFilter.addEventListener('change', filterHistory);
        sortFilter.addEventListener('change', filterHistory);

        // Initial render (keeps the server-provided order, just paginated)
        updateHistoryTable(false);

        // Details window: the View button, or a click anywhere on the row
        const historyRows = document.querySelectorAll('.history-row');
        historyRows.forEach(row => {
            row.addEventListener('click', () => {
                const eqName = row.getAttribute('data-equipment');
                const category = row.getAttribute('data-category');
                const borrowDate = row.getAttribute('data-borrow-date');
                const returnDate = row.getAttribute('data-return-date');
                const status = row.getAttribute('data-status');
                const remarks = row.getAttribute('data-remarks');
                const condition = row.getAttribute('data-condition');
                const handledBy = row.getAttribute('data-handled-by');
                const imgUrl = row.getAttribute('data-img');

                document.getElementById('historyEqName').value = eqName;
                document.getElementById('historyEqCategory').value = category;
                document.getElementById('historyBorrowDate').value = borrowDate;
                document.getElementById('historyReturnDate').value = returnDate;
                document.getElementById('historyCondition').value = condition;
                document.getElementById('historyRemarks').value = remarks;
                document.getElementById('historyHandledBy').value = handledBy;
                document.getElementById('historyPenalties').value = row.getAttribute('data-penalties') || 'None';

                const imgElement = document.getElementById('historyEqImg');
                if (imgUrl) {
                    imgElement.src = imgUrl;
                    imgElement.style.display = 'block';
                } else {
                    imgElement.style.display = 'none';
                }

                const badge = document.getElementById('historyStatusBadge');
                badge.textContent = status;
                badge.className = 'detail-status-badge'; // Reset class
                // Badge variant is derived on the server (approved / borrowed / pending / rejected)
                badge.classList.add('status-' + (row.getAttribute('data-badge') || 'approved'));

                historyModal.classList.add('show');
            });
        });

        // Close functions
        function closeHistoryModal() {
            historyModal.classList.remove('show');
        }

        closeHistoryBtn.addEventListener('click', closeHistoryModal);
        historyModal.addEventListener('click', (e) => {
            if (e.target === historyModal) {
                closeHistoryModal();
            }
        });
    
</script>
@endpush
