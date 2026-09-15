@extends('layouts.admin')

@section('title', 'EquipTrack - Admin Profile')

@push('css')
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminprofile.css') }}">
@endpush

@section('content')
    <div class="profile-container">
        {{-- Header Section --}}
        <div class="profile-header-section">
            <h1 class="profile-page-title">Profile</h1>
            <p class="profile-page-subtitle">Manage your administrator account information and security settings.</p>
        </div>

        @if ($success_msg)
            <div style="color: #065f46; background: #d1fae5; padding: 12px 18px; border-radius: 10px; font-size: 0.95rem; margin-bottom: 20px; border: 1px solid #6ee7b7; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-check" style="color: #059669; font-size: 1.1rem;"></i> {{ $success_msg }}
            </div>
        @endif

        @if ($error_msg)
            <div style="color: #ef4444; background: #fee2e2; padding: 12px 18px; border-radius: 10px; font-size: 0.95rem; margin-bottom: 20px; border: 1px solid #fca5a5; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-exclamation" style="color: #dc2626; font-size: 1.1rem;"></i> {{ $error_msg }}
            </div>
        @endif

        @if ($errors->any())
            <div style="color: #ef4444; background: #fee2e2; padding: 12px 18px; border-radius: 10px; font-size: 0.95rem; margin-bottom: 20px; border: 1px solid #fca5a5; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-exclamation" style="color: #dc2626; font-size: 1.1rem;"></i> {{ $errors->first() }}
            </div>
        @endif

        {{-- Two-Column Profile Grid --}}
        <div class="profile-layout-grid">
            {{-- Left Overview Card --}}
            <div class="profile-card profile-sidebar-card">
                <div class="avatar-upload-container">
                    <div class="avatar-image-ring">
                        <img src="{{ $admin_profile_image ?: 'https://ui-avatars.com/api/?name=' . urlencode($admin_name) . '&background=5C74A8&color=fff&size=200' }}" id="profileAvatarImg" alt="Admin Avatar">
                    </div>
                    <button type="button" class="btn-avatar-camera" id="btnUploadAvatar" title="Change Profile Picture">
                        <i class="fa-solid fa-camera"></i>
                    </button>
                    <input type="file" id="avatarFileInput" accept="image/*" style="display: none;">
                </div>

                <h3 class="profile-card-name" id="cardProfileName">{{ $admin_name }}</h3>
                <p class="profile-card-role">System Administrator</p>
                <p class="profile-card-email" id="cardProfileEmail">{{ $admin_email }}</p>

                <div class="profile-status-wrapper">
                    <span class="profile-status-badge">ACTIVE</span>
                </div>

                <div class="profile-card-footer">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn-profile-logout">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                        </button>
                    </form>
                </div>
            </div>

            {{-- Right Form Card --}}
            <div class="profile-card profile-details-card">
                <form id="adminProfileForm" action="{{ route('admin.profile.update') }}" method="POST">
                    @csrf
                    {{-- Personal Information Block --}}
                    <div class="form-section-block">
                        <h2 class="form-section-title">Personal Information</h2>

                        <div class="profile-form-group">
                            <label for="adminFullName">Full Name</label>
                            <input type="text" id="adminFullName" name="admin_full_name" class="profile-input" value="{{ old('admin_full_name', $admin_name) }}" placeholder="Enter full name" required>
                        </div>

                        <div class="profile-form-group">
                            <label for="adminEmail">Email</label>
                            <input type="email" id="adminEmail" name="admin_email" class="profile-input" value="{{ old('admin_email', $admin_email) }}" placeholder="Enter email address" required>
                        </div>

                        <div class="profile-form-row">
                            <div class="profile-form-group">
                                <label for="adminEmployeeId">Employee ID</label>
                                <input type="text" id="adminEmployeeId" name="admin_employee_id" class="profile-input" value="{{ old('admin_employee_id', $admin_employee_id) }}" placeholder="Enter employee ID">
                            </div>
                            <div class="profile-form-group">
                                <label for="adminRole">Role</label>
                                <input type="text" id="adminRole" class="profile-input" value="System Administrator" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="profile-section-divider"></div>

                    {{-- Change Password Block --}}
                    <div class="form-section-block">
                        <h2 class="form-section-title">Change Password</h2>

                        <div class="profile-form-group">
                            <label for="currentPassword">Current Password</label>
                            <div class="password-field-container">
                                <input type="password" id="currentPassword" name="current_password" class="profile-input" placeholder="Enter current password">
                                <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('currentPassword', this)" title="Toggle password visibility">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="profile-form-group">
                            <label for="newPassword">New Password</label>
                            <div class="password-field-container">
                                <input type="password" id="newPassword" name="new_password" class="profile-input" placeholder="Enter new password">
                                <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('newPassword', this)" title="Toggle password visibility">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="profile-form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <div class="password-field-container">
                                <input type="password" id="confirmPassword" name="confirm_password" class="profile-input" placeholder="Confirm new password">
                                <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility('confirmPassword', this)" title="Toggle password visibility">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Form Actions --}}
                    <div class="profile-actions-bar">
                        <button type="button" class="btn-form-cancel" id="btnCancelProfile">Cancel</button>
                        <button type="submit" class="btn-form-save" id="btnSaveProfile">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Toast Notification --}}
    <div class="toast-notification" id="toastNotif">
        <i class="fa-solid fa-circle-check"></i> <span id="toastMsg">Profile changes saved successfully!</span>
    </div>
