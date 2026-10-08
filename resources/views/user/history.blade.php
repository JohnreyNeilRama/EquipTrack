@extends('layouts.user')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('user/css/userhistory.css') }}">
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
                        <option value="Borrowed">Borrowed</option>
                        <option value="Overdue">Overdue</option>
                        <option value="Returned">Returned</option>
                        <option value="Late Return">Late Return</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
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
        <div class="table-container card history-table-card">
            <table @if ($historyItems->isEmpty()) style="display: none;" @endif>
                <thead>
                    <tr>
                        <th class="col-no">No.</th>
                        <th>Equipment</th>
                        <th>Borrow Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Remarks</th>
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
                            data-img="{{ $item['img'] }}"
                            data-timestamp="{{ $item['timestamp'] }}">
                            <td class="row-index">{{ $idx + 1 }}</td>
                            <td class="equipment-col">
                                <div class="eq-cell" style="display: flex; align-items: center; gap: 12px;">
                                    <img src="{{ $item['img'] }}" alt="{{ $item['equipment'] }}" class="eq-thumb" style="width: 42px; height: 42px; border-radius: 8px; object-fit: cover; background: #fff;" onerror="this.onerror=null; this.src='{{ asset('images/EquipTrack_logo.png') }}';">
                                    <div class="eq-info" style="display: flex; flex-direction: column;">
                                        <span class="eq-title" style="font-weight: 600; color: var(--text-main);">{{ $item['equipment'] }}</span>
                                        <span class="eq-sub" style="font-size: 12px; color: var(--text-muted);">{{ $item['category'] }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $item['borrow_date'] }}</td>
                            <td>{{ $item['return_date'] }}</td>
                            <td>
                                <span class="status-text {{ $item['status_class'] }}">{{ $item['status'] }}</span>
                            </td>
                            <td class="col-remarks">{{ $item['remarks'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <!-- Empty state illustration if search yields nothing or no data -->
            <div id="historyEmptyState" class="empty-state-container" style="display: {{ $historyItems->isEmpty() ? 'flex' : 'none' }};">
                <i class="fa-solid fa-clock-rotate-left empty-state-icon"></i>
                <h4>No transaction history</h4>
                <p>Your past borrowing history and transaction logs will appear here.</p>
            </div>

            <!-- Pagination Footer -->
            <div class="pagination-container" id="paginationContainer">
                <span class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                <div class="pagination-buttons" id="paginationButtons">
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
                        <textarea id="historyRemarks" class="detail-form-control textarea-control" readonly rows="2"></textarea>
                    </div>
                    <div class="detail-form-group">
                        <label class="detail-form-label">Received By</label>
                        <input type="text" id="historyHandledBy" class="detail-form-control" readonly>
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

        // Render pagination buttons dynamically (same behavior as Admin Users)
        function renderPaginationButtons(totalPages) {
            paginationButtons.innerHTML = '';

            const prevBtn = document.createElement('button');
            prevBtn.className = 'btn-page';
            prevBtn.innerHTML = '<i class="fa-solid fa-angle-left"></i>';
            prevBtn.disabled = currentPage === 1;
            prevBtn.addEventListener('click', () => {
                currentPage--;
                updateHistoryTable();
            });
            paginationButtons.appendChild(prevBtn);

            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.className = `btn-page ${currentPage === i ? 'active' : ''}`;
                pageBtn.textContent = i;
                pageBtn.addEventListener('click', () => {
                    currentPage = i;
                    updateHistoryTable();
                });
                paginationButtons.appendChild(pageBtn);
            }

            const nextBtn = document.createElement('button');
            nextBtn.className = 'btn-page';
            nextBtn.innerHTML = '<i class="fa-solid fa-angle-right"></i>';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.addEventListener('click', () => {
                currentPage++;
                updateHistoryTable();
            });
            paginationButtons.appendChild(nextBtn);
        }

        // Dark Mode Toggle Logic




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
                paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalEntries} entries`;
                renderPaginationButtons(totalPages);
            }

            // Empty state display
            const emptyState = document.getElementById('historyEmptyState');
            const tableElement = document.querySelector('.history-table-card table');
            if (visibleCount === 0) {
                emptyState.style.display = 'flex';
                tableElement.style.display = 'none';
                paginationContainer.style.display = 'none';
            } else {
                emptyState.style.display = 'none';
                tableElement.style.display = 'table';
                paginationContainer.style.display = 'flex';
            }
        }

        // Attach listeners
        searchInput.addEventListener('input', filterHistory);
        statusFilter.addEventListener('change', filterHistory);
        sortFilter.addEventListener('change', filterHistory);

        // Initial render (keeps the server-provided order, just paginated)
        updateHistoryTable(false);

        // Click Row Event for Details Modal
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
