@extends('layouts.department')

@section('title', 'EquipTrack - Department Equipment')

@push('css')
<link rel="stylesheet" href="{{ asset('departments/css/equipment.css') }}?v={{ filemtime(public_path('departments/css/equipment.css')) }}">
<link rel="stylesheet" href="{{ asset('css/equipment-units.css') }}?v={{ filemtime(public_path('css/equipment-units.css')) }}">
@endpush

@section('content')
<!-- Page Header -->
        <div class="page-title-section">
            <h2>Department Equipment</h2>
            <p>Manage equipment assigned to your department.</p>
        </div>

        <!-- Summary Cards Section -->
        <div class="equipment-summary-section">
            <h4 class="section-subtitle">Summary Cards</h4>
            <div class="summary-cards-grid">
                <!-- Card 1: Registered Equipment -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumRegistered">0</span>
                        <div class="summary-card-icon icon-gray">
                            <i class="fa-solid fa-laptop"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Registered Equipment</span>
                </div>

                <!-- Card 2: Ready for Borrowing -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumReady">0</span>
                        <div class="summary-card-icon icon-green">
                            <i class="fa-solid fa-hand-holding"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Ready for Borrowing</span>
                </div>

                <!-- Card 3: Currently Borrowed -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumBorrowed">0</span>
                        <div class="summary-card-icon icon-blue">
                            <i class="fa-solid fa-box"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Currently Borrowed</span>
                </div>

                <!-- Card 4: Unavailable -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumUnavailable">0</span>
                        <div class="summary-card-icon icon-red">
                            <i class="fa-solid fa-ban"></i>
                        </div>
                    </div>
                    <span class="summary-card-label" title="Units on hand that can't be borrowed: Unavailable, On Hold or Under Maintenance">Out of Service</span>
                </div>
            </div>
        </div>

        <!-- Controls Bar (Filter Container Card) -->
        <div class="filter-card-container">
            <div class="search-box-right-icon">
                <input type="text" id="searchEquipment" placeholder="Search name, brand or serial no." aria-label="Search equipment">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div class="filter-select-item">
                <select id="filterCategory">
                    <option value="all">All Categories</option>
                    @foreach ($dbCategories as $cat)
                        <option value="{{ strtolower($cat) }}">{{ $cat }}</option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>

            <div class="filter-select-item">
                {{-- "In Service" (the default) hides Retired equipment. --}}
                <select id="filterStatus" aria-label="Filter by status">
                    <option value="inservice" selected>In Service</option>
                    <option value="all">All Statuses</option>
                    <option value="available">Available</option>
                    <option value="unavailable">Unavailable</option>
                    <option value="on hold">On Hold</option>
                    <option value="under maintenance">Under Maintenance</option>
                    <option value="retired">Retired</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>

            <button class="btn-add-equipment-primary" id="addEquipmentBtn">
                <i class="fa-solid fa-plus"></i> Add Equipment
            </button>
        </div>

        <!-- Equipment Cards Grid -->
        <div class="equipment-grid" id="equipmentGrid">
            <!-- Dynamic rendering via JS -->
        </div>

        <!-- Empty state: same message design as the empty Release Equipment table -->
        <div class="dept-empty-card" id="emptyStateContainer" style="display: none;">
            <i class="fa-solid fa-box" aria-hidden="true"></i>
            <span id="emptyStateText">No equipment matches your search or filters.</span>
        </div>
    {{-- Add / Edit window, shared with Admin Equipment. Department accounts always
         save to their own department, and only the admin changes a classification. --}}
    @include('partials.equipment.form-modal', [
        'categories' => $dbCategoryMap,
        'classHint' => 'Requests for Expensive equipment need approval from both the admin and your department. Not Expensive requests are approved by your department alone.',
        'classLockedHint' => 'Only the admin can change the classification of existing equipment.',
    ])

    <!-- View Equipment Modal -->
    <div class="modal-overlay" id="viewEquipmentModal" role="dialog" aria-modal="true" aria-labelledby="viewModalTitle">
        <div class="modal-card eq-modal-card">
            <header class="eq-modal-header">
                <div class="eq-modal-icon" aria-hidden="true"><i class="fa-solid fa-circle-info"></i></div>
                <div class="eq-modal-heading">
                    <h3 class="eq-modal-title" id="viewModalTitle">Equipment Details</h3>
                    <p class="eq-modal-subtitle" id="viewModalSubtitle">Equipment Information</p>
                </div>
                <button type="button" class="modal-close" id="closeViewModalBtn" aria-label="Close">&times;</button>
            </header>

            <div class="eq-modal-body">
                <!-- Same frame as the equipment cards: whole item shown, blurred fill, placeholder -->
                <div class="eq-card-img-wrapper view-media" id="viewModalMedia">
                    <img src="" alt="" class="eq-card-img-backdrop" id="viewModalImgBackdrop" aria-hidden="true">
                    <img src="" alt="" class="eq-card-img" id="viewModalImg">
                    <div class="eq-card-img-placeholder">
                        <i class="fa-regular fa-image" aria-hidden="true"></i>
                        <span>No image available</span>
                    </div>
                </div>

                <div class="view-summary">
                    <div class="view-badges">
                        <span class="eq-status-badge" id="viewModalStatus">Available</span>
                        <span class="eq-class-badge" id="viewModalClass">Not Expensive</span>
                    </div>
                    <div class="view-stock" id="viewModalStock">
                        <p class="view-stock-text"><strong id="viewModalAvail">0</strong> of <span id="viewModalTotal">0</span> units available</p>
                        <div class="view-stock-bar" role="progressbar" aria-label="Available units" aria-valuemin="0" id="viewModalStockBar">
                            <span id="viewModalStockFill"></span>
                        </div>
                    </div>
                </div>

                <section class="eq-form-section" aria-labelledby="viewDetailsHeading">
                    <h4 class="eq-form-section-title" id="viewDetailsHeading">Details</h4>
                    <dl class="view-details-list">
                        <div>
                            <dt>Brand / Model</dt>
                            <dd id="viewModalBrand">—</dd>
                        </div>
                        <div>
                            <dt>Units</dt>
                            <dd class="mono" id="viewModalSerial">—</dd>
                        </div>
                        <div>
                            <dt>Category</dt>
                            <dd id="viewModalCategory">—</dd>
                        </div>
                        <div>
                            <dt>Accessories</dt>
                            <dd id="viewModalAccessories">—</dd>
                        </div>
                        <div>
                            <dt>Department</dt>
                            <dd id="viewModalDepartment">{{ $departmentName }}</dd>
                        </div>
                    </dl>
                </section>
            </div>

            <footer class="eq-modal-footer">
                <button type="button" class="btn-modal-danger" id="viewDeleteBtn">
                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete
                </button>
                <button type="button" class="btn-modal-secondary" id="viewCloseBtn">Close</button>
                <button type="button" class="btn-submit-request" id="viewEditBtn">
                    <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i> Edit Equipment
                </button>
            </footer>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toast">
        <div class="toast-content">
            <i class="fa-solid fa-circle-check toast-icon" id="toastIcon"></i>
            <div class="toast-message">
                <span class="toast-title" id="toastTitle">Success</span>
                <span class="toast-desc" id="toastMsg">Action processed successfully!</span>
            </div>
        </div>
    </div>

    <!-- Interactivity -->
    

    <!-- Data Management & Interactive Logic -->
