<?php
// PHP page setup - static mockup prototype view
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - User Management</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../ccs/userdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/adminequipment.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/adminusers.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Check for saved theme preference immediately to prevent flash of light theme
        (function() {
            if (localStorage.getItem('dashboard-theme') === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="../images/EquipTrack_logo.png" alt="EquipTrack Logo" class="sidebar-logo-img">
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-label">General</div>
            <a href="admindashboard.php" class="nav-item">
                <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
            </a>
            <a href="equipment.php" class="nav-item">
                <i class="fa-solid fa-toolbox"></i> <span>Equipment Management</span>
            </a>
            <a href="requests.php" class="nav-item">
                <i class="fa-solid fa-clipboard-list"></i> <span>Requests</span>
            </a>
            <a href="users.php" class="nav-item active">
                <i class="fa-solid fa-users"></i> <span>Users</span>
            </a>
            
            <div class="sidebar-section-label">Monitoring</div>
            <a href="monitoring.php" class="nav-item">
                <i class="fa-solid fa-desktop"></i> <span>Equipment Monitoring</span>
            </a>
            <a href="reports.php" class="nav-item">
                <i class="fa-solid fa-file-lines"></i> <span>Reports</span>
            </a>
            <a href="audit.php" class="nav-item">
                <i class="fa-solid fa-clipboard-check"></i> <span>Audit Trail</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Navbar -->
        <header class="top-navbar profile-navbar">
            <div class="navbar-right">
                <span class="navbar-divider"></span>
                <div class="icon-btn" id="themeToggleBtn">
                    <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                </div>
                <div class="icon-btn notification">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <span class="navbar-divider"></span>
                <div class="user-profile" id="userProfileDropdown">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&h=100&q=80" alt="Admin Avatar" class="avatar">
                    <span class="user-name">Admin</span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
                    
                    <!-- Dropdown Menu -->
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name">Admin</span>
                            <span class="header-email">admin@equiptrack.edu</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="admindashboard.php"><i class="fa-solid fa-sliders"></i> Settings</a>
                        <a href="../login.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="users-container">
            <!-- Header Section -->
            <div class="equipment-header-section">
                <h2>User Accounts</h2>
                <p>Monitor, search, and manage registered student and department accounts</p>
            </div>

            <!-- Controls bar (Search, Filters & Actions) -->
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

            <!-- Users Table Card -->
            <div class="table-container card admin-table-card">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>ID Number</th>
                            <th>Role</th>
                            <th>Details</th>
                            <th>Address / Location</th>
                            <th>Status</th>
                            <th class="action-column" style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <!-- Loaded dynamically via JavaScript -->
                    </tbody>
                </table>

                <!-- Empty state illustration -->
                <div id="usersEmptyState" class="empty-state-container" style="display: none;">
                    <i class="fa-solid fa-users-slash empty-state-icon"></i>
                    <h4>No users found</h4>
                    <p>Try adjusting your search criteria or filters.</p>
                </div>

                <!-- Pagination Footer -->
                <div class="pagination-container" id="paginationContainer">
                    <span class="pagination-info" id="paginationInfo">Showing 1 to 5 of 5 entries</span>
                    <div class="pagination-buttons" id="paginationButtons">
                        <!-- Buttons added dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Student Modal -->
    <div class="modal-overlay" id="studentModal">
        <div class="modal-card eq-modal-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Register a new student account in the system</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeStudentModalBtn">&times;</button>
                <h3 class="modal-title-center">Add Student Account</h3>
                
                <form id="studentForm" class="new-modal-form" style="padding-top: 10px;">
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group-flat">
                            <label>First Name</label>
                            <input type="text" id="studFirstName" class="form-control-flat" required placeholder="Gabriel">
                        </div>
                        <div class="form-group-flat">
                            <label>Last Name</label>
                            <input type="text" id="studLastName" class="form-control-flat" required placeholder="Fernandez">
                        </div>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group-flat">
                            <label>Student ID Number</label>
                            <input type="text" id="studIdNumber" class="form-control-flat" required placeholder="2023-00123">
                        </div>
                        <div class="form-group-flat">
                            <label>Year & Level</label>
                            <div class="flat-select-wrapper" style="width: 100%;">
                                <select id="studYearLevel" class="form-control-flat" required style="width: 100%; height: 42px; padding: 8px 14px; border-radius: 8px;">
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year" selected>3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-flat">
                        <label>Email Address</label>
                        <input type="email" id="studEmail" class="form-control-flat" required placeholder="gabriel.fernandez@example.com">
                    </div>

                    <div class="form-group-flat">
                        <label>Home Address</label>
                        <input type="text" id="studAddress" class="form-control-flat" required placeholder="123 University Ave, Tech City">
                    </div>

                    <div class="form-group-flat">
                        <label>Password</label>
                        <input type="password" id="studPassword" class="form-control-flat" required placeholder="••••••••" minlength="6">
                    </div>

                    <button type="submit" class="btn-submit-request" style="margin-top: 10px;">
                        Create Student Account
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal-overlay" id="departmentModal">
        <div class="modal-card eq-modal-card">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Register a new department/office account in the system</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeDeptModalBtn">&times;</button>
                <h3 class="modal-title-center">Add Department Account</h3>
                
                <form id="departmentForm" class="new-modal-form" style="padding-top: 10px;">
                    <div class="form-group-flat">
                        <label>Department Name</label>
                        <input type="text" id="deptName" class="form-control-flat" required placeholder="e.g., IT Department">
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group-flat">
                            <label>Department Code / ID</label>
                            <input type="text" id="deptIdNumber" class="form-control-flat" required placeholder="e.g., D-IT-200">
                        </div>
                        <div class="form-group-flat">
                            <label>Room / Office Location</label>
                            <input type="text" id="deptAddress" class="form-control-flat" required placeholder="e.g., Tech Building Room 302">
                        </div>
                    </div>

                    <div class="form-group-flat">
                        <label>Department Email</label>
                        <input type="email" id="deptEmail" class="form-control-flat" required placeholder="e.g., it.dept@equiptrack.edu">
                    </div>

                    <div class="form-group-flat">
                        <label>Password</label>
                        <input type="password" id="deptPassword" class="form-control-flat" required placeholder="••••••••" minlength="6">
                    </div>

                    <button type="submit" class="btn-submit-request" style="margin-top: 10px;">
                        Create Department Account
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal-overlay" id="userDetailsModal">
        <div class="modal-card eq-modal-card" style="max-width: 680px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Detailed view of user account information</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeUserDetailsBtn">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;">User Details</h3>
                
                <!-- Requester Profile Block -->
                <div class="modal-requester-profile">
                    <img src="" alt="User Avatar" class="modal-requester-avatar" id="modalUserAvatar">
                    <div class="modal-requester-meta">
                        <span class="modal-requester-name" id="modalUserName">John Doe</span>
                        <span class="modal-requester-details" id="modalUserRole">Student</span>
                    </div>
                </div>

                <!-- Main Fields Section -->
                <div class="detail-main-content">
                    <div class="detail-left-side">
                        <div class="detail-img-container">
                            <img src="" alt="User Profile Image" id="modalUserProfileImg">
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
                                <label class="detail-form-label">Role</label>
                                <input type="text" id="modalRole" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Department</label>
                                <input type="text" id="modalDepartment" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Student ID</label>
                                <input type="text" id="modalStudentId" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Username</label>
                                <input type="text" id="modalUsername" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Email</label>
                                <input type="text" id="modalEmail" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Date Created</label>
                                <input type="text" id="modalDateCreated" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Last Login</label>
                                <input type="text" id="modalLastLogin" class="detail-form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address / Location Info Section -->
                <div class="detail-form-group" style="margin-bottom: 16px;">
                    <label class="detail-form-label">Address / Location</label>
                    <input type="text" id="modalAddress" class="detail-form-control" readonly>
                </div>

                <!-- Modal Actions Footer -->
                <div class="modal-actions-footer">
                    <button class="btn-modal-close" style="width: 100%;" id="modalCloseDetailsBtn">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Toast Notification -->
    <div class="toast-notification" id="toast">
        <div class="toast-content">
            <i class="fa-solid fa-circle-check toast-icon" id="toastIcon"></i>
            <div class="toast-message">
                <span class="toast-title" id="toastTitle">Success</span>
                <span class="toast-desc" id="toastMsg">Action processed successfully!</span>
            </div>
        </div>
    </div>

    <!-- JavaScript logic -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // DOM Elements
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const themeToggleIcon = document.getElementById('themeToggleIcon');
            const userProfileDropdown = document.getElementById('userProfileDropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastTitle = document.getElementById('toastTitle');
            const toastMsg = document.getElementById('toastMsg');

            // Search, Filter & Sort inputs
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
            const modalUsername = document.getElementById('modalUsername');
            const modalEmail = document.getElementById('modalEmail');
            const modalDateCreated = document.getElementById('modalDateCreated');
            const modalLastLogin = document.getElementById('modalLastLogin');
            const modalAddress = document.getElementById('modalAddress');

            // Pagination Elements
            const paginationContainer = document.getElementById('paginationContainer');
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationButtons = document.getElementById('paginationButtons');

            // Initial defaults for user list in local storage
            const defaultUsers = [
                {
                    id: 1,
                    first_name: "Gabriel",
                    last_name: "Fernandez",
                    id_number: "2023-00123",
                    role: "student",
                    year_level: "3rd Year",
                    email: "gabriel.fernandez@example.com",
                    address: "123 University Ave, Tech City",
                    status: "Active",
                    username: "gfernandez",
                    created_at: "May 10, 2026",
                    last_login: "June 17, 2026, 10:15 AM"
                },
                {
                    id: 2,
                    first_name: "Johnrey Neil",
                    last_name: "Rama",
                    id_number: "2023-00456",
                    role: "student",
                    year_level: "4th Year",
                    email: "johnrey.rama@example.com",
                    address: "456 College Lane, Tech City",
                    status: "Active",
                    username: "johnrey.rama",
                    created_at: "May 12, 2026",
                    last_login: "June 16, 2026, 02:45 PM"
                },
                {
                    id: 3,
                    first_name: "IT",
                    last_name: "Department",
                    id_number: "D-IT-200",
                    role: "department",
                    year_level: "N/A",
                    email: "it.dept@equiptrack.edu",
                    address: "Tech Building Room 302",
                    status: "Active",
                    username: "it.dept",
                    created_at: "May 01, 2026",
                    last_login: "June 17, 2026, 08:30 AM"
                },
                {
                    id: 4,
                    first_name: "Science",
                    last_name: "Department",
                    id_number: "D-SCI-300",
                    role: "department",
                    year_level: "N/A",
                    email: "science.dept@equiptrack.edu",
                    address: "Science Hall Room 204",
                    status: "Active",
                    username: "science.dept",
                    created_at: "May 02, 2026",
                    last_login: "June 15, 2026, 11:20 AM"
                },
                {
                    id: 5,
                    first_name: "Anna Mae",
                    last_name: "S.",
                    id_number: "T-2021-001",
                    role: "teacher",
                    year_level: "N/A",
                    email: "anna.mae@example.com",
                    address: "Science Hall Room 102",
                    status: "Active",
                    username: "anna.mae",
                    created_at: "May 15, 2026",
                    last_login: "June 17, 2026, 09:10 AM"
                }
            ];

            let users = JSON.parse(localStorage.getItem('equip-track-users'));
            if (!users) {
                users = defaultUsers;
                localStorage.setItem('equip-track-users', JSON.stringify(users));
            }

            // Pagination state
            let currentPage = 1;
            const pageSize = 5;
            let filteredUsers = [];

            // Display Toast Notifications
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

            // Render table based on current page, search, filter and sort
            function renderUsersTable() {
                const query = searchInput.value.toLowerCase().trim();
                const role = filterRole.value;

                // 1. Filter
                filteredUsers = users.filter(user => {
                    const fullName = `${user.first_name} ${user.last_name}`.toLowerCase();
                    const matchesSearch = fullName.includes(query) || 
                                          user.id_number.toLowerCase().includes(query) || 
                                          user.email.toLowerCase().includes(query);
                    const matchesRole = role === 'all' || user.role.toLowerCase() === role;
                    return matchesSearch && matchesRole;
                });

                // 2. Sort (Default by Name A-Z)
                filteredUsers.sort((a, b) => {
                    const valA = `${a.first_name} ${a.last_name}`.toLowerCase();
                    const valB = `${b.first_name} ${b.last_name}`.toLowerCase();
                    return valA.localeCompare(valB);
                });

                // 3. Paginate
                const totalEntries = filteredUsers.length;
                const totalPages = Math.ceil(totalEntries / pageSize);
                
                // Adjust current page if it is out of range after filtering
                if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

                const startIndex = (currentPage - 1) * pageSize;
                const endIndex = Math.min(startIndex + pageSize, totalEntries);
                const paginatedUsers = filteredUsers.slice(startIndex, endIndex);

                // Render Table rows
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
                        
                        // Row click opens the User Details modal
                        tr.addEventListener('click', (e) => {
                            if (!e.target.closest('.action-buttons') && !e.target.closest('button')) {
                                openUserDetailsModal(user.id);
                            }
                        });

                        const isStudent = user.role.toLowerCase() === 'student';
                        const details = isStudent ? user.year_level : 'N/A';
                        const roleLabel = user.role.charAt(0).toUpperCase() + user.role.slice(1);
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
                            <td>${escapeHTML(user.address)}</td>
                            <td><span class="status-badge ${statusClass}">${user.status}</span></td>
                            <td class="action-cell" style="text-align: center;">
                                <div class="action-buttons" style="justify-content: center; gap: 8px;">
                                    <button class="btn-action-toggle" onclick="toggleUserStatus(${user.id})">${toggleText}</button>
                                    <button class="btn-action-delete" onclick="deleteUser(${user.id})"><i class="fa-regular fa-trash-can"></i></button>
                                </div>
                            </td>
                        `;
                        tableBody.appendChild(tr);
                    });

                    // Update Pagination UI
                    paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalEntries} entries`;
                    renderPaginationButtons(totalPages);
                }
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
                    renderUsersTable();
                });
                paginationButtons.appendChild(prevBtn);

                // Page number buttons
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

                // Next button
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

            // Toggle User Status
            window.toggleUserStatus = function(id) {
                const user = users.find(u => u.id === id);
                if (!user) return;

                const originalStatus = user.status;
                user.status = originalStatus.toLowerCase() === 'active' ? 'Inactive' : 'Active';
                localStorage.setItem('equip-track-users', JSON.stringify(users));
                
                showNotification(
                    user.status === 'Active' ? 'Activated Account' : 'Deactivated Account',
                    `The account for ${user.first_name} ${user.last_name} has been ${user.status.toLowerCase()}.`,
                    'success'
                );
                renderUsersTable();
            };

            // Delete User Account
            window.deleteUser = function(id) {
                const user = users.find(u => u.id === id);
                if (!user) return;

                if (confirm(`Are you sure you want to permanently delete the account of ${user.first_name} ${user.last_name}?`)) {
                    users = users.filter(u => u.id !== id);
                    localStorage.setItem('equip-track-users', JSON.stringify(users));
                    showNotification('User Deleted', `Successfully removed account from the system.`, 'success');
                    renderUsersTable();
                }
            };

            // Open User Details Modal
            window.openUserDetailsModal = function(id) {
                const user = users.find(u => u.id === id);
                if (!user) return;

                const fullName = user.last_name ? `${user.first_name} ${user.last_name}` : user.first_name;
                const roleLabel = user.role.charAt(0).toUpperCase() + user.role.slice(1);
                
                const avatarUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=random&size=200`;
                modalUserAvatar.src = avatarUrl;
                modalUserProfileImg.src = avatarUrl;
                modalUserName.textContent = fullName;
                modalUserRole.textContent = roleLabel;

                // Status Badge
                const statusClass = 'status-' + user.status.toLowerCase();
                modalStatusBadge.textContent = user.status;
                modalStatusBadge.className = 'status-badge ' + statusClass;

                // Form Fields
                modalFullName.value = fullName;
                modalRole.value = roleLabel;
                
                if (user.role.toLowerCase() === 'student') {
                    modalDepartment.value = 'N/A';
                    modalStudentId.value = user.id_number;
                } else if (user.role.toLowerCase() === 'department') {
                    modalDepartment.value = fullName;
                    modalStudentId.value = 'N/A';
                } else {
                    modalDepartment.value = 'N/A';
                    modalStudentId.value = 'N/A';
                }

                modalUsername.value = user.username || user.email.split('@')[0];
                modalEmail.value = user.email;
                modalDateCreated.value = user.created_at || 'June 1, 2026';
                modalLastLogin.value = user.last_login || 'June 17, 2026, 10:30 AM';
                modalAddress.value = user.address || 'N/A';

                userDetailsModal.classList.add('show');
            };

            // HTML Escaping Helper
            function escapeHTML(str) {
                if (!str) return '';
                return str.replace(/[&<>'"]/g, 
                    tag => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        "'": '&#39;',
                        '"': '&quot;'
                    }[tag] || tag)
                );
            }

            // Dark Mode toggler click event
            if (themeToggleBtn && themeToggleIcon) {
                if (document.documentElement.classList.contains('dark-theme')) {
                    themeToggleIcon.className = 'fa-solid fa-sun';
                } else {
                    themeToggleIcon.className = 'fa-solid fa-moon';
                }

                themeToggleBtn.addEventListener('click', () => {
                    const isDark = document.documentElement.classList.toggle('dark-theme');
                    if (isDark) {
                        themeToggleIcon.className = 'fa-solid fa-sun';
                        localStorage.setItem('dashboard-theme', 'dark');
                    } else {
                        themeToggleIcon.className = 'fa-solid fa-moon';
                        localStorage.setItem('dashboard-theme', 'light');
                    }
                });
            }

            // User dropdown toggler click event
            if (userProfileDropdown && dropdownMenu) {
                userProfileDropdown.addEventListener('click', (e) => {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                });

                document.addEventListener('click', () => {
                    dropdownMenu.classList.remove('show');
                });
            }

            // Form Submissions & Validations
            // Add Student Submit Handler
            studentForm.addEventListener('submit', (e) => {
                e.preventDefault();

                const firstName = document.getElementById('studFirstName').value.trim();
                const lastName = document.getElementById('studLastName').value.trim();
                const idNumber = document.getElementById('studIdNumber').value.trim();
                const yearLevel = document.getElementById('studYearLevel').value;
                const email = document.getElementById('studEmail').value.trim();
                const address = document.getElementById('studAddress').value.trim();
                const password = document.getElementById('studPassword').value;

                // Validate if email or ID already exists
                const exists = users.some(u => u.id_number.toLowerCase() === idNumber.toLowerCase() || u.email.toLowerCase() === email.toLowerCase());
                if (exists) {
                    showNotification('Registration Error', 'A user with this ID number or email already exists.', 'error');
                    return;
                }

                const newStudent = {
                    id: Date.now(),
                    first_name: firstName,
                    last_name: lastName,
                    id_number: idNumber,
                    role: 'student',
                    year_level: yearLevel,
                    email: email,
                    address: address,
                    status: 'Active',
                    username: email.split('@')[0],
                    created_at: new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }),
                    last_login: 'Never'
                };

                users.push(newStudent);
                localStorage.setItem('equip-track-users', JSON.stringify(users));
                
                showNotification('Account Created', `Successfully registered student account for ${firstName} ${lastName}.`, 'success');
                
                studentForm.reset();
                studentModal.classList.remove('show');
                renderUsersTable();
            });

            // Add Department Submit Handler
            departmentForm.addEventListener('submit', (e) => {
                e.preventDefault();

                const name = document.getElementById('deptName').value.trim();
                const idNumber = document.getElementById('deptIdNumber').value.trim();
                const address = document.getElementById('deptAddress').value.trim();
                const email = document.getElementById('deptEmail').value.trim();
                const password = document.getElementById('deptPassword').value;

                // Validate if email or ID already exists
                const exists = users.some(u => u.id_number.toLowerCase() === idNumber.toLowerCase() || u.email.toLowerCase() === email.toLowerCase());
                if (exists) {
                    showNotification('Registration Error', 'A department with this Code or Email already exists.', 'error');
                    return;
                }

                const newDept = {
                    id: Date.now(),
                    first_name: name,
                    last_name: '',
                    id_number: idNumber,
                    role: 'department',
                    year_level: 'N/A',
                    email: email,
                    address: address,
                    status: 'Active',
                    username: email.split('@')[0],
                    created_at: new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }),
                    last_login: 'Never'
                };

                users.push(newDept);
                localStorage.setItem('equip-track-users', JSON.stringify(users));

                showNotification('Account Created', `Successfully registered department account for ${name}.`, 'success');

                departmentForm.reset();
                departmentModal.classList.remove('show');
                renderUsersTable();
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

            // Close modals when clicking backdrop
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

            // Close User Details Modal listeners
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
</body>
</html>
