{{-- Shared Penalties page script (Admin + Lab Personnel).
     $party: 'admin' or 'dept'; $urls: data, update, settle, waive (+ store for the admin). --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
    const IS_ADMIN = @json($party === 'admin');
    const URLS = @json($urls);
    const CSRF_TOKEN = @json(csrf_token());

    let penalties = @json($dbPenalties, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    let returnedLoans = @json($returnedLoans ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    const $ = (id) => document.getElementById(id);
    const tableBody = $('penaltiesTableBody');
    const searchInput = $('searchPenalties');
    const filterStatus = $('filterStatus');
    const filterType = $('filterType');
    const filterDepartment = $('filterDepartment');
    const detailModal = $('penaltyModal');
    const formModal = $('penaltyFormModal');
    const form = $('penaltyForm');
    const submitBtn = $('penaltyFormSubmit');

    const pageSize = 10;
    let currentPage = 1;
    let busy = false;
    let openId = null;
    let formMode = null;

    const STATUS_CLASS = { Pending: 'status-pending', Settled: 'status-approved', Waived: 'status-waived' };
    const peso = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>'"]/g, tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag));
    }

    function typeTag(type) {
        return `<span class="type-tag type-${String(type).toLowerCase().replace(/\s+/g, '-')}">${escapeHTML(type)}</span>`;
    }

    // ------------------------------------------------------------------
    // Table
    // ------------------------------------------------------------------
    function updateSummary() {
        const pending = penalties.filter(p => p.status === 'Pending');
        $('sumPending').textContent = pending.length;
        $('sumPendingAmount').textContent = peso(pending.reduce((sum, p) => sum + (p.amount || 0), 0));
        $('sumSettled').textContent = penalties.filter(p => p.status === 'Settled').length;
        $('sumWaived').textContent = penalties.filter(p => p.status === 'Waived').length;
    }

    function renderTable() {
        updateSummary();
        const query = searchInput.value.toLowerCase().trim();
        const status = filterStatus.value;
        const type = filterType.value;
        const department = filterDepartment ? filterDepartment.value : 'all';

        const filtered = penalties.filter(p => {
            const haystack = `${p.penaltyId} ${p.user} ${p.email} ${p.equipment} ${p.serial} ${p.department} #${p.requestId}`.toLowerCase();
            return haystack.includes(query)
                && (status === 'all' || p.status === status)
                && (type === 'all' || p.type === type)
                && (department === 'all' || p.department === department);
        });

        tableBody.innerHTML = '';
        if (filtered.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="8" class="no-requests-message"><i class="fa-solid fa-folder-open"></i> ${
                penalties.length ? 'No penalties match your search or filters.' : 'No penalties have been recorded yet.'}</td></tr>`;
            $('paginationContainer').style.display = 'none';
            return;
        }

        const totalPages = Math.ceil(filtered.length / pageSize);
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, filtered.length);
        $('paginationContainer').style.display = 'flex';

        filtered.slice(start, end).forEach(p => {
            const tr = document.createElement('tr');
            tr.className = 'admin-table-row';
            tr.addEventListener('click', (e) => { if (!e.target.closest('button')) openDetails(p.id); });

            const action = p.canEdit
                ? `<button class="btn-approve" data-open="${p.id}"><i class="fa-solid fa-gavel"></i> Manage</button>`
                : `<button class="btn-view-only" data-open="${p.id}"><i class="fa-solid fa-eye"></i> View</button>`;

            // The request number is in the View form; the table shows the penalty ID only.
            const equipmentSub = IS_ADMIN ? p.department : p.serial;
            tr.innerHTML = `
                <td class="col-fit"><span class="penalty-id">${escapeHTML(p.penaltyId)}</span></td>
                <td class="col-user">
                    <span class="cell-main" title="${escapeHTML(p.user)}">${escapeHTML(p.user)}</span>
                    <span class="cell-sub">${escapeHTML(p.role)}</span>
                </td>
                <td class="col-equipment">
                    <span class="cell-main" title="${escapeHTML(p.equipment)}">${escapeHTML(p.equipment)}</span>
                    <span class="cell-sub" title="${escapeHTML(equipmentSub)}">${escapeHTML(equipmentSub)}</span>
                </td>
                <td class="col-fit">${typeTag(p.type)}</td>
                <td class="col-fit col-amount"><span class="penalty-amount ${p.amount === null ? 'none' : ''}">${escapeHTML(p.amountLabel)}</span></td>
                <td class="col-fit">${escapeHTML(p.recordedAt.split(' ').slice(0, 3).join(' '))}</td>
                <td class="col-fit"><span class="status-badge ${STATUS_CLASS[p.status] || 'status-pending'}">${escapeHTML(p.status)}</span></td>
                <td class="col-fit action-cell">${action}</td>`;
            tr.querySelector('[data-open]').addEventListener('click', () => openDetails(p.id));
            tableBody.appendChild(tr);
        });

        $('paginationInfo').textContent = `Showing ${start + 1} to ${end} of ${filtered.length} entries`;
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        const container = $('paginationButtons');
        container.innerHTML = '';
        const add = (html, disabled, onClick, active = false) => {
            const btn = document.createElement('button');
            btn.className = `btn-page ${active ? 'active' : ''}`;
            btn.innerHTML = html;
            btn.disabled = disabled;
            btn.addEventListener('click', onClick);
            container.appendChild(btn);
        };
        add('<i class="fa-solid fa-angle-left"></i>', currentPage === 1, () => { currentPage--; renderTable(); });
        for (let i = 1; i <= totalPages; i++) add(String(i), false, () => { currentPage = i; renderTable(); }, currentPage === i);
        add('<i class="fa-solid fa-angle-right"></i>', currentPage === totalPages, () => { currentPage++; renderTable(); });
    }

    // ------------------------------------------------------------------
    // Details
    // ------------------------------------------------------------------
    // Admin record view (partials/penalties/page.blade.php).
    function fillAdminRecord(p) {
        // Main line, then a muted second line (role / e-mail, serial number, days late, time).
        const twoLines = (main, sub) => `${escapeHTML(main)}${sub ? `<span class="dd-sub">${escapeHTML(sub)}</span>` : ''}`;

        $('pdUser').innerHTML = twoLines(p.user, [p.role, p.email].filter(Boolean).join(' · '));
        $('pdEquipment').innerHTML = twoLines(p.equipment, p.serial);
        $('pdDepartment').textContent = p.department;
        $('pdRequest').textContent = `#${p.requestId}`;
        $('pdRecordedDate').textContent = p.recordedAt.split(' ').slice(0, 3).join(' ');
        $('pdDates').innerHTML = twoLines(`${p.dueDate} / ${p.returnDate}`,
            p.daysLate > 0 ? `${p.daysLate} day${p.daysLate === 1 ? '' : 's'} late` : 'Returned on time');
        $('pdCondition').textContent = p.condition;
        $('pdType').innerHTML = typeTag(p.type);
        $('pdAmount').textContent = p.amountLabel;
        $('pdAmount').classList.toggle('is-empty', p.amount === null);
        $('pdDescription').textContent = p.description;
        $('pdRecorded').innerHTML = twoLines(p.recordedBy, p.recordedAt);

        const closed = p.status !== 'Pending';
        $('pdSettledGroup').style.display = closed ? '' : 'none';
        $('pdSettledLabel').textContent = p.status === 'Waived' ? 'Waived By' : 'Settled By';
        $('pdSettled').innerHTML = twoLines(p.settledBy, p.settledAt);
        $('pdReferenceGroup').style.display = p.status === 'Settled' && p.referenceNo ? '' : 'none';
        $('pdReference').textContent = p.referenceNo;
        $('pdRemarksGroup').style.display = closed && p.settleRemarks ? '' : 'none';
        $('pdRemarksLabel').textContent = p.status === 'Waived' ? 'Reason for Waiving' : 'Remarks';
        $('pdRemarks').textContent = p.settleRemarks;
    }

    // Lab Personnel record view (partials/penalties/dept-record.blade.php),
    // laid out like the Return Confirmation form.
    function fillDeptRecord(p) {
        const twoLines = (main, sub) => `${escapeHTML(main)}${sub ? `<span class="dd-sub">${escapeHTML(sub)}</span>` : ''}`;

        showBorrowerAvatar(p.avatar, p.user);
        $('prUserName').textContent = p.user;
        $('prUserMeta').textContent = [p.role, p.email].filter(Boolean).join(' · ');

        showEquipmentPhoto(p.equipmentImage, p.equipment);
        $('pdEquipment').textContent = p.equipment;
        $('prCategory').textContent = p.category;
        $('prSerial').textContent = p.serial;
        $('prQuantity').textContent = `${p.quantity} pc(s)`;
        $('pdRequest').textContent = `#${p.requestId}`;

        const condition = $('pdCondition');
        const conditionKey = String(p.condition || '').toLowerCase();
        condition.textContent = p.condition;
        condition.className = 'condition-tag' + (['good', 'damaged', 'incomplete', 'lost'].includes(conditionKey) ? ' condition-' + conditionKey : '');

        $('prDueDate').textContent = p.dueDate;
        $('prReturnDate').textContent = p.returnDate;
        const late = $('pdDates');
        late.textContent = p.daysLate > 0 ? `${p.daysLate} day${p.daysLate === 1 ? '' : 's'}` : 'On time';
        late.classList.toggle('is-late', p.daysLate > 0);

        $('pdType').innerHTML = typeTag(p.type);
        $('pdAmount').textContent = p.amountLabel;
        $('pdAmount').classList.toggle('is-empty', p.amount === null);
        $('pdDescription').textContent = p.description;
        $('pdRecorded').innerHTML = twoLines(p.recordedBy, p.recordedAt);

        const closed = p.status !== 'Pending';
        const waived = p.status === 'Waived';
        $('pdSettledGroup').style.display = closed ? '' : 'none';
        $('prAwaitingGroup').style.display = closed ? 'none' : '';
        $('prPendingNote').style.display = p.canEdit ? '' : 'none';
        $('pdSettledLabel').textContent = waived ? 'Waived By' : 'Settled By';
        $('pdSettled').innerHTML = twoLines(p.settledBy, p.settledAt);
        $('pdReferenceGroup').style.display = p.status === 'Settled' && p.referenceNo ? '' : 'none';
        $('pdReference').textContent = p.referenceNo;
        $('pdRemarksGroup').style.display = closed && p.settleRemarks ? '' : 'none';
        $('pdRemarksGroup').classList.toggle('review-info-danger', waived);
        $('pdRemarksLabel').textContent = waived ? 'Reason for Waiving' : 'Remarks';
        $('pdRemarks').textContent = p.settleRemarks;

        document.querySelector('#penaltyModal .review-scroll').scrollTop = 0;
    }

    // The photo uploaded in Equipment Management; a placeholder when there is
    // none or it cannot be loaded (same behaviour as Return Confirmation).
    function showEquipmentPlaceholder() {
        $('prEquipmentFrame').classList.add('is-missing');
        $('prEquipmentImg').removeAttribute('src');
        $('prEquipmentBackdrop').removeAttribute('src');
        $('prEquipmentLink').removeAttribute('href');
    }

    function showEquipmentPhoto(url, name) {
        if (!url) {
            showEquipmentPlaceholder();
            return;
        }
        const img = $('prEquipmentImg');
        img.alt = `Photo of ${name}`;
        img.onerror = showEquipmentPlaceholder;
        img.src = url;
        $('prEquipmentBackdrop').src = url;
        $('prEquipmentLink').href = url;
        $('prEquipmentLink').setAttribute('aria-label', `Open the full-size photo of ${name}`);
        $('prEquipmentFrame').classList.remove('is-missing');
    }

    // Borrower picture, or their initials on the EquipTrack blue (drawn locally).
    function initialsAvatar(name) {
        const initials = String(name || '')
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(word => Array.from(word)[0])
            .join('')
            .toUpperCase()
            .replace(/[&<>"']/g, '') || 'U';
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">`
            + `<rect width="96" height="96" fill="#385585"/>`
            + `<text x="48" y="48" dy="0.35em" text-anchor="middle" fill="#ffffff" `
            + `font-family="Inter, Segoe UI, Arial, sans-serif" font-size="36" font-weight="600">${initials}</text></svg>`;
        return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
    }

    function showBorrowerAvatar(url, name) {
        const avatar = $('prUserAvatar');
        const fallback = initialsAvatar(name);
        avatar.alt = `${name || 'Borrower'}'s profile picture`;
        avatar.onerror = function () {
            this.onerror = null;
            this.src = fallback;
        };
        avatar.src = url || fallback;
    }

    function openDetails(id) {
        const p = penalties.find(item => item.id === id);
        if (!p) return;
        openId = id;

        $('penaltyTitle').textContent = p.penaltyId;
        const badge = $('penaltyStatusBadge');
        badge.textContent = p.status;
        badge.className = 'status-badge ' + (STATUS_CLASS[p.status] || 'status-pending');

        if (IS_ADMIN) {
            fillAdminRecord(p);
        } else {
            fillDeptRecord(p);
        }

        const footer = $('penaltyFooter');
        if (IS_ADMIN) {
            // Close on the left; on the right the secondary actions, then the
            // primary one (Mark as Settled) last and filled.
            footer.innerHTML = p.canEdit
                ? `<button type="button" class="btn-sheet btn-sheet-secondary" data-act="close">Close</button>
                   <div class="penalty-sheet-actions">
                       <button type="button" class="btn-sheet btn-sheet-outline" data-act="edit"><i class="fa-regular fa-pen-to-square"></i> Edit</button>
                       <button type="button" class="btn-sheet btn-sheet-danger-outline" data-act="waive"><i class="fa-solid fa-ban"></i> Waive</button>
                       <button type="button" class="btn-sheet btn-sheet-success" data-act="settle"><i class="fa-solid fa-check"></i> Mark as Settled</button>
                   </div>`
                : `<button type="button" class="btn-sheet btn-sheet-secondary btn-sheet-block" data-act="close">Close</button>`;
        } else {
            // Same buttons as the Return Confirmation form.
            footer.innerHTML = p.canEdit
                ? `<button type="button" class="btn-modal-close" data-act="close">Close</button>
                   <button type="button" class="btn-modal-edit" data-act="edit"><i class="fa-regular fa-pen-to-square"></i> Edit</button>
                   <button type="button" class="btn-modal-reject-trigger" data-act="waive"><i class="fa-solid fa-ban"></i> Waive</button>
                   <button type="button" class="btn-modal-approve" data-act="settle"><i class="fa-solid fa-check"></i> Mark as Settled</button>`
                : `<button type="button" class="btn-modal-close is-only" data-act="close">Close</button>`;
        }
        footer.querySelectorAll('[data-act]').forEach(btn => btn.addEventListener('click', () => {
            const act = btn.dataset.act;
            if (act === 'close') closeDetails();
            else openForm(act, p);
        }));

        detailModal.classList.add('show');
    }

    function closeDetails() {
        detailModal.classList.remove('show');
        openId = null;
    }

    // ------------------------------------------------------------------
    // Forms: add (admin) / edit / settle / waive
    // ------------------------------------------------------------------
    const FORM_TEXT = {
        add: { title: 'Add Penalty', subtitle: 'Record a penalty on a returned loan', submit: 'Record Penalty' },
        edit: { title: 'Edit Penalty', subtitle: 'Change the amount or description of a pending penalty', submit: 'Save Changes' },
        settle: { title: 'Mark as Settled', subtitle: 'The penalty was paid or fulfilled outside the system', submit: 'Mark as Settled' },
        waive: { title: 'Waive Penalty', subtitle: 'Cancel this penalty; the reason is kept on record', submit: 'Waive Penalty' },
    };

    function openForm(mode, p = null) {
        formMode = mode;
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('[data-modes]').forEach(el => {
            el.hidden = !el.dataset.modes.split(' ').includes(mode);
        });

        $('penaltyFormTitle').textContent = FORM_TEXT[mode].title;
        $('penaltyFormSubtitle').textContent = FORM_TEXT[mode].subtitle;
        const context = $('penaltyFormContext');
        context.hidden = !p;
        context.innerHTML = p
            ? `<strong>${escapeHTML(p.penaltyId)}</strong><span class="ctx-part">${typeTag(p.type)}</span>`
                + `<span class="ctx-part">${escapeHTML(p.amountLabel)}</span>`
                + `<span class="ctx-part ctx-user" title="${escapeHTML(p.user)}">${escapeHTML(p.user)}</span>`
            : '';
        submitBtn.textContent = FORM_TEXT[mode].submit;
        submitBtn.classList.toggle('is-danger', mode === 'waive');
        submitBtn.classList.toggle('is-success', mode === 'settle');

        const remarksLabel = $('pfRemarksLabel');
        remarksLabel.innerHTML = mode === 'waive'
            ? 'Reason for Waiving <span class="req">*</span>'
            : 'Remarks <span class="optional">optional</span>';
        $('pfRemarks').placeholder = mode === 'waive'
            ? 'e.g. The damage was already there before the loan.'
            : 'e.g. Paid at the Accounting Office.';

        if (mode === 'edit' && p) {
            $('pfAmount').value = p.amount !== null ? Number(p.amount).toFixed(2) : '';
            $('pfDescription').value = p.description;
        }

        if (mode === 'add') {
            const loanSelect = $('pfLoan');
            loanSelect.innerHTML = returnedLoans.length
                ? returnedLoans.map(l => `<option value="${l.id}">${escapeHTML(l.label)}</option>`).join('')
                : '<option value="">No returned loans can take a penalty</option>';
            syncAddTypes();
        }

        formModal.classList.add('show');
    }

    // Add: the types still open for the chosen loan, and its suggested late fee.
    function selectedLoan() {
        return returnedLoans.find(l => String(l.id) === $('pfLoan').value) || null;
    }

    function syncAddTypes() {
        const loan = selectedLoan();
        const typeSelect = $('pfType');
        typeSelect.innerHTML = (loan ? loan.types : []).map(t => `<option value="${escapeHTML(t)}">${escapeHTML(t)}</option>`).join('');
        $('pfLoanHint').textContent = loan && loan.daysLate > 0
            ? `Returned ${loan.daysLate} day${loan.daysLate === 1 ? '' : 's'} late.`
            : 'Only returned loans that can still take a penalty are listed.';
        fillLateFee();
    }

    function fillLateFee() {
        const loan = selectedLoan();
        if (loan && $('pfType').value === 'Late Return' && loan.suggestedLateFee && $('pfAmount').value === '') {
            $('pfAmount').value = Number(loan.suggestedLateFee).toFixed(2);
        }
    }

    if (IS_ADMIN) {
        $('pfLoan').addEventListener('change', () => { $('pfAmount').value = ''; syncAddTypes(); });
        $('pfType').addEventListener('change', fillLateFee);
        $('addPenaltyBtn').addEventListener('click', () => openForm('add'));
    }

    function closeForm() {
        formModal.classList.remove('show');
        formMode = null;
    }

    function invalid(id, message) {
        const el = $(id);
        el.classList.add('is-invalid');
        el.focus();
        showNotification('Input Required', message, 'error');
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!formMode || busy) return;

        const payload = {};
        let url;

        if (formMode === 'add' || formMode === 'edit') {
            const description = $('pfDescription').value.trim();
            if (formMode === 'add' && !$('pfLoan').value) return invalid('pfLoan', 'Choose a returned loan.');
            if (!description) return invalid('pfDescription', 'Please describe the penalty.');
            payload.amount = $('pfAmount').value.trim();
            payload.description = description;
            if (formMode === 'add') {
                payload.transaction_id = $('pfLoan').value;
                payload.type = $('pfType').value;
                url = URLS.store;
            } else {
                payload.penalty_id = openId;
                url = URLS.update;
            }
        } else if (formMode === 'settle') {
            payload.penalty_id = openId;
            payload.reference_no = $('pfReference').value.trim();
            payload.remarks = $('pfRemarks').value.trim();
            url = URLS.settle;
        } else {
            const reason = $('pfRemarks').value.trim();
            if (!reason) return invalid('pfRemarks', 'Please state why the penalty is waived.');
            payload.penalty_id = openId;
            payload.reason = reason;
            url = URLS.waive;
        }

        const data = new FormData();
        data.append('_token', CSRF_TOKEN);
        Object.entries(payload).forEach(([key, value]) => data.append(key, value ?? ''));

        busy = true;
        submitBtn.disabled = true;
        fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, body: data })
            .then(res => res.json().catch(() => ({})).then(body => ({ ok: res.ok, body })))
            .then(({ ok, body }) => {
                if (!ok || !body.success) {
                    const first = body && body.errors ? Object.values(body.errors)[0] : null;
                    throw new Error((Array.isArray(first) ? first[0] : first) || (body && body.message) || 'Unable to save the penalty.');
                }
                applyFeed(body);
                const reopen = formMode !== 'add' ? openId : null;
                closeForm();
                if (reopen) openDetails(reopen);
                showNotification('Saved', body.message || 'Penalty saved.', 'success');
            })
            .catch(err => showNotification('Action Failed', err.message, 'error'))
            .finally(() => { busy = false; submitBtn.disabled = false; });
    });

    function applyFeed(body) {
        if (Array.isArray(body.penalties)) penalties = body.penalties;
        if (Array.isArray(body.returnedLoans)) returnedLoans = body.returnedLoans;
        renderTable();
    }

    function refresh() {
        if (busy || formMode) return;
        fetch(URLS.data, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(body => { if (body.success) applyFeed(body); })
            .catch(err => console.error('Error loading penalties:', err));
    }

    // ------------------------------------------------------------------
    // Wiring
    // ------------------------------------------------------------------
    $('closePenaltyBtn').addEventListener('click', closeDetails);
    $('closePenaltyFormBtn').addEventListener('click', closeForm);
    $('penaltyFormCancel').addEventListener('click', closeForm);
    detailModal.addEventListener('click', (e) => { if (e.target === detailModal) closeDetails(); });
    formModal.addEventListener('click', (e) => { if (e.target === formModal) closeForm(); });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (formModal.classList.contains('show')) closeForm();
        else if (detailModal.classList.contains('show')) closeDetails();
    });

    [searchInput, filterStatus, filterType, filterDepartment].filter(Boolean).forEach(el => {
        el.addEventListener(el === searchInput ? 'input' : 'change', () => { currentPage = 1; renderTable(); });
    });
    form.querySelectorAll('input, textarea, select').forEach(el => el.addEventListener('input', () => el.classList.remove('is-invalid')));

    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') refresh(); });
    setInterval(refresh, 15000);

    renderTable();
});
</script>
