@extends('layouts.user')

@section('title', 'EquipTrack - My Penalties')

@push('css')
<link rel="stylesheet" href="{{ asset('user/css/userpenalties.css') }}?v={{ filemtime(public_path('user/css/userpenalties.css')) }}">
@endpush

@section('content')
    <div class="penalties-page">
        <header class="pp-header">
            <h3 class="pp-title">Track your penalties and submit proof of settlement</h3>
            <p class="pp-subtitle">
                Pay, replace or repair outside EquipTrack (for example at the Accounting Office), then upload your receipt or photos here.
                Staff will verify your proof; borrowing stays on hold until it is approved.
            </p>
        </header>

        <div class="pp-summary" aria-label="Summary">
            <div class="pp-summary-card tone-action">
                <span class="pp-summary-icon"><i class="fa-solid fa-circle-exclamation"></i></span>
                <div><span class="pp-summary-value" id="sumAction">0</span><span class="pp-summary-label">Action needed</span></div>
            </div>
            <div class="pp-summary-card tone-review">
                <span class="pp-summary-icon"><i class="fa-solid fa-hourglass-half"></i></span>
                <div><span class="pp-summary-value" id="sumReview">0</span><span class="pp-summary-label">Under review</span></div>
            </div>
            <div class="pp-summary-card tone-done">
                <span class="pp-summary-icon"><i class="fa-solid fa-circle-check"></i></span>
                <div><span class="pp-summary-value" id="sumDone">0</span><span class="pp-summary-label">Settled or waived</span></div>
            </div>
        </div>

        <div class="pp-tabs" role="tablist" aria-label="Filter penalties">
            <button type="button" class="pp-tab is-active" data-filter="all" role="tab" aria-selected="true">All</button>
            <button type="button" class="pp-tab" data-filter="action" role="tab" aria-selected="false">Action needed</button>
            <button type="button" class="pp-tab" data-filter="review" role="tab" aria-selected="false">Under review</button>
            <button type="button" class="pp-tab" data-filter="done" role="tab" aria-selected="false">Settled &amp; waived</button>
        </div>

        <div class="pp-list" id="penaltyList" aria-live="polite"></div>

        <div class="pp-empty" id="emptyState" hidden>
            <i class="fa-solid fa-face-smile"></i>
            <h4 id="emptyTitle">No penalties</h4>
            <p id="emptyText">You have no penalties. Keep returning equipment on time and in good condition.</p>
        </div>
    </div>

    {{-- Submit Proof --}}
    <div class="pp-modal" id="proofModal" role="dialog" aria-modal="true" aria-labelledby="proofTitle" hidden>
        <div class="pp-modal-card">
            <header class="pp-modal-head">
                <div>
                    <h3 class="pp-modal-title" id="proofTitle">Submit Proof</h3>
                    <p class="pp-modal-context" id="proofContext">—</p>
                </div>
                <button type="button" class="pp-modal-close" id="closeProofBtn" aria-label="Close">&times;</button>
            </header>

            <form id="proofForm" class="pp-modal-body" novalidate>
                <input type="hidden" id="proofPenaltyId">

                <section class="pp-step">
                    <h4 class="pp-step-title"><span class="pp-step-no">1</span> How did you settle it?</h4>
                    <div class="pp-type-options" id="typeOptions"></div>
                </section>

                <section class="pp-step">
                    <h4 class="pp-step-title"><span class="pp-step-no">2</span> Upload your proof</h4>
                    <label class="pp-dropzone" id="dropzone" for="proofFiles" tabindex="0">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span class="pp-drop-main">Drag files here, or <span class="pp-link">browse</span></span>
                        <span class="pp-drop-sub" id="dropHint">JPG, PNG, WEBP or PDF · up to {{ $limits['maxFiles'] }} files · {{ $limits['maxFileMb'] }} MB each</span>
                    </label>
                    <input type="file" id="proofFiles" class="pp-file-input" multiple
                           accept="{{ collect($limits['mimes'])->map(fn ($m) => $m === 'pdf' ? 'application/pdf' : 'image/' . ($m === 'jpg' ? 'jpeg' : $m))->unique()->implode(',') }}">
                    <ul class="pp-thumbs" id="thumbs"></ul>
                </section>

                <section class="pp-step">
                    <h4 class="pp-step-title"><span class="pp-step-no">3</span> Details</h4>
                    <div class="pp-fields">
                        <div class="pp-field" id="referenceField">
                            <label for="proofReference">Official Receipt / Reference No. <span class="pp-req" id="referenceReq">*</span></label>
                            <input type="text" id="proofReference" maxlength="100" placeholder="e.g. OR-2026-00123">
                        </div>
                        <div class="pp-field" id="amountField">
                            <label for="proofAmount">Amount Paid</label>
                            <div class="pp-prefix"><span>₱</span><input type="number" id="proofAmount" min="0" max="999999.99" step="0.01" inputmode="decimal"></div>
                        </div>
                        <div class="pp-field">
                            <label for="proofDate" id="proofDateLabel">Date Paid <span class="pp-req">*</span></label>
                            <input type="date" id="proofDate" max="{{ now(config('app.display_timezone'))->toDateString() }}">
                        </div>
                        <div class="pp-field pp-field-wide">
                            <label for="proofNotes">Notes <span class="pp-optional">optional</span></label>
                            <textarea id="proofNotes" rows="3" maxlength="1000" placeholder="e.g. Replaced with the same model of charger."></textarea>
                        </div>
                    </div>
                </section>

                <p class="pp-note"><i class="fa-solid fa-circle-info"></i> Staff will verify your proof. Borrowing stays on hold until it is approved.</p>
            </form>

            <footer class="pp-modal-foot">
                <button type="button" class="pp-btn pp-btn-secondary" id="cancelProofBtn">Cancel</button>
                <button type="submit" form="proofForm" class="pp-btn pp-btn-primary" id="submitProofBtn"><i class="fa-solid fa-paper-plane"></i> Submit Proof</button>
            </footer>
        </div>
    </div>

    {{-- Full-size proof image --}}
    <div class="pp-lightbox" id="lightbox" hidden>
        <button type="button" class="pp-lightbox-close" id="closeLightbox" aria-label="Close">&times;</button>
        <img src="" alt="" id="lightboxImg">
    </div>

    <div class="pp-toast" id="ppToast" role="status" hidden></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const URLS = {
        data: @json(route('user.penalties.data')),
        submit: @json(route('user.penalties.submit')),
    };
    const CSRF_TOKEN = @json(csrf_token());
    const MAX_FILES = @json($limits['maxFiles']);
    const MAX_BYTES = @json($limits['maxFileMb'] * 1024 * 1024);
    const ACCEPTED = /\.(jpe?g|png|webp|pdf)$/i;
    const FALLBACK_IMG = @json(asset('images/EquipTrack_logo.png'));
    const FOCUS_ID = Number(new URLSearchParams(location.search).get('penalty')) || null;

    let penalties = @json($dbPenalties, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    let filter = 'all';
    let files = [];
    let busy = false;

    const $ = (id) => document.getElementById(id);

    const TYPE_INFO = {
        Payment: { icon: 'fa-receipt', title: 'Paid the penalty', desc: 'Upload the official receipt.' },
        Replacement: { icon: 'fa-box-open', title: 'Replaced the item', desc: 'Photo of the new item and its receipt.' },
        Repair: { icon: 'fa-screwdriver-wrench', title: 'Repaired the item', desc: 'Repair receipt and a photo of the fixed item.' },
    };

    const STATE = {
        none: { group: 'action', badge: 'Action needed', cls: 'badge-action', icon: 'fa-circle-exclamation' },
        rejected: { group: 'action', badge: 'Proof rejected', cls: 'badge-rejected', icon: 'fa-circle-xmark' },
        review: { group: 'review', badge: 'Proof submitted', cls: 'badge-review', icon: 'fa-paperclip' },
        settled: { group: 'done', badge: 'Settled', cls: 'badge-settled', icon: 'fa-circle-check' },
        waived: { group: 'done', badge: 'Waived', cls: 'badge-waived', icon: 'fa-hand-holding-heart' },
    };

    function escapeHTML(value) {
        if (value === null || value === undefined) return '';
        return String(value).replace(/[&<>'"]/g, tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag]));
    }

    function toast(message, ok = true) {
        const el = $('ppToast');
        el.textContent = message;
        el.className = 'pp-toast ' + (ok ? 'is-ok' : 'is-error');
        el.hidden = false;
        clearTimeout(toast.timer);
        toast.timer = setTimeout(() => { el.hidden = true; }, 4500);
    }

    // ------------------------------------------------------------------
    // Cards
    // ------------------------------------------------------------------
    // Progress for each state, from what actually happened to this penalty:
    // done = finished (green check), current = the step it is waiting on,
    // rejected = the last proof was not accepted.
    function trackerSteps(p) {
        switch (p.proofState) {
            case 'review':
                return [['Issued', 'done'], ['Proof submitted', 'done'], ['Awaiting verification', 'current']];
            case 'rejected':
                return [['Issued', 'done'], ['Proof rejected', 'rejected'], ['Verified', '']];
            case 'settled':
                // Settled from approved proof, or directly by staff (no proof involved).
                return p.proof && p.proof.status === 'Approved'
                    ? [['Issued', 'done'], ['Proof submitted', 'done'], ['Verified', 'done']]
                    : [['Issued', 'done'], ['Settled by staff', 'done']];
            case 'waived':
                return [['Issued', 'done'], ['Waived', 'done']];
            default:
                return [['Issued', 'done'], ['Submit proof', 'current'], ['Verified', '']];
        }
    }

    function tracker(p) {
        const steps = trackerSteps(p);
        return `<ol class="pp-tracker" style="--steps: ${steps.length}">${steps.map(([label, state]) =>
            `<li class="${state ? 'is-' + state : ''}">${escapeHTML(label)}</li>`).join('')}</ol>`;
    }

    function fileList(proof) {
        if (!proof || !proof.files.length) return '';
        return `<ul class="pp-files">${proof.files.map(f => f.isImage
            ? `<li><button type="button" class="pp-file-thumb" data-lightbox="${escapeHTML(f.url)}" title="${escapeHTML(f.name)}"><img src="${escapeHTML(f.url)}" alt="${escapeHTML(f.name)}" loading="lazy"></button></li>`
            : `<li><a class="pp-file-doc" href="${escapeHTML(f.url)}" target="_blank" rel="noopener" title="${escapeHTML(f.name)}"><i class="fa-solid fa-file-pdf"></i><span>${escapeHTML(f.name)}</span></a></li>`
        ).join('')}</ul>`;
    }

    function proofPanel(p) {
        const proof = p.proof;
        if (!proof) return '';
        const rows = [
            ['Settlement', proof.typeLabel],
            proof.referenceNo ? ['Reference No.', proof.referenceNo] : null,
            proof.amountPaid ? ['Amount Paid', proof.amountPaid] : null,
            ['Date', proof.settledOn],
            ['Submitted', proof.submittedAt],
        ].filter(Boolean);
        const statusLine = {
            'Under Review': '<p class="pp-proof-status is-review"><i class="fa-solid fa-hourglass-half"></i> Waiting for staff to verify your proof.</p>',
            'Approved': `<p class="pp-proof-status is-approved"><i class="fa-solid fa-circle-check"></i> Approved by ${escapeHTML(proof.reviewedBy)} · ${escapeHTML(proof.reviewedAt)}</p>`,
            'Rejected': `<p class="pp-proof-status is-rejected"><i class="fa-solid fa-circle-xmark"></i> Rejected: ${escapeHTML(proof.rejectReason)}</p>`,
        }[proof.status] || '';
        const history = p.proofHistory.length
            ? `<details class="pp-history"><summary>Earlier submissions (${p.proofHistory.length})</summary><ul>${p.proofHistory.map(h =>
                `<li><strong>${escapeHTML(h.status)}</strong> · ${escapeHTML(h.typeLabel)} · ${escapeHTML(h.submittedAt)}${h.rejectReason ? `<span>Reason: ${escapeHTML(h.rejectReason)}</span>` : ''}</li>`).join('')}</ul></details>`
            : '';
        return `
            <div class="pp-proof">
                <div class="pp-proof-head">
                    <h5 class="pp-proof-title">Your submission</h5>
                    ${statusLine}
                </div>
                <dl class="pp-proof-facts">${rows.map(([k, v]) => `<div><dt>${escapeHTML(k)}</dt><dd>${escapeHTML(v)}</dd></div>`).join('')}</dl>
                ${proof.notes ? `<p class="pp-proof-notes">${escapeHTML(proof.notes)}</p>` : ''}
                ${fileList(proof)}
                ${history}
            </div>`;
    }

    function card(p) {
        const state = STATE[p.proofState] || STATE.none;
        const action = p.proofState === 'none'
            ? `<button type="button" class="pp-btn pp-btn-primary" data-submit="${p.id}"><i class="fa-solid fa-upload"></i> Submit Proof</button>`
            : p.proofState === 'rejected'
                ? `<button type="button" class="pp-btn pp-btn-primary" data-submit="${p.id}"><i class="fa-solid fa-rotate-right"></i> Resubmit Proof</button>`
                : '';
        const closedNote = p.proofState === 'settled'
            ? `<p class="pp-closed-note is-settled"><i class="fa-solid fa-circle-check"></i> Settled ${escapeHTML(p.settledAt)}${p.referenceNo ? ` · Ref. ${escapeHTML(p.referenceNo)}` : ''}</p>`
            : p.proofState === 'waived'
                ? `<p class="pp-closed-note is-waived"><i class="fa-solid fa-hand-holding-heart"></i> Waived${p.settleRemarks ? `: ${escapeHTML(p.settleRemarks)}` : ''}</p>`
                : '';

        // Sections, top to bottom: header (ID, type, status), summary (photo,
        // equipment, amount, description), progress, the user's submission,
        // then the action bar.
        return `
            <article class="pp-card state-${p.proofState}${FOCUS_ID === p.id ? ' is-focus' : ''}" id="penalty-${p.id}">
                <header class="pp-card-head">
                    <div class="pp-card-ident">
                        <span class="pp-card-id">${escapeHTML(p.penaltyId)}</span>
                        <span class="pp-type type-${String(p.type).toLowerCase().replace(/\s+/g, '-')}">${escapeHTML(p.type)}</span>
                    </div>
                    <span class="pp-badge ${state.cls}"><i class="fa-solid ${state.icon}"></i> ${escapeHTML(state.badge)}</span>
                </header>
                <div class="pp-card-main">
                    <div class="pp-card-media">
                        <img src="${escapeHTML(p.equipmentImage || FALLBACK_IMG)}" alt="${escapeHTML(p.equipment)}" loading="lazy"
                             onerror="this.onerror=null; this.src='${FALLBACK_IMG}';">
                    </div>
                    <div class="pp-card-info">
                        <div class="pp-card-heading">
                            <div class="pp-card-titles">
                                <h4 class="pp-card-title" title="${escapeHTML(p.equipment)}">${escapeHTML(p.equipment)}</h4>
                                <p class="pp-card-meta">${escapeHTML(p.category)} · Request #${escapeHTML(p.requestId)} · Recorded ${escapeHTML(p.recordedAt.split(' ').slice(0, 3).join(' '))}</p>
                            </div>
                            <div class="pp-card-amount ${p.amount === null ? 'is-empty' : ''}">${escapeHTML(p.amountLabel)}</div>
                        </div>
                        <p class="pp-card-desc">${escapeHTML(p.description)}</p>
                    </div>
                </div>
                <div class="pp-card-progress">
                    ${tracker(p)}
                    ${closedNote}
                </div>
                ${proofPanel(p)}
                ${action ? `<footer class="pp-card-actions">${action}</footer>` : ''}
            </article>`;
    }

    function render() {
        const counts = { action: 0, review: 0, done: 0 };
        penalties.forEach(p => { counts[(STATE[p.proofState] || STATE.none).group]++; });
        $('sumAction').textContent = counts.action;
        $('sumReview').textContent = counts.review;
        $('sumDone').textContent = counts.done;

        const shown = penalties.filter(p => filter === 'all' || (STATE[p.proofState] || STATE.none).group === filter);
        $('penaltyList').innerHTML = shown.map(card).join('');
        $('emptyState').hidden = shown.length > 0;
        if (!shown.length) {
            $('emptyTitle').textContent = penalties.length ? 'Nothing here' : 'No penalties';
            $('emptyText').textContent = penalties.length
                ? 'No penalties match this filter.'
                : 'You have no penalties. Keep returning equipment on time and in good condition.';
        }

        $('penaltyList').querySelectorAll('[data-submit]').forEach(btn => btn.addEventListener('click', () => openProof(Number(btn.dataset.submit))));
        $('penaltyList').querySelectorAll('[data-lightbox]').forEach(btn => btn.addEventListener('click', () => openLightbox(btn.dataset.lightbox)));
    }

    document.querySelectorAll('.pp-tab').forEach(tab => tab.addEventListener('click', () => {
        filter = tab.dataset.filter;
        document.querySelectorAll('.pp-tab').forEach(t => {
            t.classList.toggle('is-active', t === tab);
            t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
        });
        render();
    }));

    // ------------------------------------------------------------------
    // Submit Proof
    // ------------------------------------------------------------------
    let current = null;

    function selectedType() {
        const checked = document.querySelector('input[name="proofType"]:checked');
        return checked ? checked.value : 'Payment';
    }

    function syncTypeFields() {
        const type = selectedType();
        const payment = type === 'Payment';
        $('referenceReq').hidden = !payment;
        $('proofReference').placeholder = payment ? 'e.g. OR-2026-00123' : 'Receipt number, if any';
        $('amountField').hidden = !(payment && current && current.amount !== null);
        $('proofDateLabel').innerHTML = (payment ? 'Date Paid' : type === 'Replacement' ? 'Date Replaced' : 'Date Repaired') + ' <span class="pp-req">*</span>';
        document.querySelectorAll('.pp-type-option').forEach(o => o.classList.toggle('is-selected', o.querySelector('input').checked));
    }

    function openProof(id) {
        current = penalties.find(p => p.id === id);
        if (!current) return;
        $('proofForm').reset();
        files = [];
        renderThumbs();
        $('proofPenaltyId').value = id;
        $('proofTitle').textContent = current.proofState === 'rejected' ? 'Resubmit Proof' : 'Submit Proof';
        $('proofContext').textContent = `${current.penaltyId} · ${current.type} · ${current.amountLabel} · ${current.equipment}`;
        $('typeOptions').innerHTML = current.proofTypes.map((type, i) => `
            <label class="pp-type-option">
                <input type="radio" name="proofType" value="${type}" ${i === 0 ? 'checked' : ''}>
                <i class="fa-solid ${TYPE_INFO[type].icon}"></i>
                <span><strong>${TYPE_INFO[type].title}</strong><small>${TYPE_INFO[type].desc}</small></span>
            </label>`).join('');
        document.querySelectorAll('input[name="proofType"]').forEach(r => r.addEventListener('change', syncTypeFields));
        $('proofAmount').value = current.amount !== null ? Number(current.amount).toFixed(2) : '';
        $('proofDate').value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        syncTypeFields();
        $('proofModal').hidden = false;
        document.body.classList.add('pp-modal-open');
        setTimeout(() => document.querySelector('input[name="proofType"]')?.focus(), 30);
    }

    function closeProof() {
        $('proofModal').hidden = true;
        document.body.classList.remove('pp-modal-open');
        current = null;
    }

    // Files: drag and drop, browse or camera; checked here and again on the server.
    function addFiles(list) {
        for (const file of list) {
            if (files.length >= MAX_FILES) { toast(`You can attach up to ${MAX_FILES} files.`, false); break; }
            if (!ACCEPTED.test(file.name)) { toast(`${file.name}: only JPG, PNG, WEBP or PDF files are accepted.`, false); continue; }
            if (file.size > MAX_BYTES) { toast(`${file.name} is larger than ${MAX_BYTES / 1048576} MB.`, false); continue; }
            files.push(file);
        }
        renderThumbs();
    }

    function renderThumbs() {
        const list = $('thumbs');
        list.querySelectorAll('img[data-object-url]').forEach(img => URL.revokeObjectURL(img.src));
        list.innerHTML = files.map((file, i) => {
            const isImage = file.type.startsWith('image/');
            const preview = isImage ? `<img src="${URL.createObjectURL(file)}" alt="" data-object-url>` : '<i class="fa-solid fa-file-pdf"></i>';
            return `<li class="pp-thumb">${preview}<span title="${escapeHTML(file.name)}">${escapeHTML(file.name)}</span>
                <button type="button" class="pp-thumb-remove" data-remove="${i}" aria-label="Remove ${escapeHTML(file.name)}">&times;</button></li>`;
        }).join('');
        list.querySelectorAll('[data-remove]').forEach(btn => btn.addEventListener('click', () => {
            files.splice(Number(btn.dataset.remove), 1);
            renderThumbs();
        }));
        $('dropzone').classList.toggle('is-full', files.length >= MAX_FILES);
    }

    $('proofFiles').addEventListener('change', (e) => { addFiles(e.target.files); e.target.value = ''; });
    const dropzone = $('dropzone');
    ['dragenter', 'dragover'].forEach(ev => dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(ev => dropzone.addEventListener(ev, (e) => { e.preventDefault(); dropzone.classList.remove('is-over'); }));
    dropzone.addEventListener('drop', (e) => addFiles(e.dataTransfer.files));
    dropzone.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $('proofFiles').click(); } });

    $('proofForm').addEventListener('submit', (e) => {
        e.preventDefault();
        if (busy || !current) return;

        const type = selectedType();
        const reference = $('proofReference').value.trim();
        if (!files.length) return toast('Please attach at least one photo or receipt.', false);
        if (type === 'Payment' && !reference) { $('proofReference').focus(); return toast('Please enter the official receipt or reference number.', false); }
        if (!$('proofDate').value) { $('proofDate').focus(); return toast('Please enter the date.', false); }

        const data = new FormData();
        data.append('_token', CSRF_TOKEN);
        data.append('penalty_id', current.id);
        data.append('type', type);
        data.append('reference_no', reference);
        if (!$('amountField').hidden) data.append('amount_paid', $('proofAmount').value.trim());
        data.append('settled_on', $('proofDate').value);
        data.append('notes', $('proofNotes').value.trim());
        files.forEach(file => data.append('files[]', file));

        busy = true;
        const button = $('submitProofBtn');
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

        fetch(URLS.submit, { method: 'POST', headers: { 'Accept': 'application/json' }, body: data })
            .then(res => res.json().catch(() => ({ message: 'Unexpected response from the server (HTTP ' + res.status + ').' }))
                .then(body => ({ ok: res.ok, body })))
            .then(({ ok, body }) => {
                if (!ok || !body.success) {
                    const first = body.errors ? Object.values(body.errors)[0] : null;
                    throw new Error((Array.isArray(first) ? first[0] : first) || body.message || 'Your proof could not be submitted.');
                }
                penalties = body.penalties;
                closeProof();
                render();
                toast(body.message);
            })
            .catch(err => toast(err.message, false))
            .finally(() => {
                busy = false;
                button.disabled = false;
                button.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Proof';
            });
    });

    $('closeProofBtn').addEventListener('click', closeProof);
    $('cancelProofBtn').addEventListener('click', closeProof);
    $('proofModal').addEventListener('click', (e) => { if (e.target === $('proofModal')) closeProof(); });

    // ------------------------------------------------------------------
    // Lightbox
    // ------------------------------------------------------------------
    function openLightbox(url) {
        $('lightboxImg').src = url;
        $('lightbox').hidden = false;
    }
    function closeLightbox() {
        $('lightbox').hidden = true;
        $('lightboxImg').removeAttribute('src');
    }
    $('closeLightbox').addEventListener('click', closeLightbox);
    $('lightbox').addEventListener('click', (e) => { if (e.target === $('lightbox')) closeLightbox(); });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (!$('lightbox').hidden) closeLightbox();
        else if (!$('proofModal').hidden) closeProof();
    });

    // Staff decisions show up without reloading.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible' || busy || !$('proofModal').hidden) return;
        fetch(URLS.data, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(body => { if (body.success) { penalties = body.penalties; render(); } })
            .catch(() => {});
    });

    render();
    if (FOCUS_ID) document.getElementById('penalty-' + FOCUS_ID)?.scrollIntoView({ block: 'center' });
});
</script>
@endpush
