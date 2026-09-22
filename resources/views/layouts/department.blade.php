{{-- Shared shell for authenticated Department pages --}}
@php
    $d = auth('dept')->user();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'EquipTrack - Department')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_only.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo_only.png') }}">
    <link rel="stylesheet" href="{{ asset('ccs/global.css') }}">
    @stack('css')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        (function() {
            if (localStorage.getItem('dept-dashboard-theme') === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>
</head>
<body data-avatar-key="dept-avatar-src" data-theme-key="dept-dashboard-theme">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/EquipTrack_logo.png') }}" alt="EquipTrack Logo" class="sidebar-logo-img">
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-label">General</div>
            <a href="{{ route('department.dashboard') }}" class="nav-item {{ request()->routeIs('department.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
            </a>
            <a href="{{ route('department.profile') }}" class="nav-item {{ request()->routeIs('department.profile') ? 'active' : '' }}">
                <i class="fa-solid fa-user"></i> <span>Profile</span>
            </a>

            <div class="sidebar-section-label">Department</div>
            <a href="{{ route('department.equipment') }}" class="nav-item {{ request()->routeIs('department.equipment') ? 'active' : '' }}">
                <i class="fa-solid fa-box"></i> <span>Department Equipment</span>
            </a>
            <a href="{{ route('department.requests') }}" class="nav-item {{ request()->routeIs('department.requests') ? 'active' : '' }}">
                <i class="fa-solid fa-clipboard-list"></i> <span>Borrow Requests</span>
            </a>
            <a href="{{ route('department.users') }}" class="nav-item {{ request()->routeIs('department.users') ? 'active' : '' }}">
                <i class="fa-solid fa-users"></i> <span>Department Users</span>
            </a>
            <a href="{{ route('department.monitoring') }}" class="nav-item {{ request()->routeIs('department.monitoring') ? 'active' : '' }}">
                <i class="fa-solid fa-desktop"></i> <span>Equipment Monitoring</span>
            </a>
            <a href="{{ route('department.history') }}" class="nav-item {{ request()->routeIs('department.history') ? 'active' : '' }}">
                <i class="fa-solid fa-clock-rotate-left"></i> <span>Borrowing History</span>
            </a>
        </nav>
    </aside>

    <main class="main-content">
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
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; overflow: hidden; {{ $d?->profile_image ? 'padding: 0; background: transparent;' : '' }}">
                        @if ($d?->profile_image)
                            <img src="{{ $d->profile_image }}" alt="Department Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        @else
                            {{ $d?->initials() ?? 'DP' }}
                        @endif
                    </div>
                    <span class="user-name">{{ $d?->full_name ?: 'Department' }}</span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>

                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name">{{ $d?->full_name }}</span>
                            <span class="header-email">{{ $d?->email }}</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('department.profile') }}"><i class="fa-solid fa-user"></i> My Profile</a>
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

    {{-- Toast only exists on admin pages; dept pages ship their own toastNotif markup --}}

    <script>
        window.navAvatarDbValue = @json($d?->profile_image);
        window.navInitials = @json($d?->initials() ?? 'DP');
    </script>
    <script src="{{ asset('js/admin-shell.js') }}"></script>
    @stack('scripts')
</body>
</html>
