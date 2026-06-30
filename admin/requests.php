<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Equipment Requests</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../ccs/userdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/adminequipment.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/adminrequests.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Check for saved theme preference immediately to prevent flash of light theme
        (function() {
            if (localStorage.getItem('dashboard-theme') === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>
    <style>
        /* Custom overrides to match the exact mockup screenshot */
        .requests-page-title {
            font-size: 28px;
            font-weight: 700;
            color: #385585; /* Specific blue for title */
            margin-bottom: 24px;
        }
        .dark-theme .requests-page-title {
            color: #60a5fa;
        }
        
        /* Actions container spacing */
        .action-cell {
            padding-right: 20px !important;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        /* Row clickable cursor for viewing details */
        .admin-table-row {
            cursor: pointer;
        }
        .admin-table-row:hover td {
            background-color: rgba(56, 85, 133, 0.03) !important;
        }
        
        /* Reject/View buttons styling */
        .btn-approve, .btn-reject, .btn-view-only {
            border: none;
            border-radius: 6px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            outline: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 32px;
        }

        .btn-approve {
            background-color: #3b5998;
            color: #ffffff;
        }
        .btn-approve:hover {
            background-color: #2f477a;
            transform: translateY(-1px);
        }

        .btn-reject {
            background-color: #ff0000;
            color: #ffffff;
        }
        .btn-reject:hover {
            background-color: #cc0000;
            transform: translateY(-1px);
        }

        .btn-view-only {
            background-color: rgba(79, 107, 156, 0.1);
            color: #4f6b9c;
        }
        .btn-view-only:hover {
            background-color: #4f6b9c;
            color: #ffffff;
            transform: translateY(-1px);
        }
        
        .dark-theme .btn-approve {
            background-color: #3b82f6;
        }
        .dark-theme .btn-approve:hover {
            background-color: #2563eb;
        }
        .dark-theme .btn-view-only {
            background-color: rgba(129, 140, 248, 0.1);
            color: #818cf8;
        }
        .dark-theme .btn-view-only:hover {
            background-color: #818cf8;
            color: #0f172a;
        }

        /* Modal Details Form styling */
        .detail-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .detail-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            text-align: left;
        }
        .detail-form-group.full-width {
            grid-column: span 2;
        }
        .detail-form-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .detail-form-control {
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--bg-color);
            color: var(--text-main);
            font-size: 14px;
            outline: none;
            width: 100%;
        }
        .detail-form-control[readonly] {
            cursor: default;
        }
        .detail-main-content {
            display: flex;
            gap: 24px;
            margin-bottom: 20px;
        }
        .detail-left-side {
            width: 180px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .detail-right-side {
            flex: 1;
        }
        .detail-img-container {
            width: 180px;
            height: 140px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            background-color: var(--bg-color);
        }
        .detail-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .textarea-control {
            resize: none;
            font-family: inherit;
        }
        .text-danger-control {
            border-color: rgba(239, 68, 68, 0.3);
            background-color: rgba(239, 68, 68, 0.03);
            color: #ef4444;
        }
        .dark-theme .text-danger-control {
            border-color: rgba(248, 113, 113, 0.3);
            background-color: rgba(248, 113, 113, 0.05);
            color: #f87171;
        }
        
        /* Adjust detail status badge align */
        .status-badge-wrapper {
            display: flex;
            justify-content: flex-start;
        }

        .no-requests-message {
            text-align: center;
            color: var(--text-muted);
            padding: 40px;
        }
        .no-requests-message i {
            display: block;
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 12px;
            opacity: 0.7;
        }
    </style>
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
            <a href="requests.php" class="nav-item active">
                <i class="fa-solid fa-clipboard-list"></i> <span>Requests</span>
            </a>
            <a href="users.php" class="nav-item">
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

        <!-- Requests Content Container -->
        <div class="requests-container">
            <!-- Header Section -->
            <div class="equipment-header-section">
                <h2>Requests</h2>
                <p>Review, approve, or reject student and faculty borrowing requests.</p>
            </div>

            <!-- Controls bar (Search & Filters) -->
            <div class="controls-bar">
                <div class="controls-left">
                    <div class="search-box-wrapper">
                        <input type="text" id="searchRequests" placeholder="Search by user or equipment...">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                    <div class="filter-select-wrapper">
                        <select id="filterStatus">
                            <option value="all">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- Requests Table Card -->
            <div class="table-container card admin-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Equipment</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="action-column">Action</th>
                        </tr>
                    </thead>
                    <tbody id="requestsTableBody">
                        <!-- Loaded dynamically via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Request Details Modal -->
    <div class="modal-overlay" id="detailsModal">
        <div class="modal-card eq-modal-card" style="max-width: 680px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Detailed view of user request</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeDetailsBtn">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;">Request Details</h3>
                
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
                            <img src="" alt="Equipment Image" id="modalEqImg">
                        </div>
                        <div class="detail-form-group">
                            <label class="detail-form-label">Status</label>
                            <div class="status-badge-wrapper">
                                <span class="status-badge" id="modalStatusBadge">Pending</span>
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
                                <input type="text" id="modalReqDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group">
                                <label class="detail-form-label">Borrow Date</label>
                                <input type="text" id="modalBorrowDate" class="detail-form-control" readonly>
                            </div>
                            <div class="detail-form-group full-width">
                                <label class="detail-form-label">Due Date</label>
                                <input type="text" id="modalDueDate" class="detail-form-control" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purpose & Notes Section -->
                <div class="detail-form-group" style="margin-bottom: 16px;">
                    <label class="detail-form-label">Purpose of Borrowing</label>
                    <textarea id="modalPurpose" class="detail-form-control textarea-control" rows="2" readonly></textarea>
                </div>
                <div class="detail-form-group" style="margin-bottom: 16px;">
                    <label class="detail-form-label">Additional Notes</label>
                    <textarea id="modalNotes" class="detail-form-control textarea-control" rows="2" readonly></textarea>
                </div>
                <div class="detail-form-group" id="modalReasonGroup" style="display: none; margin-bottom: 16px;">
                    <label class="detail-form-label text-danger">Reason for Rejection</label>
                    <textarea id="modalReason" class="detail-form-control textarea-control text-danger-control" rows="2" readonly></textarea>
                </div>

                <!-- Modal Actions Footer -->
                <div class="modal-actions-footer" id="modalActionsFooter">
                    <!-- Loaded dynamically based on status -->
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Reason Input Modal -->
    <div class="modal-overlay" id="rejectReasonModal">
        <div class="modal-card eq-modal-card" style="max-width: 480px;">
            <div class="modal-outer-header">
                <p class="modal-subtitle-top">Provide rejection feedback</p>
            </div>
            <div class="modal-inner-card">
                <button class="modal-close" id="closeRejectReasonBtn">&times;</button>
                <h3 class="modal-title-center" style="margin-bottom: 20px;">Reject Request</h3>
                
                <form id="rejectReasonForm">
                    <input type="hidden" id="rejectRequestId">
                    <div class="reject-reason-input-group">
                        <label class="detail-form-label text-danger" style="text-align: left;">Reason for Rejection</label>
                        <textarea id="rejectReasonInput" class="detail-form-control textarea-control" rows="4" placeholder="Please provide a brief explanation for rejecting this request..." required style="border-color: rgba(239, 68, 68, 0.3);"></textarea>
                    </div>
                    <div class="form-submit-container" style="margin-top: 24px;">
                        <button type="submit" class="btn-submit-reject">Reject Request</button>
                    </div>
                </form>
            </div>
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

    <!-- Interactivity Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Default seeding requests if not present in localStorage
            const defaultRequestsList = [
                {
                    id: 1,
                    user: "Gabriel F.",
                    role: "Student",
                    avatar: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Laptop Dell",
                    category: "Laptop",
                    date: "May 1",
                    fullDate: "May 1, 2026",
                    borrowDate: "May 2, 2026",
                    dueDate: "May 5, 2026",
                    status: "Pending",
                    purpose: "Class Presentation",
                    notes: "Need a high-performance laptop for my Software Engineering presentation.",
                    img: "https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 2,
                    user: "Anna Mae S.",
                    role: "Teacher",
                    avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Camera Canon",
                    category: "Camera",
                    date: "May 11",
                    fullDate: "May 11, 2026",
                    borrowDate: "May 12, 2026",
                    dueDate: "May 15, 2026",
                    status: "Pending",
                    purpose: "Field Research Documentation",
                    notes: "Will document plant samples in biology forestry campus lab.",
                    img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 3,
                    user: "Anna Mae S.",
                    role: "Teacher",
                    avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Camera Canon",
                    category: "Camera",
                    date: "May 11",
                    fullDate: "May 11, 2026",
                    borrowDate: "May 13, 2026",
                    dueDate: "May 16, 2026",
                    status: "Pending",
                    purpose: "Classroom Activity",
                    notes: "Needed for photography lighting demonstration in multimedia lab.",
                    img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 4,
                    user: "Anna Mae S.",
                    role: "Teacher",
                    avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Camera Canon",
                    category: "Camera",
                    date: "May 11",
                    fullDate: "May 11, 2026",
                    borrowDate: "May 14, 2026",
                    dueDate: "May 17, 2026",
                    status: "Pending",
                    purpose: "Event Documentation",
                    notes: "Documenting the school's intra-mural sports activities.",
                    img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 5,
                    user: "Anna Mae S.",
                    role: "Teacher",
                    avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Camera Canon",
                    category: "Camera",
                    date: "May 11",
                    fullDate: "May 11, 2026",
                    borrowDate: "May 15, 2026",
                    dueDate: "May 18, 2026",
                    status: "Pending",
                    purpose: "Class Activity",
                    notes: "Visual arts photography workshop session.",
                    img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 6,
                    user: "Anna Mae S.",
                    role: "Teacher",
                    avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Camera Canon",
                    category: "Camera",
                    date: "May 11",
                    fullDate: "May 11, 2026",
                    borrowDate: "May 16, 2026",
                    dueDate: "May 19, 2026",
                    status: "Pending",
                    purpose: "Research Project",
                    notes: "Gathering high resolution visuals for the regional science fair project.",
                    img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                }
            ];

            // LocalStorage loading
            let requests = JSON.parse(localStorage.getItem('equip-track-requests'));
            if (!requests) {
                requests = defaultRequestsList;
                localStorage.setItem('equip-track-requests', JSON.stringify(requests));
            }

            // DOM Elements
            const tableBody = document.getElementById('requestsTableBody');
            const searchInput = document.getElementById('searchRequests');
            const filterSelect = document.getElementById('filterStatus');

            // Modals
            const detailsModal = document.getElementById('detailsModal');
            const closeDetailsBtn = document.getElementById('closeDetailsBtn');
            const rejectReasonModal = document.getElementById('rejectReasonModal');
            const closeRejectReasonBtn = document.getElementById('closeRejectReasonBtn');
            const rejectReasonForm = document.getElementById('rejectReasonForm');
            const rejectRequestIdInput = document.getElementById('rejectRequestId');
            const rejectReasonInput = document.getElementById('rejectReasonInput');

            // Toast Elements
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastTitle = document.getElementById('toastTitle');
            const toastMsg = document.getElementById('toastMsg');

            // Toggle Profile Dropdown
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const themeToggleIcon = document.getElementById('themeToggleIcon');
            const userProfileDropdown = document.getElementById('userProfileDropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');

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

            if (userProfileDropdown && dropdownMenu) {
                userProfileDropdown.addEventListener('click', (e) => {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                });
                document.addEventListener('click', () => {
                    dropdownMenu.classList.remove('show');
                });
            }

            // Render Table function
            function renderTable() {
                const query = searchInput.value.toLowerCase().trim();
                const filter = filterSelect.value;
                tableBody.innerHTML = '';

                const filtered = requests.filter(req => {
                    const matchesSearch = req.user.toLowerCase().includes(query) || 
                                          req.equipment.toLowerCase().includes(query);
                    const matchesFilter = filter === 'all' || req.status.toLowerCase() === filter;
                    return matchesSearch && matchesFilter;
                });

                if (filtered.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="6" class="no-requests-message">
                                <i class="fa-solid fa-folder-open"></i>
                                No requests found matching your query.
                            </td>
                        </tr>
                    `;
                    return;
                }

                filtered.forEach(req => {
                    const tr = document.createElement('tr');
                    tr.className = 'admin-table-row';
                    
                    // Click row opens details modal (excluding clicking action buttons)
                    tr.addEventListener('click', (e) => {
                        if (!e.target.closest('.action-buttons') && !e.target.closest('button')) {
                            openDetailsModal(req.id);
                        }
                    });

                    // Action buttons cells
                    let actionHtml = '';
                    if (req.status.toLowerCase() === 'pending') {
                        actionHtml = `
                            <div class="action-buttons">
                                <button class="btn-approve" onclick="approveRequest(${req.id})">Approve</button>
                                <button class="btn-reject" onclick="triggerRejectReason(${req.id})">Reject</button>
                            </div>
                        `;
                    } else {
                        actionHtml = `
                            <button class="btn-view-only" onclick="openDetailsModal(${req.id})">
                                <i class="fa-solid fa-eye"></i> View
                            </button>
                        `;
                    }

                    // Format status text
                    const statusClass = 'status-' + req.status.toLowerCase();

                    tr.innerHTML = `
                        <td>
                            <div class="requester-info">
                                <span class="requester-name">${escapeHTML(req.user)}</span>
                            </div>
                        </td>
                        <td>${escapeHTML(req.role)}</td>
                        <td>${escapeHTML(req.equipment)}</td>
                        <td>${escapeHTML(req.date)}</td>
                        <td>
                            <span class="status-badge ${statusClass}">${req.status}</span>
                        </td>
                        <td class="action-cell">${actionHtml}</td>
                    `;
                    tableBody.appendChild(tr);
                });
            }

            // Toast helper
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

            // Approve Request function
            window.approveRequest = function(id) {
                const reqIndex = requests.findIndex(r => r.id === id);
                if (reqIndex > -1) {
                    const req = requests[reqIndex];
                    req.status = 'Approved';
                    req.rejectReason = '';
                    localStorage.setItem('equip-track-requests', JSON.stringify(requests));
                    showNotification('Request Approved', `Approved request for ${req.equipment} by ${req.user}`, 'success');
                    
                    // Close details modal if open
                    detailsModal.classList.remove('show');
                    
                    renderTable();
                    updateDashboardStats();
                }
            };

            // Trigger Rejection Reason modal
            window.triggerRejectReason = function(id) {
                rejectRequestIdInput.value = id;
                rejectReasonInput.value = '';
                rejectReasonModal.classList.add('show');
            };

            // Rejection reason form submission
            rejectReasonForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const id = parseInt(rejectRequestIdInput.value);
                const reason = rejectReasonInput.value.trim();

                const reqIndex = requests.findIndex(r => r.id === id);
                if (reqIndex > -1 && reason) {
                    const req = requests[reqIndex];
                    req.status = 'Rejected';
                    req.rejectReason = reason;
                    localStorage.setItem('equip-track-requests', JSON.stringify(requests));
                    
                    rejectReasonModal.classList.remove('show');
                    // Close details modal if open
                    detailsModal.classList.remove('show');

                    showNotification('Request Rejected', `Rejected request for ${req.equipment} by ${req.user}`, 'error');
                    renderTable();
                    updateDashboardStats();
                }
            });

            // Open Details Modal
            window.openDetailsModal = function(id) {
                const req = requests.find(r => r.id === id);
                if (!req) return;

                // Populate modal
                document.getElementById('modalUserAvatar').src = req.avatar || "https://ui-avatars.com/api/?name=" + encodeURIComponent(req.user);
                document.getElementById('modalUserName').textContent = req.user;
                document.getElementById('modalUserRole').textContent = req.role;

                document.getElementById('modalEqImg').src = req.img || "https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60";
                
                const statusBadge = document.getElementById('modalStatusBadge');
                statusBadge.textContent = req.status;
                statusBadge.className = 'status-badge status-' + req.status.toLowerCase();

                document.getElementById('modalEqName').value = req.equipment;
                document.getElementById('modalEqCategory').value = req.category;
                document.getElementById('modalReqDate').value = req.fullDate;
                document.getElementById('modalBorrowDate').value = req.borrowDate;
                document.getElementById('modalDueDate').value = req.dueDate;
                document.getElementById('modalPurpose').value = req.purpose;
                document.getElementById('modalNotes').value = req.notes || 'N/A';

                const reasonGroup = document.getElementById('modalReasonGroup');
                if (req.status.toLowerCase() === 'rejected') {
                    document.getElementById('modalReason').value = req.rejectReason || 'No reason provided.';
                    reasonGroup.style.display = 'block';
                } else {
                    reasonGroup.style.display = 'none';
                }

                // Populate action buttons in footer
                const footer = document.getElementById('modalActionsFooter');
                if (req.status.toLowerCase() === 'pending') {
                    footer.innerHTML = `
                        <button class="btn-modal-close" id="modalCancelBtn">Close</button>
                        <button class="btn-modal-reject-trigger" onclick="triggerRejectReason(${req.id})">Reject</button>
                        <button class="btn-modal-approve" onclick="approveRequest(${req.id})">Approve</button>
                    `;
                } else {
                    footer.innerHTML = `
                        <button class="btn-modal-close" style="width: 100%;" id="modalCancelBtn">Close</button>
                    `;
                }

                // Add close listener for modal close button
                document.getElementById('modalCancelBtn').addEventListener('click', () => {
                    detailsModal.classList.remove('show');
                });

                detailsModal.classList.add('show');
            };

            // Close modals functions
            closeDetailsBtn.addEventListener('click', () => {
                detailsModal.classList.remove('show');
            });
            detailsModal.addEventListener('click', (e) => {
                if (e.target === detailsModal) {
                    detailsModal.classList.remove('show');
                }
            });

            closeRejectReasonBtn.addEventListener('click', () => {
                rejectReasonModal.classList.remove('show');
            });
            rejectReasonModal.addEventListener('click', (e) => {
                if (e.target === rejectReasonModal) {
                    rejectReasonModal.classList.remove('show');
                }
            });

            // Helper to escape HTML values
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

            // Sync stats to dashboard in localStorage
            function updateDashboardStats() {
                const pendingCount = requests.filter(r => r.status.toLowerCase() === 'pending').length;
                localStorage.setItem('dashboard-pending-count', pendingCount);
            }

            // Event Listeners for search & filters
            searchInput.addEventListener('input', renderTable);
            filterSelect.addEventListener('change', renderTable);

            // Initial render
            renderTable();
            updateDashboardStats();
        });
    </script>
</body>
</html>
