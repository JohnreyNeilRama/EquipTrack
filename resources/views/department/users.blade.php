@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('departments/css/users.css') }}">
@endpush

@section('content')
<!-- Page Header -->
        <div class="page-title-section" style="margin-top: 10px;">
            <h2>Users Management</h2>
            <p>Manage registered users and departments.</p>
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

            <div class="filter-select-item">
                <select id="filterDept">
                    <option value="all">All Categories</option>
                    <option value="ccs">College of Computer Studies (CCS)</option>
                    <option value="engineering">Engineering</option>
                    <option value="business">Business</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
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
                        <th style="width: 12%;">Date Registered</th>
                        <th style="width: 8%; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <!-- Dynamic rendering via JS -->
                </tbody>
            </table>

            <!-- Table Footer Pagination Matching Reference Image -->
            <div class="table-footer-pagination">
                <span class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                <div class="pagination-controls">
                    <button class="page-btn disabled"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="page-btn active">1</button>
                    <button class="page-btn disabled"><i class="fa-solid fa-chevron-right"></i></button>
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
        <div class="modal-card">
            <button class="modal-close" id="closeViewUserModalBtn">&times;</button>
            <h3 class="modal-title-center">User Details</h3>
            <p class="modal-subtitle-top">Information about registered user</p>
            
            <div class="view-details-card">
                <div class="view-detail-item">
                    <span class="label">Full Name</span>
                    <span class="val" id="viewUserName">-</span>
                </div>
                <div class="view-detail-item">
                    <span class="label">ID Number</span>
                    <span class="val" id="viewUserId">-</span>
                </div>
                <div class="view-detail-item">
                    <span class="label">User Type</span>
                    <span class="val" id="viewUserType">-</span>
                </div>
                <div class="view-detail-item">
                    <span class="label">Year Level / Attainment</span>
                    <span class="val" id="viewUserAttainment">-</span>
                </div>
                <div class="view-detail-item">
                    <span class="label">Account Status</span>
                    <span class="val" id="viewUserStatus">-</span>
                </div>
                <div class="view-detail-item">
                    <span class="label">Date Registered</span>
                    <span class="val" id="viewUserDate">-</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactivity Script -->
    

    <!-- Data & Table Script -->
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

        // Notifications bell
        const notifBtn = document.getElementById('notifBtn');
        if (notifBtn) {
            notifBtn.addEventListener('click', () => {
                alert('No new notifications.');
            });
        }
    

        document.addEventListener('DOMContentLoaded', () => {
            const tableBody = document.getElementById('requestsTableBody') || document.getElementById('usersTableBody');
            const searchInput = document.getElementById('searchUsers');
            const filterRoleSelect = document.getElementById('filterRole');
            const filterStatusSelect = document.getElementById('filterStatus');
            const filterDeptSelect = document.getElementById('filterDept');
            const emptyStateContainer = document.getElementById('emptyStateContainer');
            const paginationInfo = document.getElementById('paginationInfo');

            // Summary metrics
            const sumTotalUsers = document.getElementById('sumTotalUsers');
            const sumStudents = document.getElementById('sumStudents');
            const sumFaculty = document.getElementById('sumFaculty');
            const sumActive = document.getElementById('sumActive');

            // Modal elements
            const viewUserModal = document.getElementById('viewUserModal');
            const closeViewUserModalBtn = document.getElementById('closeViewUserModalBtn');

            // Storage Management
            let users = JSON.parse(localStorage.getItem('equip-track-table-users'));
            if (!users || !Array.isArray(users)) {
                users = [];
            } else {
                users = users.filter(u => !['Johnrey Neil Rama', 'Gabriel Fernandez', 'Michael John Silva', 'Jeffrey Gaviola'].includes(u.fullName));
            }
            localStorage.setItem('equip-track-table-users', JSON.stringify(users));

            function updateSummaryCards() {
                const totalCount = users.length;
                const studentsCount = users.filter(u => u.userType.toLowerCase().includes('student')).length;
                const facultyCount = users.filter(u => u.userType.toLowerCase().includes('faculty')).length;
                const activeCount = users.filter(u => u.status.toLowerCase() === 'active').length;

                sumTotalUsers.textContent = totalCount;
                sumStudents.textContent = studentsCount;
                sumFaculty.textContent = facultyCount;
                sumActive.textContent = activeCount;
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
                        <td class="col-fullname">${escapeHTML(user.fullName)}</td>
                        <td class="col-id">${escapeHTML(user.idNumber)}</td>
                        <td><span class="user-type-badge ${badgeClass}">${escapeHTML(user.userType)}</span></td>
                        <td class="col-attainment">${escapeHTML(user.attainment)}</td>
                        <td><span class="status-badge ${statusClass}">${escapeHTML(user.status)}</span></td>
                        <td class="col-date">${escapeHTML(user.dateRegistered)}</td>
                        <td style="text-align: center;">
                            <button class="btn-action-view" onclick="openViewUserModal('${user.idNumber}')">
                                <i class="fa-regular fa-clock" style="display:none;"></i>
                                <i class="fa-solid fa-arrow-up-right-from-square" style="display:none;"></i>
                                <i class="fa-solid fa-eye"></i> View
                            </button>
                        </td>
                    `;

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

            window.openViewUserModal = function(idNum) {
                const user = users.find(u => u.idNumber === idNum);
                if (user) {
                    document.getElementById('viewUserName').textContent = user.fullName;
                    document.getElementById('viewUserId').textContent = user.idNumber;
                    document.getElementById('viewUserType').textContent = user.userType;
                    document.getElementById('viewUserAttainment').textContent = user.attainment;
                    document.getElementById('viewUserStatus').textContent = user.status;
                    document.getElementById('viewUserDate').textContent = user.dateRegistered;
                    viewUserModal.classList.add('show');
                }
            };

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

                const rows = tableBody.querySelectorAll('tr');
                let visibleCount = 0;

                rows.forEach(row => {
                    const searchData = row.getAttribute('data-search');
                    const rowUserType = row.getAttribute('data-usertype');
                    const rowStatus = row.getAttribute('data-status');

                    const matchesSearch = searchData.includes(query);
                    const matchesRole = (selectedRole === 'all' || rowUserType.includes(selectedRole));
                    const matchesStatus = (selectedStatus === 'all' || rowStatus === selectedStatus);

                    if (matchesSearch && matchesRole && matchesStatus) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (paginationInfo) {
                    paginationInfo.textContent = `Showing ${visibleCount > 0 ? 1 : 0} to ${visibleCount} of ${visibleCount} entries`;
                }

                if (visibleCount === 0) {
                    emptyStateContainer.style.display = 'flex';
                } else {
                    emptyStateContainer.style.display = 'none';
                }
            }

            searchInput.addEventListener('input', filterTable);
            filterRoleSelect.addEventListener('change', filterTable);
            filterStatusSelect.addEventListener('change', filterTable);
            if (filterDeptSelect) filterDeptSelect.addEventListener('change', filterTable);

            // Initial render
            renderTable();
        });
    
</script>
@endpush
