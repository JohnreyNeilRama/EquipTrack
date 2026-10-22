{{-- Shared Penalties page (Admin + Lab Personnel).
     $party: 'admin' (all departments, can add penalties) or 'dept' (own department).
     EquipTrack only records penalties; payment happens outside the system. --}}
@php($isAdmin = $party === 'admin')
    <div class="requests-container">
        <div class="equipment-header-section penalties-header">
            <div>
                <h2>Penalties</h2>
                <p>
                    @if ($isAdmin)
                        Damage and penalty records from every department. Payment is handled outside the system; record here when a penalty is settled or waived.
                    @else
                        Penalties recorded on your department's equipment. Payment is handled outside the system; record here when a penalty is settled or waived.
                    @endif
                </p>
            </div>
            @if ($isAdmin)
                <button type="button" class="btn-add-penalty" id="addPenaltyBtn">
                    <i class="fa-solid fa-plus"></i> Add Penalty
                </button>
            @endif
        </div>

        <div class="requests-summary-section">
            <h4 class="section-subtitle">Summary Cards</h4>
            <div class="summary-cards-grid penalties-summary-grid">
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumPending">0</span>
                        <div class="summary-card-icon icon-orange"><i class="fa-solid fa-hourglass-half"></i></div>
                    </div>
                    <span class="summary-card-label">Pending</span>
                </div>
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumPendingAmount">₱0.00</span>
                        <div class="summary-card-icon icon-red"><i class="fa-solid fa-peso-sign"></i></div>
                    </div>
                    <span class="summary-card-label">Pending Amount</span>
                </div>
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumSettled">0</span>
                        <div class="summary-card-icon icon-green"><i class="fa-solid fa-circle-check"></i></div>
                    </div>
                    <span class="summary-card-label">Settled</span>
                </div>
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumWaived">0</span>
                        <div class="summary-card-icon icon-blue"><i class="fa-solid fa-hand-holding-heart"></i></div>
                    </div>
                    <span class="summary-card-label">Waived</span>
                </div>
            </div>
        </div>

        <div class="controls-bar">
            <div class="controls-left penalties-controls">
                <div class="search-box-wrapper">
                    <input type="text" id="searchPenalties" placeholder="Search by user, equipment or penalty ID..." aria-label="Search penalties">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <div class="filter-select-wrapper">
                    <select id="filterStatus" aria-label="Filter by status">
                        <option value="all">All Status</option>
                        @foreach (\App\Models\Penalty::STATUSES as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                <div class="filter-select-wrapper">
                    <select id="filterType" aria-label="Filter by type">
                        <option value="all">All Types</option>
                        @foreach (\App\Models\Penalty::TYPES as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
                @if ($isAdmin)
                    <div class="filter-select-wrapper">
                        <select id="filterDepartment" aria-label="Filter by department">
                            <option value="all">All Departments</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department }}">{{ $department }}</option>
                            @endforeach
                        </select>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                @endif
            </div>
        </div>

        <div class="table-container card admin-table-card">
            <table class="penalties-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th class="col-fit">Penalty ID</th>
                        <th class="col-user">User</th>
                        <th class="col-equipment">Equipment</th>
                        <th class="col-fit">Type</th>
                        <th class="col-fit col-amount">Amount</th>
                        <th class="col-fit">Recorded</th>
                        <th class="col-fit">Status</th>
                        <th class="col-fit action-column">Action</th>
                    </tr>
                </thead>
                <tbody id="penaltiesTableBody"></tbody>
            </table>

            <div class="pagination-container" id="paginationContainer">
                <span class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                <div class="pagination-buttons" id="paginationButtons"></div>
            </div>
        </div>
    </div>

    {{-- Damage & Penalty Record, laid out like the department's Return Confirmation form --}}
    @include('partials.penalties.record')

    {{-- Edit / Settle / Waive / Add form --}}
    <div class="modal-overlay" id="penaltyFormModal" role="dialog" aria-modal="true" aria-labelledby="penaltyFormTitle">
        <div class="modal-card eq-modal-card penalty-form-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top" id="penaltyFormSubtitle">—</p>
            </div>
            <div class="modal-inner-card penalty-sheet">
                <button type="button" class="modal-close" id="closePenaltyFormBtn" aria-label="Close">&times;</button>

                <header class="penalty-sheet-head">
                    <h3 class="penalty-sheet-title" id="penaltyFormTitle">—</h3>
                    {{-- Which penalty the form acts on (hidden when adding one) --}}
                    <p class="penalty-sheet-meta" id="penaltyFormContext" hidden></p>
                </header>

                <form id="penaltyForm" class="penalty-form" novalidate>
                    <div class="penalty-sheet-body penalty-form-body">
                        @if ($isAdmin)
                            <div class="penalty-field" data-modes="add">
                                <label class="detail-form-label" for="pfLoan">Returned Loan <span class="req">*</span></label>
                                <select id="pfLoan" class="detail-form-control"></select>
                                <p class="penalty-hint" id="pfLoanHint">Only returned loans that can still take a penalty are listed.</p>
                            </div>
                        @endif
                        <div class="penalty-field-row" data-modes="add edit">
                            @if ($isAdmin)
                                <div class="penalty-field" data-modes="add">
                                    <label class="detail-form-label" for="pfType">Type <span class="req">*</span></label>
                                    <select id="pfType" class="detail-form-control"></select>
                                </div>
                            @endif
                            <div class="penalty-field" data-modes="add edit">
                                <label class="detail-form-label" for="pfAmount">Amount <span class="optional">optional</span></label>
                                <div class="penalty-input-prefix">
                                    <span aria-hidden="true">₱</span>
                                    <input type="number" id="pfAmount" class="detail-form-control" min="0" max="999999.99" step="0.01" inputmode="decimal" placeholder="0.00">
                                </div>
                                <p class="penalty-hint">Leave empty for a non-monetary penalty.</p>
                            </div>
                        </div>
                        <div class="penalty-field" data-modes="add edit">
                            <label class="detail-form-label" for="pfDescription">Description <span class="req">*</span></label>
                            <textarea id="pfDescription" class="detail-form-control textarea-control" rows="4" maxlength="1000" placeholder="e.g. Pay for the replacement charger at the Accounting Office."></textarea>
                        </div>
                        <div class="penalty-field" data-modes="settle">
                            <label class="detail-form-label" for="pfReference">Reference / Receipt No. <span class="optional">optional</span></label>
                            <input type="text" id="pfReference" class="detail-form-control" maxlength="100" placeholder="e.g. OR-2026-00123">
                        </div>
                        <div class="penalty-field" data-modes="settle waive">
                            <label class="detail-form-label" for="pfRemarks" id="pfRemarksLabel">Remarks</label>
                            <textarea id="pfRemarks" class="detail-form-control textarea-control" rows="4" maxlength="1000"></textarea>
                        </div>
                        <p class="penalty-note" data-modes="settle"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Payment is made outside EquipTrack (e.g. at the cashier). This only records that the penalty was settled.</p>
                    </div>

                    <footer class="penalty-sheet-footer">
                        <button type="button" class="btn-sheet btn-sheet-secondary" id="penaltyFormCancel">Cancel</button>
                        <button type="submit" class="btn-sheet btn-sheet-primary" id="penaltyFormSubmit">Save</button>
                    </footer>
                </form>
            </div>
        </div>
    </div>

    @unless ($isAdmin)
        {{-- The department layout has no toast; showNotification (admin-shell.js) uses this one. --}}
        <div class="toast-notification" id="toast">
            <div class="toast-content">
                <i class="fa-solid fa-circle-check toast-icon" id="toastIcon"></i>
                <div class="toast-message">
                    <span class="toast-title" id="toastTitle">Success</span>
                    <span class="toast-desc" id="toastMsg">Action processed successfully!</span>
                </div>
            </div>
        </div>
    @endunless
