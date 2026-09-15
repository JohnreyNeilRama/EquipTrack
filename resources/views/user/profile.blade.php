@extends('layouts.user')

@section('title', 'EquipTrack - User Profile')

@push('css')
<link rel="stylesheet" href="{{ asset('user/css/userprofile.css') }}">
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
