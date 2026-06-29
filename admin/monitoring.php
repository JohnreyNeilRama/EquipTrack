<?php
// PHP page setup - static mockup prototype view
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EquipTrack - Equipment Monitoring</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo_only.png">
    <link rel="apple-touch-icon" href="../images/logo_only.png">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../ccs/userdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/adminequipment.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/adminusers.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../ccs/adminmonitoring.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            <a href="users.php" class="nav-item">
                <i class="fa-solid fa-users"></i> <span>Users</span>
            </a>
            
            <div class="sidebar-section-label">Monitoring</div>
            <a href="monitoring.php" class="nav-item active">
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
    <main class="main-content" style="min-width: 0;">
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

        <!-- Main container matching users-container exactly -->
        <div class="users-container" style="min-width: 0; max-width: 100%;">
            <!-- Header Title Block -->
            <div class="equipment-header-section">
                <h2>Equipment Monitoring</h2>
                <p>Monitor borrowed equipment, return requests, and overdue records.</p>
            </div>

            <!-- Summary Cards Section matching Users page exactly -->
            <div class="admin-section" style="margin-bottom: 8px; min-width: 0; width: 100%; overflow: hidden;">
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
            <div class="controls-bar">
                <div class="controls-left">
                    <!-- Search Bar -->
                    <div class="search-box-wrapper">
                        <input type="text" id="monitoringSearch" placeholder="Search by borrower, equipment, or ID...">
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
                            <option value="Return Pending">Return Pending</option>
                            <option value="Returned">Returned</option>
                        </select>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>

                    <!-- Category Filter Dropdown -->
                    <div class="filter-select-wrapper">
                        <select id="filterCategory">
                            <option value="all">All Categories</option>
                            <option value="Laptop">Laptop</option>
                            <option value="Projector">Projector</option>
                            <option value="Camera">Camera</option>
                            <option value="Laboratory Equipment">Laboratory Equipment</option>
                            <option value="Audio Equipment">Audio Equipment</option>
                        </select>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- Table Card matching Users page exactly -->
            <div class="table-container card admin-table-card">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Borrower</th>
                            <th>ID Number</th>
                            <th>Equipment</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th class="action-column" style="text-align: center;">Action</th>
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
    </main>

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
            // Seeding monitoring requests with comprehensive representation of all statuses
            const monitoringSeed = [
                {
                    id: 20,
                    user: "Gabriel Fernandez",
                    id_number: "20230123",
                    role: "Student",
                    avatar: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Laptop Dell XPS",
                    category: "Laptop",
                    date: "Jun 1",
                    fullDate: "June 1, 2026",
                    borrowDate: "June 1, 2026",
                    dueDate: "June 5, 2026",
                    status: "Borrowed",
                    purpose: "Software Engineering Class",
                    notes: "Group coding project in school library.",
                    img: "https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 21,
                    user: "Anna Mae Santos",
                    id_number: "T-00987",
                    role: "Teacher",
                    avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Projector Epson",
                    category: "Projector",
                    date: "Jun 2",
                    fullDate: "June 2, 2026",
                    borrowDate: "June 2, 2026",
                    dueDate: "June 6, 2026",
                    status: "Borrowed",
                    purpose: "Visual Arts Lecture",
                    notes: "Need for showing slideshow presentation in room 203.",
                    img: "https://images.unsplash.com/photo-1588696860356-0eaee7d7c67c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 22,
                    user: "Johnrey Neil Rama",
                    id_number: "20230456",
                    role: "Student",
                    avatar: "https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Camera Canon EOS",
                    category: "Camera",
                    date: "Jun 4",
                    fullDate: "June 4, 2026",
                    borrowDate: "June 4, 2026",
                    dueDate: "June 8, 2026",
                    status: "Return Pending",
                    purpose: "Photography Assignment",
                    notes: "Submitted photo essay to the professor.",
                    img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 23,
                    user: "Information Technology Department",
                    id_number: "D-IT-200",
                    role: "Department",
                    avatar: "https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Lenovo ThinkPad",
                    category: "Laptop",
                    date: "May 15",
                    fullDate: "May 15, 2026",
                    borrowDate: "May 15, 2026",
                    dueDate: "May 20, 2026",
                    status: "Overdue",
                    purpose: "IT Support Lab",
                    notes: "Urgent troubleshooting laptop setup.",
                    img: "https://images.unsplash.com/photo-1603302576837-37561b2e2302?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 24,
                    user: "Engineering Department",
                    id_number: "D-ENG-300",
                    role: "Department",
                    avatar: "https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Projector Epson",
                    category: "Projector",
                    date: "Jun 26",
                    fullDate: "June 26, 2026",
                    borrowDate: "June 26, 2026",
                    dueDate: "June 30, 2026",
                    status: "Returned",
                    purpose: "Department Meeting",
                    notes: "Annual research conference presentation.",
                    img: "https://images.unsplash.com/photo-1588696860356-0eaee7d7c67c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 25,
                    user: "Gabriel Fernandez",
                    id_number: "20230123",
                    role: "Student",
                    avatar: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Wireless Microphone Set",
                    category: "Audio Equipment",
                    date: "Jun 3",
                    fullDate: "June 3, 2026",
                    borrowDate: "June 3, 2026",
                    dueDate: "June 7, 2026",
                    status: "Borrowed",
                    purpose: "Debate Competition",
                    notes: "Need for auditorium microphone system.",
                    img: "https://images.unsplash.com/photo-1590602847861-f357a9332bbc?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 26,
                    user: "Anna Mae Santos",
                    id_number: "T-00987",
                    role: "Teacher",
                    avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Scientific Calculator",
                    category: "Others",
                    date: "Jun 10",
                    fullDate: "June 10, 2026",
                    borrowDate: "June 10, 2026",
                    dueDate: "June 14, 2026",
                    status: "Returned",
                    purpose: "Exam Session",
                    notes: "To be used for mathematics exam.",
                    img: "https://images.unsplash.com/photo-1587145820266-a5951ee6f620?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 27,
                    user: "Johnrey Neil Rama",
                    id_number: "20230456",
                    role: "Student",
                    avatar: "https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Laptop Dell XPS",
                    category: "Laptop",
                    date: "May 10",
                    fullDate: "May 10, 2026",
                    borrowDate: "May 10, 2026",
                    dueDate: "May 15, 2026",
                    status: "Overdue",
                    purpose: "Midterm Exam Preparation",
                    notes: "Borrowed for exam review.",
                    img: "https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 28,
                    user: "Information Technology Department",
                    id_number: "D-IT-200",
                    role: "Department",
                    avatar: "https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Wireless Microphone Set",
                    category: "Audio Equipment",
                    date: "Jun 25",
                    fullDate: "June 25, 2026",
                    borrowDate: "June 25, 2026",
                    dueDate: "June 29, 2026",
                    status: "Return Pending",
                    purpose: "Orientation Day",
                    notes: "Sound system testing and setup.",
                    img: "https://images.unsplash.com/photo-1590602847861-f357a9332bbc?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 29,
                    user: "N/A",
                    id_number: "N/A",
                    role: "N/A",
                    avatar: "https://images.unsplash.com/photo-1590602847861-f357a9332bbc?ixlib=rb-1.2.1&auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Wireless Microphone Set",
                    category: "Audio Equipment",
                    date: "N/A",
                    fullDate: "N/A",
                    borrowDate: "N/A",
                    dueDate: "N/A",
                    status: "Available",
                    purpose: "N/A",
                    notes: "In excellent working condition.",
                    img: "https://images.unsplash.com/photo-1590602847861-f357a9332bbc?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 30,
                    user: "N/A",
                    id_number: "N/A",
                    role: "N/A",
                    avatar: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Camera Canon EOS",
                    category: "Camera",
                    date: "N/A",
                    fullDate: "N/A",
                    borrowDate: "N/A",
                    dueDate: "N/A",
                    status: "Available",
                    purpose: "N/A",
                    notes: "Fully charged battery, ready to use.",
                    img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 31,
                    user: "Anna Mae Santos",
                    id_number: "T-00987",
                    role: "Teacher",
                    avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Scientific Calculator",
                    category: "Others",
                    date: "Jun 28",
                    fullDate: "June 28, 2026",
                    borrowDate: "June 30, 2026",
                    dueDate: "July 2, 2026",
                    status: "Reserved",
                    purpose: "Math Seminar",
                    notes: "Reserved for the upcoming national seminar.",
                    img: "https://images.unsplash.com/photo-1587145820266-a5951ee6f620?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                },
                {
                    id: 32,
                    user: "Information Technology Department",
                    id_number: "D-IT-200",
                    role: "Department",
                    avatar: "https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Lenovo ThinkPad",
                    category: "Laptop",
                    date: "N/A",
                    fullDate: "N/A",
                    borrowDate: "N/A",
                    dueDate: "N/A",
                    status: "Maintenance",
                    purpose: "N/A",
                    notes: "Keyboard replacement in progress.",
                    img: "https://images.unsplash.com/photo-1603302576837-37561b2e2302?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: ""
                }
            ];

            let requests = JSON.parse(localStorage.getItem('equip-track-requests'));
            const isOutdated = !requests || requests.some(r => r.user === "Science Department" || r.id_number === "2023-00123" || r.user === "IT Department" || r.user === "College of IT Department");
            if (isOutdated) {
                requests = monitoringSeed;
                localStorage.setItem('equip-track-requests', JSON.stringify(requests));
            } else {
                // Ensure all standard monitoring seed statuses are merged if not present
                const hasSeedStatus = requests.some(r => r.status === 'Available' || r.status === 'Reserved' || r.status === 'Maintenance');
                if (!hasSeedStatus) {
                    requests = [...requests, ...monitoringSeed.filter(s => s.id >= 29)];
                    localStorage.setItem('equip-track-requests', JSON.stringify(requests));
                }
            }

            // DOM elements selection
            const tableBody = document.getElementById('monitoringTableBody');
            const emptyState = document.getElementById('monitoringEmptyState');
            const searchInput = document.getElementById('monitoringSearch');
            const filterStatusSelect = document.getElementById('filterStatus');
            const filterCategorySelect = document.getElementById('filterCategory');

            const detailsModal = document.getElementById('detailsModal');
            const closeDetailsBtn = document.getElementById('closeDetailsBtn');
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastTitle = document.getElementById('toastTitle');
            const toastMsg = document.getElementById('toastMsg');

            // Pagination Elements
            const paginationContainer = document.getElementById('paginationContainer');
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationButtons = document.getElementById('paginationButtons');

            // Pagination state
            let currentPage = 1;
            const pageSize = 25;

            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const themeToggleIcon = document.getElementById('themeToggleIcon');
            const userProfileDropdown = document.getElementById('userProfileDropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');

            // Dark Mode toggle logic
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

            // User dropdown toggling
            if (userProfileDropdown && dropdownMenu) {
                userProfileDropdown.addEventListener('click', (e) => {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                });
                document.addEventListener('click', () => {
                    dropdownMenu.classList.remove('show');
                });
            }

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

            // Render stats cards values dynamically
            function updateSummaryCards() {
                const total = requests.length;
                const available = requests.filter(r => r.status === 'Available').length;
                const borrowed = requests.filter(r => r.status === 'Borrowed').length;
                const reserved = requests.filter(r => r.status === 'Reserved').length;
                const overdue = requests.filter(r => r.status === 'Overdue').length;
                const maintenance = requests.filter(r => r.status === 'Maintenance').length;

                document.getElementById('statTotalMonitored').textContent = total;
                document.getElementById('statAvailable').textContent = available;
                document.getElementById('statBorrowed').textContent = borrowed;
                document.getElementById('statReserved').textContent = reserved;
                document.getElementById('statOverdue').textContent = overdue;
                document.getElementById('statMaintenance').textContent = maintenance;
            }

            // Render Table function
            function renderTable() {
                const query = searchInput.value.toLowerCase().trim();
                const selectedStatus = filterStatusSelect.value;
                const selectedCategory = filterCategorySelect.value;

                tableBody.innerHTML = '';

                const filtered = requests.filter(req => {
                    // Search term filter
                    const matchesSearch = req.user.toLowerCase().includes(query) || 
                                          req.equipment.toLowerCase().includes(query) ||
                                          (req.id_number && req.id_number.toLowerCase().includes(query));

                    // Category filter
                    const matchesCategory = selectedCategory === 'all' || req.category.toLowerCase() === selectedCategory.toLowerCase();

                    // Status filter
                    const matchesStatus = selectedStatus === 'all' || req.status.toLowerCase() === selectedStatus.toLowerCase();

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
                                openDetailsModal(req.id);
                            }
                        });

                        // Action buttons cells
                        let actionHtml = '';
                        if (req.status === 'Return Pending') {
                            actionHtml = `<button class="btn-action-confirm" onclick="confirmReturn(${req.id}, event)"><i class="fa-solid fa-circle-check"></i> Return</button>`;
                        } else {
                            actionHtml = `<button class="btn-action-view" onclick="openDetailsModal(${req.id})"><i class="fa-solid fa-eye"></i> View</button>`;
                        }

                        // Format due date indicator if overdue
                        let dueDateStyle = '';
                        if (req.status === 'Overdue') {
                            dueDateStyle = 'style="color: #ef4444; font-weight: 600;"';
                        }

                        const statusClass = 'status-' + req.status.toLowerCase().replace(' ', '-');
                        const borrowDateText = req.borrowDate || 'N/A';
                        const dueDateText = req.dueDate || 'N/A';
                        const idNumberText = req.id_number || 'N/A';

                        tr.innerHTML = `
                            <td>
                                <div class="user-details-cell">
                                    <span class="user-name-text">${escapeHTML(req.user)}</span>
                                </div>
                            </td>
                            <td><strong>${escapeHTML(idNumberText)}</strong></td>
                            <td>${escapeHTML(req.equipment)}</td>
                            <td>${escapeHTML(borrowDateText)}</td>
                            <td ${dueDateStyle}>${escapeHTML(dueDateText)}</td>
                            <td>
                                <span class="status-badge ${statusClass}">${req.status}</span>
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
                    req.status = 'Returned';
                    
                    // Save to localStorage
                    localStorage.setItem('equip-track-requests', JSON.stringify(requests));
                    
                    // Update layout
                    showNotification('Return Confirmed', `Equipment ${req.equipment} returned successfully by ${req.user}`, 'success');
                    
                    // Close details modal if open
                    detailsModal.classList.remove('show');

                    renderTable();
                }
            };

            // Open Details View Modal
            window.openDetailsModal = function(id) {
                const req = requests.find(r => r.id === id);
                if (!req) return;

                // Set content values
                document.getElementById('modalBorrowerAvatar').src = req.avatar || "https://ui-avatars.com/api/?name=" + encodeURIComponent(req.user);
                document.getElementById('modalBorrowerName').textContent = req.user;
                document.getElementById('modalBorrowerRole').textContent = req.role;
                document.getElementById('modalBorrowerId').textContent = req.id_number || 'N/A';

                document.getElementById('modalEqImg').src = req.img || "https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60";
                
                const statusBadge = document.getElementById('modalStatusBadge');
                statusBadge.textContent = req.status;
                statusBadge.className = 'status-badge status-' + req.status.toLowerCase().replace(' ', '-');

                document.getElementById('modalEqName').value = req.equipment;
                document.getElementById('modalEqCategory').value = req.category;
                document.getElementById('modalBorrowDate').value = req.borrowDate || req.date + ', 2026';
                document.getElementById('modalDueDate').value = req.dueDate || 'N/A';
                document.getElementById('modalPurpose').value = req.purpose || 'N/A';
                document.getElementById('modalNotes').value = req.notes || 'N/A';

                // Setup footer buttons based on status
                const footer = document.getElementById('modalActionsFooter');
                if (req.status === 'Return Pending') {
                    footer.innerHTML = `
                        <button class="btn-modal-close" style="background-color: var(--border-color, #e2e8f0); color: var(--text-main); margin-right: 12px;" onclick="closeModal()">Close</button>
                        <button class="btn-action-confirm" style="height: 40px; padding: 0 20px;" onclick="confirmReturn(${req.id})"><i class="fa-solid fa-circle-check"></i> Confirm Return</button>
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

            // Initial renders
            renderTable();
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
</body>
</html>
