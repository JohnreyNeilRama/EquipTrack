@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('departments/css/users.css') }}">
<link rel="stylesheet" href="{{ asset('departments/css/user-details-modal.css') }}">
<link rel="stylesheet" href="{{ asset('departments/css/user-forms.css') }}?v={{ filemtime(public_path('departments/css/user-forms.css')) }}">
@endpush

@section('content')
<!-- Page Header -->
        <div class="page-title-section" style="margin-top: 10px;">
            <h2>Users Management</h2>
            <p>Students and faculty members registered under your department.</p>
        </div>

        <!-- Summary Cards Section -->
        <div class="users-summary-section">
            <h4 class="section-subtitle">Summary Cards</h4>
            <div class="summary-cards-grid">
                <!-- Card 1: Total Users -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumTotalUsers">0</span>
                        <div class="summary-card-icon icon-blue">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Total Users</span>
                </div>

                <!-- Card 2: Students -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumStudents">0</span>
                        <div class="summary-card-icon icon-green">
                            <i class="fa-solid fa-graduation-cap"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Students</span>
                </div>

                <!-- Card 3: Faculty Members -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumFaculty">0</span>
                        <div class="summary-card-icon icon-amber">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Faculty Members</span>
                </div>

                <!-- Card 4: Active Accounts -->
                <div class="summary-card-item">
                    <div class="summary-card-header">
                        <span class="summary-card-val" id="sumActive">0</span>
                        <div class="summary-card-icon icon-green">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                    </div>
                    <span class="summary-card-label">Active Accounts</span>
                </div>
            </div>
        </div>

        <!-- Controls Bar (Filter Container Card) -->
        <div class="filter-card-container">
            <div class="search-box-right-icon">
                <input type="text" id="searchUsers" placeholder="Search by name or ID...">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div class="filter-select-item">
                <select id="filterRole">
                    <option value="all">All Roles</option>
                    <option value="student">Student</option>
                    <option value="faculty">Faculty Member</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>

            <div class="filter-select-item">
                <select id="filterStatus">
                    <option value="all">Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>

            <div class="users-add-actions">
                <button type="button" class="btn-add-user" id="addStudentBtn">
                    <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Add Student
                </button>
                <button type="button" class="btn-add-user" id="addFacultyBtn">
                    <i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> Add Faculty
                </button>
            </div>
        </div>

        <!-- Department Users Data Table Card -->
        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Full Name</th>
                        <th style="width: 15%;">ID Number</th>
                        <th style="width: 15%;">User Type</th>
                        <th style="width: 18%;">Year Level / Attainment</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 8%;">Action</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <!-- Dynamic rendering via JS -->
                </tbody>
            </table>

            <!-- Table Footer Pagination Matching Reference Image -->
            <div class="table-footer-pagination">
                <span class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                <div class="pagination-controls" id="paginationControls">
                    <button type="button" class="page-btn disabled"><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="page-btn active">1</button>
                    <button type="button" class="page-btn disabled"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <!-- Empty State Container -->
        <div class="empty-state-container" id="emptyStateContainer" style="display: none; margin-top: 20px;">
            <i class="fa-solid fa-users-slash empty-state-icon"></i>
            <h4>No users found</h4>
            <p>No department user records match your search or filter parameters.</p>
        </div>
