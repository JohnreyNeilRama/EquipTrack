// Shared by Admin Equipment and Department Equipment, so a fix here reaches
// both pages:
//   EquipmentForm.init(...)  the Add / Edit window (partials/equipment/form-modal):
//                            photo uploader, quantity check, saving
//   EquipmentForm.card(...)  one equipment card for the grid
//   EquipmentForm.escapeHTML(...)
// Each page keeps its own extras (Admin: Manage Categories, Delete;
// Department: summary counts, View window) and its own styles.

(function () {
    'use strict';

    const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

    // Escapes text for HTML. Numbers (including 0) are kept, so a zero
    // quantity shows as "0" instead of a blank.
    function escapeHTML(value) {
        if (value === null || value === undefined) return '';
        return String(value).replace(/[&<>'"]/g, tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag));
    }

    const STATUS_CLASS = {
        'available': 'status-available',
        'unavailable': 'status-unavailable',
        'on hold': 'status-on-hold',
        'under maintenance': 'status-under-maintenance',
        'retired': 'status-retired'
    };

    function statusClass(status) {
        return STATUS_CLASS[String(status || '').toLowerCase()] || 'status-unavailable';
    }

    // ------------------------------------------------------------------
    // Equipment card
    // ------------------------------------------------------------------

    /**
     * item: { id, name, brand, model, serial_number, unit_labels, unit_search,
     *         category, category_id,
     *         department, classification, accessories, available, total,
     *         status, image }
     * options.showDepartment: add a Department row (Admin sees every department)
     * options.actions: buttons under the card, in order, e.g.
     *         [{ kind: 'edit', onClick }, { kind: 'delete', onClick }]
     *         kinds: edit, delete, view
     */
    function card(item, options = {}) {
        const el = document.createElement('div');
        const status = String(item.status || 'Available');
        const available = parseInt(item.available, 10) || 0;
        const total = parseInt(item.total, 10) || 0;
        const pct = total > 0 ? Math.min(100, Math.round((available / total) * 100)) : 0;
        const stockClass = available === 0 ? 'is-empty' : (available <= Math.max(1, Math.floor(total * 0.25)) ? 'is-low' : '');
        const classification = item.classification || 'Not Expensive';
        const subtitle = [item.brand, item.model].filter(v => v && String(v).trim()).join(' · ');
        const imgUrl = item.image ? escapeHTML(item.image) : '';

        el.className = 'eq-card-admin';
        el.setAttribute('data-id', item.id);
        el.setAttribute('data-status', status.toLowerCase());
        el.setAttribute('data-category', String(item.category || '').toLowerCase());
        el.setAttribute('data-category-id', item.category_id || '');
        el.setAttribute('data-search', [item.name, item.brand, item.model, item.serial_number, item.unit_search].filter(Boolean).join(' ').toLowerCase());

        el.innerHTML = `
            <div class="eq-card-img-wrapper${imgUrl ? '' : ' is-missing'}">
                ${imgUrl ? `
                <img class="eq-card-img-backdrop" src="${imgUrl}" alt="" aria-hidden="true" loading="lazy" decoding="async">
                <img class="eq-card-img" src="${imgUrl}" alt="${escapeHTML(item.name)}" loading="lazy" decoding="async">` : ''}
                <div class="eq-card-img-placeholder">
                    <i class="fa-regular fa-image" aria-hidden="true"></i>
                    <span>No image available</span>
                </div>
            </div>
            <div class="eq-card-details">
                <div class="eq-card-heading">
                    <h4 class="eq-card-title" title="${escapeHTML(item.name)}">${escapeHTML(item.name)}</h4>
                    <span class="eq-status-badge ${statusClass(status)}">${escapeHTML(status)}</span>
                </div>
                ${subtitle ? `<p class="eq-card-subtitle" title="${escapeHTML(subtitle)}">${escapeHTML(subtitle)}</p>` : ''}

                <dl class="eq-card-specs">
                    <div class="eq-spec-row">
                        <dt>Units</dt>
                        <dd class="mono" title="${escapeHTML(item.unit_labels || '')}">${escapeHTML(item.unit_labels || item.serial_number || '—')}</dd>
                    </div>
                    <div class="eq-spec-row">
                        <dt>Category</dt>
                        <dd class="cat-val">${escapeHTML(item.category || 'Unassigned')}</dd>
                    </div>
                    ${options.showDepartment ? `
                    <div class="eq-spec-row">
                        <dt>Department</dt>
                        <dd>${escapeHTML(item.department || 'Unassigned')}</dd>
                    </div>` : ''}
                    <div class="eq-spec-row">
                        <dt>Classification</dt>
                        <dd><span class="eq-class-badge ${classification === 'Expensive' ? 'class-expensive' : 'class-not-expensive'}">${escapeHTML(classification)}</span></dd>
                    </div>
                    ${item.accessories ? `
                    <div class="eq-spec-row">
                        <dt>Accessories</dt>
                        <dd>${escapeHTML(item.accessories)}</dd>
                    </div>` : ''}
                </dl>

                <div class="eq-stock ${stockClass}">
                    <div class="eq-stock-text">
                        <span><strong class="avail-val">${available}</strong> / <span class="total-val">${total}</span> available</span>
                        ${available === 0 ? '<span class="eq-stock-flag">None left</span>' : ''}
                    </div>
                    <div class="eq-stock-bar" role="progressbar" aria-label="Available units" aria-valuemin="0" aria-valuemax="${total}" aria-valuenow="${available}">
                        <span style="width: ${pct}%"></span>
                    </div>
                </div>
            </div>
            <div class="eq-card-actions"></div>
        `;

        // A photo that fails to load shows the placeholder instead.
        const photo = el.querySelector('.eq-card-img');
        if (photo) {
            photo.addEventListener('error', () => el.querySelector('.eq-card-img-wrapper').classList.add('is-missing'), { once: true });
        }

        const BUTTONS = {
            edit: ['btn-card-edit', 'fa-regular fa-pen-to-square', 'Edit'],
            delete: ['btn-card-delete', 'fa-regular fa-trash-can', 'Delete'],
            view: ['btn-card-view', 'fa-regular fa-eye', 'View']
        };
        const actions = el.querySelector('.eq-card-actions');
        (options.actions || []).forEach(action => {
            const [cls, icon, label] = BUTTONS[action.kind];
            const button = document.createElement('button');
            button.type = 'button';
            button.className = cls;
            button.innerHTML = `<i class="${icon}" aria-hidden="true"></i> ${label}`;
            button.addEventListener('click', () => action.onClick(item.id));
            actions.appendChild(button);
        });

        return el;
    }

    // ------------------------------------------------------------------
    // Add / Edit window
    // ------------------------------------------------------------------

    /**
     * options.saveUrl, options.csrfToken
     * options.notify(title, message, type)   the page's toast ('success' | 'error')
     * options.onSaved(data, wasEdit)         after a successful save (reload the list)
     * options.lockClassificationOnEdit       Department: only the admin changes it
     *
     * Returns { openAdd(), openEdit(values), close(), isOpen() }.
     * openEdit values: { id, name, brand, model, category_id, department_id,
     *   classification, accessories, status, image,
     *   units: [{ unit_id, unit_code, serial_number, status, remarks, was_lent }] }
     */
    function init(options) {
        const $ = id => document.getElementById(id);
        const notify = options.notify || (() => {});

        const modal = $('equipmentModal');
        const form = $('equipmentForm');
        const modalTitle = $('modalTitle');
        const modalSubtitle = $('modalSubtitle');
        const modalIcon = $('modalIcon');
        const submitBtn = $('submitBtn');
        const idField = $('editItemId');

        const fields = {
            name: $('eqFormName'),
            brand: $('eqFormBrand'),
            model: $('eqFormModel'),
            category: $('eqFormCategory'),
            department: $('eqFormDepartment'),   // Admin only
            status: $('eqFormStatus'),
            classification: $('eqFormClassification'),
            accessories: $('eqFormAccessories')
        };
        const classLockedHint = $('eqClassLockedHint');

        // Photo uploader
        const imageField = $('eqFormImg');
        const imageUrlField = $('eqFormImgUrl');
        const dropzone = $('imageDropzone');
        const fileInput = $('imageFileInput');
        const urlAlternative = $('imageUrlAlternative');
        const applyUrlBtn = $('btnApplyUrl');
        const previewContainer = $('imagePreviewContainer');
        const previewImg = $('imagePreviewImg');
        const previewBackdrop = $('imagePreviewBackdrop');

        function showPreview(url) {
            previewImg.src = url;
            previewBackdrop.src = url;
            imageField.value = url;
            dropzone.style.display = 'none';
            urlAlternative.style.display = 'none';
            previewContainer.style.display = 'block';
        }

        function resetUploader() {
            previewImg.src = '';
            previewBackdrop.src = '';
            fileInput.value = '';
            imageField.value = '';
            imageUrlField.value = '';
            dropzone.style.display = 'flex';
            urlAlternative.style.display = 'flex';
            previewContainer.style.display = 'none';
        }

        function handleFile(file) {
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                notify('Invalid File', 'Please select a valid image file.', 'error');
                return;
            }
            if (file.size > MAX_PHOTO_BYTES) {
                notify('File Too Large', 'Please upload an image smaller than 5 MB.', 'error');
                return;
            }
            const reader = new FileReader();
            reader.onload = e => showPreview(e.target.result);
            reader.readAsDataURL(file);
        }

        fileInput.addEventListener('change', e => handleFile(e.target.files[0]));

        ['dragenter', 'dragover'].forEach(type => dropzone.addEventListener(type, e => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        }));
        ['dragleave', 'drop'].forEach(type => dropzone.addEventListener(type, e => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        }));
        dropzone.addEventListener('drop', e => handleFile(e.dataTransfer.files[0]));

        // The drop zone is a <label> for the file input, so a click opens the
        // file picker; Enter / Space do the same from the keyboard.
        dropzone.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                fileInput.click();
            }
        });

        applyUrlBtn.addEventListener('click', e => {
            e.preventDefault();
            const url = imageUrlField.value.trim();
            if (url) {
                showPreview(url);
            } else {
                notify('Empty URL', 'Please paste a valid image URL.', 'error');
            }
        });
        imageUrlField.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyUrlBtn.click();
            }
        });

        $('btnReplacePreview').addEventListener('click', () => fileInput.click());
        $('btnRemovePreview').addEventListener('click', resetUploader);

        // ------------------------------------------------------------------
        // Units: one row per physical unit (code, serial number, status).
        // The quantities are counted from them by the server.
        // ------------------------------------------------------------------
        const unitsList = $('eqUnitsList');
        const unitsSummary = $('eqUnitsSummary');
        const unitsHint = $('eqUnitsHint');
        const bulkPanel = $('eqUnitsBulk');
        const bulkToggle = $('eqUnitsBulkToggle');
        const bulkSerials = $('eqUnitsBulkSerials');
        const bulkCount = $('eqUnitsBulkCount');
        const EDITABLE = ['Available', 'Under Maintenance', 'Retired'];
        const GONE = ['Lost', 'Retired'];
        let removedUnitIds = [];

        const isExpensive = () => fields.classification.value === 'Expensive';

        function unitRow(unit) {
            const isNew = !unit.unit_id;
            const status = unit.status || 'Available';
            const editable = isNew || EDITABLE.includes(status);
            const removable = isNew || (!unit.was_lent && status !== 'On Loan');
            const code = isNew ? 'New unit' : unit.unit_code;
            const row = document.createElement('div');
            row.className = 'eq-unit-row';
            row.setAttribute('role', 'listitem');
            row.dataset.unitId = unit.unit_id || '';
            row.dataset.status = status;

            const statusOptions = (isNew ? ['Available', 'Under Maintenance'] : EDITABLE)
                .map(s => `<option value="${s}"${s === status ? ' selected' : ''}>${s}</option>`).join('');

            row.innerHTML = `
                <span class="eq-unit-code mono${isNew ? ' is-new' : ''}">${escapeHTML(code)}</span>
                <input type="text" class="form-control-flat mono eq-unit-serial" maxlength="50" autocomplete="off"
                       value="${escapeHTML(unit.serial_number || '')}" aria-label="Serial number of ${escapeHTML(code)}">
                ${editable
                    ? `<div class="select-wrapper eq-unit-status-wrap">
                           <select class="form-control-flat eq-unit-status" aria-label="Status of ${escapeHTML(code)}">${statusOptions}</select>
                           <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                       </div>`
                    : `<span class="eq-unit-badge is-${status === 'On Loan' ? 'loan' : 'lost'}" title="${status === 'On Loan' ? 'With a borrower: it changes when it is returned.' : 'Lost: kept for its history.'}">${escapeHTML(status)}</span>`}
                ${removable
                    ? `<button type="button" class="eq-unit-remove" aria-label="Remove ${escapeHTML(code)}" title="Remove this unit"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>`
                    : '<span class="eq-unit-remove-spacer" aria-hidden="true"></span>'}
                ${unit.remarks ? `<p class="eq-unit-note">${escapeHTML(unit.remarks)}</p>` : ''}`;
            return row;
        }

        function rows() {
            return Array.from(unitsList.querySelectorAll('.eq-unit-row'));
        }

        function rowStatus(row) {
            const select = row.querySelector('.eq-unit-status');
            return select ? select.value : row.dataset.status;
        }

        // Summary, hint and the Expensive rule (every unit in use needs a serial number).
        function refreshUnits() {
            const all = rows();
            const inUse = all.filter(r => !GONE.includes(rowStatus(r)));
            const available = all.filter(r => rowStatus(r) === 'Available').length;
            unitsSummary.textContent = `${inUse.length} ${inUse.length === 1 ? 'unit' : 'units'} · ${available} available`;

            const expensive = isExpensive();
            unitsHint.textContent = expensive
                ? 'Expensive equipment: every unit needs its own serial number.'
                : 'Serial numbers are optional. A unit without one is known by its code (e.g. EQ-12-003).';
            all.forEach(r => {
                const serial = r.querySelector('.eq-unit-serial');
                const needed = expensive && !GONE.includes(rowStatus(r));
                serial.required = needed;
                serial.placeholder = needed ? 'Serial number (required)' : 'Serial number (optional)';
                r.classList.toggle('is-retired', GONE.includes(rowStatus(r)));
            });
            $('eqUnitsBulkCountLabel').textContent = expensive
                ? 'Expensive units need serial numbers, so add them as a list above.'
                : 'or this many units without serial numbers';
            bulkCount.disabled = expensive;
        }

        function addUnits(units, focusLast = false) {
            units.forEach(u => unitsList.appendChild(unitRow(u)));
            refreshUnits();
            if (focusLast) rows().at(-1)?.querySelector('.eq-unit-serial').focus();
        }

        function setUnits(units) {
            unitsList.innerHTML = '';
            removedUnitIds = [];
            addUnits(units);
        }

        unitsList.addEventListener('click', e => {
            const remove = e.target.closest('.eq-unit-remove');
            if (!remove) return;
            const row = remove.closest('.eq-unit-row');
            if (row.dataset.unitId) removedUnitIds.push(row.dataset.unitId);
            const next = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            refreshUnits();
            (next?.querySelector('.eq-unit-serial') || $('eqUnitsAdd')).focus();
        });
        unitsList.addEventListener('change', e => {
            if (e.target.matches('.eq-unit-status')) refreshUnits();
        });
        fields.classification.addEventListener('change', refreshUnits);
        $('eqUnitsAdd').addEventListener('click', () => addUnits([{}], true));

        function setBulkOpen(open) {
            bulkPanel.hidden = !open;
            bulkToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                bulkSerials.focus();
            } else {
                bulkSerials.value = '';
                bulkCount.value = '';
            }
        }
        bulkToggle.addEventListener('click', () => setBulkOpen(bulkPanel.hidden));
        $('eqUnitsBulkCancel').addEventListener('click', () => {
            setBulkOpen(false);
            bulkToggle.focus();
        });
        $('eqUnitsBulkAdd').addEventListener('click', () => {
            const serials = bulkSerials.value.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
            const count = bulkCount.disabled ? 0 : Math.min(200, parseInt(bulkCount.value, 10) || 0);
            if (!serials.length && !count) {
                notify('Nothing to Add', 'Type serial numbers (one per line) or how many units to add.', 'error');
                return;
            }
            addUnits([...serials.map(s => ({ serial_number: s })), ...Array.from({ length: count }, () => ({}))]);
            setBulkOpen(false);
            bulkToggle.focus();
        });

        // The units as the server reads them (EquipmentUnitForm).
        function appendUnits(formData) {
            rows().forEach((r, i) => {
                formData.append(`units[${i}][unit_id]`, r.dataset.unitId || '');
                formData.append(`units[${i}][serial_number]`, r.querySelector('.eq-unit-serial').value.trim());
                const select = r.querySelector('.eq-unit-status');
                if (select) formData.append(`units[${i}][status]`, select.value);
            });
            removedUnitIds.forEach(id => formData.append('remove_units[]', id));
        }

        // Checked here too, so the problem is shown before anything is sent.
        function unitsProblem() {
            const all = rows();
            if (!all.length && idField.value === '') return 'Add at least one unit.';
            const seen = new Map();
            for (const r of all) {
                const serial = r.querySelector('.eq-unit-serial').value.trim();
                const name = r.querySelector('.eq-unit-code').textContent;
                if (!serial) continue;
                const key = serial.toLowerCase();
                if (seen.has(key)) return `Serial number "${serial}" is entered twice. Each unit needs its own.`;
                seen.set(key, name);
            }
            return null;
        }

        function setClassificationLocked(locked) {
            fields.classification.disabled = locked;
            if (classLockedHint) classLockedHint.hidden = !locked;
        }

        function show() {
            refreshUnits();
            setBulkOpen(false);
            modal.querySelector('.eq-modal-body').scrollTop = 0;   // always start at the photo
            modal.classList.add('show');
            setTimeout(() => fields.name.focus({ preventScroll: true }), 50);
        }

        function setHeading(isEdit) {
            modalTitle.textContent = isEdit ? 'Edit Equipment' : 'Add New Equipment';
            modalSubtitle.textContent = isEdit ? 'Update inventory details for this item' : 'Provide details for the inventory item';
            modalIcon.className = isEdit ? 'fa-solid fa-pen-to-square' : 'fa-solid fa-plus';
            submitBtn.textContent = isEdit ? 'Save Changes' : 'Add Item';
        }

        function clearForm() {
            form.reset();
            idField.value = '';   // hidden fields are not cleared by reset()
            resetUploader();
            setClassificationLocked(false);
        }

        function openAdd() {
            clearForm();
            setUnits([{}]);   // one new unit to start with
            setHeading(false);
            show();
        }

        function openEdit(values) {
            clearForm();
            idField.value = values.id;
            fields.name.value = values.name || '';
            fields.brand.value = values.brand || '';
            fields.model.value = values.model || '';
            fields.category.value = values.category_id || '';
            if (fields.department) fields.department.value = values.department_id || '';
            fields.status.value = values.status || 'Available';
            fields.classification.value = values.classification === 'Expensive' ? 'Expensive' : 'Not Expensive';
            fields.accessories.value = values.accessories || '';
            setUnits(values.units || []);
            setClassificationLocked(!!options.lockClassificationOnEdit);

            // No photo on file: show the upload box instead of a broken preview
            if (values.image) showPreview(values.image);

            setHeading(true);
            show();
        }

        function close() {
            modal.classList.remove('show');
            clearForm();
            setUnits([]);
        }

        function isOpen() {
            return modal.classList.contains('show');
        }

        $('closeModalBtn').addEventListener('click', close);
        $('cancelModalBtn').addEventListener('click', close);
        modal.addEventListener('click', e => {
            if (e.target === modal) close();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && isOpen()) close();
        });

        form.addEventListener('submit', e => {
            e.preventDefault();

            if (!imageField.value.trim()) {
                notify('Image Required', 'Please upload an image or enter an image URL.', 'error');
                return;
            }
            const problem = unitsProblem();
            if (problem) {
                notify('Check the Units', problem, 'error');
                return;
            }
            if (!fields.classification.disabled && !['Expensive', 'Not Expensive'].includes(fields.classification.value)) {
                notify('Classification Required', 'Please select an equipment classification.', 'error');
                return;
            }

            const wasEdit = idField.value !== '';
            const name = fields.name.value.trim();

            // Lock the button while saving so the request can't be sent twice
            const label = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving…';

            const body = new FormData(form);   // a locked (disabled) classification is not sent
            appendUnits(body);

            fetch(options.saveUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': options.csrfToken, 'Accept': 'application/json' },
                body
            })
                .then(res => res.json().then(data => ({ ok: res.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        close();
                        notify(wasEdit ? 'Updated Successfully' : 'Added Successfully', data.message || `Saved ${name}.`, 'success');
                        if (options.onSaved) options.onSaved(data, wasEdit);
                    } else {
                        notify('Save Failed', (data && data.message) || 'Unable to save the equipment.', 'error');
                    }
                })
                .catch(() => notify('System Error', 'An unexpected error occurred while saving equipment.', 'error'))
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = label;
                });
        });

        return { openAdd, openEdit, close, isOpen };
    }

    window.EquipmentForm = { init, card, escapeHTML, statusClass };
})();
