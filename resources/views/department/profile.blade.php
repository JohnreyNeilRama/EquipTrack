@extends('layouts.department')

@section('title', 'EquipTrack - Profile')

@push('css')
<link rel="stylesheet" href="{{ asset('departments/css/profile.css') }}?v={{ filemtime(public_path('departments/css/profile.css')) }}">
@endpush

@php
    $deptAccount = auth('dept')->user();
@endphp

@section('content')
    {{-- Page Header --}}
    <div class="page-title-section" style="margin-top: 10px;">
        <h2>Profile</h2>
        <p>Manage your account information and security settings.</p>
    </div>

    {{-- Main Profile Grid Layout --}}
    <div class="profile-grid-container">

        {{-- Left Profile Card --}}
        {{-- Same structure and look as the User account's profile card --}}
        <aside class="profile-card-left">
            <div class="profile-banner"></div>

            <div class="avatar-wrapper">
                <div class="profile-card-avatar-circle {{ $dept_profile_img ? 'has-image' : '' }}" id="avatarCircle">
                    @if ($dept_profile_img)
                        <img src="{{ $dept_profile_img }}" alt="Profile photo" class="avatar-img" id="avatarImage">
                    @else
                        <span id="avatarInitials">{{ $deptAccount?->initials() ?? 'DP' }}</span>
                    @endif
                </div>
                <button type="button" class="avatar-camera-btn" id="changeAvatarBtn"
                        title="Upload picture (JPG, PNG, WebP or GIF, up to 5 MB)" aria-label="Upload profile picture">
                    <i class="fa-solid fa-camera" aria-hidden="true"></i>
                </button>
                <input type="file" id="deptAvatarInput" hidden accept="image/jpeg,image/png,image/webp,image/gif">
            </div>

            <div class="profile-info">
                <h3 class="account-name" id="displayAccountName">{{ $dept_full_name }}</h3>
                <p class="account-role">{{ $dept_role_val }}</p>

                {{-- Personal Information --}}
                <ul class="profile-meta" aria-label="Personal information">
                    <li>
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        <div>
                            <span class="meta-label">Email</span>
                            <span class="meta-value" id="displayAccountEmail">{{ $dept_email_val }}</span>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-building" aria-hidden="true"></i>
                        <div>
                            <span class="meta-label">Department</span>
                            <span class="meta-value">{{ $dept_assigned }}</span>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-id-badge" aria-hidden="true"></i>
                        <div>
                            <span class="meta-label">Employee ID</span>
                            <span class="meta-value {{ $dept_employee_id ? '' : 'is-empty' }}" id="displayEmployeeId">{{ $dept_employee_id ?: 'Not set' }}</span>
                        </div>
                    </li>
                </ul>

                <button type="button" class="btn-profile-logout" id="deptProfileLogout">
                    <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Logout
                </button>
            </div>
        </aside>

        {{-- Right Profile Form Card --}}
        <div class="profile-card-right">
            <form id="profileUpdateForm" novalidate>

                {{-- Personal Information Section --}}
                <div class="form-section-intro">
                    <h3 class="form-section-header">Personal Information</h3>
                    <p class="form-section-desc">Update your name, email address and employee ID.</p>
                </div>

                <div class="form-grid">
                    <div class="form-group-item">
                        <label for="fullName">Full Name <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="form-input-box">
                            <input type="text" id="fullName" value="{{ $dept_full_name }}" placeholder="Enter full name" autocomplete="name" required>
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label for="email">Email <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="form-input-box">
                            <input type="email" id="email" value="{{ $dept_email_val }}" placeholder="Enter email address" autocomplete="email" required>
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label for="employeeId">Employee ID</label>
                        <div class="form-input-box">
                            <input type="text" id="employeeId" value="{{ $dept_employee_id }}" placeholder="Enter employee ID">
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label for="role">Role</label>
                        {{-- Role is display-only: changing it is an admin action --}}
                        <div class="form-input-box is-readonly">
                            <input type="text" id="role" value="{{ $dept_role_val }}" readonly aria-describedby="readonlyNote">
                            <i class="fa-solid fa-lock field-icon" aria-hidden="true"></i>
                        </div>
                    </div>

                    <div class="form-group-item span-2">
                        <label for="department">Assigned Department</label>
                        <div class="form-input-box is-readonly">
                            <input type="text" id="department" value="{{ $dept_assigned }}" readonly aria-describedby="readonlyNote">
                            <i class="fa-solid fa-lock field-icon" aria-hidden="true"></i>
                        </div>
                        <p class="field-hint" id="readonlyNote">Role and department are managed by the administrator.</p>
                    </div>
                </div>

                <div class="form-divider-line"></div>

                {{-- Change Password Section --}}
                <div class="form-section-intro">
                    <h3 class="form-section-header">Change Password</h3>
                    <p class="form-section-desc">Leave these fields blank to keep your current password.</p>
                </div>

                <div class="form-grid">
                    <div class="form-group-item span-2">
                        <label for="currentPassword">Current Password</label>
                        <div class="form-input-box">
                            <input type="password" id="currentPassword" placeholder="••••••••" autocomplete="current-password">
                            <button type="button" class="btn-toggle-password" data-target="currentPassword" aria-label="Show password" aria-pressed="false">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label for="newPassword">New Password</label>
                        <div class="form-input-box">
                            <input type="password" id="newPassword" placeholder="••••••••" autocomplete="new-password" aria-describedby="newPasswordHint">
                            <button type="button" class="btn-toggle-password" data-target="newPassword" aria-label="Show password" aria-pressed="false">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p class="field-hint" id="newPasswordHint">At least 6 characters.</p>
                    </div>

                    <div class="form-group-item">
                        <label for="confirmPassword">Confirm Password</label>
                        <div class="form-input-box">
                            <input type="password" id="confirmPassword" placeholder="••••••••" autocomplete="new-password" aria-describedby="confirmPasswordError">
                            <button type="button" class="btn-toggle-password" data-target="confirmPassword" aria-label="Show password" aria-pressed="false">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p class="field-error" id="confirmPasswordError" role="alert" hidden></p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save-profile" id="saveProfileBtn">Save Changes</button>
                </div>

            </form>
        </div>

    </div>

    {{-- Toast Notification --}}
    <div class="toast-notification" id="toastNotif" role="status" aria-live="polite">
        <i class="fa-solid fa-circle-check" id="toastIcon" aria-hidden="true"></i>
        <span id="toastMessage">Profile updated successfully.</span>
    </div>
    <form id="deptLogoutForm" method="POST" action="{{ route('logout') }}" style="display:none;">@csrf</form>
@endsection

@push('scripts')
<script>
    const URL_UPDATE = @json(route('department.profile.update'));
    const URL_AVATAR = @json(route('department.profile.avatar'));
    const CSRF_TOKEN = @json(csrf_token());
    const MAX_AVATAR_BYTES = 5 * 1024 * 1024;
    const MIN_PASSWORD_LENGTH = 6;

    // Show / hide password (real buttons, so they work from the keyboard too)
    document.querySelectorAll('.btn-toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', String(show));
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.querySelector('i').className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });
    });

    // Toast message display: success (green check) or error (red alert)
    let toastTimer = null;
    function showToast(msg, type = 'success') {
        const toastNotif = document.getElementById('toastNotif');
        const toastMessage = document.getElementById('toastMessage');
        const toastIcon = document.getElementById('toastIcon');
        if (!toastNotif || !toastMessage) return;
        toastMessage.textContent = msg;
        toastNotif.classList.toggle('is-error', type === 'error');
        if (toastIcon) {
            toastIcon.className = type === 'error' ? 'fa-solid fa-circle-exclamation' : 'fa-solid fa-circle-check';
        }
        toastNotif.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toastNotif.classList.remove('show'), 3500);
    }

    // Reads a JSON reply even for 4xx responses, so Laravel's validation
    // messages (e.g. "The email has already been taken.") reach the user.
    function readJson(res) {
        return res.json().catch(() => ({ success: false }));
    }

    function firstError(data, fallback) {
        if (data && data.errors) {
            const first = Object.values(data.errors)[0];
            if (Array.isArray(first) && first.length) return first[0];
        }
        return (data && data.message) || fallback;
    }

    // Inline check that the two new password fields match
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const confirmPasswordError = document.getElementById('confirmPasswordError');

    function setConfirmError(message) {
        if (!confirmPasswordError) return;
        confirmPasswordError.textContent = message || '';
        confirmPasswordError.hidden = !message;
        confirmPasswordInput.classList.toggle('is-invalid', !!message);
    }

    function checkPasswordMatch() {
        const a = newPasswordInput.value, b = confirmPasswordInput.value;
        setConfirmError(b && a !== b ? 'Passwords do not match.' : '');
    }
    newPasswordInput.addEventListener('input', checkPasswordMatch);
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);

    // Handle profile submit via AJAX
    const profileForm = document.getElementById('profileUpdateForm');
    const saveBtn = document.getElementById('saveProfileBtn');

    profileForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const fullNameVal = document.getElementById('fullName').value.trim();
        const emailInput = document.getElementById('email');
        const emailVal = emailInput.value.trim();
        const employeeIdVal = document.getElementById('employeeId').value.trim();
        const currentPasswordInput = document.getElementById('currentPassword');
        const currentPasswordVal = currentPasswordInput.value;
        const newPasswordVal = newPasswordInput.value;
        const confirmPasswordVal = confirmPasswordInput.value;

        if (!fullNameVal) return showToast('Please enter your full name.', 'error');
        if (!emailVal || !emailInput.checkValidity()) return showToast('Please enter a valid email address.', 'error');

        if (currentPasswordVal || newPasswordVal || confirmPasswordVal) {
            if (!newPasswordVal) return showToast('Enter a new password, or clear the password fields.', 'error');
            if (!currentPasswordVal) return showToast('Enter your current password to set a new one.', 'error');
            if (newPasswordVal.length < MIN_PASSWORD_LENGTH) return showToast(`New password must be at least ${MIN_PASSWORD_LENGTH} characters.`, 'error');
            if (newPasswordVal !== confirmPasswordVal) {
                setConfirmError('Passwords do not match.');
                return showToast('New password and confirmation do not match.', 'error');
            }
        }

        const formData = new FormData();
        formData.append('_token', CSRF_TOKEN);
        formData.append('full_name', fullNameVal);
        formData.append('email', emailVal);
        formData.append('employee_id', employeeIdVal);
        formData.append('current_password', currentPasswordVal);
        formData.append('new_password', newPasswordVal);
        formData.append('confirm_password', confirmPasswordVal);

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving…';

        fetch(URL_UPDATE, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        })
        .then(readJson)
        .then(data => {
            if (data.success) {
                document.getElementById('displayAccountName').textContent = fullNameVal;
                document.getElementById('displayAccountEmail').textContent = emailVal;
                const empEl = document.getElementById('displayEmployeeId');
                empEl.textContent = employeeIdVal || 'Not set';
                empEl.classList.toggle('is-empty', !employeeIdVal);
                // Clear password fields after a successful save
                [currentPasswordInput, newPasswordInput, confirmPasswordInput].forEach(i => { i.value = ''; });
                setConfirmError('');
                showToast(data.message || 'Profile updated successfully.');
            } else {
                showToast(firstError(data, 'Failed to update profile.'), 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error updating profile information.', 'error');
        })
        .finally(() => {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Changes';
        });
    });

    // Avatar change & upload handler
    const changeAvatarBtn = document.getElementById('changeAvatarBtn');
    const deptAvatarInput = document.getElementById('deptAvatarInput');
    const avatarCircle = document.getElementById('avatarCircle');

    // Shows the new photo, replacing the initials on a first upload
    function showAvatar(src) {
        let img = document.getElementById('avatarImage');
        if (!img) {
            img = document.createElement('img');
            img.id = 'avatarImage';
            img.className = 'avatar-img';
            img.alt = 'Profile photo';
            avatarCircle.innerHTML = '';
            avatarCircle.appendChild(img);
            avatarCircle.classList.add('has-image');
        }
        img.src = src;
    }

    if (changeAvatarBtn && deptAvatarInput) {
        changeAvatarBtn.addEventListener('click', () => deptAvatarInput.click());

        deptAvatarInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            this.value = ''; // allow choosing the same file again later
            if (!file) return;

            if (file.size > MAX_AVATAR_BYTES) {
                return showToast('Profile picture must be 5 MB or smaller.', 'error');
            }

            const formData = new FormData();
            formData.append('_token', CSRF_TOKEN);
            formData.append('avatar_file', file);

            changeAvatarBtn.disabled = true;
            changeAvatarBtn.classList.add('is-loading');

            fetch(URL_AVATAR, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            })
            .then(readJson)
            .then(data => {
                if (data.success && data.profile_image) {
                    showAvatar(data.profile_image);
                    window.navAvatarDbValue = data.profile_image;
                    if (typeof syncNavbarAvatar === 'function') syncNavbarAvatar(data.profile_image);
                    showToast(data.message || 'Profile picture updated successfully!');
                } else {
                    showToast(firstError(data, 'Failed to update profile picture.'), 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Error uploading profile picture.', 'error');
            })
            .finally(() => {
                changeAvatarBtn.disabled = false;
                changeAvatarBtn.classList.remove('is-loading');
            });
        });
    }

    document.getElementById('deptProfileLogout').addEventListener('click', () => {
        document.getElementById('deptLogoutForm').submit();
    });
</script>
@endpush
