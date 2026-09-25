@extends('layouts.user')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('user/css/userreturns.css') }}">
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
            <table @if ($returnItems->isEmpty()) style="display: none;" @endif>
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
                    @forelse ($returnItems as $idx => $item)
                        @php
                            $eq = $item->equipment;
                            $eqName = $eq->name ?? 'Unknown equipment';
                            $eqCat = $eq->category?->category_name ?: 'General';
                            $bDateFormatted = $fmt($item->borrow_date ?: $item->date_needed);
                            $dueDateRaw = $item->transaction?->due_date ?: $item->due_date ?: $item->return_date;
                            $dDateFormatted = $fmt($dueDateRaw);
                            // Overdue once the due date is strictly before today
                            $status = ($dueDateRaw && \Illuminate\Support\Carbon::parse($dueDateRaw)->startOfDay()->lt(now()->startOfDay()))
                                ? 'Overdue'
                                : 'Borrowed';
                            $imgUrl = $resolveImg($eq->image ?? '');
                        @endphp
                        <tr class="return-row"
                            data-id="{{ $item->request_id }}"
                            data-equipment="{{ $eqName }}"
                            data-category="{{ $eqCat }}"
                            data-borrow-date="{{ $bDateFormatted }}"
                            data-due-date="{{ $dDateFormatted }}"
                            data-status="{{ $status }}"
                            data-img="{{ $imgUrl }}">
                            <td class="row-index">{{ $idx + 1 }}</td>
                            <td class="equipment-col">
                                <div class="eq-cell" style="display: flex; align-items: center; gap: 12px;">
                                    <img src="{{ $imgUrl }}" alt="{{ $eqName }}" class="eq-thumb" style="width: 42px; height: 42px; border-radius: 8px; object-fit: cover; background: #fff;" onerror="this.onerror=null; this.src='{{ asset('images/EquipTrack_logo.png') }}';">
                                    <div class="eq-info" style="display: flex; flex-direction: column;">
                                        <span class="eq-title" style="font-weight: 600; color: var(--text-main);">{{ $eqName }}</span>
                                        <span class="eq-sub" style="font-size: 12px; color: var(--text-muted);">{{ $eqCat }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $bDateFormatted }}</td>
                            <td @if ($status === 'Overdue') style="color: #ef4444; font-weight: 600;" @endif>{{ $dDateFormatted }}</td>
                            <td>
                                <span class="status-text status-{{ strtolower($status) }}">{{ $status }}</span>
                            </td>
                            <td>
                                <button type="button" class="btn-return-action" onclick="openReturnModal(this)">
                                    <i class="fa-solid fa-rotate-left"></i> Return Item
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <!-- Empty state illustration if all items returned -->
            <div id="emptyState" class="empty-state-container" style="display: {{ $returnItems->isEmpty() ? 'flex' : 'none' }};">
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
                    @csrf
                    <input type="hidden" name="request_id" id="returnRequestId" value="">
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
        const returnModal = document.getElementById('returnModal');
        const closeReturnBtn = document.getElementById('closeReturnBtn');
        const cancelReturnBtn = document.getElementById('cancelReturnBtn');
        const successToast = document.getElementById('successToast');
        const returnForm = document.getElementById('returnForm');
        const submitReturnBtn = returnForm.querySelector('.btn-modal-submit');

        let activeRow = null;
        let toastTimer = null;

        // Set return date to current local date
        const today = new Date();
        const formattedToday = today.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        document.getElementById('returnDateToday').value = formattedToday;

        // Toast helper (success / error)
        function showToast(title, message, type = 'success') {
            const toastTitle = successToast.querySelector('.toast-title');
            const toastDesc = successToast.querySelector('.toast-desc');
            const toastIcon = successToast.querySelector('.toast-icon');
            const toastContent = successToast.querySelector('.toast-content');

            toastTitle.textContent = title;
            toastDesc.textContent = message;

            if (type === 'success') {
                toastIcon.className = 'fa-solid fa-circle-check toast-icon';
                toastIcon.style.color = '#10b981';
                toastContent.style.borderLeft = '4px solid #10b981';
            } else {
                toastIcon.className = 'fa-solid fa-circle-xmark toast-icon';
                toastIcon.style.color = '#ef4444';
                toastContent.style.borderLeft = '4px solid #ef4444';
            }

            successToast.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => successToast.classList.remove('show'), 4000);
        }

        // Search filtering function
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.return-row');
            const emptyState = document.getElementById('emptyState');
            const tableElement = document.querySelector('.returns-table-card table');
            let visibleCount = 0;

            rows.forEach(row => {
                const equipment = (row.getAttribute('data-equipment') || '').toLowerCase();
                const category = (row.getAttribute('data-category') || '').toLowerCase();
                if (query === '' || equipment.includes(query) || category.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const hasRows = rows.length > 0;
            if (visibleCount === 0 && (hasRows || query !== '')) {
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

            document.getElementById('returnRequestId').value = activeRow.getAttribute('data-id') || '';
            document.getElementById('returnEqName').value = activeRow.getAttribute('data-equipment') || '';
            document.getElementById('returnEqCategory').value = activeRow.getAttribute('data-category') || '';
            document.getElementById('returnBorrowDate').value = activeRow.getAttribute('data-borrow-date') || '';
            document.getElementById('returnDueDate').value = activeRow.getAttribute('data-due-date') || '';
            document.getElementById('returnRemarks').value = '';
            document.getElementById('returnCondition').value = 'Good';

            const imgUrl = activeRow.getAttribute('data-img');
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


        // Submit the return to the backend and update the UI on success
        async function submitReturn(event) {
            event.preventDefault();

            const row = activeRow;
            const requestId = document.getElementById('returnRequestId').value;
            const eqName = document.getElementById('returnEqName').value;
            const condition = document.getElementById('returnCondition').value;
            const remarks = document.getElementById('returnRemarks').value.trim();
            const tokenInput = returnForm.querySelector('input[name="_token"]');

            if (!requestId) {
                showToast('Error', 'Missing borrow record. Please refresh the page and try again.', 'error');
                return;
            }

            submitReturnBtn.disabled = true;
            submitReturnBtn.textContent = 'Processing...';

            try {
                const response = await fetch('{{ route('user.returns.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': tokenInput ? tokenInput.value : '',
                    },
                    body: JSON.stringify({
                        request_id: requestId,
                        condition: condition,
                        remarks: remarks,
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (response.ok && data.success) {
                    closeModal();
                    showToast('Success', data.message || `Returned ${eqName} in ${condition} condition.`, 'success');

                    if (row) {
                        row.style.transition = 'all 0.5s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-20px)';

                        setTimeout(() => {
                            row.remove();
                            reindexTable();
                        }, 500);
                    }
                } else {
                    closeModal();
                    let message = data.message;
                    if (!message && data.errors) {
                        const firstError = Object.values(data.errors)[0];
                        message = Array.isArray(firstError) ? firstError[0] : firstError;
                    }
                    showToast('Error', message || 'Unable to process the return. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Return submission failed:', error);
                closeModal();
                showToast('System Error', 'An unexpected error occurred while processing the return.', 'error');
            } finally {
                submitReturnBtn.disabled = false;
                submitReturnBtn.textContent = 'Confirm Return';
            }
        }

        // Recalculate Row Indices & Show Empty State if no rows left
        function reindexTable() {
            const remainingRows = document.querySelectorAll('.return-row');
            const emptyState = document.getElementById('emptyState');
            const tableElement = document.querySelector('.returns-table-card table');

            if (remainingRows.length === 0) {
                emptyState.style.display = 'flex';
                tableElement.style.display = 'none';
            } else {
                remainingRows.forEach((row, index) => {
                    row.querySelector('.row-index').textContent = index + 1;
                });
            }
        }
</script>
@endpush

