@extends('layouts.admin')

@section('title', 'EquipTrack - User Management')

@push('css')
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminequipment.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminusers.css') }}">
@endpush

@php
    $serverMsg = session('serverMsg');
    $serverMsgType = session('serverMsgType', 'success');
@endphp

@section('content')
    <div class="users-container" style="min-width: 0; max-width: 100%;">
        {{-- Header Section --}}
        <div class="equipment-header-section">
            <h2>User Management</h2>
            <p>Manage registered users and departments.</p>
        </div>

        {{-- Summary Cards Section --}}
        <div class="admin-section" style="margin-bottom: 8px; min-width: 0; width: 100%; overflow: hidden;">
            <h4 class="admin-section-heading">Summary Cards</h4>
            <div class="stats-scroll-container">
            <div class="stat-card-mini">
                <div class="stat-card-header">
                    <span class="stat-card-title">Total Users</span>
                    <div class="stat-icon-wrapper info">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
                <span class="stat-card-value" id="statTotalUsers">0</span>
            </div>
            <div class="stat-card-mini">
                <div class="stat-card-header">
                    <span class="stat-card-title">Students</span>
                    <div class="stat-icon-wrapper success">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                </div>
                <span class="stat-card-value" id="statStudents">0</span>
            </div>
            <div class="stat-card-mini">
                <div class="stat-card-header">
                    <span class="stat-card-title">Faculty Members</span>
                    <div class="stat-icon-wrapper warning">
                        <i class="fa-solid fa-chalkboard-user"></i>
                    </div>
                </div>
                <span class="stat-card-value" id="statFaculty">0</span>
            </div>
            <div class="stat-card-mini">
                <div class="stat-card-header">
                    <span class="stat-card-title">Departments</span>
                    <div class="stat-icon-wrapper primary">
                        <i class="fa-solid fa-building"></i>
                    </div>
                </div>
                <span class="stat-card-value" id="statDepartments">0</span>
            </div>
            <div class="stat-card-mini">
                <div class="stat-card-header">
                    <span class="stat-card-title">Active Accounts</span>
                    <div class="stat-icon-wrapper info" style="background-color: rgba(16, 185, 129, 0.08); color: #10b981;">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
                <span class="stat-card-value" id="statActive">0</span>
            </div>
            <div class="stat-card-mini">
                <div class="stat-card-header">
                    <span class="stat-card-title">Deactivated Accounts</span>
                    <div class="stat-icon-wrapper danger">
                        <i class="fa-solid fa-user-slash"></i>
                    </div>
                </div>
                <span class="stat-card-value" id="statDeactivated">0</span>
            </div>
        </div>
        </div>

        {{-- Controls bar (Search, Filters & Actions) --}}
        <div class="controls-bar">
            <div class="controls-left">
                <div class="search-box-wrapper">
                    <input type="text" id="searchUsers" placeholder="Search by name, ID or email...">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <div class="filter-select-wrapper">
                    <select id="filterRole">
                        <option value="all">All Roles</option>
                        <option value="student">Student</option>
                        <option value="teacher">Faculty Member</option>
                        <option value="department">Department</option>
                    </select>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
            </div>
            <div class="controls-right-buttons">
                <button class="btn-add-student" id="addStudentBtn">
                    <i class="fa-solid fa-graduation-cap"></i> Add Student
                </button>
                <button class="btn-add-department" id="addDepartmentBtn">
                    <i class="fa-solid fa-building"></i> Add Department
                </button>
            </div>
        </div>

        {{-- Users Table Card --}}
        <div class="table-container card admin-table-card">
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>ID Number</th>
                        <th>Role</th>
                        <th>Academic Info</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th class="action-column" style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    {{-- Loaded dynamically via JavaScript --}}
                </tbody>
            </table>

            {{-- Empty state illustration --}}
            <div id="usersEmptyState" class="empty-state-container" style="display: none;">
                <i class="fa-solid fa-users-slash empty-state-icon"></i>
                <h4>No users found</h4>
                <p>Try adjusting your search criteria or filters.</p>
            </div>

            {{-- Pagination Footer --}}
            <div class="pagination-container" id="paginationContainer">
                <span class="pagination-info" id="paginationInfo">Showing 1 to 5 of 5 entries</span>
                <div class="pagination-buttons" id="paginationButtons">
                    {{-- Buttons added dynamically --}}
                </div>
            </div>
        </div>
    </div>

    {{-- Add Student Modal --}}
    <div class="modal-overlay" id="studentModal">
        <div class="modal-card eq-modal-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Register a new student account in the system</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeStudentModalBtn">&times;</button>
                <h3 class="modal-title-center">Add Student Account</h3>

                <form id="studentForm" action="{{ route('admin.users.student') }}" method="POST" class="new-modal-form" style="padding-top: 10px;">
                    @csrf
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group-flat">
                            <label>First Name</label>
                            <input type="text" id="studFirstName" name="first_name" class="form-control-flat" required placeholder="Gabriel">
                        </div>
                        <div class="form-group-flat">
                            <label>Last Name</label>
                            <input type="text" id="studLastName" name="last_name" class="form-control-flat" required placeholder="Fernandez">
                        </div>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group-flat">
                            <label>Student ID Number</label>
                            <input type="text" id="studIdNumber" name="id_number" class="form-control-flat" required placeholder="e.g., 20230123" pattern="\d{8}" title="Student ID must be exactly 8 digits." maxlength="8" inputmode="numeric">
                        </div>
                        <div class="form-group-flat">
                            <label>Year & Level</label>
                            <div class="flat-select-wrapper" style="width: 100%;">
                                <select id="studYearLevel" name="year_level" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year" selected>3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-flat">
                        <label>Department</label>
                        <div class="flat-select-wrapper" style="width: 100%;">
                            <select id="studDepartment" name="department" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                <option value="" disabled selected>Select Department</option>
                                @foreach ($departmentsList as $deptOption)
                                    <option value="{{ $deptOption->department_name }}">{{ $deptOption->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group-flat">
                        <label>Email Address</label>
                        <input type="email" id="studEmail" name="email" class="form-control-flat" required placeholder="gabriel.fernandez@example.com">
                    </div>

                    <div class="form-group-flat">
                        <label>Home Address</label>
                        <input type="text" id="studAddress" name="address" class="form-control-flat" required placeholder="123 University Ave, Tech City">
                    </div>

                    <div class="form-group-flat">
                        <label>Password</label>
                        <input type="password" id="studPassword" name="password" class="form-control-flat" required placeholder="••••••••" minlength="6">
                    </div>

                    <button type="submit" class="btn-submit-request" style="margin-top: 10px;">
                        Create Student Account
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Add Department Modal --}}
    <div class="modal-overlay" id="departmentModal">
        <div class="modal-card eq-modal-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Register a new department account in the system</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeDeptModalBtn">&times;</button>
                <h3 class="modal-title-center">Add Department Account</h3>

                <form id="departmentForm" action="{{ route('admin.users.department') }}" method="POST" class="new-modal-form" style="padding-top: 10px;">
                    @csrf
                    <div class="form-group-flat">
                        <label>Full Name <span style="color: #ef4444;">*</span></label>
                        <input type="text" id="deptFullName" name="full_name" class="form-control-flat" required placeholder="e.g., Dr. Jane Doe">
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group-flat">
                            <label>Employee ID <span style="color: #ef4444;">*</span></label>
                            <input type="text" id="deptEmployeeId" name="employee_id" class="form-control-flat" required placeholder="e.g., EMP-2026-001">
                        </div>
                        <div class="form-group-flat">
                            <label>Email Address <span style="color: #ef4444;">*</span></label>
                            <input type="email" id="deptEmail" name="email" class="form-control-flat" required placeholder="e.g., jane.doe@equiptrack.edu">
                        </div>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group-flat">
                            <label>Role <span style="color: #ef4444;">*</span></label>
                            <div class="flat-select-wrapper" style="width: 100%;">
                                <select id="deptRole" name="role" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                    <option value="Department Head" selected>Department Head</option>
                                    <option value="Department Staff">Department Staff</option>
                                    <option value="Department Admin">Department Admin</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group-flat">
                            <label>Department <span style="color: #ef4444;">*</span></label>
                            <div class="flat-select-wrapper" style="width: 100%;">
                                <select id="deptDepartmentId" name="department_id" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                    <option value="" disabled selected>Select Department</option>
                                    @foreach ($departmentsList as $dItem)
                                        <option value="{{ $dItem->department_id }}">
                                            {{ $dItem->department_name }}{{ $dItem->department_code ? ' (' . $dItem->department_code . ')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-flat">
                        <label>Password <span style="color: #ef4444;">*</span></label>
                        <input type="password" id="deptPassword" name="password" class="form-control-flat" required placeholder="••••••••" minlength="6">
                    </div>

                    <button type="submit" class="btn-submit-request" style="margin-top: 10px;">
                        Create Department Account
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- User Details Modal --}}
    <div class="modal-overlay" id="userDetailsModal">
        <div class="modal-card eq-modal-card" style="max-width: 800px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Detailed view of user account information</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeUserDetailsBtn">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;">User Details</h3>

                {{-- Requester Profile Block --}}
                <div class="modal-requester-profile">
                    <img src="" alt="User Avatar" class="modal-requester-avatar" id="modalUserAvatar">
                    <div class="modal-requester-meta">
                        <span class="modal-requester-name" id="modalUserName">John Doe</span>
                        <span class="modal-requester-details" id="modalUserRole">Student</span>
                    </div>
                </div>

                {{-- Main Fields Section --}}
                <div class="detail-main-content">
                    <div class="detail-left-side">
                        <div class="detail-img-container" style="position: relative;">
                            <img src="" alt="User Profile Image" id="modalUserProfileImg">
                            <button type="button" class="btn-upload-icon" id="adminUploadPicBtn" style="position: absolute; bottom: 8px; right: 8px; background: #385585; color: #fff; border: none; width: 34px; height: 34px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.3);" title="Upload / Change Profile Picture">
                                <i class="fa-solid fa-camera"></i>
                            </button>
                            <input type="file" id="adminProfilePicInput" style="display: none;" accept="image/*">
                        </div>
                        <div class="detail-form-group">
                            <label class="detail-form-label">Status</label>
                            <div class="status-badge-wrapper">
                                <span class="status-badge" id="modalStatusBadge">Active</span>
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
                                <label class="detail-form-label">Year Level</label>
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

                {{-- Address / Location Info Section --}}
                <div class="detail-form-group" style="margin-bottom: 16px;">
                    <label class="detail-form-label">Address</label>
                    <input type="text" id="modalAddress" class="detail-form-control" readonly>
                </div>

                {{-- Modal Actions Footer --}}
                <div class="modal-actions-footer" style="display: flex; gap: 12px; align-items: center;">
                    <button type="button" id="modalToggleStatusBtn" class="btn-modal-toggle-status" style="flex: 1; padding: 10px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: background 0.2s ease, transform 0.1s ease;">Deactivate Account</button>
                    <button type="button" class="btn-modal-close" style="flex: 1;" id="modalCloseDetailsBtn">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const CSRF_TOKEN = @json(csrf_token());
        const URL_TOGGLE = @json(route('admin.users.toggle'));
        const URL_DELETE = @json(route('admin.users.delete'));
        const URL_IMAGE = @json(route('admin.users.image'));

        // Helper: submit a hidden POST form (same full-page-submit UX as legacy)
        function postForm(url, fields, fileField) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = url;
            if (fileField) form.enctype = 'multipart/form-data';

            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = CSRF_TOKEN;
            form.appendChild(tokenInput);

            Object.entries(fields).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });

            if (fileField) form.appendChild(fileField);

            document.body.appendChild(form);
            form.submit();
        }

        // DOM Elements
        const searchInput = document.getElementById('searchUsers');
        const filterRole = document.getElementById('filterRole');
        const tableBody = document.getElementById('usersTableBody');
        const emptyState = document.getElementById('usersEmptyState');

        // Modals & Forms
        const studentModal = document.getElementById('studentModal');
        const addStudentBtn = document.getElementById('addStudentBtn');
        const closeStudentModalBtn = document.getElementById('closeStudentModalBtn');
        const studentForm = document.getElementById('studentForm');

        const departmentModal = document.getElementById('departmentModal');
        const addDepartmentBtn = document.getElementById('addDepartmentBtn');
        const closeDeptModalBtn = document.getElementById('closeDeptModalBtn');
        const departmentForm = document.getElementById('departmentForm');

        // User Details Modal DOM Elements
        const userDetailsModal = document.getElementById('userDetailsModal');
        const closeUserDetailsBtn = document.getElementById('closeUserDetailsBtn');
        const modalCloseDetailsBtn = document.getElementById('modalCloseDetailsBtn');
        const modalUserAvatar = document.getElementById('modalUserAvatar');
        const modalUserName = document.getElementById('modalUserName');
        const modalUserRole = document.getElementById('modalUserRole');
        const modalUserProfileImg = document.getElementById('modalUserProfileImg');
        const modalStatusBadge = document.getElementById('modalStatusBadge');
        const modalFullName = document.getElementById('modalFullName');
        const modalRole = document.getElementById('modalRole');
        const modalDepartment = document.getElementById('modalDepartment');
        const modalStudentId = document.getElementById('modalStudentId');
        const modalYearLevel = document.getElementById('modalYearLevel');
        const modalEmail = document.getElementById('modalEmail');
        const modalDateCreated = document.getElementById('modalDateCreated');
        const modalLastOnline = document.getElementById('modalLastOnline');
        const modalAddress = document.getElementById('modalAddress');

        // Pagination Elements
        const paginationContainer = document.getElementById('paginationContainer');
        const paginationInfo = document.getElementById('paginationInfo');
        const paginationButtons = document.getElementById('paginationButtons');

        // Initial users loaded directly from database tables
        let users = @json($dbUsers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        // Re-show validation/operation errors on the relevant modal
        @if ($errors->any())
            @php
                $isDeptError = $errors->hasAny(['full_name', 'employee_id', 'department_id', 'role']);
            @endphp
            @if ($isDeptError)
                departmentModal.classList.add('show');
            @else
                studentModal.classList.add('show');
            @endif
            showNotification('Validation Error', @json($errors->first()), 'error');
        @endif

        @if ($serverMsg)
            showNotification(
                @json($serverMsgType === 'success' ? 'Success' : 'Error'),
                @json($serverMsg),
                @json($serverMsgType)
            );
        @endif

        // Pagination state
        let currentPage = 1;
        const pageSize = 5;
        let filteredUsers = [];

        // Render table based on current page, search, filter
        function renderUsersTable() {
            const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
            const role = filterRole ? filterRole.value : 'all';

            filteredUsers = users.filter(user => {
                if (!user) return false;
                const firstName = user.first_name || '';
                const lastName = user.last_name || '';
                const fullName = `${firstName} ${lastName}`.trim().toLowerCase();
                const idNum = (user.id_number || '').toLowerCase();
                const email = (user.email || '').toLowerCase();
                const userRole = (user.role || '').toLowerCase();

                const matchesSearch = fullName.includes(query) ||
                                      idNum.includes(query) ||
                                      email.includes(query);
                const matchesRole = role === 'all' || userRole === role || (role === 'department' && user.db_type === 'department');
                return matchesSearch && matchesRole;
            });

            filteredUsers.sort((a, b) => {
                const valA = `${a.first_name || ''} ${a.last_name || ''}`.trim().toLowerCase();
                const valB = `${b.first_name || ''} ${b.last_name || ''}`.trim().toLowerCase();
                return valA.localeCompare(valB);
            });

            const totalEntries = filteredUsers.length;
            const totalPages = Math.ceil(totalEntries / pageSize);

            if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, totalEntries);
            const paginatedUsers = filteredUsers.slice(startIndex, endIndex);

            tableBody.innerHTML = '';

            if (paginatedUsers.length === 0) {
                emptyState.style.display = 'flex';
                document.querySelector('.table-container table').style.display = 'none';
                paginationContainer.style.display = 'none';
            } else {
                emptyState.style.display = 'none';
                document.querySelector('.table-container table').style.display = 'table';
                paginationContainer.style.display = 'flex';

                paginatedUsers.forEach(user => {
                    const tr = document.createElement('tr');
                    tr.className = 'admin-table-row';

                    tr.addEventListener('click', (e) => {
                        if (!e.target.closest('.action-buttons') && !e.target.closest('button')) {
                            openUserDetailsModal(user.id);
                        }
                    });

                    const isStudent = user.role.toLowerCase() === 'student';
                    const details = isStudent ? user.year_level : 'N/A';
                    let roleLabel = user.role.charAt(0).toUpperCase() + user.role.slice(1);
                    if (user.role.toLowerCase() === 'teacher') {
                        roleLabel = 'Faculty Member';
                    }
                    const statusClass = 'status-' + user.status.toLowerCase();
                    const toggleText = user.status.toLowerCase() === 'active' ? 'Deactivate' : 'Activate';
                    const fullName = user.last_name ? `${user.first_name} ${user.last_name}` : user.first_name;

                    tr.innerHTML = `
                        <td>
                            <div class="user-details-cell">
                                <span class="user-name-text">${escapeHTML(fullName)}</span>
                            </div>
                        </td>
                        <td><strong>${escapeHTML(user.id_number)}</strong></td>
                        <td><span style="font-weight: 500;">${roleLabel}</span></td>
                        <td>${escapeHTML(details)}</td>
                        <td>${escapeHTML(user.department || 'N/A')}</td>
                        <td><span class="status-badge ${statusClass}">${escapeHTML(user.status)}</span></td>
                        <td class="action-cell" style="text-align: center;">
                            <div class="action-buttons" style="justify-content: center; gap: 8px;">
                                <button class="btn-action-toggle" onclick="toggleUserStatus(${user.id})">${toggleText}</button>
                                <button class="btn-action-delete" onclick="deleteUser(${user.id})"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });

                paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalEntries} entries`;
                renderPaginationButtons(totalPages);
            }
            updateSummaryStats();
        }

        // Update Summary Stats Section Cards dynamically
        function updateSummaryStats() {
            const totalUsers = users.length;
            const students = users.filter(u => u.role.toLowerCase() === 'student').length;
            const faculty = users.filter(u => u.role.toLowerCase() === 'teacher' || u.role.toLowerCase() === 'faculty' || u.role.toLowerCase() === 'faculty member').length;
            const departments = users.filter(u => u.db_type === 'department' || u.role.toLowerCase().includes('department')).length;
            const active = users.filter(u => u.status.toLowerCase() === 'active').length;
            const deactivated = users.filter(u => u.status.toLowerCase() === 'inactive' || u.status.toLowerCase() === 'deactivated').length;

            const statTotalUsers = document.getElementById('statTotalUsers');
            const statStudents = document.getElementById('statStudents');
            const statFaculty = document.getElementById('statFaculty');
            const statDepartments = document.getElementById('statDepartments');
            const statActive = document.getElementById('statActive');
            const statDeactivated = document.getElementById('statDeactivated');

            if (statTotalUsers) statTotalUsers.textContent = totalUsers;
            if (statStudents) statStudents.textContent = students;
            if (statFaculty) statFaculty.textContent = faculty;
            if (statDepartments) statDepartments.textContent = departments;
            if (statActive) statActive.textContent = active;
            if (statDeactivated) statDeactivated.textContent = deactivated;
        }

        // Render pagination buttons dynamically
        function renderPaginationButtons(totalPages) {
            paginationButtons.innerHTML = '';

            const prevBtn = document.createElement('button');
            prevBtn.className = 'btn-page';
            prevBtn.innerHTML = '<i class="fa-solid fa-angle-left"></i>';
            prevBtn.disabled = currentPage === 1;
            prevBtn.addEventListener('click', () => {
                currentPage--;
                renderUsersTable();
            });
            paginationButtons.appendChild(prevBtn);

            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.className = `btn-page ${currentPage === i ? 'active' : ''}`;
                pageBtn.textContent = i;
                pageBtn.addEventListener('click', () => {
                    currentPage = i;
                    renderUsersTable();
                });
                paginationButtons.appendChild(pageBtn);
            }

            const nextBtn = document.createElement('button');
            nextBtn.className = 'btn-page';
            nextBtn.innerHTML = '<i class="fa-solid fa-angle-right"></i>';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.addEventListener('click', () => {
                currentPage++;
                renderUsersTable();
            });
            paginationButtons.appendChild(nextBtn);
        }

        // Toggle User Status — FIX vs legacy: now POSTs so it persists
        window.toggleUserStatus = function(id) {
            const user = users.find(u => u.id === id);
            if (!user) return;

            const newStatus = user.status.toLowerCase() === 'active' ? 'Deactivated' : 'Active';
            postForm(URL_TOGGLE, {
                target_user_id: user.id,
                target_user_type: user.db_type,
                new_status: newStatus,
            });
        };

        // Delete User Account
        window.deleteUser = function(id) {
            const user = users.find(u => u.id === id);
            if (!user) return;

            const fullName = user.last_name ? `${user.first_name} ${user.last_name}` : user.first_name;
            if (confirm(`Are you sure you want to permanently delete the account of ${fullName}?`)) {
                postForm(URL_DELETE, {
                    user_id: user.id,
                    user_type: user.db_type,
                });
            }
        };

        let activeModalUser = null;

        // Open User Details Modal
        window.openUserDetailsModal = function(id) {
            const user = users.find(u => u.id === id);
            if (!user) return;
            activeModalUser = user;

            const fullName = user.last_name ? `${user.first_name} ${user.last_name}` : user.first_name;
            let roleLabel = user.role.charAt(0).toUpperCase() + user.role.slice(1);
            if (user.role.toLowerCase() === 'teacher') {
                roleLabel = 'Faculty Member';
            }

            // Profile Picture: DB image or generated fallback avatar
            let avatarUrl = user.profile_image || user.avatar;

            if (!avatarUrl) {
                avatarUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=385585&color=fff&size=300&bold=true`;
            }

            const fallbackAvatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=385585&color=fff&size=300&bold=true`;
            modalUserAvatar.onerror = function() {
                this.onerror = null;
                this.src = fallbackAvatar;
            };
            modalUserProfileImg.onerror = function() {
                this.onerror = null;
                this.src = fallbackAvatar;
            };

            modalUserAvatar.src = avatarUrl;
            modalUserProfileImg.src = avatarUrl;
            modalUserName.textContent = fullName;
            modalUserRole.textContent = roleLabel;

            const statusClass = 'status-' + user.status.toLowerCase();
            modalStatusBadge.textContent = user.status;
            modalStatusBadge.className = 'status-badge ' + statusClass;

            modalFullName.value = fullName;
            modalStudentId.value = user.id_number || 'N/A';
            modalYearLevel.value = user.year_level || 'N/A';
            modalEmail.value = user.email || 'N/A';
            modalRole.value = roleLabel;

            if (user.role.toLowerCase() === 'student') {
                modalDepartment.value = user.department || 'N/A';
            } else if (user.role.toLowerCase() === 'department') {
                modalDepartment.value = user.department || fullName;
            } else {
                modalDepartment.value = user.department || 'N/A';
            }

            modalLastOnline.value = user.last_online || 'Offline / Never';
            modalDateCreated.value = user.created_at || 'Database Record';
            modalAddress.value = user.address || 'N/A';

            const modalToggle = document.getElementById('modalToggleStatusBtn');
            if (modalToggle) {
                modalToggle.textContent = user.status.toLowerCase() === 'active' ? 'Deactivate Account' : 'Activate Account';
            }

            userDetailsModal.classList.add('show');
        };

        // Details-modal toggle button (dead in legacy — wired to the same action as row buttons)
        document.getElementById('modalToggleStatusBtn').addEventListener('click', () => {
            if (activeModalUser) toggleUserStatus(activeModalUser.id);
        });

        // Admin Profile Picture Upload Event Listeners
        const adminUploadPicBtn = document.getElementById('adminUploadPicBtn');
        const adminProfilePicInput = document.getElementById('adminProfilePicInput');

        if (adminUploadPicBtn && adminProfilePicInput) {
            adminUploadPicBtn.addEventListener('click', () => {
                adminProfilePicInput.click();
            });

            adminProfilePicInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file && activeModalUser) {
                    const fileInput = e.target.cloneNode(true);
                    fileInput.name = 'admin_profile_img_file';
                    fileInput.style.display = 'none';

                    postForm(URL_IMAGE, {
                        target_user_id: activeModalUser.id,
                        target_user_type: activeModalUser.db_type,
                    }, fileInput);
                }
            });
        }

        // HTML Escaping Helper
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

        // Form Submissions & Validations (client-side checks kept from legacy;
        // the server re-validates everything independently)
        studentForm.addEventListener('submit', (e) => {
            const idNumber = document.getElementById('studIdNumber').value.trim();
            const email = document.getElementById('studEmail').value.trim();

            if (!/^\d{8}$/.test(idNumber)) {
                e.preventDefault();
                showNotification('Validation Error', 'Student ID must be exactly 8 digits.', 'error');
                return;
            }

            const exists = users.some(u => u.id_number.toLowerCase() === idNumber.toLowerCase() || u.email.toLowerCase() === email.toLowerCase());
            if (exists) {
                e.preventDefault();
                showNotification('Registration Error', 'A user with this ID number or email already exists.', 'error');
                return;
            }
        });

        departmentForm.addEventListener('submit', (e) => {
            const emailEl = document.getElementById('deptEmail');
            const empIdEl = document.getElementById('deptEmployeeId');

            const email = emailEl ? emailEl.value.trim() : '';
            const employeeId = empIdEl ? empIdEl.value.trim() : '';

            const exists = users.some(u =>
                (u.email && u.email.toLowerCase() === email.toLowerCase()) ||
                (u.id_number && u.id_number.toLowerCase() === employeeId.toLowerCase())
            );
            if (exists) {
                e.preventDefault();
                showNotification('Registration Error', 'A department account with this Email or Employee ID already exists.', 'error');
                return;
            }
        });

        // Listeners for inputs
        searchInput.addEventListener('input', () => {
            currentPage = 1;
            renderUsersTable();
        });

        filterRole.addEventListener('change', () => {
            currentPage = 1;
            renderUsersTable();
        });

        // Open/Close Modals
        addStudentBtn.addEventListener('click', () => {
            studentModal.classList.add('show');
        });

        closeStudentModalBtn.addEventListener('click', () => {
            studentModal.classList.remove('show');
            studentForm.reset();
        });

        addDepartmentBtn.addEventListener('click', () => {
            departmentModal.classList.add('show');
        });

        closeDeptModalBtn.addEventListener('click', () => {
            departmentModal.classList.remove('show');
            departmentForm.reset();
        });

        studentModal.addEventListener('click', (e) => {
            if (e.target === studentModal) {
                studentModal.classList.remove('show');
                studentForm.reset();
            }
        });

        departmentModal.addEventListener('click', (e) => {
            if (e.target === departmentModal) {
                departmentModal.classList.remove('show');
                departmentForm.reset();
            }
        });

        closeUserDetailsBtn.addEventListener('click', () => {
            userDetailsModal.classList.remove('show');
        });
        modalCloseDetailsBtn.addEventListener('click', () => {
            userDetailsModal.classList.remove('show');
        });
        userDetailsModal.addEventListener('click', (e) => {
            if (e.target === userDetailsModal) {
                userDetailsModal.classList.remove('show');
            }
        });

        // Initial render
        renderUsersTable();
    });
</script>
@endpush
