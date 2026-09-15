@extends('layouts.department')

@section('title', 'EquipTrack - Profile')

@push('css')
<link rel="stylesheet" href="{{ asset('departments/css/profile.css') }}">
@endpush

@section('content')
    {{-- Page Header --}}
    <div class="page-title-section" style="margin-top: 10px;">
        <h2>Profile</h2>
        <p>Manage your account information and security settings.</p>
    </div>

    {{-- Main Profile Grid Layout --}}
    <div class="profile-grid-container">

        {{-- Left Profile Card --}}
        <div class="profile-card-left">
            <div style="display: flex; flex-direction: column; align-items: center; width: 100%;">
                <div class="avatar-wrapper">
                    <div class="profile-card-avatar-circle" style="width: 120px; height: 120px; border-radius: 50%; background-color: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 36px; overflow: hidden; {{ $dept_profile_img ? 'padding: 0; background: transparent;' : '' }}">
                        @if ($dept_profile_img)
                            <img src="{{ $dept_profile_img }}" alt="Department Avatar" class="avatar-img" id="avatarImage" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        @else
                            <span id="avatarInitials">{{ auth('dept')->user()?->initials() ?? 'DP' }}</span>
                        @endif
                    </div>
                    <button class="avatar-camera-btn" id="changeAvatarBtn" title="Upload new photo">
                        <i class="fa-solid fa-camera"></i>
                    </button>
                    <input type="file" id="deptAvatarInput" style="display: none;" accept="image/*">
                </div>

                <h3 class="account-name" id="displayAccountName">{{ strtoupper($dept_full_name) }}</h3>
                <p class="account-role">{{ $dept_role_val }}</p>

                <span class="badge-active-status">ACTIVE</span>
            </div>

            <button type="button" class="btn-profile-logout" id="deptProfileLogout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
            </button>
        </div>

        {{-- Right Profile Form Card --}}
        <div class="profile-card-right">
            <form id="profileUpdateForm" onsubmit="handleProfileSubmit(event)">

                {{-- Personal Information Section --}}
                <h3 class="form-section-header">Personal Information</h3>

                <div class="form-group-item">
                    <label for="fullName">Full Name</label>
                    <div class="form-input-box">
                        <input type="text" id="fullName" value="{{ $dept_full_name }}" placeholder="Enter full name" required>
                    </div>
                </div>

                <div class="form-group-item">
                    <label for="email">Email</label>
                    <div class="form-input-box">
                        <input type="email" id="email" value="{{ $dept_email_val }}" placeholder="Enter email address" required>
                    </div>
                </div>

                <div class="form-row-two-cols">
                    <div class="form-group-item">
                        <label for="employeeId">Employee ID</label>
                        <div class="form-input-box">
                            <input type="text" id="employeeId" value="{{ $dept_employee_id }}" placeholder="Enter employee ID">
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label for="role">Role</label>
                        <div class="form-input-box">
                            {{-- Role is now display-only: changing it is an admin action --}}
                            <input type="text" id="role" value="{{ $dept_role_val }}" readonly style="background-color: var(--bg-tertiary, #f3f4f6); cursor: not-allowed;">
                        </div>
                    </div>
                </div>

                <div class="form-group-item">
                    <label for="department">Assigned Department</label>
                    <div class="form-input-box">
                        <input type="text" id="department" value="{{ $dept_assigned }}" readonly placeholder="Assigned department" style="background-color: var(--bg-tertiary, #f3f4f6); cursor: not-allowed;">
                    </div>
                </div>

                <div class="form-divider-line"></div>

                {{-- Change Password Section --}}
                <h3 class="form-section-header">Change Password</h3>

                <div class="form-group-item">
                    <label for="currentPassword">Current Password</label>
                    <div class="form-input-box">
                        <input type="password" id="currentPassword" placeholder="••••••••">
                        <i class="fa-solid fa-eye eye-icon" onclick="togglePasswordVisibility('currentPassword', this)"></i>
                    </div>
                </div>

                <div class="form-group-item">
                    <label for="newPassword">New Password</label>
                    <div class="form-input-box">
                        <input type="password" id="newPassword" placeholder="••••••••">
                        <i class="fa-solid fa-eye eye-icon" onclick="togglePasswordVisibility('newPassword', this)"></i>
                    </div>
                </div>

                <div class="form-group-item">
                    <label for="confirmPassword">Confirm Password</label>
                    <div class="form-input-box">
                        <input type="password" id="confirmPassword" placeholder="••••••••">
                        <i class="fa-solid fa-eye eye-icon" onclick="togglePasswordVisibility('confirmPassword', this)"></i>
                    </div>
                </div>

                <div style="margin-top: 28px; text-align: right;">
                    <button type="submit" class="btn-save-profile">Save Changes</button>
                </div>

            </form>
        </div>

    </div>

    {{-- Toast Notification --}}
    <div class="toast-notification" id="toastNotif">
        <i class="fa-solid fa-circle-check" style="color: #10b981;"></i>
        <span id="toastMessage">Profile updated successfully.</span>
    </div>
    <form id="deptLogoutForm" method="POST" action="{{ route('logout') }}" style="display:none;">@csrf</form>
