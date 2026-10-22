{{-- Department (Lab Personnel) penalty record view. Same structure as the
     Return Confirmation review form: title + status, the borrower, then
     numbered sections, with the equipment's reference photo beside its
     details. Styles: public/departments/css/penalty-record.css. The element
     ids are the ones partials/penalties/script.blade.php fills. --}}
<div class="modal-overlay" id="penaltyModal" role="dialog" aria-modal="true" aria-labelledby="penaltyTitle">
    <div class="modal-card eq-modal-card penalty-review-card">
        <div class="modal-outer-header">
            <p class="modal-subtitle-top">Damage &amp; penalty record</p>
        </div>
        <div class="modal-inner-card">
            <button type="button" class="modal-close" id="closePenaltyBtn" aria-label="Close">&times;</button>

            {{-- Title + status --}}
            <div class="review-heading">
                <h3 class="review-title" id="penaltyTitle">Penalty</h3>
                <span class="status-badge" id="penaltyStatusBadge">—</span>
            </div>

            <div class="review-scroll">
                {{-- Borrower --}}
                <div class="modal-requester-profile review-requester">
                    <img src="" alt="" class="modal-requester-avatar" id="prUserAvatar">
                    <div class="modal-requester-meta">
                        <span class="modal-requester-name" id="prUserName">—</span>
                        <span class="modal-requester-details" id="prUserMeta">—</span>
                    </div>
                </div>

                {{-- 1. Equipment and its return --}}
                <section class="review-section">
                    <h4 class="review-section-title"><span class="review-step">1</span> Equipment &amp; Return</h4>

                    <div class="return-item">
                        <figure class="return-photo">
                            <div class="return-photo-frame" id="prEquipmentFrame">
                                <a href="#" class="return-photo-link" id="prEquipmentLink" target="_blank" rel="noopener" title="Open the full-size photo">
                                    <img src="" alt="" class="return-photo-backdrop" id="prEquipmentBackdrop" aria-hidden="true">
                                    <img src="" alt="" class="return-photo-img" id="prEquipmentImg">
                                    <span class="return-photo-zoom" aria-hidden="true"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
                                </a>
                                <div class="return-photo-placeholder">
                                    <i class="fa-regular fa-image" aria-hidden="true"></i>
                                    <span>No equipment photo available</span>
                                </div>
                            </div>
                            <figcaption><i class="fa-regular fa-image" aria-hidden="true"></i> Reference photo</figcaption>
                        </figure>

                        <div class="return-item-body">
                            <span class="return-item-label">Equipment</span>
                            <p class="return-item-name" id="pdEquipment">—</p>
                            <p class="return-item-category" id="prCategory">—</p>
                            <dl class="return-item-facts">
                                <div>
                                    <dt>Equipment ID / Serial No.</dt>
                                    <dd class="mono" id="prSerial">—</dd>
                                </div>
                                <div>
                                    <dt>Quantity Borrowed</dt>
                                    <dd id="prQuantity">—</dd>
                                </div>
                                <div>
                                    <dt>Request No.</dt>
                                    <dd class="mono" id="pdRequest">—</dd>
                                </div>
                                <div>
                                    <dt>Inspected Condition</dt>
                                    <dd><span class="condition-tag" id="pdCondition">—</span></dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <dl class="return-dates">
                        <div>
                            <dt>Due Date</dt>
                            <dd id="prDueDate">—</dd>
                        </div>
                        <div>
                            <dt>Returned</dt>
                            <dd id="prReturnDate">—</dd>
                        </div>
                        <div>
                            <dt>Days Late</dt>
                            <dd id="pdDates">—</dd>
                        </div>
                    </dl>
                </section>

                {{-- 2. The penalty --}}
                <section class="review-section">
                    <h4 class="review-section-title"><span class="review-step">2</span> Penalty Details</h4>
                    <dl class="review-info-grid">
                        <div class="review-info-item">
                            <dt>Type</dt>
                            <dd id="pdType">—</dd>
                        </div>
                        <div class="review-info-item">
                            <dt>Amount</dt>
                            <dd class="pr-amount" id="pdAmount">—</dd>
                        </div>
                    </dl>
                    <div class="reported-box">
                        <span class="reported-label">Damage / Penalty Description</span>
                        <p class="reported-notes" id="pdDescription">—</p>
                    </div>
                </section>

                {{-- 3. Who recorded it, and how it was settled --}}
                <section class="review-section">
                    <h4 class="review-section-title"><span class="review-step">3</span> Record &amp; Settlement</h4>
                    <dl class="review-info-grid">
                        <div class="review-info-item">
                            <dt>Recorded By</dt>
                            <dd id="pdRecorded">—</dd>
                        </div>
                        <div class="review-info-item" id="pdSettledGroup">
                            <dt id="pdSettledLabel">Settled By</dt>
                            <dd id="pdSettled">—</dd>
                        </div>
                        <div class="review-info-item pr-awaiting" id="prAwaitingGroup">
                            <dt>Settlement</dt>
                            <dd>Awaiting settlement</dd>
                        </div>
                        <div class="review-info-item review-info-wide" id="pdReferenceGroup">
                            <dt>Reference / Receipt No.</dt>
                            <dd class="mono" id="pdReference">—</dd>
                        </div>
                        <div class="review-info-item review-info-wide" id="pdRemarksGroup">
                            <dt id="pdRemarksLabel">Remarks</dt>
                            <dd id="pdRemarks">—</dd>
                        </div>
                    </dl>
                    <p class="review-hint pr-note" id="prPendingNote">Payment is handled outside EquipTrack. Mark the penalty as settled once it has been paid or fulfilled, or waive it with a reason.</p>
                </section>
            </div>

            <div class="modal-actions-footer review-footer" id="penaltyFooter"></div>
        </div>
    </div>
</div>
