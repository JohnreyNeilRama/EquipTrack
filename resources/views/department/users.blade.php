@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('departments/css/users.css') }}">
<link rel="stylesheet" href="{{ asset('departments/css/user-details-modal.css') }}">
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
                    <button type="button" class="btn-modal-close" id="modalCloseDetailsBtn">Close</button>
                </div>
            </div>
        </div>
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
                        <td class="col-fullname">${escapeHTML(user.fullName)}</td>
                        <td class="col-id">${escapeHTML(user.idNumber)}</td>
                        <td><span class="user-type-badge ${badgeClass}">${escapeHTML(user.userType)}</span></td>
                        <td class="col-attainment">${escapeHTML(user.attainment)}</td>
                        <td><span class="status-badge ${statusClass}">${escapeHTML(user.status)}</span></td>
                        <td>
                            <button class="btn-action-view" onclick="openViewUserModal(${Number(user.id)})">
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

                viewUserModal.classList.add('show');
            };

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

                // Mark the last visible row so the table's footer line doesn't
                // double up with that row's bottom line (rows are hidden, not removed).
                rows.forEach(row => row.classList.remove('last-visible-row'));
                const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
                if (visibleRows.length) {
                    visibleRows[visibleRows.length - 1].classList.add('last-visible-row');
                }

                if (paginationInfo) {
                    paginationInfo.textContent = `Showing ${visibleCount > 0 ? 1 : 0} to ${visibleCount} of ${visibleCount} entries`;
                }

                if (visibleCount === 0) {
                    emptyStateContainer.style.display = 'flex';
                } else {
                    emptyStateContainer.style.display = 'none';
                }
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

            searchInput.addEventListener('input', filterTable);
            filterRoleSelect.addEventListener('change', filterTable);
            filterStatusSelect.addEventListener('change', filterTable);

            // Initial render, then keep it current.
            renderTable();
            setInterval(refreshUsers, REFRESH_MS);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') refreshUsers();
            });
        });
    
</script>
@endpush
