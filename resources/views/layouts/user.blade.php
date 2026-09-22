{{-- Shared shell for authenticated Student/Faculty pages --}}
@php
    $u = auth('user')->user();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'EquipTrack')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_only.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo_only.png') }}">
    <link rel="stylesheet" href="{{ asset('ccs/global.css') }}">
    @stack('css')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        (function() {
            if (localStorage.getItem('dashboard-theme') === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>
</head>
<body data-avatar-key="user-avatar-src">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/EquipTrack_logo.png') }}" alt="EquipTrack Logo" class="sidebar-logo-img">
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-label">General</div>
            <a href="{{ route('user.dashboard') }}" class="nav-item {{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-table-cells-large"></i> <span>Dashboard</span>
            </a>
            <a href="{{ route('user.profile') }}" class="nav-item {{ request()->routeIs('user.profile') ? 'active' : '' }}">
                <i class="fa-solid fa-user"></i> <span>Profile</span>
            </a>

            <div class="sidebar-section-label">Equipment</div>
            <a href="{{ route('user.equipment') }}" class="nav-item {{ request()->routeIs('user.equipment') ? 'active' : '' }}">
                <i class="fa-solid fa-toolbox"></i> <span>Available Equipment</span>
            </a>
            <a href="{{ route('user.requests') }}" class="nav-item {{ request()->routeIs('user.requests') ? 'active' : '' }}">
                <i class="fa-solid fa-clipboard-list"></i> <span>My Request</span>
            </a>
            <a href="{{ route('user.returns') }}" class="nav-item {{ request()->routeIs('user.returns') ? 'active' : '' }}">
                <i class="fa-solid fa-check-double"></i> <span>Return Item</span>
            </a>
            <a href="{{ route('user.history') }}" class="nav-item {{ request()->routeIs('user.history') ? 'active' : '' }}">
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
                    <div class="profile-avatar" style="width: 38px; height: 38px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; overflow: hidden; {{ $u?->profile_image ? 'padding: 0; background: transparent;' : '' }}">
                        @if ($u?->profile_image)
                            <img src="{{ $u->profile_image }}" alt="User Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        @else
                            {{ $u?->initials() ?? 'US' }}
                        @endif
                    </div>
                    <span class="user-name">{{ $u?->fullName() ?: 'User' }}</span>
                    <i class="fa-solid fa-chevron-down dropdown-arrow"></i>

                    <div class="profile-dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-profile-header">
                            <span class="header-name">{{ $u?->fullName() }}</span>
                            <span class="header-email">{{ $u?->email }}</span>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('user.profile') }}"><i class="fa-solid fa-user"></i> My Profile</a>
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

    {{-- Toast only exists on admin pages; user/dept pages ship their own toastNotif markup --}}

    <script>
        window.navAvatarDbValue = @json($u?->profile_image);
        window.navInitials = @json($u?->initials() ?? 'US');
    </script>
    <script src="{{ asset('js/admin-shell.js') }}"></script>
    @stack('scripts')
</body>
</html>