@endsection

@push('scripts')
<script>
    const URL_UPDATE = @json(route('department.profile.update'));
    const URL_AVATAR = @json(route('department.profile.avatar'));
    const CSRF_TOKEN = @json(csrf_token());

    // Toggle password visibility
    function togglePasswordVisibility(inputId, iconEl) {
        const inputEl = document.getElementById(inputId);
        if (inputEl) {
            if (inputEl.type === 'password') {
                inputEl.type = 'text';
                iconEl.className = 'fa-solid fa-eye-slash eye-icon';
            } else {
                inputEl.type = 'password';
                iconEl.className = 'fa-solid fa-eye eye-icon';
            }
        }
    }

    // Toast message display
    function showToast(msg) {
        const toastNotif = document.getElementById('toastNotif');
        const toastMessage = document.getElementById('toastMessage');
        if (toastNotif && toastMessage) {
            toastMessage.textContent = msg;
            toastNotif.classList.add('show');
            setTimeout(() => {
                toastNotif.classList.remove('show');
            }, 3000);
        }
    }

    // Handle profile submit via AJAX
    function handleProfileSubmit(e) {
        e.preventDefault();
        const fullNameVal = document.getElementById('fullName').value.trim();
        const emailVal = document.getElementById('email').value.trim();
        const employeeIdVal = document.getElementById('employeeId').value.trim();
        const currentPasswordVal = document.getElementById('currentPassword').value;
        const newPasswordVal = document.getElementById('newPassword').value;
        const confirmPasswordVal = document.getElementById('confirmPassword').value;

        const formData = new FormData();
        formData.append('_token', CSRF_TOKEN);
        formData.append('full_name', fullNameVal);
        formData.append('email', emailVal);
        formData.append('employee_id', employeeIdVal);
        formData.append('current_password', currentPasswordVal);
        formData.append('new_password', newPasswordVal);
        formData.append('confirm_password', confirmPasswordVal);

        fetch(URL_UPDATE, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (fullNameVal) {
                    document.getElementById('displayAccountName').textContent = fullNameVal.toUpperCase();
                }
                showToast(data.message || 'Profile updated successfully.');
            } else {
                showToast(data.message || 'Failed to update profile.');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error updating profile information.');
        });
    }

    // Avatar change & upload handler
    const changeAvatarBtn = document.getElementById('changeAvatarBtn');
    const deptAvatarInput = document.getElementById('deptAvatarInput');
    const avatarImage = document.getElementById('avatarImage');

    if (changeAvatarBtn && deptAvatarInput) {
        changeAvatarBtn.addEventListener('click', () => {
            deptAvatarInput.click();
        });

        deptAvatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const formData = new FormData();
                formData.append('_token', CSRF_TOKEN);
                formData.append('avatar_file', file);

                fetch(URL_AVATAR, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.profile_image) {
                        if (avatarImage) avatarImage.src = data.profile_image;
                        window.navAvatarDbValue = data.profile_image;
                        if (typeof syncNavbarAvatar === 'function') syncNavbarAvatar(data.profile_image);
                        showToast(data.message || 'Profile picture updated successfully!');
                    } else {
                        showToast(data.message || 'Failed to update profile picture.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('Error uploading profile picture.');
                });
            }
        });
    }

    document.getElementById('deptProfileLogout').addEventListener('click', () => {
        document.getElementById('deptLogoutForm').submit();
    });
</script>
@endpush
