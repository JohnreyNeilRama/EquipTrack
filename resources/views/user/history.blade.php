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
                        <option value="Returned">Returned</option>
                        <option value="Late Return">Late Return</option>
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
            <table style="display: none;">
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
                    <!-- Dynamic transaction rows -->
                </tbody>
            </table>
            <!-- Empty state illustration if search yields nothing or no data -->
            <div id="historyEmptyState" class="empty-state-container" style="display: flex;">
                <i class="fa-solid fa-clock-rotate-left empty-state-icon"></i>
                <h4>No transaction history</h4>
                <p>Your past borrowing history and transaction logs will appear here.</p>
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

        // Dark Mode Toggle Logic




        // Filter and Sort function
        function updateHistoryTable() {
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
            rows.sort((a, b) => {
                const dateA = new Date(a.getAttribute('data-timestamp'));
                const dateB = new Date(b.getAttribute('data-timestamp'));
                return sortOrder === 'latest' ? dateB - dateA : dateA - dateB;
            });

            // Re-append sorted rows to table body
            rows.forEach(row => tableBody.appendChild(row));

            // Re-calculate visible indices
            let visibleIndex = 1;
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    row.querySelector('.row-index').textContent = visibleIndex++;
                }
            });

            // Empty state display
            const emptyState = document.getElementById('historyEmptyState');
            const tableElement = document.querySelector('.history-table-card table');
            if (visibleCount === 0) {
                emptyState.style.display = 'flex';
                tableElement.style.display = 'none';
            } else {
                emptyState.style.display = 'none';
                tableElement.style.display = 'table';
            }
        }

        // Attach listeners
        searchInput.addEventListener('input', updateHistoryTable);
        statusFilter.addEventListener('change', updateHistoryTable);
        sortFilter.addEventListener('change', updateHistoryTable);

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
                badge.classList.add('status-' + (status === 'Late Return' ? 'rejected' : 'approved'));

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
