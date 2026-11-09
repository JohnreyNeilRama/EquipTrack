@extends('layouts.user')

@section('title', 'EquipTrack - Available Equipment')

@push('css')
<link rel="stylesheet" href="{{ asset('user/css/useravailequipment.css') }}?v={{ filemtime(public_path('user/css/useravailequipment.css')) }}">
@endpush

@php
    $categoryIcon = function (string $catName): string {
        $lower = strtolower($catName);
        if (str_contains($lower, 'laptop') || str_contains($lower, 'computer')) return 'fa-laptop';
        if (str_contains($lower, 'projector') || str_contains($lower, 'video')) return 'fa-video';
        if (str_contains($lower, 'camera')) return 'fa-camera';
        if (str_contains($lower, 'audio') || str_contains($lower, 'sound') || str_contains($lower, 'speaker')) return 'fa-music';
        if (str_contains($lower, 'mic')) return 'fa-microphone';
        if (str_contains($lower, 'lab')) return 'fa-flask';
        if (str_contains($lower, 'calc')) return 'fa-calculator';
        return 'fa-box';
    };
    $resolveImg = function (?string $raw): string {
        $raw = trim((string) $raw);
        if ($raw === '') return asset('images/EquipTrack_logo.png');
        if (preg_match('/^(https?:\/\/|data:|\/storage\/)/i', $raw)) return $raw;
        return asset(ltrim($raw, '/'));
    };
@endphp