@endsection

@push('scripts')
<script>
    // Password Visibility Toggle Function
    function togglePasswordVisibility(inputId, buttonEl) {
        const inputField = document.getElementById(inputId);
        const icon = buttonEl.querySelector('i');
        if (inputField.type === 'password') {
            inputField.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            inputField.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const URL_AVATAR = @json(route('admin.profile.avatar'));

        // Avatar Upload Trigger & Handler
        const btnUploadAvatar = document.getElementById('btnUploadAvatar');
        const avatarFileInput = document.getElementById('avatarFileInput');
        const profileAvatarImg = document.getElementById('profileAvatarImg');

        if (btnUploadAvatar && avatarFileInput) {
            btnUploadAvatar.addEventListener('click', function() {
                avatarFileInput.click();
            });

            avatarFileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const formData = new FormData();
                    formData.append('_token', @json(csrf_token()));
                    formData.append('admin_avatar_file', file);
                    fetch(URL_AVATAR, {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.image_url) {
                            if (profileAvatarImg) profileAvatarImg.src = data.image_url;
                            window.navAvatarDbValue = data.image_url;
                            if (typeof syncNavbarAvatar === 'function') syncNavbarAvatar(data.image_url);
                            showToast('Profile picture updated successfully!');
                        } else {
                            alert(data.message || 'Error uploading profile picture');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('An error occurred while uploading profile picture.');
                    });
                }
            });
        }

        // Toast Helper
        function showToast(msg) {
            const toast = document.getElementById('toastNotif');
            const toastMsg = document.getElementById('toastMsg');
            if (toast && toastMsg) {
                toastMsg.textContent = msg;
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3500);
            }
        }

        // Form Validation on submit
        const adminProfileForm = document.getElementById('adminProfileForm');
        if (adminProfileForm) {
            adminProfileForm.addEventListener('submit', function(e) {
                const newPwd = document.getElementById('newPassword').value;
                const confirmPwd = document.getElementById('confirmPassword').value;

                if (newPwd || confirmPwd) {
                    if (newPwd !== confirmPwd) {
                        e.preventDefault();
                        alert('New password and confirm password do not match!');
                        return false;
                    }
                }
            });
        }

        // Cancel Button Handler
        const btnCancelProfile = document.getElementById('btnCancelProfile');
        if (btnCancelProfile) {
            btnCancelProfile.addEventListener('click', function() {
                window.location.reload();
            });
        }
    });
</script>
@endpush
