@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('departments/css/equipment.css') }}?v={{ filemtime(public_path('departments/css/equipment.css')) }}">
@endpush

@section('content')
<!-- Page Header -->
        <div class="page-title-section" style="margin-top: 10px;">
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
                <input type="text" id="searchEquipment" placeholder="Search by name, brand, model or serial no..." aria-label="Search equipment">
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
                <select id="filterStatus">
                    <option value="all">All Statuses</option>
                    <option value="available">Available</option>
                    <option value="unavailable">Unavailable</option>
                    <option value="on hold">On Hold</option>
                    <option value="under maintenance">Under Maintenance</option>
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

        <!-- Empty State Container -->
        <div class="empty-state-container" id="emptyStateContainer" style="display: none; margin-top: 20px;">
            <i class="fa-solid fa-boxes-stacked empty-state-icon" id="emptyStateIcon"></i>
            <h4 id="emptyStateTitle">No equipment found</h4>
            <p id="emptyStateDesc">No inventory items match your current search query or category filter.</p>
        </div>
<!-- Equipment Modal (Add / Edit) -->
    <div class="modal-overlay" id="equipmentModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalSubtitle">
        <div class="modal-card eq-modal-card">
            <header class="eq-modal-header">
                <div class="eq-modal-icon" aria-hidden="true"><i class="fa-solid fa-plus" id="modalIcon"></i></div>
                <div class="eq-modal-heading">
                    <h3 class="eq-modal-title" id="modalTitle">Add New Equipment</h3>
                    <p class="eq-modal-subtitle" id="modalSubtitle">Provide details for the inventory item</p>
                </div>
                <button type="button" class="modal-close" id="closeModalBtn" aria-label="Close">&times;</button>
            </header>

            <form id="equipmentForm" class="eq-modal-form">
                <div class="eq-modal-body">
                    <input type="hidden" id="editItemId" value="">

                    <section class="eq-form-section" aria-labelledby="eqPhotoHeading">
                        <h4 class="eq-form-section-title" id="eqPhotoHeading">Equipment Photo</h4>
                        <div class="image-upload-wrapper" id="imageUploadWrapper">
                            <!-- Dropzone State: click, keyboard or drag a file -->
                            <label class="image-dropzone" id="imageDropzone" for="imageFileInput" tabindex="0">
                                <span class="dropzone-icon" aria-hidden="true"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                                <span class="dropzone-text">
                                    <span class="main-text">Drag and drop or <span class="browse-link">browse</span> to upload</span>
                                    <span class="sub-text">PNG, JPG, JPEG or WEBP, up to 5 MB</span>
                                </span>
                            </label>
                            <input type="file" id="imageFileInput" accept="image/*" class="file-input-hidden">

                            <!-- URL Input Alternative -->
                            <div class="image-url-alternative" id="imageUrlAlternative">
                                <span class="or-separator">or use an image link</span>
                                <div class="url-input-container">
                                    {{-- type="text", not "url": stored photos are paths like /storage/images/…, which a url field rejects and silently blocks the save --}}
                                    <input type="text" inputmode="url" autocomplete="off" id="eqFormImg" class="form-control-flat" placeholder="https://example.com/photo.jpg" aria-label="Image URL">
                                    <button type="button" class="btn-apply-url" id="btnApplyUrl">Apply</button>
                                </div>
                            </div>

                            <!-- Image Preview State -->
                            <div class="image-preview-container" id="imagePreviewContainer" style="display: none;">
                                <img src="" alt="" class="preview-backdrop" id="imagePreviewBackdrop" aria-hidden="true">
                                <img src="" alt="Selected equipment photo" id="imagePreviewImg">
                                <div class="preview-actions-overlay">
                                    <button type="button" class="btn-preview-action replace" id="btnReplacePreview">
                                        <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i> Change
                                    </button>
                                    <button type="button" class="btn-preview-action remove" id="btnRemovePreview">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="eq-form-section" aria-labelledby="eqBasicHeading">
                        <h4 class="eq-form-section-title" id="eqBasicHeading">Basic Information</h4>
                        <div class="form-group-flat">
                            <label for="eqFormName">Equipment Name <span class="req" aria-hidden="true">*</span></label>
                            <input type="text" id="eqFormName" class="form-control-flat" required maxlength="100" placeholder="e.g., Laptop Dell XPS">
                        </div>
                        <div class="form-row-flat">
                            <div class="form-group-flat">
                                <label for="eqFormCategory">Category <span class="req" aria-hidden="true">*</span></label>
                                <div class="select-wrapper">
                                    <select id="eqFormCategory" class="form-control-flat" required>
                                        @foreach ($dbCategories as $cat)
                                            <option value="{{ $cat }}">{{ $cat }}</option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                </div>
                            </div>
                            <div class="form-group-flat">
                                <label for="eqFormStatus">Status <span class="req" aria-hidden="true">*</span></label>
                                <div class="select-wrapper">
                                    <select id="eqFormStatus" class="form-control-flat" required>
                                        <option value="Available">Available</option>
                                        <option value="Unavailable">Unavailable</option>
                                        <option value="On Hold">On Hold</option>
                                        <option value="Under Maintenance">Under Maintenance</option>
                                    </select>
                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="eq-form-section" aria-labelledby="eqIdentHeading">
                        <h4 class="eq-form-section-title" id="eqIdentHeading">Identification</h4>
                        <div class="form-row-flat">
                            <div class="form-group-flat">
                                <label for="eqFormBrand">Brand <span class="req" aria-hidden="true">*</span></label>
                                <input type="text" id="eqFormBrand" class="form-control-flat" required maxlength="50" placeholder="e.g., Dell">
                            </div>
                            <div class="form-group-flat">
                                <label for="eqFormModel">Model</label>
                                <input type="text" id="eqFormModel" class="form-control-flat" maxlength="50" placeholder="e.g., Latitude 5420">
                            </div>
                        </div>
                        <div class="form-group-flat">
                            <label for="eqFormSerial">Serial Number <span class="req" aria-hidden="true">*</span></label>
                            <input type="text" id="eqFormSerial" class="form-control-flat mono" required maxlength="50" placeholder="e.g., SN-000123" aria-describedby="eqSerialHint">
                            <p class="form-hint" id="eqSerialHint">Must be unique across all equipment.</p>
                        </div>
                    </section>

                    <section class="eq-form-section" aria-labelledby="eqApprovalHeading">
                        <h4 class="eq-form-section-title" id="eqApprovalHeading">Approval</h4>
                        <div class="form-group-flat">
                            <label for="eqFormClassification">Classification <span class="req" aria-hidden="true">*</span></label>
                            <div class="select-wrapper">
                                <select id="eqFormClassification" class="form-control-flat" required aria-describedby="eqClassHint">
                                    <option value="">Select Classification</option>
                                    <option value="Expensive">Expensive</option>
                                    <option value="Not Expensive">Not Expensive</option>
                                </select>
                                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                            </div>
                            <p class="form-hint" id="eqClassHint">Requests for Expensive equipment need approval from both the admin and your department. Not Expensive requests are approved by your department alone.</p>
                            <p class="form-hint" id="eqClassLockedHint" hidden>Only the admin can change the classification of existing equipment.</p>
                        </div>
                    </section>

                    <section class="eq-form-section" aria-labelledby="eqStockHeading">
                        <h4 class="eq-form-section-title" id="eqStockHeading">Inventory</h4>
                        <div class="form-row-flat">
                            <div class="form-group-flat">
                                <label for="eqFormAvail">Available Quantity <span class="req" aria-hidden="true">*</span></label>
                                <input type="number" id="eqFormAvail" class="form-control-flat" min="0" required value="4" inputmode="numeric" aria-describedby="eqQtyHint">
                            </div>
                            <div class="form-group-flat">
                                <label for="eqFormTotal">Total Quantity <span class="req" aria-hidden="true">*</span></label>
                                <input type="number" id="eqFormTotal" class="form-control-flat" min="0" required value="10" inputmode="numeric" aria-describedby="eqQtyHint">
                            </div>
                        </div>
                        <p class="form-hint" id="eqQtyHint" aria-live="polite">Available units can't be more than the total.</p>
                        <div class="form-group-flat">
                            <label for="eqFormAccessories">Accessories Included</label>
                            <input type="text" id="eqFormAccessories" class="form-control-flat" maxlength="150" placeholder="e.g., Charger, Power Adapter, Carrying Case">
                        </div>
                    </section>
                </div>

                <footer class="eq-modal-footer">
                    <p class="eq-modal-footnote"><span class="req" aria-hidden="true">*</span> Required</p>
                    <button type="button" class="btn-modal-secondary" id="cancelModalBtn">Cancel</button>
                    <button type="submit" class="btn-submit-request" id="submitBtn">Add Item</button>
                </footer>
            </form>
        </div>
    </div>

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
                            <dt>Serial Number</dt>
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
<script>






        


        // Mobile sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarScrim = document.getElementById('sidebarScrim');
        const topbarMenuBtn = document.getElementById('topbarMenuBtn');
        const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');

        function openSidebar() {
            if (sidebar) sidebar.classList.add('open');
            if (sidebarScrim) sidebarScrim.classList.add('show');
        }
        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('open');
            if (sidebarScrim) sidebarScrim.classList.remove('show');
        }

        if (topbarMenuBtn) topbarMenuBtn.addEventListener('click', openSidebar);
        if (sidebarCloseBtn) sidebarCloseBtn.addEventListener('click', closeSidebar);
        if (sidebarScrim) sidebarScrim.addEventListener('click', closeSidebar);

        // Notifications
        const notifBtn = document.getElementById('notifBtn');
        if (notifBtn) {
            notifBtn.addEventListener('click', () => {
                alert('No new notifications.');
            });
        }
    

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

            // Add/Edit Modal
            const modal = document.getElementById('equipmentModal');
            const addBtn = document.getElementById('addEquipmentBtn');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const form = document.getElementById('equipmentForm');
            
            const modalTitle = document.getElementById('modalTitle');
            const modalSubtitle = document.getElementById('modalSubtitle');
            const modalIcon = document.getElementById('modalIcon');
            const cancelModalBtn = document.getElementById('cancelModalBtn');
            const qtyHint = document.getElementById('eqQtyHint');
            const submitBtn = document.getElementById('submitBtn');
            const editItemIdInput = document.getElementById('editItemId');
            
            const eqNameField = document.getElementById('eqFormName');
            const eqCategoryField = document.getElementById('eqFormCategory');
            const eqBrandField = document.getElementById('eqFormBrand');
            const eqModelField = document.getElementById('eqFormModel');
            const eqClassificationField = document.getElementById('eqFormClassification');
            const eqAccessoriesField = document.getElementById('eqFormAccessories');
            const eqSerialField = document.getElementById('eqFormSerial');
            const eqImgField = document.getElementById('eqFormImg');
            const eqAvailField = document.getElementById('eqFormAvail');
            const eqTotalField = document.getElementById('eqFormTotal');
            const eqStatusField = document.getElementById('eqFormStatus');

            // Image Upload Components
            const imageDropzone = document.getElementById('imageDropzone');
            const imageFileInput = document.getElementById('imageFileInput');
            const imageUrlAlternative = document.getElementById('imageUrlAlternative');
            const btnApplyUrl = document.getElementById('btnApplyUrl');
            const imagePreviewContainer = document.getElementById('imagePreviewContainer');
            const imagePreviewImg = document.getElementById('imagePreviewImg');
            const imagePreviewBackdrop = document.getElementById('imagePreviewBackdrop');
            const btnReplacePreview = document.getElementById('btnReplacePreview');
            const btnRemovePreview = document.getElementById('btnRemovePreview');

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
            const CATEGORY_MAP = @json($dbCategoryMap);

            // Same status colours as the Admin Equipment Management cards
            const STATUS_CLASS = {
                'available': 'status-available',
                'unavailable': 'status-unavailable',
                'on hold': 'status-on-hold',
                'under maintenance': 'status-under-maintenance'
            };

            let equipment = [];

            function categoryIdFor(name) {
                const match = CATEGORY_MAP.find(c => c.category_name.toLowerCase() === String(name || '').toLowerCase());
                return match ? match.category_id : (CATEGORY_MAP[0] ? CATEGORY_MAP[0].category_id : '');
            }

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

            // Render equipment cards
            function renderEquipment() {
                grid.innerHTML = '';
                equipment.forEach(item => {
                    const card = document.createElement('div');
                    const status = String(item.status || 'Available');
                    const statusKey = status.toLowerCase();
                    const available = parseInt(item.available) || 0;
                    const total = parseInt(item.total) || 0;
                    const pct = total > 0 ? Math.round((available / total) * 100) : 0;
                    const stockClass = available === 0 ? 'is-empty' : (available <= Math.max(1, Math.floor(total * 0.25)) ? 'is-low' : '');
                    const isExpensive = item.classification === 'Expensive';
                    const subtitle = brandModel(item);
                    const imgUrl = item.imgUrl ? escapeHTML(item.imgUrl) : '';

                    card.className = 'eq-card-admin';
                    card.setAttribute('data-category', String(item.category || '').toLowerCase());
                    card.setAttribute('data-status', statusKey);
                    card.setAttribute('data-id', item.id);
                    card.setAttribute('data-search', [item.name, item.brand, item.model, item.serial_number].filter(Boolean).join(' ').toLowerCase());

                    card.innerHTML = `
                        <div class="eq-card-img-wrapper${imgUrl ? '' : ' is-missing'}">
                            ${imgUrl ? `
                            <img class="eq-card-img-backdrop" src="${imgUrl}" alt="" aria-hidden="true" loading="lazy" decoding="async">
                            <img class="eq-card-img" src="${imgUrl}" alt="${escapeHTML(item.name)}" loading="lazy" decoding="async"
                                 onerror="this.onerror=null; this.parentElement.classList.add('is-missing');">` : ''}
                            <div class="eq-card-img-placeholder">
                                <i class="fa-regular fa-image" aria-hidden="true"></i>
                                <span>No image available</span>
                            </div>
                        </div>
                        <div class="eq-card-details">
                            <div class="eq-card-heading">
                                <h4 class="eq-card-title" title="${escapeHTML(item.name)}">${escapeHTML(item.name)}</h4>
                                <span class="eq-status-badge ${STATUS_CLASS[statusKey] || 'status-unavailable'}">${escapeHTML(status)}</span>
                            </div>
                            ${subtitle ? `<p class="eq-card-subtitle">${escapeHTML(subtitle)}</p>` : ''}

                            <dl class="eq-card-specs">
                                <div class="eq-spec-row">
                                    <dt>Serial No.</dt>
                                    <dd class="mono">${escapeHTML(item.serial_number || '—')}</dd>
                                </div>
                                <div class="eq-spec-row">
                                    <dt>Category</dt>
                                    <dd class="cat-val">${escapeHTML(item.category)}</dd>
                                </div>
                                <div class="eq-spec-row">
                                    <dt>Classification</dt>
                                    <dd><span class="eq-class-badge ${isExpensive ? 'class-expensive' : 'class-not-expensive'}">${escapeHTML(item.classification || 'Not Expensive')}</span></dd>
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
                        <div class="eq-card-actions">
                            <button type="button" class="btn-card-edit" onclick="openEditModal(${item.id})"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i> Edit</button>
                            <button type="button" class="btn-card-view" onclick="openViewModal(${item.id})"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</button>
                        </div>
                    `;
                    grid.appendChild(card);
                });
                updateSummaryCards();
            }

            // Escapes text for HTML. Numbers (including 0) are kept, so a zero
            // quantity shows as "0" instead of a blank.
            function escapeHTML(str) {
                if (str === null || str === undefined) return '';
                return String(str).replace(/[&<>'"]/g,
                    tag => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        "'": '&#39;',
                        '"': '&quot;'
                    }[tag] || tag)
                );
            }

            // Image Preview Helpers
            function showPreview(url) {
                imagePreviewImg.src = url;
                imagePreviewBackdrop.src = url;
                eqImgField.value = url;
                imageDropzone.style.display = 'none';
                imageUrlAlternative.style.display = 'none';
                imagePreviewContainer.style.display = 'block';
            }

            function resetUploader() {
                imagePreviewImg.src = "";
                imagePreviewBackdrop.src = "";
                imageFileInput.value = "";
                eqImgField.value = "";
                imageDropzone.style.display = 'flex';
                imageUrlAlternative.style.display = 'flex';
                imagePreviewContainer.style.display = 'none';
            }

            function handleFile(file) {
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        showPreview(e.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            }

            imageFileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                handleFile(file);
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                imageDropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    imageDropzone.classList.add('dragover');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                imageDropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    imageDropzone.classList.remove('dragover');
                }, false);
            });

            imageDropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const file = dt.files[0];
                handleFile(file);
            }, false);

            // The dropzone is a <label> for the file input, so a click opens the
            // file picker; Enter / Space do the same from the keyboard.
            imageDropzone.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    imageFileInput.click();
                }
            });

            btnApplyUrl.addEventListener('click', (e) => {
                e.preventDefault();
                const urlValue = eqImgField.value.trim();
                if (urlValue) {
                    showPreview(urlValue);
                } else {
                    showNotification('Empty URL', 'Please paste a valid image URL.', 'error');
                }
            });

            eqImgField.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    btnApplyUrl.click();
                }
            });

            btnReplacePreview.addEventListener('click', () => {
                imageFileInput.click();
            });

            btnRemovePreview.addEventListener('click', () => {
                resetUploader();
            });

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
                    const matchesStatus = (status === 'all' || cardStatus === status);

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
                    document.getElementById('emptyStateTitle').textContent = noneAssigned ? 'No equipment assigned yet' : 'No equipment found';
                    document.getElementById('emptyStateDesc').textContent = noneAssigned
                        ? 'Equipment assigned to your department will appear here. Use "Add Equipment" to register an item.'
                        : 'No items match your search or filters. Try a different keyword or clear the filters.';
                    document.getElementById('emptyStateIcon').className = 'fa-solid ' + (noneAssigned ? 'fa-boxes-stacked' : 'fa-magnifying-glass') + ' empty-state-icon';
                    emptyStateContainer.style.display = 'flex';
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

            // Available can't exceed Total: flagged as soon as it is typed
            // (the submit check below still blocks the save).
            const QTY_HINT = "Available units can't be more than the total.";

            function updateQtyState() {
                const available = parseInt(eqAvailField.value) || 0;
                const total = parseInt(eqTotalField.value) || 0;
                const invalid = available > total;
                eqAvailField.classList.toggle('is-invalid', invalid);
                eqAvailField.setAttribute('aria-invalid', invalid ? 'true' : 'false');
                qtyHint.classList.toggle('is-error', invalid);
                qtyHint.textContent = invalid
                    ? `Available (${available}) is more than the total (${total}).`
                    : QTY_HINT;
            }

            eqAvailField.addEventListener('input', updateQtyState);
            eqTotalField.addEventListener('input', updateQtyState);

            // The classification is chosen when registering; on edits it is shown
            // read-only (the server ignores it, only the admin can change it).
            function setClassificationLocked(locked) {
                eqClassificationField.disabled = locked;
                document.getElementById('eqClassLockedHint').hidden = !locked;
            }

            function openFormModal() {
                updateQtyState();
                modal.querySelector('.eq-modal-body').scrollTop = 0;
                modal.classList.add('show');
                setTimeout(() => eqNameField.focus({ preventScroll: true }), 50);
            }

            // Open Add Modal
            addBtn.addEventListener('click', () => {
                form.reset();
                editItemIdInput.value = "";
                modalTitle.textContent = "Add New Equipment";
                modalSubtitle.textContent = "Provide details for the inventory item";
                modalIcon.className = 'fa-solid fa-plus';
                submitBtn.textContent = "Add Item";
                setClassificationLocked(false);

                showPreview("https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60");
                openFormModal();
            });

            // Close Add/Edit Modal
            closeModalBtn.addEventListener('click', closeModal);
            cancelModalBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            });

            function closeModal() {
                modal.classList.remove('show');
                form.reset();
            }

            // Open Edit Modal
            window.openEditModal = function(id) {
                const item = equipment.find(e => e.id == id);
                if (!item) return;

                editItemIdInput.value = item.id;
                eqNameField.value = item.name;
                eqCategoryField.value = item.category;
                eqBrandField.value = item.brand || '';
                eqModelField.value = item.model || '';
                eqClassificationField.value = item.classification === 'Expensive' ? 'Expensive' : 'Not Expensive';
                setClassificationLocked(true);
                eqAccessoriesField.value = item.accessories || '';
                eqSerialField.value = item.serial_number || '';
                eqAvailField.value = item.available;
                eqTotalField.value = item.total;
                eqStatusField.value = item.status;

                // No photo on file: show the upload box instead of a broken preview
                if (item.imgUrl) {
                    showPreview(item.imgUrl);
                } else {
                    resetUploader();
                }

                modalTitle.textContent = "Edit Equipment";
                modalSubtitle.textContent = "Update inventory details for this item";
                modalIcon.className = 'fa-solid fa-pen-to-square';
                submitBtn.textContent = "Save Changes";

                openFormModal();
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
                viewModalStatus.className = 'eq-status-badge ' + (STATUS_CLASS[status.toLowerCase()] || 'status-unavailable');
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
                document.getElementById('viewModalSerial').textContent = item.serial_number || '—';
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
            viewDeleteBtn.addEventListener('click', () => {
                const item = equipment.find(e => e.id == viewedItemId);
                if (!item) return;
                if (!confirm(`Are you sure you want to delete "${item.name}" (${item.serial_number})? This cannot be undone.`)) return;

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

            // Escape closes whichever equipment modal is open
            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                if (modal.classList.contains('show')) closeModal();
                else if (viewModal.classList.contains('show')) closeViewModal();
            });

            // Form Submit (Add / Edit)
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                
                const id = editItemIdInput.value;
                const name = eqNameField.value.trim();
                const category = eqCategoryField.value;
                const brand = eqBrandField.value.trim();
                const serial = eqSerialField.value.trim();
                const imgUrl = eqImgField.value.trim();
                const available = parseInt(eqAvailField.value) || 0;
                const total = parseInt(eqTotalField.value) || 0;
                const status = eqStatusField.value;

                if (!imgUrl) {
                    showNotification('Image Required', 'Please upload an image or enter a URL first.', 'error');
                    return;
                }

                if (available > total) {
                    showNotification('Invalid Quantity', 'Available Quantity cannot be greater than Total Quantity.', 'error');
                    return;
                }

                if (!id && !['Expensive', 'Not Expensive'].includes(eqClassificationField.value)) {
                    showNotification('Classification Required', 'Please select an equipment classification.', 'error');
                    return;
                }

                // Persist to the shared equipment record (server forces the
                // department, so an item can never move to another department).
                const payload = new FormData();
                if (id) payload.append('equipment_id', id);
                payload.append('name', name);
                payload.append('brand', brand);
                payload.append('model', eqModelField.value.trim());
                if (!id) payload.append('classification', eqClassificationField.value);
                payload.append('accessories_included', eqAccessoriesField.value.trim());
                payload.append('serial_number', serial);
                payload.append('category_id', categoryIdFor(category));
                payload.append('image', imgUrl);
                payload.append('available_qty', available);
                payload.append('total_qty', total);
                payload.append('status', status);

                // Lock the button while saving so the request can't be sent twice
                const submitLabel = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving…';

                fetch(URL_SAVE, {
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
                        closeModal();
                        loadEquipment();
                        showNotification(
                            id ? 'Updated Successfully' : 'Added Successfully',
                            data.message || `Saved ${name}.`,
                            'success'
                        );
                    } else {
                        showNotification('Save Failed', (data && data.message) || 'Unable to save the equipment.', 'error');
                    }
                })
                .catch(() => {
                    showNotification('System Error', 'An unexpected error occurred while saving equipment.', 'error');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = submitLabel;
                });
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