@section('content')
    <div class="equipment-container">
        @if ($penaltyBlock)
            <div class="penalty-block-banner" role="alert">
                <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
                <div>
                    <strong>Borrowing is on hold</strong>
                    <span>{{ $penaltyBlock }}</span>
                </div>
                <a href="{{ $penaltyBlockUrl }}" class="penalty-block-action"><i class="fa-solid fa-upload" aria-hidden="true"></i> Submit Proof</a>
            </div>
        @endif

        {{-- Categories Section --}}
        <div class="categories-section">
            <h2 class="section-title">Categories</h2>
            <div class="categories-list" id="categoriesList">
                <div class="category-card active" data-category="all">
                    <i class="fa-solid fa-table-cells-large"></i>
                    <div class="cat-info">
                        <span class="cat-name">All</span>
                        <span class="cat-count">{{ $dbEquipment->count() }}</span>
                    </div>
                </div>
                @foreach ($dbCategories as $catRow)
                    @php $cName = $catRow->category_name; @endphp
                    <div class="category-card" data-category="{{ strtolower($cName) }}">
                        <i class="fa-solid {{ $categoryIcon($cName) }}"></i>
                        <div class="cat-info">
                            <span class="cat-name">{{ $cName }}</span>
                            <span class="cat-count">{{ $categoryCounts[$cName] ?? 0 }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Available Equipments Section --}}
        <div class="equipments-section">
            <div class="equipments-header">
                <h2 class="section-title">Available Equipments</h2>
                <div class="search-wrapper">
                    <input type="text" id="searchInput" placeholder="Search equipment by name, brand, model...">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                </div>
            </div>

            <div class="equipment-grid" id="equipmentGrid">
                @forelse ($dbEquipment as $eq)
                    @php
                        $eqCategory = $eq->category?->category_name ?: 'Uncategorized';
                        $eqDept = $eq->department?->department_name ?: ($eq->department?->department_code ?: 'General');
                        $eqAvail = (int) $eq->available_qty;
                        $eqStatus = $eq->status ?: 'Available';
                        $imgUrl = $resolveImg($eq->image);
                        $isLowStock = ($eqAvail <= 1);
                        // Units out on loan can still be booked for other dates, so the
                        // button only depends on the admin's status and lendable units.
                        $eqLendable = $availability[$eq->equipment_id]['lendable'] ?? $eqAvail;
                        $eqFullyBooked = $availability[$eq->equipment_id]['fullyBooked'] ?? [];
                        $isUnavailable = (strtolower($eqStatus) !== 'available' || $eqLendable <= 0);
                        // Units one request may ask for (1 for Expensive equipment); the server checks it again.
                        $eqMaxUnits = $eq->maxUnitsPerRequest((int) $eqLendable);
                    @endphp
                    <div class="equipment-card"
                         data-id="{{ $eq->equipment_id }}"
                         data-name="{{ strtolower($eq->name) }}"
                         data-brand="{{ strtolower($eq->brand) }}"
                         data-model="{{ strtolower($eq->model ?? '') }}"
                         data-category="{{ strtolower($eqCategory) }}"
                         data-status="{{ strtolower($eqStatus) }}">

                        <div class="eq-img-container">
                            <img src="{{ $imgUrl }}"
                                 alt="{{ $eq->name }}"
                                 onerror="this.onerror=null; this.src='{{ asset('images/EquipTrack_logo.png') }}';">
                        </div>
                        <div class="eq-details">
                            <h4 class="eq-name" title="{{ $eq->name }}">{{ $eq->name }}</h4>

                            <div class="eq-meta-row">
                                <span class="eq-category"><i class="fa-solid fa-tag"></i> {{ $eqCategory }}</span>
                                <span class="eq-dept"><i class="fa-solid fa-building"></i> {{ $eqDept }}</span>
                            </div>

                            <div class="eq-stock-row">
                                <span class="eq-available {{ $isLowStock ? 'low-stock' : '' }}">
                                    <i class="fa-solid fa-circle-check"></i> In the lab now: <strong>{{ $eqAvail }} / {{ (int) $eq->total_qty }}</strong>
                                </span>
                                <span class="eq-status-badge {{ strtolower($eqStatus) === 'available' ? 'status-available' : 'status-unavailable' }}">
                                    {{ $eqStatus }}
                                </span>
                            </div>
                        </div>

                        <button type="button" class="btn-request"
                                data-id="{{ $eq->equipment_id }}"
                                data-name="{{ $eq->name }}"
                                data-category="{{ $eqCategory }}"
                                data-available="{{ $eqAvail }}"
                                data-max-units="{{ $eqMaxUnits }}"
                                data-expensive="{{ $eq->isExpensive() ? '1' : '0' }}"
                                data-fully-booked="{{ json_encode(array_column($eqFullyBooked, 'label')) }}"
                                data-img="{{ $imgUrl }}"
                                @if ($isUnavailable) disabled @endif>
                            {{ $isUnavailable ? 'Not Available' : 'Request Item' }}
                        </button>
                    </div>
                @empty
                    <div class="empty-state-card" id="emptyStateCard">
                        <i class="fa-solid fa-box-open empty-icon"></i>
                        <h4 class="empty-title">No available equipment</h4>
                        <p class="empty-desc">Equipment items created by the admin will appear here automatically.</p>
                    </div>
                @endforelse
            </div>

            {{-- Dynamic Empty State JS Container --}}
            <div class="empty-state-card" id="searchEmptyState" style="display: none;">
                <i class="fa-solid fa-magnifying-glass empty-icon"></i>
                <h4 class="empty-title">No matching equipment found</h4>
                <p class="empty-desc">Try adjusting your search terms or category selection.</p>
            </div>
        </div>
    </div>

    {{-- Borrow Request Modal --}}
    <div class="modal-overlay" id="borrowModal">
        <div class="modal-card borrow-modal-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Fill in the details to borrow equipment</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeModalBtn">&times;</button>
                <h3 class="modal-title-center">Request Equipment</h3>

                <form id="borrowForm" class="new-modal-form">
                    <input type="hidden" id="modalEqId" value="">
                    <div class="form-main-content">
                        {{-- Left Side: Image Preview --}}
                        <div class="form-left-img">
                            <img src="" alt="" id="modalEqImg">
                        </div>

                        {{-- Right Side: Details --}}
                        <div class="form-right-fields">
                            <div class="form-horizontal-group">
                                <label class="flat-label">Name:</label>
                                <input type="text" id="modalEqName" class="flat-control" readonly>
                            </div>
                            <div class="form-horizontal-group">
                                <label class="flat-label">Available:</label>
                                <input type="text" id="modalEqAvailable" class="flat-control" readonly>
                            </div>
                            <div class="form-horizontal-group">
                                <label class="flat-label">Category:</label>
                                <input type="text" id="modalEqCategory" class="flat-control" readonly>
                            </div>
                            {{-- Units: 1 for high-value equipment, otherwise up to what the lab can lend --}}
                            <div class="form-horizontal-group">
                                <label class="flat-label" for="borrowQuantity">Quantity:</label>
                                <input type="number" id="borrowQuantity" class="flat-control" min="1" max="1" step="1" value="1" inputmode="numeric" required>
                            </div>
                            <p class="lab-hours-note" id="quantityNote"></p>
                            {{-- Dates only: Within the Day (returned the same day) or several days --}}
                            <div class="form-horizontal-group">
                                <span class="flat-label" id="borrowTypeLabel">Duration:</span>
                                <div class="duration-chips" role="radiogroup" aria-labelledby="borrowTypeLabel">
                                    <button type="button" class="duration-chip is-active" role="radio" aria-checked="true" data-type="within_day">Within the Day</button>
                                    <button type="button" class="duration-chip" role="radio" aria-checked="false" data-type="multiple_days">Multiple Days</button>
                                </div>
                            </div>
                            <div class="form-horizontal-group">
                                <label class="flat-label" for="borrowDate">Borrow:</label>
                                <input type="date" id="borrowDate" class="flat-control" aria-label="Borrow date" required>
                            </div>
                            <div class="form-horizontal-group" id="returnDateGroup" hidden>
                                <label class="flat-label" for="returnDate">Return:</label>
                                <input type="date" id="returnDate" class="flat-control" aria-label="Return date">
                            </div>
                            <p class="lab-hours-note">
                                Office days: {{ \App\Support\LoanTime::officeDaysLabel() }}.
                                Pick up any time during office hours ({{ \App\Support\LoanTime::clock(\App\Support\LoanTime::opens()) }} – {{ \App\Support\LoanTime::clock(\App\Support\LoanTime::closes()) }}).
                                Return by <strong>{{ \App\Support\LoanTime::clock(\App\Support\LoanTime::deadline()) }}</strong> on the <span id="returnByWhen">same day</span>.
                            </p>
                            {{-- Periods when no unit is free (filled in when the form opens) --}}
                            <p class="booked-dates" id="bookedDates" aria-live="polite"></p>
                        </div>
                    </div>

                    {{-- Lower section: Purpose, Instructor Name and Notes --}}
                    <div class="form-lower-section">
                        <div class="form-horizontal-group align-start">
                            <label class="flat-label">Purpose:</label>
                            <div class="flat-select-wrapper">
                                <select id="borrowPurpose" class="flat-control flat-select" required>
                                    <option value="" disabled selected>Select your purpose...</option>
                                    <option value="Class Project / Presentation">Class Project / Presentation</option>
                                    <option value="Laboratory Activity">Laboratory Activity</option>
                                    <option value="Research & Development">Research &amp; Development</option>
                                    <option value="School Event / Organization">School Event / Organization</option>
                                    <option value="Personal Study">Personal Study</option>
                                </select>
                            </div>
                        </div>

                        {{-- Students only: faculty members are the instructors themselves --}}
                        @if ($askInstructor)
                        <div class="form-horizontal-group">
                            <label class="flat-label" for="borrowInstructor">Instructor Name:</label>
                            <input type="text" id="borrowInstructor" class="flat-control" maxlength="150" autocomplete="off" placeholder="Enter your instructor's name" required>
                        </div>
                        @endif

                        <div class="form-horizontal-group align-start">
                            <label class="flat-label">Notes:</label>
                            <textarea id="borrowNotes" class="flat-control flat-textarea" rows="3" placeholder="Additional notes or instructions (optional)..."></textarea>
                        </div>
                    </div>

                    {{-- Warning Message --}}
                    <div class="form-warning">
                        <i class="fa-solid fa-triangle-exclamation warning-icon"></i>
                        <span>Please return the equipment on time to avoid penalties.</span>
                    </div>

                    {{-- Submit Button --}}
                    <div class="form-submit-container">
                        <button type="submit" class="btn-submit-request">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    {{-- EquipTrack's own message (same look as My Requests) for results and errors --}}
    <div class="toast-notification" id="equipmentToast" role="status" aria-live="polite">
        <div class="toast-content">
            <i class="fa-solid fa-circle-check toast-icon"></i>
            <div class="toast-message">
                <span class="toast-title"></span>
                <span class="toast-desc"></span>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const CSRF_TOKEN = @json(csrf_token());

    document.addEventListener('DOMContentLoaded', () => {
        const categoryCards = document.querySelectorAll('.category-card');
        const equipmentCards = document.querySelectorAll('.equipment-card');
        const searchInput = document.getElementById('searchInput');
        const searchEmptyState = document.getElementById('searchEmptyState');

        // Filter items function
        function filterItems() {
            const activeCard = document.querySelector('.category-card.active');
            const selectedCategory = activeCard ? activeCard.getAttribute('data-category').toLowerCase() : 'all';
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';

            let visibleCount = 0;

            equipmentCards.forEach(card => {
                const cardCategory = card.getAttribute('data-category').toLowerCase();
                const cardName     = card.getAttribute('data-name') || '';
                const cardBrand    = card.getAttribute('data-brand') || '';
                const cardModel    = card.getAttribute('data-model') || '';

                const matchesCategory = (selectedCategory === 'all' || cardCategory === selectedCategory);
                const matchesSearch   = !searchTerm ||
                                       cardName.includes(searchTerm) ||
                                       cardBrand.includes(searchTerm) ||
                                       cardModel.includes(searchTerm);

                if (matchesCategory && matchesSearch) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (searchEmptyState) {
                if (visibleCount === 0 && equipmentCards.length > 0) {
                    searchEmptyState.style.display = 'block';
                } else {
                    searchEmptyState.style.display = 'none';
                }
            }
        }

        categoryCards.forEach(card => {
            card.addEventListener('click', () => {
                categoryCards.forEach(c => c.classList.remove('active'));
                card.classList.add('active');
                filterItems();
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', filterItems);
        }

        // Modal Interactions
        const modal = document.getElementById('borrowModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const borrowForm = document.getElementById('borrowForm');
        const borrowDateInput = document.getElementById('borrowDate');
        const returnDateInput = document.getElementById('returnDate');
        const returnDateGroup = document.getElementById('returnDateGroup');
        const returnByWhen = document.getElementById('returnByWhen');
        const typeChips = document.querySelectorAll('.duration-chip[data-type]');

        // Today's return deadline, the office days and the booking window
        // (config/equiptrack.php). After the deadline nothing can be borrowed
        // today; borrow and return dates must be office days. The server
        // checks the same rules.
        const RETURN_DEADLINE = @json(\App\Support\LoanTime::deadline());
        const RETURN_DEADLINE_TEXT = @json(\App\Support\LoanTime::clock(\App\Support\LoanTime::deadline()));
        const OFFICE_DAYS = @json(\App\Support\LoanTime::officeDays());
        const OFFICE_DAYS_TEXT = @json(\App\Support\LoanTime::officeDaysLabel());
        const MAX_ADVANCE_DAYS = @json((int) config('equiptrack.max_advance_days', 30));
        const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        // Dates as YYYY-MM-DD in the user's own time zone. toISOString() would
        // give the UTC date, which is still yesterday before 8:00 AM in the
        // Philippines.
        const pad = (n) => String(n).padStart(2, '0');
        const toYmd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        const toHm = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
        const parseYmd = (ymd) => {
            const [y, m, d] = ymd.split('-').map(Number);
            return new Date(y, m - 1, d);
        };
        const addDays = (ymd, days) => {
            const d = parseYmd(ymd);
            return toYmd(new Date(d.getFullYear(), d.getMonth(), d.getDate() + days));
        };
        const nextDay = (ymd) => addDays(ymd, 1);
        // OFFICE_DAYS uses ISO weekdays (1 = Monday ... 7 = Sunday).
        const isOfficeDay = (ymd) => OFFICE_DAYS.includes(parseYmd(ymd).getDay() || 7);
        const nextOfficeDay = (ymd) => {
            let day = ymd;
            for (let i = 0; i < 7 && !isOfficeDay(day); i++) day = nextDay(day);
            return day;
        };
        const closedMessage = (ymd, which) => 'The equipment office is closed on ' + DAY_NAMES[parseYmd(ymd).getDay()]
            + 's. Please choose a ' + which + ' date from ' + OFFICE_DAYS_TEXT + '.';

        // Today while the office is open and before the return deadline,
        // otherwise the next office day.
        function earliestBorrowDate() {
            const now = new Date();
            const today = toYmd(now);
            return isOfficeDay(today) && toHm(now) < RETURN_DEADLINE ? today : nextOfficeDay(nextDay(today));
        }

        const latestBorrowDate = () => addDays(toYmd(new Date()), MAX_ADVANCE_DAYS);

        // ---- Messages -----------------------------------------------------
        // A mistake in the form shows in red under its field (the field gets a
        // red border and the focus); results and server errors show as
        // EquipTrack's own message at the corner (same as My Requests).
        function clearFieldMessages() {
            borrowForm.querySelectorAll('.field-message').forEach(el => el.remove());
            borrowForm.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
                el.removeAttribute('aria-invalid');
            });
        }

        function showFieldMessage(input, message, kind = 'error') {
            clearFieldMessages();
            const group = input.closest('.form-horizontal-group') || input;
            const note = document.createElement('p');
            note.className = 'field-message field-message-' + kind;
            note.setAttribute('role', kind === 'error' ? 'alert' : 'status');
            const icon = document.createElement('i');
            icon.className = 'fa-solid ' + (kind === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info');
            icon.setAttribute('aria-hidden', 'true');
            note.append(icon, ' ', message);
            group.insertAdjacentElement('afterend', note);
            if (kind === 'error') {
                input.classList.add('is-invalid');
                input.setAttribute('aria-invalid', 'true');
                input.focus({ preventScroll: true });
            }
            note.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }

        const equipmentToast = document.getElementById('equipmentToast');
        let toastTimer = null;
        function showToast(title, message, type = 'success') {
            const ok = type === 'success';
            const icon = equipmentToast.querySelector('.toast-icon');
            equipmentToast.querySelector('.toast-title').textContent = title;
            equipmentToast.querySelector('.toast-desc').textContent = message;
            icon.className = 'fa-solid ' + (ok ? 'fa-circle-check' : 'fa-circle-xmark') + ' toast-icon';
            icon.style.color = ok ? '#10b981' : '#ef4444';
            equipmentToast.querySelector('.toast-content').style.borderLeft = '4px solid ' + (ok ? '#10b981' : '#ef4444');
            equipmentToast.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => equipmentToast.classList.remove('show'), 5000);
        }

        // Typing or picking again clears the message for that mistake.
        if (borrowForm) {
            ['input', 'change'].forEach(type => borrowForm.addEventListener(type, e => {
                if (e.target.classList && e.target.classList.contains('is-invalid')) clearFieldMessages();
            }));
        }

        // A closed day picked in the calendar moves to the next office day,
        // with a note why (the browser's date picker can't grey out weekdays).
        function keepOfficeDay(input, which) {
            if (!input.value || isOfficeDay(input.value)) return;
            const picked = input.value;
            input.value = nextOfficeDay(picked);
            showFieldMessage(input, closedMessage(picked, which) + ' It was moved to the next office day.', 'info');
        }

        let borrowType = 'within_day';

        // Within the Day: no return date (it is the borrow date).
        // Multiple Days: the return date is after the borrow date.
        function setBorrowType(type) {
            borrowType = type;
            typeChips.forEach(chip => {
                const on = chip.dataset.type === type;
                chip.classList.toggle('is-active', on);
                chip.setAttribute('aria-checked', on ? 'true' : 'false');
            });
            const multi = type === 'multiple_days';
            if (returnDateGroup) returnDateGroup.hidden = !multi;
            if (returnDateInput) returnDateInput.required = multi;
            if (returnByWhen) returnByWhen.textContent = multi ? 'return date' : 'same day';
            syncReturnMin();
        }

        function syncReturnMin() {
            if (!borrowDateInput.value || !returnDateInput) return;
            returnDateInput.min = nextOfficeDay(nextDay(borrowDateInput.value));
            if (returnDateInput.value && returnDateInput.value < returnDateInput.min) {
                returnDateInput.value = returnDateInput.min;
            }
        }

        if (borrowDateInput) borrowDateInput.addEventListener('change', () => { keepOfficeDay(borrowDateInput, 'borrow'); syncReturnMin(); });
        if (returnDateInput) returnDateInput.addEventListener('change', () => keepOfficeDay(returnDateInput, 'return'));
        typeChips.forEach(chip => chip.addEventListener('click', () => setBorrowType(chip.dataset.type)));

        // Attach request click event to buttons
        const requestBtns = document.querySelectorAll('.btn-request');
        requestBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (btn.disabled) return;

                const eqId       = btn.getAttribute('data-id');
                const eqName     = btn.getAttribute('data-name');
                const eqCategory = btn.getAttribute('data-category');
                const eqAvail    = btn.getAttribute('data-available');
                const eqImg      = btn.getAttribute('data-img');
                const maxUnits   = Math.max(1, parseInt(btn.getAttribute('data-max-units'), 10) || 1);
                const expensive  = btn.getAttribute('data-expensive') === '1';

                document.getElementById('modalEqId').value        = eqId;
                document.getElementById('modalEqName').value      = eqName;
                document.getElementById('modalEqCategory').value  = eqCategory;
                document.getElementById('modalEqAvailable').value = eqAvail;

                // Quantity: fixed at 1 for high-value equipment, else 1 up to the limit.
                const qtyInput = document.getElementById('borrowQuantity');
                const qtyNote = document.getElementById('quantityNote');
                qtyInput.value = 1;
                qtyInput.max = maxUnits;
                qtyInput.readOnly = expensive || maxUnits === 1;
                qtyNote.textContent = expensive
                    ? 'High-value equipment: 1 unit per request.'
                    : (maxUnits > 1 ? 'You can request up to ' + maxUnits + ' units.' : 'Only 1 unit can be lent right now.');

                // "Fully booked: Nov 5–7, Nov 20": no unit is free on those dates.
                const bookedLine = document.getElementById('bookedDates');
                if (bookedLine) {
                    let booked = [];
                    try { booked = JSON.parse(btn.getAttribute('data-fully-booked') || '[]'); } catch (err) { booked = []; }
                    bookedLine.classList.toggle('has-bookings', booked.length > 0);
                    bookedLine.innerHTML = booked.length
                        ? '<i class="fa-solid fa-calendar-xmark"></i> <span><strong>Fully booked:</strong> ' + booked.map(b => b.replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]))).join(', ') + '</span>'
                        : '<i class="fa-solid fa-calendar-check"></i> <span>No bookings yet, any dates are open.</span>';
                }

                const modalImg = document.getElementById('modalEqImg');
                modalImg.src = eqImg;
                modalImg.alt = eqName;

                // Start on the earliest office day, Within the Day.
                if (borrowDateInput) {
                    borrowDateInput.min = earliestBorrowDate();
                    borrowDateInput.max = latestBorrowDate();
                    borrowDateInput.value = borrowDateInput.min;
                }
                if (returnDateInput) returnDateInput.value = "";
                setBorrowType('within_day');

                if (modal) modal.classList.add('show');
            });
        });

        // Close modal functions
        const closeModal = () => {
            if (modal) modal.classList.remove('show');
            if (borrowForm) {
                borrowForm.reset();
                clearFieldMessages();
            }
        };

        if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);

        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            });
        }

        // Handle Borrow Form Submission
        if (borrowForm) {
            borrowForm.addEventListener('submit', (e) => {
                e.preventDefault();

                const eqId       = document.getElementById('modalEqId').value;
                const borrowDate = borrowDateInput ? borrowDateInput.value : '';
                const returnDate = returnDateInput ? returnDateInput.value : '';
                const purpose    = document.getElementById('borrowPurpose').value;
                // Only students have this field (faculty members are the instructors)
                const instructorInput = document.getElementById('borrowInstructor');
                const instructor = instructorInput ? instructorInput.value.trim() : '';
                const notes      = document.getElementById('borrowNotes').value;
                const submitBtn  = borrowForm.querySelector('.btn-submit-request');
                clearFieldMessages();

                if (!eqId) {
                    showToast('Request not sent', 'Invalid equipment item selected. Please close the form and choose the equipment again.', 'error');
                    return;
                }

                if (!borrowDate) {
                    showFieldMessage(borrowDateInput, 'Please choose the borrow date.');
                    return;
                }

                if (!isOfficeDay(borrowDate)) {
                    showFieldMessage(borrowDateInput, closedMessage(borrowDate, 'borrow'));
                    return;
                }

                if (borrowDate < earliestBorrowDate()) {
                    showFieldMessage(borrowDateInput, borrowDate === toYmd(new Date())
                        ? "Today's return deadline (" + RETURN_DEADLINE_TEXT + ') has passed. Please choose a later date.'
                        : 'The borrow date cannot be in the past.');
                    return;
                }

                if (borrowDate > latestBorrowDate()) {
                    showFieldMessage(borrowDateInput, 'You can request up to ' + MAX_ADVANCE_DAYS + ' days ahead.');
                    return;
                }

                if (borrowType === 'multiple_days' && !returnDate) {
                    showFieldMessage(returnDateInput, 'Please choose the return date.');
                    return;
                }

                if (borrowType === 'multiple_days' && returnDate <= borrowDate) {
                    showFieldMessage(returnDateInput, 'For Multiple Days, the return date must be after the borrow date.');
                    return;
                }

                if (borrowType === 'multiple_days' && !isOfficeDay(returnDate)) {
                    showFieldMessage(returnDateInput, closedMessage(returnDate, 'return'));
                    return;
                }

                const qtyInput = document.getElementById('borrowQuantity');
                const quantity = Number(qtyInput.value);
                const maxQty = Number(qtyInput.max) || 1;
                if (!Number.isInteger(quantity) || quantity < 1) {
                    showFieldMessage(qtyInput, 'Please enter the number of units as a whole number (at least 1).');
                    return;
                }
                if (quantity > maxQty) {
                    showFieldMessage(qtyInput, maxQty === 1 ? 'You can request 1 unit of this equipment.' : 'You can request up to ' + maxQty + ' units of this equipment.');
                    return;
                }

                if (!purpose) {
                    showFieldMessage(document.getElementById('borrowPurpose'), 'Please select a purpose for borrowing.');
                    return;
                }

                if (instructorInput && !instructor) {
                    showFieldMessage(instructorInput, "Please enter your instructor's name.");
                    return;
                }

                const formData = new FormData();
                formData.append('_token', CSRF_TOKEN);
                formData.append('equipment_id', eqId);
                formData.append('borrow_type', borrowType);
                formData.append('quantity', quantity);
                formData.append('borrow_date', borrowDate);
                formData.append('return_date', borrowType === 'within_day' ? borrowDate : returnDate);
                formData.append('purpose', purpose);
                if (instructorInput) formData.append('instructor_name', instructor);
                formData.append('notes', notes);

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
                }

                // Accept: JSON makes the server answer refused input with its
                // own message instead of redirecting.
                fetch(@json(route('user.borrow.store')), {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const message = data.message || 'Your borrowing request has been submitted successfully!';
                        closeModal();
                        if (data.redirect) {
                            // My Requests shows the message after the page opens.
                            try {
                                sessionStorage.setItem('equiptrack:flash', JSON.stringify({ title: 'Request submitted', message }));
                            } catch (err) { /* storage unavailable: the new request is still listed */ }
                            window.location.href = data.redirect;
                        } else {
                            showToast('Request submitted', message);
                        }
                    } else {
                        // A refused field shows under that field; anything else as a message.
                        const fields = {
                            borrow_date: borrowDateInput,
                            return_date: borrowType === 'multiple_days' ? returnDateInput : borrowDateInput,
                            quantity: document.getElementById('borrowQuantity'),
                            purpose: document.getElementById('borrowPurpose'),
                            instructor_name: instructorInput,
                            notes: document.getElementById('borrowNotes'),
                        };
                        const [field, errors] = data.errors ? Object.entries(data.errors)[0] : [null, null];
                        const message = (Array.isArray(errors) ? errors[0] : errors) || data.message || 'Failed to submit request.';
                        if (field && fields[field]) {
                            showFieldMessage(fields[field], message);
                        } else {
                            showToast('Request not sent', message, 'error');
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('Request not sent', 'A network error occurred while submitting your request. Please check your connection and try again.', 'error');
                })
                .finally(() => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Submit Request';
                    }
                });
            });
        }
    });
</script>
@endpush