<!-- View User Details Modal -->
    <div class="modal-overlay" id="viewUserModal">
        <div class="eq-modal-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Detailed view of user account information</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeViewUserModalBtn">&times;</button>
                <h3 class="modal-title-center">User Details</h3>

                <div class="modal-requester-profile">
                    <img src="" alt="User Avatar" class="modal-requester-avatar" id="modalUserAvatar">
                    <div class="modal-requester-meta">
                        <span class="modal-requester-name" id="modalUserName">-</span>
                        <span class="modal-requester-details" id="modalUserRole">-</span>
                    </div>
                </div>

                <div class="detail-main-content">
                    <div class="detail-left-side">
                        <div class="detail-img-container">
                            <img src="" alt="User Profile Image" id="modalUserProfileImg">
                        </div>
                        <div class="detail-form-group">
                            <label class="detail-form-label">Status</label>
                            <div class="status-badge-wrapper">
                                <span class="status-badge" id="modalStatusBadge">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-right-side">
                        <div class="detail-form-grid">
                            <div class="detail-form-group">
                                <label class="detail-form-label">Full Name</label>
                                <input type="text" id="modalFullName" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">ID Number</label>
                                <input type="text" id="modalStudentId" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label" id="modalAttainmentLabel">Year Level</label>
                                <input type="text" id="modalYearLevel" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Email</label>
                                <input type="text" id="modalEmail" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Role</label>
                                <input type="text" id="modalRole" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Department</label>
                                <input type="text" id="modalDepartment" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Date Created</label>
                                <input type="text" id="modalDateCreated" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Last Online</label>
                                <input type="text" id="modalLastOnline" class="detail-form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-form-group" style="margin-bottom: 16px;">
                    <label class="detail-form-label">Address</label>
                    <input type="text" id="modalAddress" class="detail-form-control" readonly>
                </div>

                <div class="modal-actions-footer">
                    <button type="button" class="btn-modal-edit" id="modalEditDetailsBtn"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i> Edit Details</button>
                    <button type="button" class="btn-modal-status" id="modalStatusBtn">Deactivate Account</button>
                    <button type="button" class="btn-modal-close" id="modalCloseDetailsBtn">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Student / Add Faculty / Edit Details: one form; the script shows the fields for the case --}}
    <div class="modal-overlay" id="userFormModal">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="ufTitle">
            <button type="button" class="modal-close" id="closeUserFormBtn" aria-label="Close">&times;</button>
            <div class="uf-head">
                <h3 class="uf-title" id="ufTitle">Add Student</h3>
                <p class="uf-subtitle" id="ufSubtitle">The account is added to {{ $departmentName }}.</p>
            </div>

            <form id="userForm" class="uf-body" novalidate>
                <input type="hidden" name="user_id" id="ufUserId">

                <p class="uf-note" id="ufNote" hidden></p>

                <div class="uf-row">
                    <div class="form-group-flat">
                        <label for="ufFirstName">First Name</label>
                        <input type="text" id="ufFirstName" name="first_name" class="form-control-flat" required maxlength="50">
                    </div>
                    <div class="form-group-flat">
                        <label for="ufLastName">Last Name</label>
                        <input type="text" id="ufLastName" name="last_name" class="form-control-flat" required maxlength="50">
                    </div>
                </div>

                <div class="uf-row" data-for="student">
                    <div class="form-group-flat">
                        <label for="ufIdNumber">Student ID Number</label>
                        <input type="text" id="ufIdNumber" name="id_number" class="form-control-flat" required pattern="\d{8}" maxlength="8" inputmode="numeric" placeholder="e.g., 20230123" title="Student ID must be exactly 8 digits.">
                    </div>
                    <div class="form-group-flat">
                        <label for="ufYearLevel">Year &amp; Level</label>
                        <select id="ufYearLevel" name="year_level" class="form-control-flat" required>
                            @foreach (\App\Services\UserAccountEditor::YEAR_LEVELS as $level)
                                <option value="{{ $level }}">{{ $level }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="uf-row" data-for="faculty">
                    <div class="form-group-flat">
                        <label for="ufFacultyId">Faculty ID Number</label>
                        <input type="text" id="ufFacultyId" name="faculty_id_number" class="form-control-flat" required maxlength="20" pattern="[A-Za-z0-9\-]+" placeholder="e.g., T-00987" title="Letters, numbers and hyphens only.">
                    </div>
                    <div class="form-group-flat">
                        <label for="ufLicense">Teaching License No.</label>
                        <input type="text" id="ufLicense" name="teaching_license_no" class="form-control-flat" required maxlength="30" pattern="[A-Za-z0-9\-]+" placeholder="e.g., 1234567" title="Letters, numbers and hyphens only.">
                    </div>
                </div>

                <div class="form-group-flat" data-for="faculty">
                    <label for="ufEducation">Highest Educational Attainment <span class="label-optional">(optional)</span></label>
                    <input type="text" id="ufEducation" name="highest_educational_attainment" class="form-control-flat" maxlength="100" placeholder="e.g., Master of Information Technology">
                </div>

                <div class="form-group-flat">
                    <label for="ufDepartment">Department</label>
                    <input type="text" id="ufDepartment" class="form-control-flat" value="{{ $departmentName }}" readonly aria-readonly="true">
                </div>

                <div class="form-group-flat">
                    <label for="ufEmail">Email Address</label>
                    <input type="email" id="ufEmail" name="email" class="form-control-flat" required maxlength="100" placeholder="name@example.com">
                </div>

                <div class="form-group-flat">
                    <label for="ufAddress">Home Address</label>
                    <input type="text" id="ufAddress" name="address" class="form-control-flat" required maxlength="150" placeholder="123 University Ave, Tech City">
                </div>

                <div class="form-group-flat" data-for="add">
                    <label for="ufPassword">Password</label>
                    <input type="password" id="ufPassword" name="password" class="form-control-flat" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters, with letters and numbers">
                </div>

                <p class="uf-error" id="ufError" role="alert" hidden></p>
            </form>

            <div class="uf-foot">
                <button type="button" class="uf-btn uf-btn-secondary" id="cancelUserFormBtn">Cancel</button>
                <button type="submit" form="userForm" class="uf-btn uf-btn-primary" id="ufSubmitBtn">Create Account</button>
            </div>
        </div>
    </div>

    <div class="toast-notification" id="toastNotif" role="status" aria-live="polite">
        <i class="fa-solid fa-circle-check" id="toastIcon" aria-hidden="true"></i>
        <span id="toastMessage"></span>
    </div>
@endsection

@push('scripts')
<script>
        // Mobile sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarScrim = document.getElementById('sidebarScrim');
        const topbarMenuBtn = document.getElementById('topbarMenuBtn');

        function openSidebar() {
            if (sidebar) sidebar.classList.add('open');
            if (sidebarScrim) sidebarScrim.classList.add('show');
        }
        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('open');
            if (sidebarScrim) sidebarScrim.classList.remove('show');
        }

        if (topbarMenuBtn) topbarMenuBtn.addEventListener('click', openSidebar);
        if (sidebarScrim) sidebarScrim.addEventListener('click', closeSidebar);

        document.addEventListener('DOMContentLoaded', () => {
            const tableBody = document.getElementById('usersTableBody');
            const searchInput = document.getElementById('searchUsers');
            const filterRoleSelect = document.getElementById('filterRole');
            const filterStatusSelect = document.getElementById('filterStatus');
            const emptyStateContainer = document.getElementById('emptyStateContainer');
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationControls = document.getElementById('paginationControls');

            // Pagination (same as the Monitoring and History pages): 10 per page.
            // The background refresh keeps the current page; a new search or
            // filter goes back to page 1.
            let currentPage = 1;
            const pageSize = 10;

            // Summary metrics
            const sumTotalUsers = document.getElementById('sumTotalUsers');
            const sumStudents = document.getElementById('sumStudents');
            const sumFaculty = document.getElementById('sumFaculty');
            const sumActive = document.getElementById('sumActive');

            // Modal elements
            const viewUserModal = document.getElementById('viewUserModal');
            const closeViewUserModalBtn = document.getElementById('closeViewUserModalBtn');

            // Users of the signed-in department, loaded live from the database
            // and refreshed below so the table stays current.
            const URL_DATA = @json(route('department.users.data'));
            const REFRESH_MS = 15000;
            let users = @json($dbUsers);

            function updateSummaryCards() {
                sumTotalUsers.textContent = users.length;
                sumStudents.textContent = users.filter(u => u.userType.toLowerCase().includes('student')).length;
                sumFaculty.textContent = users.filter(u => u.userType.toLowerCase().includes('faculty')).length;
                sumActive.textContent = users.filter(u => u.status.toLowerCase() === 'active').length;
            }

            function renderTable() {
                tableBody.innerHTML = '';

                users.forEach(user => {
                    const tr = document.createElement('tr');
                    const isStudent = user.userType.toLowerCase().includes('student');
                    const badgeClass = isStudent ? 'student' : 'faculty';
                    const statusClass = user.status.toLowerCase() === 'active' ? 'active' : 'inactive';

                    tr.setAttribute('data-usertype', user.userType.toLowerCase());
                    tr.setAttribute('data-status', user.status.toLowerCase());
                    tr.setAttribute('data-search', `${user.fullName} ${user.idNumber} ${user.userType} ${user.attainment}`.toLowerCase());

                    tr.innerHTML = `
                        <td class="col-fullname"><span class="fullname-text" title="${escapeHTML(user.fullName)}">${escapeHTML(user.fullName)}</span></td>
                        <td class="col-id">${escapeHTML(user.idNumber)}</td>
                        <td><span class="user-type-badge ${badgeClass}">${escapeHTML(user.userType)}</span></td>
                        <td class="col-attainment">${escapeHTML(user.attainment)}</td>
                        <td><span class="status-badge ${statusClass}">${escapeHTML(user.status)}</span></td>
                        <td>
                            <button type="button" class="btn-action-view" onclick="openViewUserModal(${Number(user.id)})">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i> View
                            </button>
                        </td>
                    `;

                    // Clicking the row (outside its button) is a shortcut to View
                    tr.addEventListener('click', (e) => {
                        if (!e.target.closest('button, a, input, select')) openViewUserModal(Number(user.id));
                    });

                    tableBody.appendChild(tr);
                });

                updateSummaryCards();
                filterTable();
            }

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

            window.openViewUserModal = function(id) {
                const user = users.find(u => Number(u.id) === Number(id));
                if (!user) return;

                // Profile picture: the user's own image, else a generated initials avatar
                // (same fallback the admin View Details uses).
                const fallbackAvatar = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.fullName) + '&background=385585&color=fff&size=300&bold=true';
                const avatarUrl = user.profileImage || fallbackAvatar;
                const avatarEl = document.getElementById('modalUserAvatar');
                const photoEl = document.getElementById('modalUserProfileImg');
                [avatarEl, photoEl].forEach(img => {
                    img.onerror = function() {
                        this.onerror = null;
                        this.src = fallbackAvatar;
                    };
                    img.src = avatarUrl;
                });

                document.getElementById('modalUserName').textContent = user.fullName;
                document.getElementById('modalUserRole').textContent = user.userType;

                const statusBadge = document.getElementById('modalStatusBadge');
                statusBadge.textContent = user.status;
                statusBadge.className = 'status-badge status-' + user.status.toLowerCase();

                document.getElementById('modalFullName').value = user.fullName;
                document.getElementById('modalStudentId').value = user.idNumber || 'N/A';
                document.getElementById('modalAttainmentLabel').textContent = user.attainmentLabel || 'Year Level';
                document.getElementById('modalYearLevel').value = user.attainment || 'N/A';
                document.getElementById('modalEmail').value = user.email || 'N/A';
                document.getElementById('modalRole').value = user.userType;
                document.getElementById('modalDepartment').value = user.department || 'N/A';
                document.getElementById('modalDateCreated').value = user.createdAt || 'Database Record';
                document.getElementById('modalLastOnline').value = user.lastOnline || 'Offline / Never';
                document.getElementById('modalAddress').value = user.address || 'N/A';

                // Activate / Deactivate follows the account's current status.
                activeUserId = Number(user.id);
                const statusBtn = document.getElementById('modalStatusBtn');
                const isActive = user.status.toLowerCase() === 'active';
                statusBtn.textContent = isActive ? 'Deactivate Account' : 'Activate Account';
                statusBtn.classList.toggle('is-activate', !isActive);
                statusBtn.disabled = false;

                viewUserModal.classList.add('show');
            };

            // --------------------------------------------------------------
            // Add Student / Add Faculty / Edit Details / Activate-Deactivate
            // (own department only; the server checks every request)
            // --------------------------------------------------------------
            const CSRF_TOKEN = @json(csrf_token());
            const URLS = {
                student: @json(route('department.users.student')),
                faculty: @json(route('department.users.faculty')),
                update: @json(route('department.users.update')),
                status: @json(route('department.users.status')),
            };
            const userFormModal = document.getElementById('userFormModal');
            const userForm = document.getElementById('userForm');
            const ufError = document.getElementById('ufError');
            const ufNote = document.getElementById('ufNote');
            const ufSubmitBtn = document.getElementById('ufSubmitBtn');
            let activeUserId = null;
            let formMode = null; // 'student' | 'faculty' | 'edit'

            function showToast(msg, type = 'success') {
                const toast = document.getElementById('toastNotif');
                document.getElementById('toastMessage').textContent = msg;
                toast.classList.toggle('is-error', type === 'error');
                document.getElementById('toastIcon').className = type === 'error' ? 'fa-solid fa-circle-exclamation' : 'fa-solid fa-circle-check';
                toast.classList.add('show');
                clearTimeout(showToast.timer);
                showToast.timer = setTimeout(() => toast.classList.remove('show'), 3500);
            }

            function firstError(data, fallback) {
                if (data && data.errors) {
                    const first = Object.values(data.errors)[0];
                    if (Array.isArray(first) && first.length) return first[0];
                }
                return (data && data.message) || fallback;
            }

            // POST and read the JSON reply, including validation errors (422).
            function post(url, formData) {
                formData.append('_token', CSRF_TOKEN);
                return fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' }, credentials: 'same-origin', body: formData })
                    .then(res => res.json().catch(() => ({})).then(data => ({ ok: res.ok && data.success, status: res.status, data })));
            }

            function applyServerUsers(data) {
                if (data && Array.isArray(data.users)) {
                    users = data.users;
                    renderTable();
                }
            }

            function openUserForm(mode, user = null) {
                formMode = mode;
                userForm.reset();
                ufError.hidden = true;
                const type = mode === 'edit' ? (user.role === 'Faculty' ? 'faculty' : 'student') : mode;

                // Show only the fields for this case; hidden ones are disabled so they aren't sent.
                userForm.querySelectorAll('[data-for]').forEach(group => {
                    const show = group.dataset.for === type || (group.dataset.for === 'add' && mode !== 'edit');
                    group.style.display = show ? '' : 'none';
                    group.querySelectorAll('input, select').forEach(input => { input.disabled = !show; });
                });

                document.getElementById('ufUserId').value = user ? user.id : '';
                const typeLabel = type === 'faculty' ? 'Faculty' : 'Student';
                document.getElementById('ufTitle').textContent = mode === 'edit' ? `Edit ${typeLabel} Account` : `Add ${typeLabel}`;
                document.getElementById('ufSubtitle').textContent = mode === 'edit'
                    ? `Update the account details of ${user.fullName}.`
                    : `The account is added to your department.`;
                ufNote.hidden = mode !== 'edit';
                ufNote.textContent = "Passwords aren't changed here. The account owner can use Change Password on their profile, or Forgot Password on the login page.";
                ufSubmitBtn.textContent = mode === 'edit' ? 'Save Changes' : 'Create Account';

                if (mode === 'edit') {
                    const v = user.edit || {};
                    const fields = { first_name: 'ufFirstName', last_name: 'ufLastName', id_number: 'ufIdNumber', year_level: 'ufYearLevel',
                        faculty_id_number: 'ufFacultyId', teaching_license_no: 'ufLicense', highest_educational_attainment: 'ufEducation',
                        email: 'ufEmail', address: 'ufAddress' };
                    Object.entries(fields).forEach(([name, id]) => {
                        const el = document.getElementById(id);
                        if (!el.disabled) el.value = v[name] ?? '';
                    });
                }

                userFormModal.classList.add('show');
                setTimeout(() => document.getElementById('ufFirstName').focus(), 50);
            }

            // Shows the error and scrolls it into view (the fields scroll on short screens).
            function showFormError(message) {
                ufError.textContent = message;
                ufError.hidden = false;
                ufError.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }

            function closeUserForm() {
                userFormModal.classList.remove('show');
            }

            document.getElementById('addStudentBtn').addEventListener('click', () => openUserForm('student'));
            document.getElementById('addFacultyBtn').addEventListener('click', () => openUserForm('faculty'));
            document.getElementById('closeUserFormBtn').addEventListener('click', closeUserForm);
            document.getElementById('cancelUserFormBtn').addEventListener('click', closeUserForm);
            userFormModal.addEventListener('click', (e) => { if (e.target === userFormModal) closeUserForm(); });

            document.getElementById('modalEditDetailsBtn').addEventListener('click', () => {
                const user = users.find(u => Number(u.id) === activeUserId);
                if (!user) return;
                viewUserModal.classList.remove('show');
                openUserForm('edit', user);
            });

            userForm.addEventListener('submit', (e) => {
                e.preventDefault();
                ufError.hidden = true;

                // Quick checks; the server checks everything again.
                if (formMode !== 'edit') {
                    const pw = document.getElementById('ufPassword').value;
                    if (pw.length < 8 || !/[A-Za-z]/.test(pw) || !/\d/.test(pw)) {
                        showFormError('The password must be at least 8 characters and include a letter and a number.');
                        return;
                    }
                }

                ufSubmitBtn.disabled = true;
                const label = ufSubmitBtn.textContent;
                ufSubmitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

                post(formMode === 'edit' ? URLS.update : URLS[formMode], new FormData(userForm))
                    .then(({ ok, status, data }) => {
                        if (!ok) {
                            showFormError(status === 419
                                ? 'Your session expired. Please reload the page and try again.'
                                : firstError(data, 'The account could not be saved.'));
                            return;
                        }
                        applyServerUsers(data);
                        closeUserForm();
                        showToast(data.message || 'Saved.');
                    })
                    .catch(() => {
                        showFormError('Could not reach the server. Check your connection and try again.');
                    })
                    .finally(() => {
                        ufSubmitBtn.disabled = false;
                        ufSubmitBtn.textContent = label;
                    });
            });

            document.getElementById('modalStatusBtn').addEventListener('click', () => {
                const user = users.find(u => Number(u.id) === activeUserId);
                if (!user) return;
                const activate = user.status.toLowerCase() !== 'active';
                const statusBtn = document.getElementById('modalStatusBtn');
                statusBtn.disabled = true;

                const formData = new FormData();
                formData.append('user_id', user.id);
                formData.append('status', activate ? 'Active' : 'Deactivated');
                post(URLS.status, formData)
                    .then(({ ok, data }) => {
                        if (!ok) {
                            showToast(firstError(data, 'The account status could not be changed.'), 'error');
                            return;
                        }
                        applyServerUsers(data);
                        viewUserModal.classList.remove('show');
                        showToast(data.message || 'Account updated.');
                    })
                    .catch(() => showToast('Could not reach the server. Check your connection and try again.', 'error'))
                    .finally(() => { statusBtn.disabled = false; });
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && userFormModal.classList.contains('show')) closeUserForm();
            });

            const modalCloseDetailsBtn = document.getElementById('modalCloseDetailsBtn');
            if (modalCloseDetailsBtn) {
                modalCloseDetailsBtn.addEventListener('click', () => {
                    viewUserModal.classList.remove('show');
                });
            }

            if (closeViewUserModalBtn) {
                closeViewUserModalBtn.addEventListener('click', () => {
                    viewUserModal.classList.remove('show');
                });
            }

            viewUserModal.addEventListener('click', (e) => {
                if (e.target === viewUserModal) {
                    viewUserModal.classList.remove('show');
                }
            });

            function filterTable() {
                const query = searchInput.value.toLowerCase().trim();
                const selectedRole = filterRoleSelect.value.toLowerCase();
                const selectedStatus = filterStatusSelect.value.toLowerCase();

                const rows = Array.from(tableBody.querySelectorAll('tr'));

                const matched = rows.filter(row => {
                    const searchData = row.getAttribute('data-search');
                    const rowUserType = row.getAttribute('data-usertype');
                    const rowStatus = row.getAttribute('data-status');

                    const matchesSearch = searchData.includes(query);
                    const matchesRole = (selectedRole === 'all' || rowUserType.includes(selectedRole));
                    const matchesStatus = (selectedStatus === 'all' || rowStatus === selectedStatus);
                    return matchesSearch && matchesRole && matchesStatus;
                });

                const visibleCount = matched.length;
                const totalPages = Math.max(1, Math.ceil(visibleCount / pageSize));
                if (currentPage > totalPages) currentPage = totalPages;

                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = Math.min(startIndex + pageSize, visibleCount);
                const pageRows = matched.slice(startIndex, endIndex);

                // Only the current page's rows are shown (rows are hidden, not removed).
                rows.forEach(row => {
                    row.style.display = 'none';
                    row.classList.remove('last-visible-row');
                });
                pageRows.forEach(row => { row.style.display = ''; });

                // Mark the last visible row so the table's footer line doesn't
                // double up with that row's bottom line.
                if (pageRows.length) {
                    pageRows[pageRows.length - 1].classList.add('last-visible-row');
                }

                if (paginationInfo) {
                    paginationInfo.textContent = visibleCount > 0
                        ? `Showing ${startIndex + 1} to ${endIndex} of ${visibleCount} entries`
                        : 'Showing 0 to 0 of 0 entries';
                }
                renderPaginationButtons(totalPages);

                if (visibleCount === 0) {
                    emptyStateContainer.style.display = 'flex';
                } else {
                    emptyStateContainer.style.display = 'none';
                }
            }

            // Render pagination buttons dynamically (keeps the existing page-btn design)
            function renderPaginationButtons(totalPages) {
                paginationControls.innerHTML = '';

                const makeBtn = (html, disabled, active, onClick, label) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'page-btn' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
                    btn.innerHTML = html;
                    btn.disabled = disabled;
                    if (label) btn.setAttribute('aria-label', label);
                    if (active) btn.setAttribute('aria-current', 'page');
                    if (!disabled) btn.addEventListener('click', onClick);
                    return btn;
                };

                paginationControls.appendChild(makeBtn('<i class="fa-solid fa-chevron-left"></i>', currentPage <= 1, false, () => {
                    currentPage--;
                    filterTable();
                }, 'Previous page'));

                for (let i = 1; i <= totalPages; i++) {
                    paginationControls.appendChild(makeBtn(String(i), false, currentPage === i, () => {
                        currentPage = i;
                        filterTable();
                    }, 'Page ' + i));
                }

                paginationControls.appendChild(makeBtn('<i class="fa-solid fa-chevron-right"></i>', currentPage >= totalPages, false, () => {
                    currentPage++;
                    filterTable();
                }, 'Next page'));
            }

            // A new search or filter starts again from the first page.
            function applyFilters() {
                currentPage = 1;
                filterTable();
            }

            // Reload the department's users so new, edited and deactivated
            // accounts show up without a manual page refresh.
            function refreshUsers() {
                fetch(URL_DATA, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                })
                    .then(res => res.ok ? res.json() : Promise.reject(new Error('HTTP ' + res.status)))
                    .then(data => {
                        if (data && data.success && Array.isArray(data.users)) {
                            users = data.users;
                            renderTable();
                        }
                    })
                    .catch(err => console.error('Error refreshing users:', err));
            }

            searchInput.addEventListener('input', applyFilters);
            filterRoleSelect.addEventListener('change', applyFilters);
            filterStatusSelect.addEventListener('change', applyFilters);

            // Initial render, then keep it current.
            renderTable();
            setInterval(refreshUsers, REFRESH_MS);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') refreshUsers();
            });
        });
    
</script>
@endpush
