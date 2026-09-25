@extends('layouts.admin')

@section('title', 'EquipTrack - Equipment Monitoring')

@push('css')
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminusers.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminmonitoring.css') }}">
@endpush

@section('content')
<!-- Main container matching users-container exactly -->
        <div class="users-container" style="min-width: 0; max-width: 100%;">
            <!-- Header Title Block -->
            <div class="equipment-header-section" id="monitoringHeaderSection">
                <h2>Equipment Monitoring</h2>
                <p>Monitor borrowed equipment, return requests, and overdue records.</p>
            </div>

            <!-- Back to Departments Button -->
            <button class="btn-back-depts" id="backToDeptsBtn" style="display: none;">
                <i class="fa-solid fa-arrow-left"></i> Back to Departments
            </button>

            <!-- Selected Department Header Card -->
            <div id="selectedDeptHeaderCard" class="selected-dept-header-card" style="display: none;"></div>

            <!-- Department Selection Section -->
            <div id="departmentSelectorSection">
                <h3 class="dept-selection-title">Select a Department to Monitor</h3>
                <div class="dept-grid" id="deptGrid">
                    @if (!empty($dbDepartments))
                        @foreach ($dbDepartments as $dept)
                            @php
                                $deptTitle = $dept['department_name'] ?? $dept['title'] ?? $dept['name'];
                                $deptCode = $dept['department_code'] ?? '';
                                $collegeName = $dept['college'] ?? '';
                                $headName = $dept['department_head'] ?? '';
                                $imgUrl = $dept['image'] ?: asset('images/logo_only.png');
                                $sub = $deptCode ? ($deptCode . ' • ' . $collegeName) : $collegeName;
                            @endphp
                            <div class="dept-card" onclick="selectDepartment({{ Js::from($deptTitle) }})">
                                <div class="dept-logo-wrapper">
                                    <img src="{{ $imgUrl }}" alt="{{ $deptTitle }}" class="dept-logo-img">
                                </div>
                                <h4 class="dept-card-title">{{ $deptTitle }}</h4>
                                <p class="dept-card-subtitle">{{ $sub }}</p>
                                @if (!empty($headName))
                                    <p style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">
                                        <i class="fa-solid fa-user-tie" style="margin-right: 4px; color: var(--primary-color);"></i>
                                        {{ $headName }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    @endif
                    <div class="add-dept-card" id="btnOpenAddDeptCard" role="button" tabindex="0" title="Add Department" onclick="openAddDeptModal()">
                        <i class="fa-solid fa-plus add-dept-icon"></i>
                    </div>
                </div>
            </div>

            <!-- Summary Cards Section matching Users page exactly -->
            <div class="admin-section" style="margin-bottom: 8px; min-width: 0; width: 100%; overflow: hidden; display: none;">
                <h4 class="admin-section-heading">Summary Cards</h4>
                <div class="stats-scroll-container">
                    <!-- Card 1: Total Monitored -->
                    <div class="stat-card-mini">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Total Monitored</span>
                            <div class="stat-icon-wrapper info">
                                <i class="fa-solid fa-desktop"></i>
                            </div>
                        </div>
                        <span class="stat-card-value" id="statTotalMonitored">0</span>
                    </div>

                    <!-- Card 2: Available -->
                    <div class="stat-card-mini">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Available</span>
                            <div class="stat-icon-wrapper success">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                        </div>
                        <span class="stat-card-value" id="statAvailable">0</span>
                    </div>

                    <!-- Card 3: Borrowed -->
                    <div class="stat-card-mini">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Borrowed</span>
                            <div class="stat-icon-wrapper primary">
                                <i class="fa-solid fa-box"></i>
                            </div>
                        </div>
                        <span class="stat-card-value" id="statBorrowed">0</span>
                    </div>

                    <!-- Card 4: Reserved -->
                    <div class="stat-card-mini">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Reserved</span>
                            <div class="stat-icon-wrapper warning">
                                <i class="fa-solid fa-bookmark"></i>
                            </div>
                        </div>
                        <span class="stat-card-value" id="statReserved">0</span>
                    </div>

                    <!-- Card 5: Overdue -->
                    <div class="stat-card-mini">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Overdue</span>
                            <div class="stat-icon-wrapper danger">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                        </div>
                        <span class="stat-card-value" id="statOverdue">0</span>
                    </div>

                    <!-- Card 6: Maintenance -->
                    <div class="stat-card-mini">
                        <div class="stat-card-header">
                            <span class="stat-card-title">Maintenance</span>
                            <div class="stat-icon-wrapper danger" style="background-color: rgba(100, 116, 139, 0.08); color: #64748b;">
                                <i class="fa-solid fa-wrench"></i>
                            </div>
                        </div>
                        <span class="stat-card-value" id="statMaintenance">0</span>
                    </div>
                </div>
            </div>

            <!-- Controls bar matching Users page exactly -->
            <div class="controls-bar" style="display: none;">
                <div class="controls-left">
                    <!-- Search Bar -->
                    <div class="search-box-wrapper">
                        <input type="text" id="monitoringSearch" placeholder="Search by equipment, borrower, or ID...">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>

                    <!-- Status Filter Dropdown -->
                    <div class="filter-select-wrapper">
                        <select id="filterStatus">
                            <option value="all">All Statuses</option>
                            <option value="Available">Available</option>
                            <option value="Borrowed">Borrowed</option>
                            <option value="Reserved">Reserved</option>
                            <option value="Overdue">Overdue</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>

                    <!-- Category Filter Dropdown -->
                    <div class="filter-select-wrapper">
                        <select id="filterCategory">
                            <option value="all">All Categories</option>
                            @foreach ($dbCategories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- Table Card matching Users page exactly -->
            <div class="table-container card admin-table-card" style="display: none;">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th class="col-equipment">Equipment</th>
                            <th class="col-borrowed">Borrower</th>
                            <th class="col-role">Role</th>
                            <th class="col-borrow-date">Borrow Date</th>
                            <th class="col-return-date">Return Date</th>
                            <th class="col-status">Status</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="monitoringTableBody">
                        <!-- Populated dynamically via JS -->
                    </tbody>
                </table>

                <!-- Empty state matching Users page exactly -->
                <div id="monitoringEmptyState" class="empty-state-container" style="display: none;">
                    <i class="fa-solid fa-inbox empty-state-icon"></i>
                    <h4>No records found</h4>
                    <p>Try adjusting your search criteria or filters.</p>
                </div>

                <!-- Pagination Footer -->
                <div class="pagination-container" id="paginationContainer">
                    <span class="pagination-info" id="paginationInfo">Showing 1 to 25 of 25 entries</span>
                    <div class="pagination-buttons" id="paginationButtons">
                        <!-- Buttons added dynamically -->
                    </div>
                </div>
            </div>
        </div>
<!-- Details View Modal -->
    <div class="modal-overlay" id="detailsModal">
        <div class="modal-card eq-modal-card" style="max-width: 680px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Equipment monitoring record details</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeDetailsBtn">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;">Borrowing Details</h3>
                
                <!-- Borrower info block -->
                <div class="modal-requester-profile" style="margin-bottom: 20px;">
                    <img src="" alt="Avatar" class="modal-requester-avatar" id="modalBorrowerAvatar">
                    <div class="modal-requester-meta">
                        <span class="modal-requester-name" id="modalBorrowerName">-</span>
                        <span class="modal-requester-details"><span id="modalBorrowerRole">-</span> | ID: <span id="modalBorrowerId">-</span></span>
                    </div>
                </div>

                <!-- Main Fields Content -->
                <div class="detail-main-content">
                    <div class="detail-left-side">
                        <div class="detail-img-container">
                            <img src="" alt="Equipment Image" id="modalEqImg">
                        </div>
                        <div class="detail-form-group" style="margin-top: 16px;">
                            <label class="detail-form-label">Status</label>
                            <div class="status-badge-wrapper">
                                <span class="status-badge" id="modalStatusBadge">-</span>
                            </div>
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
                                <label class="detail-form-label">Request Date</label>
                                <input type="text" id="modalRequestDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Borrow Date</label>
                                <input type="text" id="modalBorrowDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Due Date</label>
                                <input type="text" id="modalDueDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group full-width">
                                <label class="detail-form-label">Purpose of Borrowing</label>
                                <textarea id="modalPurpose" class="detail-form-control textarea-control" rows="2" readonly></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Notes -->
                <div class="detail-form-group" style="margin-bottom: 20px;">
                    <label class="detail-form-label">Additional Notes</label>
                    <textarea id="modalNotes" class="detail-form-control textarea-control" rows="2" readonly></textarea>
                </div>

                <!-- Modal Actions Footer -->
                <div class="modal-actions-footer" id="modalActionsFooter">
                    <!-- Confirm Return or Close buttons -->
                </div>
            </div>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal-overlay" id="addDeptModal" style="display: none;">
        <div class="add-dept-modal-box">
            <div class="modal-outer-header">
                <div>
                    <h3 class="add-dept-modal-title">Add New Department</h3>
                    <p class="add-dept-modal-desc">Create a new department record in the system.</p>
                </div>
                <button type="button" id="btnCloseAddDeptModal" class="add-dept-close-btn">&times;</button>
            </div>
            <form id="addDeptForm" style="padding: 24px;">
                <input type="hidden" name="action" value="add_department">
                
                <div class="add-dept-form-group">
                    <label class="add-dept-label">Department Name <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="department_name" class="add-dept-input" placeholder="e.g. College of Computer Studies" required>
                </div>

                <div class="add-dept-form-group">
                    <label class="add-dept-label">Department Code <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="department_code" class="add-dept-input" placeholder="e.g. CCS" required>
                </div>

                <div class="add-dept-form-group">
                    <label class="add-dept-label">College <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="college" class="add-dept-input" placeholder="e.g. College of Computer Studies" required>
                </div>

                <div class="add-dept-form-group" style="margin-bottom: 24px;">
                    <label class="add-dept-label">Department Head <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="department_head" class="add-dept-input" placeholder="e.g. Dr. Jane Doe" required>
                </div>

                <div class="add-dept-footer">
                    <button type="button" class="btn-add-dept-cancel" id="btnCancelAddDept">Cancel</button>
                    <button type="submit" class="btn-add-dept-save">Save Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Interactivity Script -->
@endsection

@push('scripts')
<script>

        document.addEventListener('DOMContentLoaded', () => {


            // Monitoring requests loaded live from the database (one row per
            // equipment with its current borrow status), so approved borrows and
            // user returns are reflected here immediately.
            let requests = @json($dbRequests);

            // Keep storage in sync so in-session actions (reminders, details) persist
            localStorage.setItem('equip-track-requests', JSON.stringify(requests));

            // Department metadata loaded from database
            let dbDepts = @json($dbDepartments);
            let departments = dbDepts && dbDepts.length > 0 ? dbDepts : [];
            let selectedDept = sessionStorage.getItem('equip-track-selected-monitoring-dept') || null;

            // DOM Element References
            const deptGrid = document.getElementById('deptGrid');
            const deptSelectorSection = document.getElementById('departmentSelectorSection');
            const backToDeptsBtn = document.getElementById('backToDeptsBtn');
            const selectedDeptHeaderCard = document.getElementById('selectedDeptHeaderCard');
            const adminSection = document.querySelector('.admin-section');
            const controlsBar = document.querySelector('.controls-bar');
            const tableCard = document.querySelector('.admin-table-card');

            // Add Department Modal Elements & Global Functions
            window.openAddDeptModal = function() {
                const modal = document.getElementById('addDeptModal');
                if (modal) {
                    modal.style.setProperty('display', 'flex', 'important');
                    modal.style.setProperty('opacity', '1', 'important');
                    modal.style.setProperty('pointer-events', 'auto', 'important');
                    modal.style.setProperty('visibility', 'visible', 'important');
                    modal.classList.add('show');
                    const firstField = document.querySelector('#addDeptForm input[name="department_name"]');
                    if (firstField) firstField.focus();
                }
            };

            window.closeAddDeptModal = function() {
                const modal = document.getElementById('addDeptModal');
                if (modal) {
                    modal.style.setProperty('display', 'none', 'important');
                    modal.style.setProperty('opacity', '0', 'important');
                    modal.style.setProperty('pointer-events', 'none', 'important');
                    modal.style.setProperty('visibility', 'hidden', 'important');
                    modal.classList.remove('show');
                }
                const form = document.getElementById('addDeptForm');
                if (form) form.reset();
            };

            const addDeptModal = document.getElementById('addDeptModal');
            const btnCloseAddDeptModal = document.getElementById('btnCloseAddDeptModal');
            const btnCancelAddDept = document.getElementById('btnCancelAddDept');
            const addDeptForm = document.getElementById('addDeptForm');

            // Attach global delegated click listener for any open trigger
            document.addEventListener('click', function(e) {
                if (e.target.closest('#btnOpenAddDeptCard') || e.target.closest('#btnOpenAddDeptModal') || e.target.closest('.add-dept-card')) {
                    e.preventDefault();
                    window.openAddDeptModal();
                }
            });

            if (btnCloseAddDeptModal) btnCloseAddDeptModal.addEventListener('click', window.closeAddDeptModal);
            if (btnCancelAddDept) btnCancelAddDept.addEventListener('click', window.closeAddDeptModal);
            if (addDeptModal) {
                addDeptModal.addEventListener('click', (e) => {
                    if (e.target === addDeptModal) window.closeAddDeptModal();
                });
            }

            if (addDeptForm) {
                addDeptForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const deptName = addDeptForm.querySelector('input[name="department_name"]').value.trim();
                    const deptCode = addDeptForm.querySelector('input[name="department_code"]').value.trim();
                    const college = addDeptForm.querySelector('input[name="college"]').value.trim();
                    const deptHead = addDeptForm.querySelector('input[name="department_head"]').value.trim();

                    if (!deptName || !deptCode || !college || !deptHead) {
                        showNotification('Validation Error', 'All department fields are required.', 'error');
                        return;
                    }

                    const formData = new FormData(addDeptForm);
                    fetch('/admin/monitoring', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('Department Added', data.message || 'Department created successfully!', 'success');
                            
                            const newDept = {
                                department_id: data.department.department_id,
                                department_name: data.department.department_name,
                                department_code: data.department.department_code,
                                college: data.department.college,
                                department_head: data.department.department_head,
                                title: data.department.department_name,
                                name: data.department.department_name,
                                image: '{{ asset('images/logo_only.png') }}'
                            };

                            departments.push(newDept);
                            closeAddDeptModal();
                            renderDepartmentGrid();
                        } else {
                            showNotification('Error', data.message || 'Failed to add department.', 'error');
                        }
                    })
                    .catch(err => {
                        console.error('Error adding department:', err);
                        showNotification('System Error', 'An unexpected error occurred while saving the department.', 'error');
                    });
                });
            }

            // Render Department Selection Cards Grid
            function renderDepartmentGrid() {
                deptGrid.innerHTML = '';
                if (departments && departments.length > 0) {
                    departments.forEach(dept => {
                        const card = document.createElement('div');
                        card.className = 'dept-card';
                        const deptTitle = dept.department_name || dept.title || dept.name;
                        const deptCode = dept.department_code || dept.code || '';
                        const collegeName = dept.college || '';
                        const headName = dept.department_head || '';
                        const imgUrl = dept.image || dept.profile_image || `https://ui-avatars.com/api/?name=${encodeURIComponent(headName || deptTitle)}&background=385585&color=fff&size=300&bold=true`;

                        card.innerHTML = `
                            <div class="dept-logo-wrapper">
                                <img src="${escapeHTML(imgUrl)}" alt="${escapeHTML(deptTitle)}" class="dept-logo-img" onerror="this.onerror=null; this.src='{{ asset('images/logo_only.png') }}';">
                            </div>
                            <h4 class="dept-card-title">${escapeHTML(deptTitle)}</h4>
                            <p class="dept-card-subtitle">${escapeHTML(deptCode ? `${deptCode} • ${collegeName}` : collegeName)}</p>
                            ${headName ? `<p style="font-size: 12px; color: var(--text-muted); margin-top: 6px;"><i class="fa-solid fa-user-tie" style="margin-right: 4px; color: var(--primary-color);"></i> ${escapeHTML(headName)}</p>` : ''}
                        `;
                        card.addEventListener('click', () => {
                            selectDepartment(deptTitle);
                        });
                        deptGrid.appendChild(card);
                    });
                }

                // Append Add Department Card directly into grid layout
                const addCard = document.createElement('div');
                addCard.className = 'add-dept-card';
                addCard.id = 'btnOpenAddDeptCard';
                addCard.title = 'Add Department';
                addCard.setAttribute('role', 'button');
                addCard.setAttribute('tabindex', '0');
                addCard.innerHTML = `<i class="fa-solid fa-plus add-dept-icon"></i>`;
                addCard.addEventListener('click', (e) => {
                    e.preventDefault();
                    window.openAddDeptModal();
                });
                deptGrid.appendChild(addCard);
            }

            // Select a department and swap views
            window.selectDepartment = function(deptName) {
                selectedDept = deptName;
                sessionStorage.setItem('equip-track-selected-monitoring-dept', deptName);
                currentPage = 1;
                updateView();
            };
            function selectDepartment(deptName) {
                window.selectDepartment(deptName);
            }

            // Go back to the department selector screen
            function deselectDepartment() {
                selectedDept = null;
                sessionStorage.removeItem('equip-track-selected-monitoring-dept');
                updateView();
            }

            // Sync layout view based on state
            function updateView() {
                const headerSection = document.getElementById('monitoringHeaderSection');
                if (selectedDept) {
                    const deptMeta = departments.find(d => (d.department_name === selectedDept || d.name === selectedDept || d.title === selectedDept));
                    
                    deptSelectorSection.style.display = 'none';
                    backToDeptsBtn.style.display = 'inline-flex';
                    if (headerSection) headerSection.style.display = 'none';
                    
                    // Display department card
                    selectedDeptHeaderCard.style.display = 'flex';
                    
                    const logoSrc = deptMeta ? (deptMeta.image || deptMeta.profile_image || `https://ui-avatars.com/api/?name=${encodeURIComponent(deptMeta.department_head || selectedDept)}&background=385585&color=fff&size=300&bold=true`) : '{{ asset('images/logo_only.png') }}';
                    
                    // Translate standard department name to exact program/degree name as requested
                    let customTitle = deptMeta ? deptMeta.title : selectedDept;
                    if (selectedDept === "Information Technology Department") {
                        customTitle = "Bachelor of Science in Information Technology";
                    } else if (selectedDept === "Education Department") {
                        customTitle = "Bachelor of Science in Education";
                    } else if (selectedDept === "Engineering Department") {
                        customTitle = "Bachelor of Science in Engineering";
                    } else if (selectedDept === "Customs Administration Department") {
                        customTitle = "Bachelor of Science in Customs Administration";
                    } else if (selectedDept === "Business and Accountancy Department") {
                        customTitle = "Bachelor of Science in Business and Accountancy";
                    } else if (selectedDept === "Criminal Justice Department") {
                        customTitle = "Bachelor of Science in Criminal Justice";
                    }
                    
                    selectedDeptHeaderCard.innerHTML = `
                        <div class="selected-dept-logo-box">
                            <img src="${escapeHTML(logoSrc)}" alt="${escapeHTML(customTitle)} Logo" onerror="this.onerror=null; this.src='{{ asset('images/logo_only.png') }}';">
                        </div>
                        <div class="selected-dept-info">
                            <h3 class="selected-dept-name">${escapeHTML(customTitle)}</h3>
                            <p class="selected-dept-subtitle">Monitor borrowed equipment, return requests, and overdue records.</p>
                        </div>
                    `;
                    
                    adminSection.style.display = 'block';
                    controlsBar.style.display = 'flex';
                    tableCard.style.display = 'block';
                    
                    renderTable();
                } else {
                    deptSelectorSection.style.display = 'block';
                    backToDeptsBtn.style.display = 'none';
                    selectedDeptHeaderCard.style.display = 'none';
                    if (headerSection) headerSection.style.display = 'block';
                    
                    adminSection.style.display = 'none';
                    controlsBar.style.display = 'none';
                    tableCard.style.display = 'none';
                    
                    renderDepartmentGrid();
                }
            }

            backToDeptsBtn.addEventListener('click', deselectDepartment);

            // Dark Mode toggle logic

            // User dropdown toggling

            // Toast feedback notification
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

            // Send notification return reminder action
            window.sendReminder = function(id, event) {
                if (event) event.stopPropagation();
                const req = requests.find(r => r.id === id);
                if (req) {
                    showNotification('Reminder Sent', `Return reminder notification successfully sent to ${req.user} for "${req.equipment}".`, 'success');
                    
                    // Store reminder alerts in localStorage for user profiles integration
                    let alerts = JSON.parse(localStorage.getItem('equip-track-alerts')) || [];
                    alerts.push({
                        id: Date.now(),
                        user: req.user,
                        equipment: req.equipment,
                        date: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
                        message: `Reminder: Please return the borrowed "${req.equipment}" as soon as possible.`
                    });
                    localStorage.setItem('equip-track-alerts', JSON.stringify(alerts));
                }
            };

            // Render stats cards values dynamically
            function updateSummaryCards() {
                if (!selectedDept) return;
                const deptRequests = requests.filter(r => r.department === selectedDept);
                
                const total = deptRequests.length;
                const available = deptRequests.filter(r => r.status === 'Available').length;
                const borrowed = deptRequests.filter(r => r.status === 'Borrowed').length;
                const reserved = deptRequests.filter(r => r.status === 'Reserved').length;
                const overdue = deptRequests.filter(r => r.status === 'Overdue').length;
                const maintenance = deptRequests.filter(r => r.status === 'Maintenance').length;

                document.getElementById('statTotalMonitored').textContent = total;
                document.getElementById('statAvailable').textContent = available;
                document.getElementById('statBorrowed').textContent = borrowed;
                document.getElementById('statReserved').textContent = reserved;
                document.getElementById('statOverdue').textContent = overdue;
                document.getElementById('statMaintenance').textContent = maintenance;
            }

            // Render Table function
            function renderTable() {
                if (!selectedDept) return;

                const query = searchInput.value.toLowerCase().trim();
                const selectedStatus = filterStatusSelect.value;
                const selectedCategory = filterCategorySelect.value;

                tableBody.innerHTML = '';

                const filtered = requests.filter(req => {
                    // Department filter
                    if (req.department !== selectedDept) return false;

                    // Search term filter
                    const matchesSearch = (req.user && req.user.toLowerCase().includes(query)) || 
                                           (req.equipment && req.equipment.toLowerCase().includes(query)) ||
                                           (req.id_number && req.id_number.toLowerCase().includes(query));

                    // Category filter
                    const matchesCategory = selectedCategory === 'all' || req.category.toLowerCase() === selectedCategory.toLowerCase();

                    // Status filter
                    const matchesStatus = selectedStatus === 'all' || 
                        (selectedStatus.toLowerCase() === 'maintenance' && req.status.toLowerCase() === 'under maintenance') ||
                        req.status.toLowerCase() === selectedStatus.toLowerCase();

                    return matchesSearch && matchesCategory && matchesStatus;
                });

                // Paginate
                const totalEntries = filtered.length;
                const totalPages = Math.ceil(totalEntries / pageSize);
                
                // Adjust current page if it is out of range after filtering
                if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = Math.min(startIndex + pageSize, totalEntries);
                const paginatedRequests = filtered.slice(startIndex, endIndex);

                if (paginatedRequests.length === 0) {
                    emptyState.style.display = 'flex';
                    document.querySelector('.table-container table').style.display = 'none';
                    paginationContainer.style.display = 'none';
                } else {
                    emptyState.style.display = 'none';
                    document.querySelector('.table-container table').style.display = 'table';
                    paginationContainer.style.display = 'flex';

                    paginatedRequests.forEach(req => {
                        const tr = document.createElement('tr');
                        tr.className = 'admin-table-row';
                        
                        // Click row opens details modal (excluding clicking action button itself)
                        tr.addEventListener('click', (e) => {
                            if (!e.target.closest('button')) {
                                openDetailsModal(req.id, e);
                            }
                        });

                        // Action buttons cell
                        let actionHtml = '';
                        if (req.status === 'Overdue') {
                            actionHtml = `<button class="btn-action-remind" title="Send Return Reminder" onclick="sendReminder(${req.id}, event)"><i class="fa-solid fa-bell"></i> Remind</button>`;
                        } else if (req.status === 'Return Pending') {
                            actionHtml = `<button class="btn-action-confirm" onclick="confirmReturn(${req.id}, event)"><i class="fa-solid fa-circle-check"></i> Return</button>`;
                        } else {
                            actionHtml = `<button class="btn-action-view" onclick="openDetailsModal(${req.id}, event)"><i class="fa-solid fa-eye"></i> View</button>`;
                        }

                        // Format due date indicator if overdue
                        let dueDateStyle = '';
                        if (req.status === 'Overdue') {
                            dueDateStyle = 'style="color: #ef4444; font-weight: 600;"';
                        }

                        const statusBadgeClass = 'status-' + req.status.toLowerCase().replace(/\s+/g, '-');
                        
                        const hasBorrower = req.status === 'Borrowed' || req.status === 'Overdue' || req.status === 'Return Pending' || req.status === 'Reserved';
                        
                        const borrowerText = hasBorrower ? req.user : '—';
                        const borrowerRole = hasBorrower ? req.role : '—';
                        const borrowDateText = hasBorrower ? req.borrowDate : '—';
                        const returnDateText = hasBorrower ? req.dueDate : '—';

                        tr.innerHTML = `
                            <td>
                                <div class="eq-name-cell">
                                    <strong class="eq-name-text" title="${escapeHTML(req.equipment)}">${escapeHTML(req.equipment)}</strong>
                                    <span class="eq-category-text">${escapeHTML(req.category)}</span>
                                </div>
                            </td>
                            <td>
                                <div class="borrower-cell">
                                    <span class="borrower-name-text" title="${escapeHTML(borrowerText)}">${escapeHTML(borrowerText)}</span>
                                    ${hasBorrower ? `<span class="borrower-id-text">ID: ${escapeHTML(req.id_number)}</span>` : ''}
                                </div>
                            </td>
                            <td>${escapeHTML(borrowerRole)}</td>
                            <td>${escapeHTML(borrowDateText)}</td>
                            <td ${dueDateStyle}>${escapeHTML(returnDateText)}</td>
                            <td>
                                <span class="status-badge ${statusBadgeClass}">${req.status}</span>
                            </td>
                            <td class="action-cell" style="text-align: center;">
                                <div class="action-buttons" style="justify-content: center; gap: 8px;">
                                    ${actionHtml}
                                </div>
                            </td>
                        `;
                        tableBody.appendChild(tr);
                    });

                    // Update Pagination UI
                    paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalEntries} entries`;
                    renderPaginationButtons(totalPages);
                }
                updateSummaryCards();
            }

            // Render pagination buttons dynamically
            function renderPaginationButtons(totalPages) {
                paginationButtons.innerHTML = '';

                // Previous button
                const prevBtn = document.createElement('button');
                prevBtn.className = 'btn-page';
                prevBtn.innerHTML = '<i class="fa-solid fa-angle-left"></i>';
                prevBtn.disabled = currentPage === 1;
                prevBtn.addEventListener('click', () => {
                    currentPage--;
                    renderTable();
                });
                paginationButtons.appendChild(prevBtn);

                // Page number buttons
                for (let i = 1; i <= totalPages; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = `btn-page ${currentPage === i ? 'active' : ''}`;
                    pageBtn.textContent = i;
                    pageBtn.addEventListener('click', () => {
                        currentPage = i;
                        renderTable();
                    });
                    paginationButtons.appendChild(pageBtn);
                }

                // Next button
                const nextBtn = document.createElement('button');
                nextBtn.className = 'btn-page';
                nextBtn.innerHTML = '<i class="fa-solid fa-angle-right"></i>';
                nextBtn.disabled = currentPage === totalPages;
                nextBtn.addEventListener('click', () => {
                    currentPage++;
                    renderTable();
                });
                paginationButtons.appendChild(nextBtn);
            }

            // Confirm Return action function
            window.confirmReturn = function(id, event) {
                if (event) event.stopPropagation();
                const reqIndex = requests.findIndex(r => r.id === id);
                if (reqIndex > -1) {
                    const req = requests[reqIndex];
                    req.status = 'Available';
                    req.user = '—';
                    req.id_number = '—';
                    req.role = '—';
                    req.borrowDate = '—';
                    req.dueDate = '—';
                    req.date = '—';
                    req.purpose = '—';
                    req.notes = 'In excellent working condition.';
                    
                    // Save to localStorage
                    localStorage.setItem('equip-track-requests', JSON.stringify(requests));
                    
                    // Update layout
                    showNotification('Return Confirmed', `Equipment "${req.equipment}" returned successfully.`, 'success');
                    
                    // Close details modal if open
                    detailsModal.classList.remove('show');

                    renderTable();
                }
            };

            // Open Details View Modal
            window.openDetailsModal = function(id, event) {
                if (event) event.stopPropagation();
                const req = requests.find(r => r.id === id);
                if (!req) return;

                // Set content values
                document.getElementById('modalBorrowerAvatar').src = req.avatar || "https://ui-avatars.com/api/?name=" + encodeURIComponent(req.user || 'N/A');
                document.getElementById('modalBorrowerName').textContent = req.user || '—';
                document.getElementById('modalBorrowerRole').textContent = req.role || '—';
                document.getElementById('modalBorrowerId').textContent = req.id_number || 'N/A';

                document.getElementById('modalEqImg').src = req.img || "https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60";
                
                const statusBadge = document.getElementById('modalStatusBadge');
                statusBadge.textContent = req.status;
                statusBadge.className = 'status-badge status-' + req.status.toLowerCase().replace(/\s+/g, '-');

                document.getElementById('modalEqName').value = req.equipment;
                document.getElementById('modalEqCategory').value = req.category;
                document.getElementById('modalRequestDate').value = req.date || '—';
                document.getElementById('modalBorrowDate').value = req.borrowDate || '—';
                document.getElementById('modalDueDate').value = req.dueDate || '—';
                document.getElementById('modalPurpose').value = req.purpose || '—';
                document.getElementById('modalNotes').value = req.notes || '—';

                // Setup footer buttons based on status
                const footer = document.getElementById('modalActionsFooter');
                if (req.status === 'Return Pending') {
                    footer.innerHTML = `
                        <button class="btn-modal-close" onclick="closeModal()">Close</button>
                        <button class="btn-action-confirm" onclick="confirmReturn(${req.id})"><i class="fa-solid fa-circle-check"></i> Confirm Return</button>
                    `;
                } else if (req.status === 'Overdue') {
                    footer.innerHTML = `
                        <button class="btn-modal-close" onclick="closeModal()">Close</button>
                        <button class="btn-action-remind" onclick="sendReminder(${req.id}); closeModal();"><i class="fa-solid fa-bell"></i> Send Reminder</button>
                    `;
                } else {
                    footer.innerHTML = `
                        <button class="btn-modal-close" style="width: 100%;" onclick="closeModal()">Close</button>
                    `;
                }

                detailsModal.classList.add('show');
            };

            window.closeModal = function() {
                detailsModal.classList.remove('show');
            };

            closeDetailsBtn.addEventListener('click', closeModal);
            detailsModal.addEventListener('click', (e) => {
                if (e.target === detailsModal) {
                    closeModal();
                }
            });

            // Input listener triggers
            searchInput.addEventListener('input', () => {
                currentPage = 1;
                renderTable();
            });
            filterStatusSelect.addEventListener('change', () => {
                currentPage = 1;
                renderTable();
            });
            filterCategorySelect.addEventListener('change', () => {
                currentPage = 1;
                renderTable();
            });

            // Initial renders based on state
            updateView();
        });

        // Helper to escape HTML characters
        function escapeHTML(str) {
            if (!str) return '';
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
    
</script>
@endpush
