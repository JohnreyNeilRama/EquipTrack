@extends('layouts.user')

@section('title', 'EquipTrack - User Profile')

@push('css')
<link rel="stylesheet" href="{{ asset('user/css/userprofile.css') }}?v={{ filemtime(public_path('user/css/userprofile.css')) }}">
@endpush

@php
    $display_avatar_url = $user_profile_image
        ?: 'https://ui-avatars.com/api/?name=' . urlencode($full_name) . '&background=385585&color=fff&size=300&bold=true';
@endphp

@section('content')
    <div class="profile-container">
        {{-- Left Column: Profile Summary --}}
        <div class="profile-summary card">
            <div class="profile-banner"></div>
            <div class="profile-img-wrapper">
                <div class="profile-img-container">
                    <img src="{{ $display_avatar_url }}" alt="User" class="profile-img">
                </div>
                <button type="button" class="btn-upload-icon" onclick="document.getElementById('profilePicInput').click()" title="Upload Picture">
                    <i class="fa-solid fa-camera"></i>
                </button>
                <input type="file" id="profilePicInput" style="display: none;" accept="image/*">
            </div>
            <div class="profile-info">
                <h3 class="profile-name">{{ $full_name }}</h3>
                <p class="profile-role">{{ $user_role }}</p>
                <div class="profile-divider"></div>
                <ul class="profile-stats">
                    <li>
                        <span class="stat-label">Active Borrows</span>
                        <span class="stat-num">0</span>
                    </li>
                    <li>
                        <span class="stat-label">Total Requests</span>
                        <span class="stat-num">0</span>
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
            <form class="profile-form" id="personalInfoForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-user input-icon"></i>
                            <input type="text" name="first_name" class="form-control" value="{{ $first_name }}" placeholder="Enter first name" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-user input-icon"></i>
                            <input type="text" name="last_name" class="form-control" value="{{ $last_name }}" placeholder="Enter last name" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope input-icon"></i>
                        <input type="email" name="email" class="form-control" value="{{ $user_email }}" placeholder="Enter email address" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label>Year Level</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-graduation-cap input-icon"></i>
                        <select name="year_level" class="form-control form-select" disabled>
                            <option value="" @selected(empty($user_year_level) || $user_year_level === 'N/A') disabled>Select Year Level</option>
                            @foreach (['1st Year', '2nd Year', '3rd Year', '4th Year'] as $opt)
                                <option value="{{ $opt }}" @selected(strcasecmp(trim($user_year_level), $opt) === 0 || str_contains($user_year_level, $opt))>{{ $opt }}</option>
                            @endforeach
                            @if ($user_role === 'Faculty')
                                <option value="N/A" @selected($user_year_level === 'N/A')>N/A</option>
                            @endif
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Home Address</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-map-pin input-icon"></i>
                        <input type="text" name="address" class="form-control" value="{{ $user_address }}" placeholder="Enter full address" readonly>
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
                    <button type="submit" class="btn-save" id="btnUpdatePassword"><i class="fa-solid fa-key"></i> Update Password</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Toast Notification --}}
    <div class="toast-notification" id="toastNotif" style="position: fixed; bottom: 24px; right: 24px; background: #1e293b; color: #fff; padding: 12px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 10px; opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 9999;">
        <i class="fa-solid fa-circle-check" style="color: #10b981;"></i>
        <span id="toastMsg">Profile picture updated successfully!</span>
    </div>
    <form id="logoutForm" method="POST" action="{{ route('logout') }}" style="display:none;">@csrf</form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const URL_UPDATE = @json(route('user.profile.update'));
        const URL_AVATAR = @json(route('user.profile.avatar'));
        const CSRF_TOKEN = @json(csrf_token());

        // Profile Avatar Image Upload & Sync
        const profilePicInput = document.getElementById('profilePicInput');
        const profileImg = document.querySelector('.profile-img-container .profile-img');

        if (profilePicInput && profileImg) {
            profilePicInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const formData = new FormData();
                    formData.append('_token', CSRF_TOKEN);
                    formData.append('profile_image_file', file);

                    fetch(URL_AVATAR, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            profileImg.src = data.image_url;
                            window.navAvatarDbValue = data.image_url;
                            if (typeof syncNavbarAvatar === 'function') syncNavbarAvatar(data.image_url);
                            showToast('Profile picture uploaded and saved to database!');
                        } else {
                            alert(data.message || 'Error uploading profile picture');
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        alert('An error occurred while uploading your profile picture.');
                    });
                }
            });
        }

        // Personal Information Edit / Read-only Mode Toggle Logic
        const personalInfoForm = document.getElementById('personalInfoForm');
        const btnEditProfile = document.getElementById('btnEditProfile');
        const btnCancelEdit = document.getElementById('btnCancelEdit');
        const btnSaveEdit = document.getElementById('btnSaveEdit');

        let initialFormState = {};

        function captureFormState() {
            if (!personalInfoForm) return;
            const inputs = personalInfoForm.querySelectorAll('.form-control');
            inputs.forEach(input => {
                initialFormState[input.name] = input.value;
            });
        }

        function setFormReadOnly(isReadOnly) {
            if (!personalInfoForm) return;
            const inputs = personalInfoForm.querySelectorAll('.form-control');
            inputs.forEach(input => {
                if (isReadOnly) {
                    input.setAttribute('readonly', 'readonly');
                    if (input.tagName === 'SELECT') {
                        input.setAttribute('disabled', 'disabled');
                    }
                } else {
                    input.removeAttribute('readonly');
                    if (input.tagName === 'SELECT') {
                        input.removeAttribute('disabled');
                    }
                }
            });

            if (isReadOnly) {
                if (btnEditProfile) btnEditProfile.style.display = 'inline-flex';
                if (btnCancelEdit) btnCancelEdit.style.display = 'none';
                if (btnSaveEdit) btnSaveEdit.style.display = 'none';
            } else {
                if (btnEditProfile) btnEditProfile.style.display = 'none';
                if (btnCancelEdit) btnCancelEdit.style.display = 'inline-flex';
                if (btnSaveEdit) btnSaveEdit.style.display = 'inline-flex';
            }
        }

        captureFormState();

        if (btnEditProfile) {
            btnEditProfile.addEventListener('click', () => {
                captureFormState();
                setFormReadOnly(false);
                const firstInput = personalInfoForm.querySelector('input[name="first_name"]');
                if (firstInput) firstInput.focus();
            });
        }

        if (btnCancelEdit) {
            btnCancelEdit.addEventListener('click', () => {
                const inputs = personalInfoForm.querySelectorAll('.form-control');
                inputs.forEach(input => {
                    if (initialFormState.hasOwnProperty(input.name)) {
                        input.value = initialFormState[input.name];
                    }
                });
                setFormReadOnly(true);
            });
        }

        if (personalInfoForm) {
            personalInfoForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(personalInfoForm);
                formData.append('_token', CSRF_TOKEN);
                fetch(URL_UPDATE, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        captureFormState();
                        setFormReadOnly(true);
                        showToast(data.message || 'Personal information updated successfully!');

                        const profileNameHeading = document.querySelector('.profile-info .profile-name');
                        if (profileNameHeading && formData.get('first_name')) {
                            profileNameHeading.textContent = (formData.get('first_name') + ' ' + (formData.get('last_name') || '')).trim();
                        }
                    } else {
                        alert(data.message || 'Error updating profile');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('An error occurred while updating your profile.');
                });
            });
        }

        // Change Password: live checklist, show/hide toggles, and the request.
        const URL_PASSWORD = @json(route('user.profile.password'));
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
            btnUpdatePassword.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';

            fetch(URL_PASSWORD, { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData })
                .then(res => res.json().catch(() => ({})).then(body => ({ status: res.status, body })))
                .then(({ status, body }) => {
                    if (status === 429) throw new Error('Too many attempts. Please wait a minute and try again.');
                    if (status >= 400 || !body.success) {
                        const first = body.errors ? Object.values(body.errors)[0] : null;
                        throw new Error((Array.isArray(first) ? first[0] : first) || body.message || 'Your password could not be updated.');
                    }
                    passwordForm.reset();
                    updateChecklist();
                    showPasswordMessage(body.message, 'success');
                    showToast('Password updated successfully!');
                })
                .catch(err => showPasswordMessage(err.message, 'error'))
                .finally(() => {
                    btnUpdatePassword.disabled = false;
                    btnUpdatePassword.innerHTML = '<i class="fa-solid fa-key"></i> Update Password';
                });
        });

        // Logout button (legacy was a link to logout.php; now a POST form)
        document.getElementById('profileLogoutBtn').addEventListener('click', () => {
            document.getElementById('logoutForm').submit();
        });

        // Toast Helper
        function showToast(msg) {
            const toast = document.getElementById('toastNotif');
            const toastMsg = document.getElementById('toastMsg');
            if (toast && toastMsg) {
                toastMsg.textContent = msg;
                toast.style.opacity = '1';
                toast.style.visibility = 'visible';
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.visibility = 'hidden';
                }, 3500);
            }
        }
    });
</script>
@endpush
