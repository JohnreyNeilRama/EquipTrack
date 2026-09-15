@extends('layouts.user')

@section('title', 'EquipTrack - Available Equipment')

@push('css')
<link rel="stylesheet" href="{{ asset('user/css/useravailequipment.css') }}">
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
                        $isUnavailable = (strtolower($eqStatus) !== 'available' || $eqAvail <= 0);
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
                                    <i class="fa-solid fa-circle-check"></i> Stock: <strong>{{ $eqAvail }} / {{ (int) $eq->total_qty }}</strong>
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
                            <div class="form-horizontal-group">
                                <label class="flat-label">Borrow Date:</label>
                                <input type="date" id="borrowDate" class="flat-control" required>
                            </div>
                            <div class="form-horizontal-group">
                                <label class="flat-label">Return Date:</label>
                                <input type="date" id="returnDate" class="flat-control" required>
                            </div>
                        </div>
                    </div>

                    {{-- Lower section: Purpose and Notes --}}
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

        // Set minimum dates
        const today = new Date().toISOString().split('T')[0];
        if (borrowDateInput) {
            borrowDateInput.min = today;
            borrowDateInput.value = today;
        }

        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        if (returnDateInput) {
            returnDateInput.min = tomorrow.toISOString().split('T')[0];
        }

        if (borrowDateInput && returnDateInput) {
            borrowDateInput.addEventListener('change', () => {
                const selectedBorrowDate = new Date(borrowDateInput.value);
                selectedBorrowDate.setDate(selectedBorrowDate.getDate() + 1);
                returnDateInput.min = selectedBorrowDate.toISOString().split('T')[0];
                if (returnDateInput.value && returnDateInput.value < returnDateInput.min) {
                    returnDateInput.value = returnDateInput.min;
                }
            });
        }

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

                document.getElementById('modalEqId').value        = eqId;
                document.getElementById('modalEqName').value      = eqName;
                document.getElementById('modalEqCategory').value  = eqCategory;
                document.getElementById('modalEqAvailable').value = eqAvail;

                const modalImg = document.getElementById('modalEqImg');
                modalImg.src = eqImg;
                modalImg.alt = eqName;

                if (borrowDateInput) borrowDateInput.value = today;
                if (returnDateInput) {
                    returnDateInput.value = "";
                    returnDateInput.min = tomorrow.toISOString().split('T')[0];
                }

                if (modal) modal.classList.add('show');
            });
        });

        // Close modal functions
        const closeModal = () => {
            if (modal) modal.classList.remove('show');
            if (borrowForm) borrowForm.reset();
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
                const notes      = document.getElementById('borrowNotes').value;
                const submitBtn  = borrowForm.querySelector('.btn-submit-request');

                if (!eqId) {
                    alert('Invalid equipment item selected.');
                    return;
                }

                if (!borrowDate || !returnDate) {
                    alert('Please select both borrow date and return date.');
                    return;
                }

                if (returnDate < borrowDate) {
                    alert('Return date cannot be earlier than borrow date.');
                    return;
                }

                if (!purpose) {
                    alert('Please select a purpose for borrowing.');
                    return;
                }

                const formData = new FormData();
                formData.append('_token', CSRF_TOKEN);
                formData.append('equipment_id', eqId);
                formData.append('borrow_date', borrowDate);
                formData.append('return_date', returnDate);
                formData.append('purpose', purpose);
                formData.append('notes', notes);

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
                }

                fetch(@json(route('user.borrow.store')), {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Borrow request submitted successfully!');
                        closeModal();
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        }
                    } else {
                        alert('Submission Error: ' + (data.message || 'Failed to submit request.'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('An unexpected network error occurred while submitting your request.');
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
