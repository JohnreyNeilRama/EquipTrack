@extends('layouts.user')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('user/css/userreturns.css') }}">
@endpush

@section('content')
<!-- Page Subtitle Header -->
        <h3 class="manage-return-header">Manage and return your borrowed equipment</h3>

        <!-- Search Bar Aligned Right -->
        <div class="search-row-right">
            <div class="search-wrapper">
                <input type="text" id="searchReturns" placeholder="Search equipment..." autocomplete="off">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
            </div>
        </div>

        <!-- Table Container -->
        <div class="table-container card returns-table-card">
            <table style="display: none;">
                <thead>
                    <tr>
                        <th class="col-no">No.</th>
                        <th>Equipment</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th class="col-action">Action</th>
                    </tr>
                </thead>
                <tbody id="returnsTableBody">
                    <!-- Dynamic return items -->
                </tbody>
            </table>
            <!-- Empty state illustration if all items returned -->
            <div id="emptyState" class="empty-state-container" style="display: flex;">
                <i class="fa-solid fa-circle-check empty-state-icon"></i>
                <h4>All items returned!</h4>
                <p>You currently do not have any borrowed equipment to return.</p>
            </div>
        </div>
<!-- Return Modal -->
    <div class="modal-overlay" id="returnModal">
        <div class="modal-card return-modal-card">
            <div class="modal-inner-card">
                <button class="modal-close" id="closeReturnBtn">&times;</button>
                
                <h3 class="modal-title-center">Return Equipment</h3>
                <p class="modal-subtitle-center">Verify return details and specify the item condition</p>
                
                <form id="returnForm" onsubmit="submitReturn(event)">
                    <div class="detail-main-content">
                        <!-- Left Side: Image Preview & Condition Select -->
                        <div class="detail-left-side">
                            <div class="detail-img-container">
                                <img src="" alt="Equipment Image" id="returnEqImg">
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Item Condition</label>
                                <div class="select-wrapper">
                                    <select id="returnCondition" class="detail-form-control select-control" required>
                                        <option value="Good">Good / Working</option>
                                        <option value="Damaged">Damaged</option>
                                        <option value="Lost">Lost</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Side: Details Form Grid -->
                        <div class="detail-right-side">
                            <div class="detail-form-grid">
                                <div class="detail-form-group">
                                    <label class="detail-form-label">Equipment Name</label>
                                    <input type="text" id="returnEqName" class="detail-form-control" readonly>
                                </div>
                                <div class="detail-form-group">
                                    <label class="detail-form-label">Category</label>
                                    <input type="text" id="returnEqCategory" class="detail-form-control" readonly>
                                </div>
                                <div class="detail-form-group">
                                    <label class="detail-form-label">Borrow Date</label>
                                    <input type="text" id="returnBorrowDate" class="detail-form-control" readonly>
                                </div>
                                <div class="detail-form-group">
                                    <label class="detail-form-label">Due Date</label>
                                    <input type="text" id="returnDueDate" class="detail-form-control" readonly>
                                </div>
                                <div class="detail-form-group">
                                    <label class="detail-form-label">Return Date</label>
                                    <input type="text" id="returnDateToday" class="detail-form-control" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lower Section: Remarks -->
                    <div class="detail-lower-section">
                        <div class="detail-form-group">
                            <label class="detail-form-label">Remarks / Return Notes</label>
                            <textarea id="returnRemarks" class="detail-form-control textarea-control" placeholder="Add any comments on item condition, usage notes, or missing accessories..." rows="2"></textarea>
                        </div>
                        <div class="modal-form-actions">
                            <button type="button" class="btn-modal-cancel" id="cancelReturnBtn">Cancel</button>
                            <button type="submit" class="btn-modal-submit">Confirm Return</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Success Notification -->
    <div class="toast-notification" id="successToast">
        <div class="toast-content">
            <i class="fa-solid fa-circle-check toast-icon"></i>
            <div class="toast-message">
                <span class="toast-title">Success</span>
                <span class="toast-desc" id="toastDescMsg">Equipment returned successfully!</span>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>

        const searchInput = document.getElementById('searchReturns');
        const returnRows = document.querySelectorAll('.return-row');
        const returnModal = document.getElementById('returnModal');
        const closeReturnBtn = document.getElementById('closeReturnBtn');
        const cancelReturnBtn = document.getElementById('cancelReturnBtn');
        const successToast = document.getElementById('successToast');
        
        let activeRow = null;

        // Dark Mode Toggle Logic




        // Set return date to current local date
        const today = new Date();
        const formattedToday = today.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        document.getElementById('returnDateToday').value = formattedToday;

        // Search filtering function
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;

            returnRows.forEach(row => {
                const equipment = row.getAttribute('data-equipment').toLowerCase();
                if (equipment.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const emptyState = document.getElementById('emptyState');
            const tableElement = document.querySelector('.returns-table-card table');
            if (visibleCount === 0 && query !== '') {
                emptyState.style.display = 'flex';
                tableElement.style.opacity = '0.3';
            } else {
                emptyState.style.display = 'none';
                tableElement.style.opacity = '1';
            }
        });

        // Open Modal and Populate Fields
        function openReturnModal(button) {
            activeRow = button.closest('.return-row');
            
            const eqName = activeRow.getAttribute('data-equipment');
            const category = activeRow.getAttribute('data-category');
            const borrowDate = activeRow.getAttribute('data-borrow-date');
            const dueDate = activeRow.getAttribute('data-due-date');
            const imgUrl = activeRow.getAttribute('data-img');

            document.getElementById('returnEqName').value = eqName;
            document.getElementById('returnEqCategory').value = category;
            document.getElementById('returnBorrowDate').value = borrowDate;
            document.getElementById('returnDueDate').value = dueDate;
            document.getElementById('returnRemarks').value = '';
            document.getElementById('returnCondition').value = 'Good';

            const imgElement = document.getElementById('returnEqImg');
            if (imgUrl) {
                imgElement.src = imgUrl;
                imgElement.style.display = 'block';
            } else {
                imgElement.style.display = 'none';
            }

            returnModal.classList.add('show');
        }

        // Close Modal Functions
        function closeModal() {
            returnModal.classList.remove('show');
            activeRow = null;
        }

        closeReturnBtn.addEventListener('click', closeModal);
        cancelReturnBtn.addEventListener('click', closeModal);

        returnModal.addEventListener('click', (e) => {
            if (e.target === returnModal) {
                closeModal();
            }
        });

        // Submit return request
        function submitReturn(event) {
            event.preventDefault();
            
            const eqName = document.getElementById('returnEqName').value;
            const condition = document.getElementById('returnCondition').value;

            // Close the modal
            closeModal();

            // Set toast text and show it
            document.getElementById('toastDescMsg').textContent = `Returned ${eqName} in ${condition} condition.`;
            successToast.classList.add('show');

            // Hide active row in table
            if (activeRow) {
                activeRow.style.transition = 'all 0.5s ease';
                activeRow.style.opacity = '0';
                activeRow.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    activeRow.remove();
                    reindexTable();
                }, 500);
            }

            // Hide Toast after 4 seconds
            setTimeout(() => {
                successToast.classList.remove('show');
            }, 4000);
        }

        // Recalculate Row Indices & Show Empty State if no rows left
        function reindexTable() {
            const remainingRows = document.querySelectorAll('.return-row');
            if (remainingRows.length === 0) {
                document.getElementById('emptyState').style.display = 'flex';
                document.querySelector('.returns-table-card table').style.display = 'none';
            } else {
                remainingRows.forEach((row, index) => {
                    row.querySelector('.row-index').textContent = index + 1;
                });
            }
        }
    
</script>
@endpush
