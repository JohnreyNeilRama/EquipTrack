{{-- Shared Request Details and Reject Request modals (Admin Requests + Department Borrow Requests). --}}
    {{-- Request Details Modal --}}
    <div class="modal-overlay" id="detailsModal">
        <div class="modal-card eq-modal-card request-details-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Detailed view of user request</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeDetailsBtn">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;">Request Details</h3>

                {{-- Requester Profile Block --}}
                <div class="modal-requester-profile">
                    <img src="" alt="User Avatar" class="modal-requester-avatar" id="modalUserAvatar">
                    <div class="modal-requester-meta">
                        <span class="modal-requester-name" id="modalUserName">John Doe</span>
                        <span class="modal-requester-details" id="modalUserRole">Student</span>
                    </div>
                </div>

                {{-- Main Fields Section --}}
                <div class="detail-main-content">
                    <div class="detail-left-side">
                        <div class="detail-img-container">
                            <img src="" alt="Equipment Image" id="modalEqImg">
                        </div>
                        <div class="detail-form-group">
                            <label class="detail-form-label">Status</label>
                            <div class="status-badge-wrapper">
                                <span class="status-badge" id="modalStatusBadge">Pending</span>
                            </div>
                            <div id="modalApprovals" style="display: none;"></div>
                        </div>
                    </div>

                    <div class="detail-right-side">
                        <div class="detail-form-grid">
                            <div class="detail-form-group">
                                <label class="detail-form-label">Equipment Name</label>
                                <input type="text" id="modalEqName" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Category</label>
                                <input type="text" id="modalEqCategory" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Quantity Requested</label>
                                <input type="text" id="modalEqQty" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Request Date</label>
                                <input type="text" id="modalReqDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Borrow Date</label>
                                <input type="text" id="modalBorrowDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Due Date</label>
                                <input type="text" id="modalDueDate" class="detail-form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Purpose, Instructor & Notes Section --}}
                <div class="detail-text-grid">
                    <div class="detail-form-group">
                        <label class="detail-form-label">Purpose of Borrowing</label>
                        <textarea id="modalPurpose" class="detail-form-control textarea-control" rows="3" readonly></textarea>
                    </div>
                    <div class="detail-form-group">
                        <label class="detail-form-label">Instructor Name</label>
                        <input type="text" id="modalInstructor" class="detail-form-control" readonly>
                    </div>
                    <div class="detail-form-group">
                        <label class="detail-form-label">Additional Notes</label>
                        <textarea id="modalNotes" class="detail-form-control textarea-control" rows="3" readonly></textarea>
                    </div>
                </div>
                <div class="detail-form-group" id="modalReasonGroup" style="display: none; margin-bottom: 16px;">
                    <label class="detail-form-label text-danger" id="modalReasonLabel">Reason for Rejection</label>
                    <textarea id="modalReason" class="detail-form-control textarea-control text-danger-control" rows="2" readonly></textarea>
                </div>

                {{-- Modal Actions Footer --}}
                <div class="modal-actions-footer" id="modalActionsFooter">
                    {{-- Loaded dynamically based on status --}}
                </div>
            </div>
        </div>
    </div>

    {{-- Rejection Reason Input Modal --}}
    <div class="modal-overlay" id="rejectReasonModal">
        <div class="modal-card eq-modal-card" style="max-width: 480px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Provide rejection feedback</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeRejectReasonBtn">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;">Reject Request</h3>

                <form id="rejectReasonForm">
                    <input type="hidden" id="rejectRequestId">
                    <div class="reject-reason-input-group">
                        <label class="detail-form-label text-danger" style="text-align: left;">Reason for Rejection</label>
                        <textarea id="rejectReasonInput" class="detail-form-control textarea-control" rows="4" placeholder="Please provide a brief explanation for rejecting this request..." required style="border-color: rgba(239, 68, 68, 0.3);"></textarea>
                    </div>
                    <div class="form-submit-container" style="margin-top: 24px;">
                        <button type="submit" class="btn-submit-reject">Reject Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
