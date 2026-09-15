@extends('layouts.user')

@section('title', 'EquipTrack')

@push('css')
<link rel="stylesheet" href="{{ asset('user/css/userdashboard.css') }}">
@endpush

@section('content')
<div class="dashboard-container">
            <!-- Left Column -->
            <div class="dashboard-left">
                <!-- Welcome Banner -->
                <div class="welcome-banner card">
                    <div class="banner-text">
                        <span class="banner-date">Today</span>
                        <h2>Welcome back, {{ auth('user')->user()?->student?->first_name ?? auth('user')->user()?->facultyMember?->first_name ?? 'User' }}!</h2>
                        <p>Here's your equipment activity overview for today.</p>
                    </div>
                    <img src="{{ asset('images/user_design1.png') }}" alt="User Illustration" class="banner-img">
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h3 class="section-title">Quick Stats</h3>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card card">
                            <div class="stat-icon info"><i class="fa-solid fa-box-open"></i></div>
                            <div class="stat-details">
                                <div class="stat-value">0</div>
                                <div class="stat-title">Active Borrows</div>
                            </div>
                        </div>
                        <div class="stat-card card">
                            <div class="stat-icon warning"><i class="fa-solid fa-clock-rotate-left"></i></div>
                            <div class="stat-details">
                                <div class="stat-value">0</div>
                                <div class="stat-title">Pending Requests</div>
                            </div>
                        </div>
                        <div class="stat-card card">
                            <div class="stat-icon danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="stat-details">
                                <div class="stat-value">0</div>
                                <div class="stat-title">Overdue Items</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h3 class="section-title">Active Borrow Items</h3>
                        <a href="/user/history" class="view-all">View All</a>
                    </div>
                    <div class="table-container card">
                        <table>
                            <thead>
                                <tr>
                                    <th>Equipment</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--text-muted, #64748b); padding: 24px;">No active borrow items.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h3 class="section-title">Recent Requests</h3>
                    </div>
                    <div class="table-container card">
                        <table>
                            <thead>
                                <tr>
                                    <th>Equipment</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: var(--text-muted, #64748b); padding: 24px;">No recent requests found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="dashboard-right">
                <div class="action-card card">
                    <h3 class="card-title">Quick Actions</h3>
                    <div class="action-grid">
                        <a href="/user/equipment" class="action-btn">
                            <div class="action-icon">
                                <i class="fa-solid fa-desktop"></i>
                            </div>
                            <div class="action-text">
                                <span class="action-label">Browse Equipment</span>
                                <span class="action-desc">Explore available items</span>
                            </div>
                            <i class="fa-solid fa-chevron-right action-arrow"></i>
                        </a>
                        <a href="/user/equipment" class="action-btn">
                            <div class="action-icon">
                                <i class="fa-solid fa-cart-plus"></i>
                            </div>
                            <div class="action-text">
                                <span class="action-label">Request Equipment</span>
                                <span class="action-desc">Borrow items for your use</span>
                            </div>
                            <i class="fa-solid fa-chevron-right action-arrow"></i>
                        </a>
                        <a href="/user/requests" class="action-btn">
                            <div class="action-icon">
                                <i class="fa-solid fa-list-check"></i>
                            </div>
                            <div class="action-text">
                                <span class="action-label">View My Requests</span>
                                <span class="action-desc">Check your current status</span>
                            </div>
                            <i class="fa-solid fa-chevron-right action-arrow"></i>
                        </a>
                    </div>
                </div>

                <div class="reminder-card card">
                    <div class="card-header">
                        <h3 class="card-title">Reminders</h3>
                    </div>
                    <ul class="reminder-list">
                        <li class="reminder-item" style="justify-content: center; text-align: center; color: var(--text-muted, #64748b); padding: 16px;">
                            <span style="font-size: 13px;">No active reminders at this time.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

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
    
</script>
@endpush
