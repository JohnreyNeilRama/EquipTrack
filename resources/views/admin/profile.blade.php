@extends('layouts.admin')

@section('title', 'EquipTrack - Profile')

@push('css')
{{-- The Admin layout's shared toast is styled here --}}
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}?v={{ filemtime(public_path('admin/css/admindashboard.css')) }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminprofile.css') }}?v={{ filemtime(public_path('admin/css/adminprofile.css')) }}">
@endpush

@php
    $display_avatar_url = $admin_profile_image
        ?: 'https://ui-avatars.com/api/?name=' . urlencode($admin_name) . '&background=385585&color=fff&size=300&bold=true';
@endphp

@section('content')
    <div class="profile-container">
        {{-- Left Column: Profile Summary --}}
        <div class="profile-summary card">
            <div class="profile-banner"></div>
            <div class="profile-img-wrapper">
                <div class="profile-img-container">
                    <img src="{{ $display_avatar_url }}" alt="Admin" class="profile-img" id="profileAvatarImg">
                </div>
                <button type="button" class="btn-upload-icon" id="btnUploadAvatar"
                        title="Upload picture (JPG, PNG, WebP or GIF, up to 5 MB)" aria-label="Upload profile picture">
                    <i class="fa-solid fa-camera" aria-hidden="true"></i>
                </button>
                <input type="file" id="avatarFileInput" hidden accept="image/jpeg,image/png,image/webp,image/gif">
            </div>
            <div class="profile-info">
                <h3 class="profile-name" id="cardProfileName">{{ $admin_name }}</h3>
                <p class="profile-role">System Administrator</p>
                <div class="profile-divider"></div>
                <ul class="profile-stats">
                    <li>
                        <span class="stat-icon" aria-hidden="true"><i class="fa-regular fa-envelope"></i></span>
                        <span class="stat-text">
                            <span class="stat-label">Email</span>
                            {{-- <wbr> after the @: a long address wraps there, not mid-word --}}
                            <span class="stat-value" id="cardProfileEmail">{!! str_replace('@', '@<wbr>', e($admin_email)) !!}</span>
                        </span>
                    </li>
                    <li>
                        <span class="stat-icon" aria-hidden="true"><i class="fa-solid fa-id-badge"></i></span>
                        <span class="stat-text">
                            <span class="stat-label">Employee ID</span>
                            <span class="stat-value {{ $admin_employee_id ? '' : 'is-empty' }}" id="cardEmployeeId">{{ $admin_employee_id ?: 'Not set' }}</span>
                        </span>
                    </li>
                </ul>
                <button type="button" class="btn-logout" id="profileLogoutBtn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</button>
            </div>
        </div>

        {{-- Right Column: Personal Information --}}
        <div class="profile-details card">
            <div class="details-header">
                <h2 class="details-title">Personal Information</h2>
                <p class="details-subtitle">View and update your profile details and settings.</p>
            </div>
            <form class="profile-form" id="personalInfoForm" novalidate>
                <div class="form-group">
                    <label for="adminFullName">Full Name</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-user input-icon"></i>
                        <input type="text" id="adminFullName" name="admin_full_name" class="form-control" value="{{ $admin_name }}" placeholder="Enter full name" autocomplete="name" maxlength="100" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="adminEmail">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" id="adminEmail" name="admin_email" class="form-control" value="{{ $admin_email }}" placeholder="Enter email address" autocomplete="email" maxlength="100" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="adminEmployeeId">Employee ID</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-id-badge input-icon"></i>
                            <input type="text" id="adminEmployeeId" name="admin_employee_id" class="form-control" value="{{ $admin_employee_id }}" placeholder="Enter employee ID" maxlength="50" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="adminRole">Role</label>
                    {{-- Display-only: every admin account is a System Administrator --}}
                    <div class="input-wrapper is-locked">
                        <i class="fa-solid fa-briefcase input-icon"></i>
                        <input type="text" id="adminRole" class="form-control" value="System Administrator" readonly data-locked>
                        <i class="fa-solid fa-lock lock-icon" aria-hidden="true"></i>
                    </div>
                </div>

                <div class="form-actions" id="formActions">
                    <button type="button" class="btn-edit-profile" id="btnEditProfile">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Profile
                    </button>
                    <button type="button" class="btn-cancel" id="btnCancelEdit" style="display: none;">Cancel</button>
                    <button type="submit" class="btn-save" id="btnSaveEdit" style="display: none;">Save Changes</button>
                </div>
            </form>

            {{-- Change Password --}}
            <div class="profile-section-divider" role="presentation"></div>
            <div class="details-header">
                <h2 class="details-title">Change Password</h2>
                <p class="details-subtitle">Enter your current password, then choose a new one. Other devices signed in to your account will be signed out.</p>
            </div>
            <form class="profile-form" id="changePasswordForm" novalidate>
                <div class="form-group">
                    <label for="currentPassword">Current Password</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="currentPassword" name="current_password" class="form-control password-control" placeholder="Enter current password" autocomplete="current-password" required>
                        <button type="button" class="btn-toggle-password" data-target="currentPassword" aria-label="Show password" aria-pressed="false"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </div>

                <div class="form-row password-row">
                    <div class="form-group">
                        <label for="newPassword">New Password</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-key input-icon"></i>
                            <input type="password" id="newPassword" name="new_password" class="form-control password-control" placeholder="Enter new password" autocomplete="new-password" aria-describedby="passwordChecklist" required>
                            <button type="button" class="btn-toggle-password" data-target="newPassword" aria-label="Show password" aria-pressed="false"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">Confirm New Password</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-key input-icon"></i>
                            <input type="password" id="confirmPassword" name="confirm_password" class="form-control password-control" placeholder="Re-enter new password" autocomplete="new-password" aria-describedby="passwordChecklist" required>
                            <button type="button" class="btn-toggle-password" data-target="confirmPassword" aria-label="Show password" aria-pressed="false"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                </div>

                <ul class="password-checklist" id="passwordChecklist" aria-live="polite">
                    <li data-rule="length"><i class="fa-solid fa-circle-check"></i> At least 8 characters</li>
                    <li data-rule="letter"><i class="fa-solid fa-circle-check"></i> Includes a letter</li>
                    <li data-rule="number"><i class="fa-solid fa-circle-check"></i> Includes a number</li>
                    <li data-rule="match"><i class="fa-solid fa-circle-check"></i> Passwords match</li>
                </ul>

                <p class="password-message" id="passwordMessage" role="alert" hidden></p>

                <div class="form-actions password-actions">
                    <button type="button" class="btn-cancel" id="btnClearPassword">Clear</button>
                    <button type="submit" class="btn-save" id="btnUpdatePassword"><i class="fa-solid fa-key"></i> Save Password</button>
                </div>
            </form>
        </div>
    </div>

    <form id="logoutForm" method="POST" action="{{ route('logout') }}" style="display:none;">@csrf</form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const URL_UPDATE = @json(route('admin.profile.update'));
        const URL_PASSWORD = @json(route('admin.profile.password'));
        const URL_AVATAR = @json(route('admin.profile.avatar'));
        const CSRF_TOKEN = @json(csrf_token());
        const MAX_AVATAR_BYTES = 5 * 1024 * 1024;

        // The Admin layout's shared toast (showNotification, admin-shell.js)
        function showToast(msg, type = 'success') {
            showNotification(type === 'error' ? 'Error' : 'Success', msg, type);
        }

        // Reads a JSON reply even for 4xx responses, so Laravel's validation
        // messages (e.g. "The admin email has already been taken.") reach the user.
        function readJson(res) {
            return res.json().catch(() => ({ success: false })).then(body => ({ status: res.status, body }));
        }

        function firstError(body, fallback) {
            if (body && body.errors) {
                const first = Object.values(body.errors)[0];
                if (Array.isArray(first) && first.length) return first[0];
            }
            return (body && body.message) || fallback;
        }

        // Profile picture upload
        const btnUploadAvatar = document.getElementById('btnUploadAvatar');
        const avatarFileInput = document.getElementById('avatarFileInput');
        const profileAvatarImg = document.getElementById('profileAvatarImg');

        btnUploadAvatar.addEventListener('click', () => avatarFileInput.click());

        avatarFileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            this.value = ''; // allow choosing the same file again later
            if (!file) return;

            if (file.size > MAX_AVATAR_BYTES) {
                return showToast('Profile picture must be 5 MB or smaller.', 'error');
            }

            const formData = new FormData();
            formData.append('_token', CSRF_TOKEN);
            formData.append('admin_avatar_file', file);

            btnUploadAvatar.disabled = true;

            fetch(URL_AVATAR, { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData })
                .then(readJson)
                .then(({ body }) => {
                    if (body.success && body.image_url) {
                        profileAvatarImg.src = body.image_url;
                        window.navAvatarDbValue = body.image_url;
                        if (typeof syncNavbarAvatar === 'function') syncNavbarAvatar(body.image_url);
                        showToast(body.message || 'Profile picture updated successfully!');
                    } else {
                        showToast(firstError(body, 'Failed to update profile picture.'), 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('Error uploading profile picture.', 'error');
                })
                .finally(() => {
                    btnUploadAvatar.disabled = false;
                });
        });

        // Personal Information: read-only until Edit Profile is clicked.
        // The role (data-locked) always stays read-only.
        const personalInfoForm = document.getElementById('personalInfoForm');
        const btnEditProfile = document.getElementById('btnEditProfile');
        const btnCancelEdit = document.getElementById('btnCancelEdit');
        const btnSaveEdit = document.getElementById('btnSaveEdit');
        const editableInputs = personalInfoForm.querySelectorAll('.form-control:not([data-locked])');

        let initialFormState = {};

        function captureFormState() {
            editableInputs.forEach(input => {
                initialFormState[input.name] = input.value;
            });
        }

        function setFormReadOnly(isReadOnly) {
            editableInputs.forEach(input => {
                if (isReadOnly) {
                    input.setAttribute('readonly', 'readonly');
                } else {
                    input.removeAttribute('readonly');
                }
            });

            btnEditProfile.style.display = isReadOnly ? 'inline-flex' : 'none';
            btnCancelEdit.style.display = isReadOnly ? 'none' : 'inline-flex';
            btnSaveEdit.style.display = isReadOnly ? 'none' : 'inline-flex';
        }

        captureFormState();

        btnEditProfile.addEventListener('click', () => {
            captureFormState();
            setFormReadOnly(false);
            document.getElementById('adminFullName').focus();
        });

        btnCancelEdit.addEventListener('click', () => {
            editableInputs.forEach(input => {
                if (Object.prototype.hasOwnProperty.call(initialFormState, input.name)) {
                    input.value = initialFormState[input.name];
                }
            });
            setFormReadOnly(true);
        });

        personalInfoForm.addEventListener('submit', (e) => {
            e.preventDefault();
            // Enter in a read-only field must not save anything.
            if (btnSaveEdit.style.display === 'none') return;

            const fullNameInput = document.getElementById('adminFullName');
            const emailInput = document.getElementById('adminEmail');
            const employeeIdInput = document.getElementById('adminEmployeeId');
            const fullName = fullNameInput.value.trim();
            const email = emailInput.value.trim();
            const employeeId = employeeIdInput.value.trim();

            if (!fullName) { fullNameInput.focus(); return showToast('Please enter your full name.', 'error'); }
            if (!email || !emailInput.checkValidity()) { emailInput.focus(); return showToast('Please enter a valid email address.', 'error'); }

            const formData = new FormData();
            formData.append('_token', CSRF_TOKEN);
            formData.append('admin_full_name', fullName);
            formData.append('admin_email', email);
            formData.append('admin_employee_id', employeeId);

            btnSaveEdit.disabled = true;
            btnSaveEdit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            fetch(URL_UPDATE, { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData })
                .then(readJson)
                .then(({ status, body }) => {
                    if (status === 429) throw new Error('Too many attempts. Please wait a minute and try again.');
                    if (status >= 400 || !body.success) throw new Error(firstError(body, 'Failed to update profile.'));

                    fullNameInput.value = fullName;
                    emailInput.value = email;
                    // A cleared Employee ID keeps the saved one (server rule).
                    const savedEmployeeId = employeeId || initialFormState['admin_employee_id'] || '';
                    employeeIdInput.value = savedEmployeeId;
                    captureFormState();
                    setFormReadOnly(true);

                    document.getElementById('cardProfileName').textContent = fullName;
                    const emailEl = document.getElementById('cardProfileEmail');
                    emailEl.textContent = '';
                    email.split('@').forEach((part, i) => {
                        if (i > 0) emailEl.append('@', document.createElement('wbr'));
                        emailEl.append(part);
                    });
                    const empEl = document.getElementById('cardEmployeeId');
                    empEl.textContent = savedEmployeeId || 'Not set';
                    empEl.classList.toggle('is-empty', !savedEmployeeId);

                    showToast(body.message || 'Profile information updated successfully!');
                })
                .catch(err => showToast(err.message || 'Error updating profile information.', 'error'))
                .finally(() => {
                    btnSaveEdit.disabled = false;
                    btnSaveEdit.textContent = 'Save Changes';
                });
        });

        // Change Password: live checklist, show/hide toggles, and the request.
        const passwordForm = document.getElementById('changePasswordForm');
        const currentPasswordInput = document.getElementById('currentPassword');
        const newPasswordInput = document.getElementById('newPassword');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const passwordMessage = document.getElementById('passwordMessage');
        const btnUpdatePassword = document.getElementById('btnUpdatePassword');
        const checklist = document.getElementById('passwordChecklist');

        // Same policy as the server (Password::defaults in AppServiceProvider).
        const PASSWORD_RULES = {
            length: (pw) => pw.length >= 8,
            letter: (pw) => /[A-Za-z]/.test(pw),
            number: (pw) => /\d/.test(pw),
            match: (pw, confirm) => pw !== '' && pw === confirm,
        };

        function updateChecklist() {
            const pw = newPasswordInput.value;
            const confirm = confirmPasswordInput.value;
            checklist.querySelectorAll('li').forEach(li => {
                li.classList.toggle('is-met', PASSWORD_RULES[li.dataset.rule](pw, confirm));
            });
        }

        function showPasswordMessage(text, type) {
            passwordMessage.textContent = text;
            passwordMessage.className = 'password-message is-' + type;
            passwordMessage.hidden = !text;
        }

        passwordForm.querySelectorAll('.btn-toggle-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.target);
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                btn.querySelector('i').className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            });
        });

        [newPasswordInput, confirmPasswordInput].forEach(input => input.addEventListener('input', () => {
            updateChecklist();
            if (!passwordMessage.hidden) showPasswordMessage('', 'error');
        }));

        document.getElementById('btnClearPassword').addEventListener('click', () => {
            passwordForm.reset();
            updateChecklist();
            showPasswordMessage('', 'error');
        });

        passwordForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const pw = newPasswordInput.value;

            if (!currentPasswordInput.value) { currentPasswordInput.focus(); return showPasswordMessage('Enter your current password.', 'error'); }
            if (!PASSWORD_RULES.length(pw) || !PASSWORD_RULES.letter(pw) || !PASSWORD_RULES.number(pw)) {
                newPasswordInput.focus();
                return showPasswordMessage('Your new password must be at least 8 characters and include a letter and a number.', 'error');
            }
            if (!PASSWORD_RULES.match(pw, confirmPasswordInput.value)) { confirmPasswordInput.focus(); return showPasswordMessage('New password and confirm password do not match.', 'error'); }

            const formData = new FormData(passwordForm);
            formData.append('_token', CSRF_TOKEN);
            btnUpdatePassword.disabled = true;
            btnUpdatePassword.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            fetch(URL_PASSWORD, { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData })
                .then(readJson)
                .then(({ status, body }) => {
                    if (status === 429) throw new Error('Too many attempts. Please wait a minute and try again.');
                    if (status >= 400 || !body.success) throw new Error(firstError(body, 'Your password could not be updated.'));
                    passwordForm.reset();
                    updateChecklist();
                    showPasswordMessage(body.message, 'success');
                    showToast('Password updated successfully!');
                })
                .catch(err => showPasswordMessage(err.message, 'error'))
                .finally(() => {
                    btnUpdatePassword.disabled = false;
                    btnUpdatePassword.innerHTML = '<i class="fa-solid fa-key"></i> Save Password';
                });
        });

        document.getElementById('profileLogoutBtn').addEventListener('click', () => {
            document.getElementById('logoutForm').submit();
        });
    });
</script>
@endpush
