@extends('layouts.department')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('departments/css/departmentdashboard.css') }}">
@endpush

@section('content')
<!-- Welcome Banner -->
        <div class="welcome-banner card" style="margin-top: 24px;">
            <div class="banner-text">
                <span class="banner-date">Today</span>
                <h2>Welcome back, Department Personnel! 👋</h2>
                <p>Manage your department's equipment, monitor borrowing activities, and review requests efficiently.</p>
            </div>
            <!-- Styled Icon Graphic instead of PNG/JPG image -->
            <div class="welcome-banner-graphic" style="position: absolute; right: 40px; top: 50%; transform: translateY(-50%); font-size: 80px; color: rgba(56, 85, 133, 0.08); pointer-events: none;">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
        </div>

        <!-- Quick Stats Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">Quick Stats</h4>
            <div class="stats-scroll-container">
                <!-- Total Equipment -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Total Equipment</span>
                            <span class="quick-stat-card-value">0</span>
                            <p class="quick-stat-card-desc">Items assigned to department</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-blue">
                            <i class="fa-solid fa-boxes-stacked"></i>
                        </div>
                    </div>
                </div>

                <!-- Pending Requests -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Pending Requests</span>
                            <span class="quick-stat-card-value">0</span>
                            <p class="quick-stat-card-desc">Awaiting review</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-orange">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                    </div>
                </div>

                <!-- Currently Borrowed -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Currently Borrowed</span>
                            <span class="quick-stat-card-value">0</span>
                            <p class="quick-stat-card-desc">Equipment on active loan</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-green">
                            <i class="fa-solid fa-box"></i>
                        </div>
                    </div>
                </div>

                <!-- Overdue Items -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Overdue Items</span>
                            <span class="quick-stat-card-value">0</span>
                            <p class="quick-stat-card-desc">Exceeded return date</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-red">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>
                </div>

                <!-- Department Users -->
                <div class="quick-stat-card">
                    <div class="quick-stat-card-main">
                        <div class="quick-stat-card-info">
                            <span class="quick-stat-card-title">Department Users</span>
                            <span class="quick-stat-card-value">0</span>
                            <p class="quick-stat-card-desc">Registered students & faculty</p>
                        </div>
                        <div class="quick-stat-icon-wrapper color-indigo">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Borrow Requests Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">
                Recent Borrow Requests
                <a href="/department/requests" class="view-all" style="margin-left: auto; font-size: 13px; font-weight: 600; color: var(--primary-color);">View All Requests &rarr;</a>
            </h4>
            <div class="table-container card admin-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Borrower Name</th>
                            <th>Equipment</th>
                            <th>Request Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                                No recent borrow requests found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Overdue Alerts Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">
                <i class="fa-solid fa-triangle-exclamation warning-icon" style="color: #ef4444;"></i> Overdue Alerts
                <a href="/department/monitoring" class="view-all" style="margin-left: auto; font-size: 13px; font-weight: 600; color: #ef4444;">View Monitoring &rarr;</a>
            </h4>
            <div class="table-container card admin-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Borrower Name</th>
                            <th>Equipment</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                                No overdue items.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Upcoming Returns Section -->
        <div class="admin-section">
            <h4 class="admin-section-heading">
                Upcoming Returns
                <a href="/department/monitoring" class="view-all" style="margin-left: auto; font-size: 13px; font-weight: 600; color: var(--success-color);">View Equipment Monitoring &rarr;</a>
            </h4>
            <div class="table-container card admin-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Borrower Name</th>
                            <th>Equipment</th>
                            <th>Due Date</th>
                            <th>Days Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted, #64748b); padding: 32px;">
                                No upcoming returns scheduled.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
<!-- Interactivity -->
@endsection

@push('scripts')
<script>

        document.addEventListener('DOMContentLoaded', () => {
            const bannerDate = document.querySelector('.banner-date');
            if (bannerDate) {
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                bannerDate.textContent = new Date().toLocaleDateString('en-US', options);
            }
        });





        // Notification bell (placeholder)
        document.getElementById('notifBtn').addEventListener('click', () => {
            alert('No new notifications yet.');
        });
    
</script>
@endpush