@endsection

@push('scripts')
<script src="{{ asset('js/equipment-form.js') }}?v={{ filemtime(public_path('js/equipment-form.js')) }}"></script>
<script>






        



    

        document.addEventListener('DOMContentLoaded', () => {
            // Search & Filters
            const searchInput = document.getElementById('searchEquipment');
            const filterCategorySelect = document.getElementById('filterCategory');
            const filterStatusSelect = document.getElementById('filterStatus');
            const grid = document.getElementById('equipmentGrid');
            const emptyStateContainer = document.getElementById('emptyStateContainer');

            // Summary Counters
            const sumRegistered = document.getElementById('sumRegistered');
            const sumReady = document.getElementById('sumReady');
            const sumBorrowed = document.getElementById('sumBorrowed');
            const sumUnavailable = document.getElementById('sumUnavailable');

            // Add Equipment button (the Add / Edit window itself is shared, see below)
            const addBtn = document.getElementById('addEquipmentBtn');

            // View Modal
            const viewModal = document.getElementById('viewEquipmentModal');
            const closeViewModalBtn = document.getElementById('closeViewModalBtn');
            const viewModalTitle = document.getElementById('viewModalTitle');
            const viewModalImg = document.getElementById('viewModalImg');
            const viewModalCategory = document.getElementById('viewModalCategory');
            const viewModalStatus = document.getElementById('viewModalStatus');
            const viewModalAvail = document.getElementById('viewModalAvail');
            const viewModalTotal = document.getElementById('viewModalTotal');
            const viewModalDepartment = document.getElementById('viewModalDepartment');
            const viewModalSubtitle = document.getElementById('viewModalSubtitle');
            const viewModalMedia = document.getElementById('viewModalMedia');
            const viewModalImgBackdrop = document.getElementById('viewModalImgBackdrop');
            const viewModalStock = document.getElementById('viewModalStock');
            const viewModalStockBar = document.getElementById('viewModalStockBar');
            const viewModalStockFill = document.getElementById('viewModalStockFill');
            const viewCloseBtn = document.getElementById('viewCloseBtn');
            const viewEditBtn = document.getElementById('viewEditBtn');
            const viewDeleteBtn = document.getElementById('viewDeleteBtn');
            let viewedItemId = null;

            // Toast
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastTitle = document.getElementById('toastTitle');
            const toastMsg = document.getElementById('toastMsg');

            // Equipment data: the shared `equipment` records, scoped by the server
            // to this department account, so admin assignments, edits and
            // quantity updates appear here automatically.
            const URL_DATA = @json(route('department.equipment.data'));
            const URL_SAVE = @json(route('department.equipment.save'));
            const URL_DELETE = @json(route('department.equipment.delete'));
            const CSRF_TOKEN = @json(csrf_token());
            const DEPARTMENT_NAME = @json($departmentName);

            let equipment = [];

            function loadEquipment() {
                fetch(URL_DATA, { headers: { 'Accept': 'application/json' } })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            equipment = data.equipment || [];
                            renderEquipment();
                            filterEquipment();
                        }
                    })
                    .catch(err => console.error('Error loading department equipment:', err));
            }

            // Summary metrics, all counted in units so the four cards add up:
            // Registered = Ready + Borrowed + Out of Service.
            function updateSummaryCards() {
                let registered = 0, ready = 0, borrowed = 0, outOfService = 0;
                equipment.forEach(e => {
                    // Retired equipment is out of the inventory: not counted.
                    if (String(e.status).toLowerCase() === 'retired') return;
                    const total = parseInt(e.total) || 0;
                    const avail = parseInt(e.available) || 0;
                    registered += total;
                    borrowed += Math.max(0, total - avail);
                    if (String(e.status).toLowerCase() === 'available') ready += avail;
                    else outOfService += avail;
                });

                sumRegistered.textContent = registered;
                sumReady.textContent = ready;
                sumBorrowed.textContent = borrowed;
                sumUnavailable.textContent = outOfService;
            }

            function brandModel(item) {
                return [item.brand, item.model].filter(v => v && String(v).trim()).join(' · ');
            }

            // Render equipment cards (same card as Admin Equipment)
            function renderEquipment() {
                grid.innerHTML = '';
                equipment.forEach(item => {
                    grid.appendChild(EquipmentForm.card({
                        id: item.id,
                        name: item.name,
                        brand: item.brand,
                        model: item.model,
                        serial_number: item.serial_number,
                        unit_labels: item.unit_labels,
                        unit_search: item.unit_search,
                        category: item.category,
                        category_id: item.categoryId,
                        classification: item.classification,
                        accessories: item.accessories,
                        available: item.available,
                        total: item.total,
                        status: item.status,
                        image: item.imgUrl,
                    }, {
                        actions: [
                            { kind: 'edit', onClick: id => window.openEditModal(id) },
                            { kind: 'view', onClick: id => window.openViewModal(id) },
                        ],
                    }));
                });
                updateSummaryCards();
            }

            // Filter execution
            function filterEquipment() {
                const query = searchInput.value.toLowerCase().trim();
                const category = filterCategorySelect.value.toLowerCase();
                const status = filterStatusSelect.value.toLowerCase();
                const cards = grid.querySelectorAll('.eq-card-admin');
                let visibleCount = 0;

                cards.forEach(card => {
                    const haystack = card.getAttribute('data-search') || '';
                    const cardCategory = card.getAttribute('data-category').toLowerCase();
                    const cardStatus = card.getAttribute('data-status').toLowerCase();

                    const matchesSearch = haystack.includes(query);
                    const matchesCategory = (category === 'all' || cardCategory.includes(category));
                    // "inservice" (the default) hides Retired equipment.
                    const matchesStatus = status === 'all'
                        || (status === 'inservice' ? cardStatus !== 'retired' : cardStatus === status);

                    if (matchesSearch && matchesCategory && matchesStatus) {
                        card.style.display = 'flex';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (visibleCount === 0) {
                    // Different message when nothing is assigned vs. nothing matches
                    const noneAssigned = equipment.length === 0;
                    document.getElementById('emptyStateText').textContent = noneAssigned
                        ? 'No equipment is assigned to your department yet. Use "Add Equipment" to register an item.'
                        : (status === 'inservice' && query === '' && category === 'all'
                            ? 'All of your equipment is retired. Choose "All Statuses" or "Retired" to see it.'
                            : 'No equipment matches your search or filters.');
                    emptyStateContainer.style.display = 'block';
                } else {
                    emptyStateContainer.style.display = 'none';
                }
            }

            searchInput.addEventListener('input', filterEquipment);
            filterCategorySelect.addEventListener('change', filterEquipment);
            filterStatusSelect.addEventListener('change', filterEquipment);

            // Toast Helper
            function showNotification(title, message, type = 'success') {
                toastTitle.textContent = title;
                toastMsg.textContent = message;
                
                if (type === 'success') {
                    toastIcon.className = 'fa-solid fa-circle-check toast-icon';
                    toastIcon.style.color = '#10b981';
                    document.querySelector('.toast-content').style.borderLeft = '4px solid #10b981';
                } else {
                    toastIcon.className = 'fa-solid fa-circle-xmark toast-icon';
                    toastIcon.style.color = '#ef4444';
                    document.querySelector('.toast-content').style.borderLeft = '4px solid #ef4444';
                }
                
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3500);
            }

            // Add / Edit window: photo uploader, checks and saving are shared
            // with Admin Equipment (public/js/equipment-form.js). The server
            // saves to this department; only the admin changes a classification.
            const equipmentForm = EquipmentForm.init({
                saveUrl: URL_SAVE,
                csrfToken: CSRF_TOKEN,
                notify: showNotification,
                onSaved: () => loadEquipment(),
                lockClassificationOnEdit: true,
            });

            addBtn.addEventListener('click', () => equipmentForm.openAdd());

            // Open Edit Modal
            window.openEditModal = function(id) {
                const item = equipment.find(e => e.id == id);
                if (!item) return;

                equipmentForm.openEdit({
                    id: item.id,
                    name: item.name,
                    brand: item.brand,
                    model: item.model,
                    category_id: item.categoryId,
                    classification: item.classification,
                    accessories: item.accessories,
                    status: item.status,
                    image: item.imgUrl,
                    units: item.units || [],
                });
            };

            // Open View Modal
            window.openViewModal = function(id) {
                const item = equipment.find(e => e.id == id);
                if (!item) return;
                viewedItemId = item.id;

                const status = String(item.status || 'Available');
                const classification = item.classification || 'Not Expensive';
                const available = parseInt(item.available) || 0;
                const total = parseInt(item.total) || 0;
                const pct = total > 0 ? Math.round((available / total) * 100) : 0;

                viewModalTitle.textContent = item.name;
                viewModalSubtitle.textContent = item.category || 'Equipment Information';

                // Image: same frame as the cards; placeholder when missing or broken
                viewModalMedia.classList.toggle('is-missing', !item.imgUrl);
                viewModalImg.alt = item.name;
                viewModalImg.onerror = function () { this.onerror = null; viewModalMedia.classList.add('is-missing'); };
                viewModalImg.src = item.imgUrl || '';
                viewModalImgBackdrop.src = item.imgUrl || '';

                viewModalStatus.textContent = status;
                viewModalStatus.className = 'eq-status-badge ' + EquipmentForm.statusClass(status);
                const viewModalClass = document.getElementById('viewModalClass');
                viewModalClass.textContent = classification;
                viewModalClass.className = 'eq-class-badge ' + (classification === 'Expensive' ? 'class-expensive' : 'class-not-expensive');

                viewModalAvail.textContent = String(available);
                viewModalTotal.textContent = String(total);
                viewModalStock.classList.toggle('is-empty', available === 0);
                viewModalStock.classList.toggle('is-low', available > 0 && available <= Math.max(1, Math.floor(total * 0.25)));
                viewModalStockBar.setAttribute('aria-valuemax', String(total));
                viewModalStockBar.setAttribute('aria-valuenow', String(available));
                viewModalStockFill.style.width = pct + '%';

                document.getElementById('viewModalBrand').textContent = brandModel(item) || '—';
                // Its units' serial numbers (else codes); Edit lists every unit.
                document.getElementById('viewModalSerial').textContent = item.unit_labels || '—';
                document.getElementById('viewModalAccessories').textContent = item.accessories || '—';
                viewModalCategory.textContent = item.category || '—';
                if (viewModalDepartment) viewModalDepartment.textContent = DEPARTMENT_NAME;

                viewModal.querySelector('.eq-modal-body').scrollTop = 0;
                viewModal.classList.add('show');
                setTimeout(() => closeViewModalBtn.focus({ preventScroll: true }), 50);
            };

            function closeViewModal() {
                viewModal.classList.remove('show');
            }

            closeViewModalBtn.addEventListener('click', closeViewModal);
            viewCloseBtn.addEventListener('click', closeViewModal);

            // Jump straight from the details to the edit form for the same item
            viewEditBtn.addEventListener('click', () => {
                closeViewModal();
                if (viewedItemId !== null) window.openEditModal(viewedItemId);
            });

            viewModal.addEventListener('click', (e) => {
                if (e.target === viewModal) {
                    closeViewModal();
                }
            });

            // Delete the item shown in the details view (same rules as the admin delete)
            viewDeleteBtn.addEventListener('click', async () => {
                const item = equipment.find(e => e.id == viewedItemId);
                if (!item) return;
                const ok = await equipConfirm({
                    title: 'Delete this equipment?',
                    message: `"${item.name}" will be permanently deleted, with its units. This cannot be undone. Only equipment that was never requested can be deleted; equipment with borrowing history is kept, so set its status to Retired instead.`,
                    confirmText: 'Delete',
                    tone: 'danger',
                });
                if (!ok) return;

                const payload = new FormData();
                payload.append('equipment_id', item.id);
                viewDeleteBtn.disabled = true;

                fetch(URL_DELETE, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: payload
                })
                .then(res => res.json().then(data => ({ ok: res.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        closeViewModal();
                        loadEquipment();
                        showNotification('Deleted Successfully', data.message || `Deleted ${item.name}.`, 'success');
                    } else {
                        showNotification('Delete Failed', (data && data.message) || 'Unable to delete the equipment.', 'error');
                    }
                })
                .catch(() => {
                    showNotification('System Error', 'An unexpected error occurred while deleting equipment.', 'error');
                })
                .finally(() => {
                    viewDeleteBtn.disabled = false;
                });
            });

            // Escape closes the View window (the Add / Edit window closes itself)
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !equipmentForm.isOpen() && viewModal.classList.contains('show')) closeViewModal();
            });

            // Initial render from the database (only this department's equipment)
            loadEquipment();

            // Admin edits, approvals and returns update these same records in the
            // background, so refresh the list when this tab regains focus.
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    loadEquipment();
                }
            });
        });
    
</script>
@endpush
