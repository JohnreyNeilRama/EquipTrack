{{-- Shared shell for all authenticated Admin pages --}}
@php
    $adminUser = auth('admin')->user();
    $adminName = $adminUser->name ?? 'Admin';
    $adminEmail = $adminUser->email ?? '';
    $adminAvatar = $adminUser->profile_image ?? null;
    $nameParts = explode(' ', trim($adminName));
    $adminInitials = '';
    foreach ($nameParts as $part) {
        if ($part !== '') $adminInitials .= strtoupper($part[0]);
    }
    $adminInitials = substr($adminInitials, 0, 2) ?: 'AD';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'EquipTrack - Admin')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_only.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo_only.png') }}">
    <link rel="stylesheet" href="{{ asset('ccs/global.css') }}">
    @stack('css')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
    {{-- Sidebar --}}
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/EquipTrack_logo.png') }}" alt="EquipTrack Logo" class="sidebar-logo-img">
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-label">General</div>
            <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
            </a>
            <a href="{{ route('admin.profile') }}" class="nav-item {{ request()->routeIs('admin.profile') ? 'active' : '' }}">
                <i class="fa-solid fa-user"></i> <span>Profile</span>
            </a>

            <div class="sidebar-section-label">Monitoring</div>
            <a href="{{ route('admin.equipment') }}" class="nav-item {{ request()->routeIs('admin.equipment') ? 'active' : '' }}">
                <i class="fa-solid fa-toolbox"></i> <span>Equipment Management</span>
            </a>
            <a href="{{ route('admin.requests') }}" class="nav-item {{ request()->routeIs('admin.requests') ? 'active' : '' }}">
                <i class="fa-solid fa-clipboard-list"></i> <span>Requests</span>
            </a>
            <a href="{{ route('admin.users') }}" class="nav-item {{ request()->routeIs('admin.users') ? 'active' : '' }}">
                <i class="fa-solid fa-users"></i> <span>Users</span>
            </a>
            <a href="{{ route('admin.monitoring') }}" class="nav-item {{ request()->routeIs('admin.monitoring') ? 'active' : '' }}">
                <i class="fa-solid fa-desktop"></i> <span>Equipment Monitoring</span>
            </a>
            <a href="{{ route('admin.reports') }}" class="nav-item {{ request()->routeIs('admin.reports') ? 'active' : '' }}">
                <i class="fa-solid fa-file-lines"></i> <span>Reports</span>
            </a>
            <a href="{{ route('admin.audit') }}" class="nav-item {{ request()->routeIs('admin.audit') ? 'active' : '' }}">
                <i class="fa-solid fa-clipboard-check"></i> <span>Audit Trail</span>
            </a>
        </nav>
    </aside>

    {{-- Main Content Area --}}
    <main class="main-content">
        {{-- Top Navbar --}}
        <header class="top-navbar profile-navbar">
            <div class="navbar-right">
                <span class="navbar-divider"></span>
                <div class="icon-btn" id="themeToggleBtn" title="Toggle theme">
                    <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                </div>
                <div class="icon-btn notification" id="notifBtn" title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <span class="navbar-divider"></span>
                <div class="user-profile" id="userProfileDropdown">
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; overflow: hidden; {{ $adminAvatar ? 'padding: 0; background: transparent;' : '' }}">
                        @if ($adminAvatar)
                            <img src="{{ $adminAvatar }}" alt="Admin Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        @else
                            {{ $adminInitials }}
                        @endif
                    </div>
                    <span class="user-name">{{ $adminName }}</span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>

                    {{-- Dropdown Menu --}}
                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name">{{ $adminName }}</span>
                            <span class="header-email">{{ $adminEmail }}</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('admin.profile') }}"><i class="fa-solid fa-user"></i> My Profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="danger">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        @yield('content')
    </main>

    {{-- Success Toast Notification --}}
    <div class="toast-notification" id="toast">
        <div class="toast-content">
            <i class="fa-solid fa-circle-check toast-icon" id="toastIcon"></i>
            <div class="toast-message">
                <span class="toast-title" id="toastTitle">Success</span>
                <span class="toast-desc" id="toastMsg">Action processed successfully!</span>
            </div>
        </div>
    </div>

    {{-- Shared scripts: banner date, theme, dropdown, avatar sync, toast --}}
    <script>
        window.navAvatarDbValue = @json($adminAvatar);
        window.navInitials = @json($adminInitials);
    </script>
    <script src="{{ asset('js/admin-shell.js') }}"></script>
    @stack('scripts')
</body>
</html>
