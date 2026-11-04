@extends('layouts.department')

@section('title', 'EquipTrack - Profile')

@push('css')
<link rel="stylesheet" href="{{ asset('departments/css/profile.css') }}?v={{ filemtime(public_path('departments/css/profile.css')) }}">
@endpush

@php
    $deptAccount = auth('dept')->user();
@endphp

@section('content')
    <div class="profile-container">
        {{-- Left Column: Profile Summary --}}
        <div class="profile-summary card">
            <div class="profile-banner"></div>
            <div class="profile-img-wrapper">
                <div class="profile-img-container" id="avatarCircle">
                    @if ($dept_profile_img)
                        <img src="{{ $dept_profile_img }}" alt="Profile photo" class="profile-img" id="avatarImage">
                    @else
                        <span class="profile-img-initials" id="avatarInitials">{{ $deptAccount?->initials() ?? 'DP' }}</span>
                    @endif
                </div>
                <button type="button" class="btn-upload-icon" id="changeAvatarBtn"
                        title="Upload picture (JPG, PNG, WebP or GIF, up to 5 MB)" aria-label="Upload profile picture">
                    <i class="fa-solid fa-camera" aria-hidden="true"></i>
                </button>
                <input type="file" id="deptAvatarInput" hidden accept="image/jpeg,image/png,image/webp,image/gif">
            </div>
            <div class="profile-info">
                <h3 class="profile-name" id="displayAccountName">{{ $dept_full_name }}</h3>
                <p class="profile-role">{{ $dept_role_val }}</p>
                <div class="profile-divider"></div>
                <ul class="profile-stats">
                    <li>
                        <span class="stat-icon" aria-hidden="true"><i class="fa-solid fa-building-columns"></i></span>
                        <span class="stat-text">
                            <span class="stat-label">Department</span>
                            <span class="stat-value">{{ $dept_assigned }}</span>
                        </span>
                    </li>
                    <li>
                        <span class="stat-icon" aria-hidden="true"><i class="fa-solid fa-id-badge"></i></span>
                        <span class="stat-text">
                            <span class="stat-label">Employee ID</span>
                            <span class="stat-value {{ $dept_employee_id ? '' : 'is-empty' }}" id="displayEmployeeId">{{ $dept_employee_id ?: 'Not set' }}</span>
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
                    <label for="fullName">Full Name</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-user input-icon"></i>
                        <input type="text" id="fullName" name="full_name" class="form-control" value="{{ $dept_full_name }}" placeholder="Enter full name" autocomplete="name" maxlength="255" readonly>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" id="email" name="email" class="form-control" value="{{ $dept_email_val }}" placeholder="Enter email address" autocomplete="email" maxlength="100" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="employeeId">Employee ID</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-id-badge input-icon"></i>
                            <input type="text" id="employeeId" name="employee_id" class="form-control" value="{{ $dept_employee_id }}" placeholder="Enter employee ID" maxlength="100" readonly>
                        </div>
                    </div>
                </div>

                {{-- Role and department are display-only: changing them is an admin action --}}
                <div class="form-row">
                    <div class="form-group">
                        <label for="role">Role</label>
                        <div class="input-wrapper is-locked">
                            <i class="fa-solid fa-briefcase input-icon"></i>
                            <input type="text" id="role" class="form-control" value="{{ $dept_role_val }}" readonly data-locked aria-describedby="lockedNote">
                            <i class="fa-solid fa-lock lock-icon" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="department">Assigned Department</label>
                        <div class="input-wrapper is-locked">
                            <i class="fa-solid fa-building input-icon"></i>
                            <input type="text" id="department" class="form-control" value="{{ $dept_assigned }}" readonly data-locked aria-describedby="lockedNote">
                            <i class="fa-solid fa-lock lock-icon" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
                <p class="locked-note" id="lockedNote">Role and department are managed by the administrator.</p>

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

    {{-- Toast Notification (same look as the User profile's) --}}
    <div class="toast-notification" id="toastNotif" role="status" aria-live="polite" style="position: fixed; bottom: 24px; right: 24px; background: #1e293b; color: #fff; padding: 12px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 10px; opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 9999;">
        <i class="fa-solid fa-circle-check" id="toastIcon" style="color: #10b981;" aria-hidden="true"></i>
        <span id="toastMsg">Profile updated successfully.</span>
    </div>
    <form id="logoutForm" method="POST" action="{{ route('logout') }}" style="display:none;">@csrf</form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const URL_UPDATE = @json(route('department.profile.update'));
        const URL_PASSWORD = @json(route('department.profile.password'));
        const URL_AVATAR = @json(route('department.profile.avatar'));
        const CSRF_TOKEN = @json(csrf_token());
        const MAX_AVATAR_BYTES = 5 * 1024 * 1024;

        // Toast: success (green check) or error (red alert)
        let toastTimer = null;
        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toastNotif');
            const toastMsg = document.getElementById('toastMsg');
            const toastIcon = document.getElementById('toastIcon');
            if (!toast || !toastMsg) return;
            toastMsg.textContent = msg;
            toast.classList.toggle('is-error', type === 'error');
            toastIcon.className = type === 'error' ? 'fa-solid fa-circle-exclamation' : 'fa-solid fa-circle-check';
            toast.style.opacity = '1';
            toast.style.visibility = 'visible';
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.visibility = 'hidden';
            }, 3500);
        }

        // Reads a JSON reply even for 4xx responses, so Laravel's validation
        // messages (e.g. "The email has already been taken.") reach the user.
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

        // Profile picture upload (initials are replaced on the first upload)
        const changeAvatarBtn = document.getElementById('changeAvatarBtn');
        const deptAvatarInput = document.getElementById('deptAvatarInput');
        const avatarCircle = document.getElementById('avatarCircle');

        function showAvatar(src) {
            let img = document.getElementById('avatarImage');
            if (!img) {
                img = document.createElement('img');
                img.id = 'avatarImage';
                img.className = 'profile-img';
                img.alt = 'Profile photo';
                avatarCircle.innerHTML = '';
                avatarCircle.appendChild(img);
            }
            img.src = src;
        }

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

            fetch(URL_AVATAR, { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData })
                .then(readJson)
                .then(({ body }) => {
                    if (body.success && body.profile_image) {
                        showAvatar(body.profile_image);
                        window.navAvatarDbValue = body.profile_image;
                        if (typeof syncNavbarAvatar === 'function') syncNavbarAvatar(body.profile_image);
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
                    changeAvatarBtn.disabled = false;
                });
        });

        // Personal Information: read-only until Edit Profile is clicked.
        // Role and department (data-locked) always stay read-only.
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
            document.getElementById('fullName').focus();
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

            const fullNameInput = document.getElementById('fullName');
            const emailInput = document.getElementById('email');
            const fullName = fullNameInput.value.trim();
            const email = emailInput.value.trim();
            const employeeId = document.getElementById('employeeId').value.trim();

            if (!fullName) { fullNameInput.focus(); return showToast('Please enter your full name.', 'error'); }
            if (!email || !emailInput.checkValidity()) { emailInput.focus(); return showToast('Please enter a valid email address.', 'error'); }

            const formData = new FormData();
            formData.append('_token', CSRF_TOKEN);
            formData.append('full_name', fullName);
            formData.append('email', email);
            formData.append('employee_id', employeeId);

            btnSaveEdit.disabled = true;
            btnSaveEdit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            fetch(URL_UPDATE, { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData })
                .then(readJson)
                .then(({ status, body }) => {
                    if (status === 429) throw new Error('Too many attempts. Please wait a minute and try again.');
                    if (status >= 400 || !body.success) throw new Error(firstError(body, 'Failed to update profile.'));

                    fullNameInput.value = fullName;
                    emailInput.value = email;
                    captureFormState();
                    setFormReadOnly(true);

                    document.getElementById('displayAccountName').textContent = fullName;
                    const empEl = document.getElementById('displayEmployeeId');
                    empEl.textContent = employeeId || 'Not set';
                    empEl.classList.toggle('is-empty', !employeeId);

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
