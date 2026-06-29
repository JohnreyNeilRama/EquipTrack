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
                    <!-- Loaded dynamically via JavaScript -->
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
                            <option value="Laptop">Laptop</option>
                            <option value="Projector">Projector</option>
                            <option value="Camera">Camera</option>
                            <option value="Laboratory Equipment">Laboratory Equipment</option>
                            <option value="Audio Equipment">Audio Equipment</option>
                            <option value="Others">Others</option>
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
            // Seeding monitoring requests with comprehensive representation of all statuses and departments
            const monitoringSeed = [
                // Information Technology Department (CCS)
                {
                    id: 20,
                    user: "Johnrey Neil Rama",
                    id_number: "20230456",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Johnrey+Neil+Rama&background=random",
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
                    rejectReason: "",
                    department: "Information Technology Department"
                },
                {
                    id: 23,
                    user: "Gabriel Fernandez",
                    id_number: "20230123",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Gabriel+Fernandez&background=random",
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
                    rejectReason: "",
                    department: "Information Technology Department"
                },
                {
                    id: 29,
                    user: "—",
                    id_number: "—",
                    role: "—",
                    avatar: "",
                    equipment: "Wireless Microphone Set",
                    category: "Audio Equipment",
                    date: "—",
                    fullDate: "—",
                    borrowDate: "—",
                    dueDate: "—",
                    status: "Available",
                    purpose: "—",
                    notes: "In excellent working condition.",
                    img: "https://images.unsplash.com/photo-1590602847861-f357a9332bbc?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Information Technology Department"
                },
                {
                    id: 31,
                    user: "Jeffrey Gaviola",
                    id_number: "20230789",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Jeffrey+Gaviola&background=random",
                    equipment: "Projector Epson",
                    category: "Projector",
                    date: "Jun 28",
                    fullDate: "June 28, 2026",
                    borrowDate: "June 28, 2026",
                    dueDate: "July 2, 2026",
                    status: "Reserved",
                    purpose: "Math Seminar",
                    notes: "Reserved for the upcoming national seminar.",
                    img: "https://images.unsplash.com/photo-1588696860356-0eaee7d7c67c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Information Technology Department"
                },
                {
                    id: 32,
                    user: "—",
                    id_number: "—",
                    role: "—",
                    avatar: "",
                    equipment: "Camera Canon EOS",
                    category: "Camera",
                    date: "—",
                    fullDate: "—",
                    borrowDate: "—",
                    dueDate: "—",
                    status: "Maintenance",
                    purpose: "—",
                    notes: "Lens cleaning and sensor check in progress.",
                    img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Information Technology Department"
                },

                // Education Department (CTE)
                {
                    id: 101,
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
                    status: "Borrowed",
                    purpose: "Exam Session",
                    notes: "To be used for mathematics exam.",
                    img: "https://images.unsplash.com/photo-1587145820266-a5951ee6f620?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Education Department"
                },
                {
                    id: 102,
                    user: "Anna Mae Santos",
                    id_number: "T-00987",
                    role: "Teacher",
                    avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Epson Projector",
                    category: "Projector",
                    date: "May 20",
                    fullDate: "May 20, 2026",
                    borrowDate: "May 20, 2026",
                    dueDate: "May 25, 2026",
                    status: "Overdue",
                    purpose: "Audio-visual session",
                    notes: "History class slides.",
                    img: "https://images.unsplash.com/photo-1588696860356-0eaee7d7c67c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Education Department"
                },
                {
                    id: 103,
                    user: "—",
                    id_number: "—",
                    role: "—",
                    avatar: "",
                    equipment: "HP Laptop",
                    category: "Laptop",
                    date: "—",
                    fullDate: "—",
                    borrowDate: "—",
                    dueDate: "—",
                    status: "Available",
                    purpose: "—",
                    notes: "Checked and cleaned.",
                    img: "https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Education Department"
                },
                {
                    id: 104,
                    user: "Jeffrey Gaviola",
                    id_number: "20230789",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Jeffrey+Gaviola&background=random",
                    equipment: "iPad Pro",
                    category: "Others",
                    date: "Jun 25",
                    fullDate: "June 25, 2026",
                    borrowDate: "June 25, 2026",
                    dueDate: "July 03, 2026",
                    status: "Reserved",
                    purpose: "Presentation",
                    notes: "Art history demonstration.",
                    img: "https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Education Department"
                },
                {
                    id: 105,
                    user: "—",
                    id_number: "—",
                    role: "—",
                    avatar: "",
                    equipment: "Smart TV",
                    category: "Others",
                    date: "—",
                    fullDate: "—",
                    borrowDate: "—",
                    dueDate: "—",
                    status: "Maintenance",
                    purpose: "—",
                    notes: "Screen flickering issue.",
                    img: "https://images.unsplash.com/photo-1593305841991-05c297ba4575?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Education Department"
                },

                // Engineering Department (COE)
                {
                    id: 201,
                    user: "Johnrey Neil Rama",
                    id_number: "20230456",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Johnrey+Neil+Rama&background=random",
                    equipment: "Digital Multimeter",
                    category: "Laboratory Equipment",
                    date: "Jun 2",
                    fullDate: "June 2, 2026",
                    borrowDate: "June 2, 2026",
                    dueDate: "June 8, 2026",
                    status: "Borrowed",
                    purpose: "Circuit Lab",
                    notes: "Basic diagnostics lab class.",
                    img: "https://images.unsplash.com/photo-1581092160607-ee22621dd758?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Engineering Department"
                },
                {
                    id: 202,
                    user: "Gabriel Fernandez",
                    id_number: "20230123",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Gabriel+Fernandez&background=random",
                    equipment: "Oscilloscope",
                    category: "Laboratory Equipment",
                    date: "May 10",
                    fullDate: "May 10, 2026",
                    borrowDate: "May 10, 2026",
                    dueDate: "May 15, 2026",
                    status: "Overdue",
                    purpose: "Signal Analysis",
                    notes: "Testing frequency response.",
                    img: "https://images.unsplash.com/photo-1517420712762-15e1f9708ab1?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Engineering Department"
                },
                {
                    id: 203,
                    user: "—",
                    id_number: "—",
                    role: "—",
                    avatar: "",
                    equipment: "Engineering Workstation",
                    category: "Laptop",
                    date: "—",
                    fullDate: "—",
                    borrowDate: "—",
                    dueDate: "—",
                    status: "Available",
                    purpose: "—",
                    notes: "High performance desktop.",
                    img: "https://images.unsplash.com/photo-1593642632823-8f785ba67e45?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Engineering Department"
                },
                {
                    id: 204,
                    user: "Jeffrey Gaviola",
                    id_number: "20230789",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Jeffrey+Gaviola&background=random",
                    equipment: "Surveying Transit",
                    category: "Laboratory Equipment",
                    date: "Jun 27",
                    fullDate: "June 27, 2026",
                    borrowDate: "June 27, 2026",
                    dueDate: "July 04, 2026",
                    status: "Reserved",
                    purpose: "Civil Surveying",
                    notes: "Field measurements training.",
                    img: "https://images.unsplash.com/photo-1504307651254-35680f356dfd?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Engineering Department"
                },
                {
                    id: 205,
                    user: "—",
                    id_number: "—",
                    role: "—",
                    avatar: "",
                    equipment: "Soldering Station",
                    category: "Laboratory Equipment",
                    date: "—",
                    fullDate: "—",
                    borrowDate: "—",
                    dueDate: "—",
                    status: "Maintenance",
                    purpose: "—",
                    notes: "Heating element replacement.",
                    img: "https://images.unsplash.com/photo-1558137623-ce93397657bf?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Engineering Department"
                },

                // Customs Administration Department (CCA)
                {
                    id: 301,
                    user: "Gabriel Fernandez",
                    id_number: "20230123",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Gabriel+Fernandez&background=random",
                    equipment: "Barcode Scanner",
                    category: "Others",
                    date: "Jun 12",
                    fullDate: "June 12, 2026",
                    borrowDate: "June 12, 2026",
                    dueDate: "June 16, 2026",
                    status: "Borrowed",
                    purpose: "Logistics Simulation",
                    notes: "Cargo scanning training.",
                    img: "https://images.unsplash.com/photo-1614014077743-30fa4622c97e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Customs Administration Department"
                },
                {
                    id: 302,
                    user: "Johnrey Neil Rama",
                    id_number: "20230456",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Johnrey+Neil+Rama&background=random",
                    equipment: "Document Scanner",
                    category: "Others",
                    date: "May 05",
                    fullDate: "May 5, 2026",
                    borrowDate: "May 05, 2026",
                    dueDate: "May 12, 2026",
                    status: "Overdue",
                    purpose: "Tariff Classification",
                    notes: "Scanning manifest records.",
                    img: "https://images.unsplash.com/photo-1562408590-e32931084e23?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Customs Administration Department"
                },
                {
                    id: 303,
                    user: "—",
                    id_number: "—",
                    role: "—",
                    avatar: "",
                    equipment: "Desktop PC",
                    category: "Others",
                    date: "—",
                    fullDate: "—",
                    borrowDate: "—",
                    dueDate: "—",
                    status: "Available",
                    purpose: "—",
                    notes: "Available in room 405.",
                    img: "https://images.unsplash.com/photo-1587831990711-23ca6441447b?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Customs Administration Department"
                },
                {
                    id: 304,
                    user: "Jeffrey Gaviola",
                    id_number: "20230789",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Jeffrey+Gaviola&background=random",
                    equipment: "Label Printer",
                    category: "Others",
                    date: "Jun 26",
                    fullDate: "June 26, 2026",
                    borrowDate: "June 26, 2026",
                    dueDate: "July 01, 2026",
                    status: "Reserved",
                    purpose: "Warehousing Project",
                    notes: "Printing inventory barcode tags.",
                    img: "https://images.unsplash.com/photo-1616401784845-180882ba9ba8?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Customs Administration Department"
                },
                {
                    id: 305,
                    user: "—",
                    id_number: "—",
                    role: "—",
                    avatar: "",
                    equipment: "Radio Transceiver",
                    category: "Others",
                    date: "—",
                    fullDate: "—",
                    borrowDate: "—",
                    dueDate: "—",
                    status: "Maintenance",
                    purpose: "—",
                    notes: "Battery replacement required.",
                    img: "https://images.unsplash.com/photo-1615486511487-12ee3c2a4c07?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Customs Administration Department"
                },

                // Business and Accountancy Department (CBA)
                {
                    id: 401,
                    user: "Anna Mae Santos",
                    id_number: "T-00987",
                    role: "Teacher",
                    avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&h=100&q=80",
                    equipment: "Financial Calculator",
                    category: "Others",
                    date: "Jun 15",
                    fullDate: "June 15, 2026",
                    borrowDate: "June 15, 2026",
                    dueDate: "June 19, 2026",
                    status: "Borrowed",
                    purpose: "Corporate Finance",
                    notes: "NPV and IRR calculation workshop.",
                    img: "https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Business and Accountancy Department"
                },
                {
                    id: 402,
                    user: "Johnrey Neil Rama",
                    id_number: "20230456",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Johnrey+Neil+Rama&background=random",
                    equipment: "Projector Sony",
                    category: "Projector",
                    date: "May 08",
                    fullDate: "May 8, 2026",
                    borrowDate: "May 08, 2026",
                    dueDate: "May 14, 2026",
                    status: "Overdue",
                    purpose: "Business Plan Pitch",
                    notes: "Interactive presentation.",
                    img: "https://images.unsplash.com/photo-1535016120720-40c646be5580?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Business and Accountancy Department"
                },
                {
                    id: 403,
                    user: "—",
                    id_number: "—",
                    role: "—",
                    avatar: "",
                    equipment: "Whiteboard Easel",
                    category: "Others",
                    date: "—",
                    fullDate: "—",
                    borrowDate: "—",
                    dueDate: "—",
                    status: "Available",
                    purpose: "—",
                    notes: "Ready for brainstorming.",
                    img: "https://images.unsplash.com/photo-1581291518633-83b4ebd1d83e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Business and Accountancy Department"
                },
                {
                    id: 404,
                    user: "Jeffrey Gaviola",
                    id_number: "20230789",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Jeffrey+Gaviola&background=random",
                    equipment: "Laser Printer",
                    category: "Others",
                    date: "Jun 24",
                    fullDate: "June 24, 2026",
                    borrowDate: "June 24, 2026",
                    dueDate: "June 30, 2026",
                    status: "Reserved",
                    purpose: "Report Printing",
                    notes: "Annual audited report files.",
                    img: "https://images.unsplash.com/photo-1612815154858-60aa4c59edd6?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Business and Accountancy Department"
                },
                {
                    id: 405,
                    user: "—",
                    id_number: "—",
                    role: "—",
                    avatar: "",
                    equipment: "Paper Shredder",
                    category: "Others",
                    date: "—",
                    fullDate: "—",
                    borrowDate: "—",
                    dueDate: "—",
                    status: "Maintenance",
                    purpose: "—",
                    notes: "Paper jam repairs.",
                    img: "https://images.unsplash.com/photo-1595225476474-87563907a212?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Business and Accountancy Department"
                },

                // Criminal Justice Department (CCJ)
                {
                    id: 501,
                    user: "Gabriel Fernandez",
                    id_number: "20230123",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Gabriel+Fernandez&background=random",
                    equipment: "DSLR Camera Nikon",
                    category: "Camera",
                    date: "Jun 20",
                    fullDate: "June 20, 2026",
                    borrowDate: "June 20, 2026",
                    dueDate: "June 24, 2026",
                    status: "Borrowed",
                    purpose: "Crime Scene Photography",
                    notes: "Documenting simulated evidence.",
                    img: "https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Criminal Justice Department"
                },
                {
                    id: 502,
                    user: "Johnrey Neil Rama",
                    id_number: "20230456",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Johnrey+Neil+Rama&background=random",
                    equipment: "Fingerprint Scanner",
                    category: "Others",
                    date: "May 12",
                    fullDate: "May 12, 2026",
                    borrowDate: "May 12, 2026",
                    dueDate: "May 18, 2026",
                    status: "Overdue",
                    purpose: "Dactyloscopy Lab",
                    notes: "Ridge matching assignment.",
                    img: "https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Criminal Justice Department"
                },
                {
                    id: 503,
                    user: "—",
                    id_number: "—",
                    role: "—",
                    avatar: "",
                    equipment: "Forensic Kit",
                    category: "Others",
                    date: "—",
                    fullDate: "—",
                    borrowDate: "—",
                    dueDate: "—",
                    status: "Available",
                    purpose: "—",
                    notes: "Fully stocked fingerprinting powder and tapes.",
                    img: "https://images.unsplash.com/photo-1582719508461-905c673771fd?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Criminal Justice Department"
                },
                {
                    id: 504,
                    user: "Jeffrey Gaviola",
                    id_number: "20230789",
                    role: "Student",
                    avatar: "https://ui-avatars.com/api/?name=Jeffrey+Gaviola&background=random",
                    equipment: "Evidence Scale",
                    category: "Others",
                    date: "Jun 22",
                    fullDate: "June 22, 2026",
                    borrowDate: "June 22, 2026",
                    dueDate: "June 29, 2026",
                    status: "Reserved",
                    purpose: "Forensics Class Demonstration",
                    notes: "Weighing trace materials.",
                    img: "https://images.unsplash.com/photo-1603302576837-37561b2e2302?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Criminal Justice Department"
                },
                {
                    id: 505,
                    user: "—",
                    id_number: "—",
                    role: "—",
                    avatar: "",
                    equipment: "Handheld Metal Detector",
                    category: "Others",
                    date: "—",
                    fullDate: "—",
                    borrowDate: "—",
                    dueDate: "—",
                    status: "Maintenance",
                    purpose: "—",
                    notes: "Sensor calibration.",
                    img: "https://images.unsplash.com/photo-1590602847861-f357a9332bbc?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60",
                    rejectReason: "",
                    department: "Criminal Justice Department"
                }
            ];

            let requests = JSON.parse(localStorage.getItem('equip-track-requests'));
            const isOutdated = !requests || !requests.some(r => r.user === "Jeffrey Gaviola") || !requests.some(r => r.hasOwnProperty('department'));
            if (isOutdated) {
                requests = monitoringSeed;
                localStorage.setItem('equip-track-requests', JSON.stringify(requests));
            }

            // Department metadata configuration
            const departments = [
                {
                    id: "it",
                    name: "Information Technology Department",
                    title: "College of Computer Studies",
                    image: "../images/it_department.png"
                },
                {
                    id: "education",
                    name: "Education Department",
                    title: "College of Teacher Education",
                    image: "../images/educ_department.jpg"
                },
                {
                    id: "engineering",
                    name: "Engineering Department",
                    title: "College of Engineering",
                    image: "../images/engineering_department.jpg"
                },
                {
                    id: "customs",
                    name: "Customs Administration Department",
                    title: "College of Customs Administration",
                    image: "../images/custom_department.jpg"
                },
                {
                    id: "business",
                    name: "Business and Accountancy Department",
                    title: "College of Business and Accountancy",
                    image: "../images/accountancy_department.jpg"
                },
                {
                    id: "criminal",
                    name: "Criminal Justice Department",
                    title: "College of Criminal Justice",
                    image: "../images/crim_department.jpg"
                }
            ];

            // State management
            let selectedDept = sessionStorage.getItem('equip-track-selected-monitoring-dept') || null;

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

            // View management elements
            const deptSelectorSection = document.getElementById('departmentSelectorSection');
            const backToDeptsBtn = document.getElementById('backToDeptsBtn');
            const selectedDeptHeaderCard = document.getElementById('selectedDeptHeaderCard');
            const deptGrid = document.getElementById('deptGrid');
            const adminSection = document.querySelector('.admin-section');
            const controlsBar = document.querySelector('.controls-bar');
            const tableCard = document.querySelector('.table-container.card.admin-table-card');

            // Pagination state
            let currentPage = 1;
            const pageSize = 25;

            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const themeToggleIcon = document.getElementById('themeToggleIcon');
            const userProfileDropdown = document.getElementById('userProfileDropdown');
            const dropdownMenu = document.getElementById('dropdownMenu');

            // Render Department Selection Cards Grid
            function renderDepartmentGrid() {
                deptGrid.innerHTML = '';
                departments.forEach(dept => {
                    const card = document.createElement('div');
                    card.className = 'dept-card';
                    card.setAttribute('title', dept.title);
                    card.innerHTML = `
                        <img src="${dept.image}" alt="${dept.title}" class="dept-logo-img">
                    `;
                    card.addEventListener('click', () => {
                        selectDepartment(dept.name);
                    });
                    deptGrid.appendChild(card);
                });
            }

            // Select a department and swap views
            function selectDepartment(deptName) {
                selectedDept = deptName;
                sessionStorage.setItem('equip-track-selected-monitoring-dept', deptName);
                currentPage = 1;
                updateView();
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
                    const deptMeta = departments.find(d => d.name === selectedDept);
                    
                    deptSelectorSection.style.display = 'none';
                    backToDeptsBtn.style.display = 'inline-flex';
                    if (headerSection) headerSection.style.display = 'none';
                    
                    // Display department card
                    selectedDeptHeaderCard.style.display = 'flex';
                    
                    const logoSrc = deptMeta ? deptMeta.image : '../images/logo_only.png';
                    
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
                            <img src="${logoSrc}" alt="${escapeHTML(customTitle)} Logo">
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
                            actionHtml = `<button class="btn-action-remind" title="Send Return Reminder" onclick="sendReminder(${req.id}, event)"><i class="fa-solid fa-bell"></i></button>`;
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
                                <strong class="eq-name-text" title="${escapeHTML(req.equipment)}">${escapeHTML(req.equipment)}</strong>
                            </td>
                            <td title="${escapeHTML(borrowerText)}">${escapeHTML(borrowerText)}</td>
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
                        <button class="btn-modal-close" style="background-color: var(--border-color, #e2e8f0); color: var(--text-main); margin-right: 12px;" onclick="closeModal()">Close</button>
                        <button class="btn-action-confirm" style="height: 40px; padding: 0 20px;" onclick="confirmReturn(${req.id})"><i class="fa-solid fa-circle-check"></i> Confirm Return</button>
                    `;
                } else if (req.status === 'Overdue') {
                    footer.innerHTML = `
                        <button class="btn-modal-close" style="background-color: var(--border-color, #e2e8f0); color: var(--text-main); margin-right: 12px;" onclick="closeModal()">Close</button>
                        <button class="btn-action-confirm" style="height: 40px; padding: 0 20px; background-color: #f59e0b; border-color: #d97706;" onclick="sendReminder(${req.id}); closeModal();"><i class="fa-solid fa-bell"></i> Send Reminder</button>
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
</body>
</html>
