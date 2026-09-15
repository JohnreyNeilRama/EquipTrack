@extends('layouts.guest')

@section('title', 'EquipTrack - Register')

@push('css')
<link rel="stylesheet" href="{{ asset('ccs/register.css') }}?v=2.2">
@endpush

@section('content')
    @php $showForm = $errors->any(); @endphp
    <div class="register-container">
        {{-- Choose a Role Container --}}
        <div class="role-selection-container {{ $showForm ? 'hidden' : '' }}" id="roleSelectionContainer">
            <div class="role-selection-card">
                <a href="/" class="role-close-btn" id="roleCloseBtn" aria-label="Close role selection">
                    <i class="fa-solid fa-xmark"></i>
                </a>
                <h2 class="role-title">What are you?</h2>
                <div class="roles-grid">
                    <div class="role-option" id="roleStudent" role="button" tabindex="0"><span class="role-label">Student</span><div class="role-circle"><img src="{{ asset('images/login_student.jpg') }}" alt="Student"></div></div>
                    <div class="role-option" id="roleTeacher" role="button" tabindex="0"><span class="role-label">Teacher</span><div class="role-circle"><img src="{{ asset('images/login_teacher.jpg') }}" alt="Teacher"></div></div>
                </div>
            </div>
        </div>

        {{-- Registration Form Panel --}}
        <div class="register-panel {{ $showForm ? 'active' : '' }}" id="registerPanel">
            <a href="#" class="back-link" id="backToRole">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>

            <div class="register-header">
                <h1 id="registerTitle">Register</h1>
                <p id="registerSubtitle">Enter your credentials to register.</p>
            </div>

            @if ($errors->any())
                <div style="color: #ef4444; background: #fee2e2; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; margin-bottom: 15px; border: 1px solid #fca5a5;">
                    <i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first() }}
                </div>
            @endif

            <form action="/register" method="POST" class="register-form">
                @csrf
                <input type="hidden" id="selected_role" name="role" value="{{ old('role', '') }}">

                <div class="form-row">
                    <div class="form-group half-width">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                    </div>
                    <div class="form-group half-width">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group half-width" id="idNumberGroup">
                        <label for="id_number" id="idNumberLabel">ID Number</label>
                        <input type="text" id="id_number" name="id_number" value="{{ old('id_number') }}" required>
                    </div>
                    <div class="form-group half-width" id="yearLevelGroup">
                        <label for="year_level">Year &amp; Level</label>
                        <select id="year_level" name="year_level" required>
                            <option value="" disabled {{ old('year_level') === '' ? 'selected' : '' }}></option>
                            @foreach (['1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('year_level') == $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="{{ old('address') }}" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" required>
                        <i class="fa-regular fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <button type="submit" class="register-btn" id="registerBtn">
                    <span class="btn-text">Register</span>
                    <div class="spinner"></div>
                </button>

                <div class="login-link">
                    Already have an account? <a href="/login" id="loginLink">Login</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // DOM Elements
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    const roleSelectionContainer = document.getElementById('roleSelectionContainer');
    const registerPanel = document.getElementById('registerPanel');
    const selectedRoleInput = document.getElementById('selected_role');
    const registerTitle = document.getElementById('registerTitle');
    const registerSubtitle = document.getElementById('registerSubtitle');
    const yearLevelGroup = document.getElementById('yearLevelGroup');
    const yearLevelSelect = document.getElementById('year_level');
    const idNumberLabel = document.getElementById('idNumberLabel');
    const backToRole = document.getElementById('backToRole');
    const roleStudentBtn = document.getElementById('roleStudent');
    const roleTeacherBtn = document.getElementById('roleTeacher');
    const loginLink = document.getElementById('loginLink');
    const registerForm = document.querySelector('.register-form');
    const registerBtn = document.getElementById('registerBtn');
    const roleCloseBtn = document.getElementById('roleCloseBtn');

    // Toggle Password Visibility
    if (togglePassword && password) {
        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    }

    // Role Selection Logic
    function selectRole(role) {
        if (selectedRoleInput) {
            selectedRoleInput.value = role;
        }

        const idInput = document.getElementById('id_number');

        if (role === 'student') {
            if (registerTitle) registerTitle.textContent = 'Register as a Student';
            if (registerSubtitle) registerSubtitle.textContent = 'Enter your student credentials to register.';
            if (idNumberLabel) idNumberLabel.textContent = 'ID Number';
            if (yearLevelGroup) yearLevelGroup.style.display = 'block';
            if (yearLevelSelect) {
                yearLevelSelect.setAttribute('required', 'required');
            }
            if (idInput) {
                idInput.placeholder = 'e.g., 20230123';
                idInput.pattern = '\\d{8}';
                idInput.title = 'Student ID must be exactly 8 digits.';
                idInput.maxLength = 8;
            }
        } else if (role === 'teacher') {
            if (registerTitle) registerTitle.textContent = 'Register as a Teacher';
            if (registerSubtitle) registerSubtitle.textContent = 'Enter your teacher credentials to register.';
            if (idNumberLabel) idNumberLabel.textContent = 'ID Number';
            if (yearLevelGroup) yearLevelGroup.style.display = 'none';
            if (yearLevelSelect) {
                yearLevelSelect.removeAttribute('required');
                yearLevelSelect.value = '';
            }
            if (idInput) {
                idInput.placeholder = 'e.g., T-00987';
                idInput.removeAttribute('pattern');
                idInput.removeAttribute('title');
                idInput.removeAttribute('maxlength');
            }
        }

        // Animate transition
        if (roleSelectionContainer) roleSelectionContainer.classList.add('hidden');
        setTimeout(() => {
            if (registerPanel) registerPanel.classList.add('active');
        }, 100);
    }

    // Re-apply saved role after a validation error (form is already shown)
    const restoredRole = @json(old('role'));
    if (restoredRole) selectRole(restoredRole);

    // Event listeners for role choice buttons
    const handleRoleKeydown = (e, role) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            selectRole(role);
        }
    };

    if (roleStudentBtn) {
        roleStudentBtn.addEventListener('click', () => selectRole('student'));
        roleStudentBtn.addEventListener('keydown', (e) => handleRoleKeydown(e, 'student'));
    }

    if (roleTeacherBtn) {
        roleTeacherBtn.addEventListener('click', () => selectRole('teacher'));
        roleTeacherBtn.addEventListener('keydown', (e) => handleRoleKeydown(e, 'teacher'));
    }

    // Back button navigation
    if (backToRole) {
        backToRole.addEventListener('click', function(e) {
            e.preventDefault();
            if (registerPanel) registerPanel.classList.remove('active');
            setTimeout(() => {
                if (roleSelectionContainer) roleSelectionContainer.classList.remove('hidden');
            }, 300);
        });
    }

    // Smooth transition to Login page
    if (loginLink) {
        loginLink.addEventListener('click', function(e) {
            e.preventDefault();
            if (registerPanel) registerPanel.classList.remove('active');
            setTimeout(() => {
                window.location.href = this.href;
            }, 400);
        });
    }

    // Fix missing panel when using browser back button (bfcache restore)
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            if (registerPanel) registerPanel.classList.remove('active');
            if (roleSelectionContainer) roleSelectionContainer.classList.remove('hidden');
            if (registerBtn) registerBtn.classList.remove('submitting');
            if (registerForm) registerForm.classList.remove('form-submitting');
        }
    });

    // Form submission loading state
    if (registerForm && registerBtn) {
        registerForm.addEventListener('submit', function () {
            if (registerForm.checkValidity()) {
                registerBtn.classList.add('submitting');
                registerForm.classList.add('form-submitting');
            }
        });
    }

    // Close button redirect animation
    if (roleCloseBtn) {
        roleCloseBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (roleSelectionContainer) roleSelectionContainer.classList.add('hidden');
            setTimeout(() => {
                window.location.href = this.href;
            }, 500);
        });
    }
</script>
@endpush
