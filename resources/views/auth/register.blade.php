@extends('layouts.guest')

@section('title', 'EquipTrack - Register')

@push('css')
<link rel="stylesheet" href="{{ asset('ccs/login.css') }}?v={{ filemtime(public_path('ccs/login.css')) }}">
<link rel="stylesheet" href="{{ asset('ccs/register.css') }}?v={{ filemtime(public_path('ccs/register.css')) }}">
@endpush

@section('content')
    @php $showForm = $errors->any(); @endphp
    <div class="login-container">
        @include('partials.auth-hero')

        <main class="login-panel">
            {{-- Step 1: Choose a role --}}
            <section class="register-step {{ $showForm ? '' : 'is-active' }}" id="roleSelectionContainer" @if ($showForm) hidden @endif>
                <div class="login-topbar">
                    <a href="{{ route('landing') }}" class="back-link" id="roleCloseBtn">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Back to Home</span>
                    </a>
                    <span class="step-badge">Step 1 of 2</span>
                </div>

                <div class="login-card register-card role-card">
                    <div class="login-header role-header">
                        <h1 class="role-title">What are you?</h1>
                        <p>Choose your role to start creating your account.</p>
                    </div>

                    <div class="roles-grid">
                        <button type="button" class="role-option" id="roleStudent">
                            <span class="role-label">Student</span>
                            <span class="role-circle"><img src="{{ asset('images/login_student.jpg') }}" alt=""></span>
                        </button>
                        <button type="button" class="role-option" id="roleTeacher">
                            <span class="role-label">Teacher</span>
                            <span class="role-circle"><img src="{{ asset('images/login_teacher.jpg') }}" alt=""></span>
                        </button>
                    </div>

                    <div class="signup-link">
                        Already have an account? <a href="{{ route('login') }}" class="to-login">Log in</a>
                    </div>
                </div>
            </section>

            {{-- Step 2: Registration form --}}
            <section class="register-step {{ $showForm ? 'is-active' : '' }}" id="registerPanel" @unless ($showForm) hidden @endunless>
                <div class="login-topbar">
                    <a href="#" class="back-link" id="backToRole">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Change Role</span>
                    </a>
                    <span class="step-badge">Step 2 of 2</span>
                </div>

                <div class="login-card register-card">
                    <div class="login-header">
                        <h1 id="registerTitle">Register</h1>
                        <p id="registerSubtitle">Enter your credentials to register.</p>
                    </div>

                    @if ($errors->any())
                        <div class="auth-alert is-error" role="alert">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    {{-- What happens after signing up (the script shows the Faculty line for teachers). --}}
                    <div class="auth-alert is-info" role="note">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>
                            We'll email you a link to verify your email address.
                            <span id="facultyApprovalNote" hidden>Teacher accounts are then approved by your department before you can borrow equipment.</span>
                        </span>
                    </div>

                    <form action="/register" method="POST" class="register-form login-form" novalidate>
                        @csrf
                        <input type="hidden" id="selected_role" name="role" value="{{ old('role', '') }}">

                        <fieldset class="form-section">
                            <legend>Personal information</legend>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <div class="input-icon">
                                        <i class="fa-regular fa-user field-icon"></i>
                                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" placeholder="Juan" autocomplete="given-name" maxlength="50" required @class(['is-invalid' => $errors->has('first_name')])>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <div class="input-icon">
                                        <i class="fa-regular fa-user field-icon"></i>
                                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" placeholder="Dela Cruz" autocomplete="family-name" maxlength="50" required @class(['is-invalid' => $errors->has('last_name')])>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group" id="idNumberGroup">
                                    <label for="id_number" id="idNumberLabel">ID Number</label>
                                    <div class="input-icon">
                                        <i class="fa-regular fa-id-card field-icon"></i>
                                        <input type="text" id="id_number" name="id_number" value="{{ old('id_number') }}" required @class(['is-invalid' => $errors->has('id_number')])>
                                    </div>
                                </div>
                                <div class="form-group" id="yearLevelGroup">
                                    <label for="year_level">Year &amp; Level</label>
                                    <div class="input-icon select-wrapper">
                                        <i class="fa-solid fa-graduation-cap field-icon"></i>
                                        <select id="year_level" name="year_level" required @class(['is-invalid' => $errors->has('year_level')])>
                                            <option value="" disabled {{ old('year_level') ? '' : 'selected' }}>Select year</option>
                                            @foreach (['1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'] as $val => $label)
                                                <option value="{{ $val }}" @selected(old('year_level') == $val)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <i class="fa-solid fa-chevron-down select-arrow" aria-hidden="true"></i>
                                    </div>
                                </div>
                                {{-- Teachers only (in place of Year & Level): the number on their
                                     professional teaching license. Shown by the role script. --}}
                                <div class="form-group" id="licenseGroup" hidden>
                                    <label for="teaching_license_no">Teaching License No.</label>
                                    <div class="input-icon">
                                        <i class="fa-solid fa-certificate field-icon"></i>
                                        <input type="text" id="teaching_license_no" name="teaching_license_no" value="{{ old('teaching_license_no') }}" maxlength="30" placeholder="e.g., 1234567" title="Letters, numbers and hyphens only." disabled @class(['is-invalid' => $errors->has('teaching_license_no')])>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="form-section">
                            <legend>School &amp; contact</legend>

                            <div class="form-group">
                                <label for="department_id">Department</label>
                                <div class="input-icon select-wrapper">
                                    <i class="fa-solid fa-building-columns field-icon"></i>
                                    <select id="department_id" name="department_id" required @class(['is-invalid' => $errors->has('department_id')])>
                                        <option value="" disabled {{ old('department_id') ? '' : 'selected' }}>Select your department</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->department_id }}" @selected(old('department_id') == $department->department_id)>
                                                {{ $department->department_name }}{{ $department->department_code ? ' (' . $department->department_code . ')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <i class="fa-solid fa-chevron-down select-arrow" aria-hidden="true"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <div class="input-icon">
                                    <i class="fa-regular fa-envelope field-icon"></i>
                                    <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" maxlength="100" required @class(['is-invalid' => $errors->has('email')])>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="address">Address</label>
                                <div class="input-icon">
                                    <i class="fa-solid fa-location-dot field-icon"></i>
                                    <input type="text" id="address" name="address" value="{{ old('address') }}" placeholder="Street, barangay, city" autocomplete="street-address" maxlength="150" required @class(['is-invalid' => $errors->has('address')])>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="form-section">
                            <legend>Security</legend>

                            <div class="form-group">
                                <label for="password">Password</label>
                                <div class="input-icon password-input-container">
                                    <i class="fa-solid fa-lock field-icon"></i>
                                    <input type="password" id="password" name="password" placeholder="Create a password" autocomplete="new-password" required minlength="8" title="At least 8 characters, with letters and numbers." @class(['is-invalid' => $errors->has('password')])>
                                    <i class="fa-regular fa-eye toggle-password" id="togglePassword" role="button" tabindex="0" aria-label="Show password"></i>
                                </div>
                            </div>

                            <ul class="password-rules" id="passwordRules" aria-live="polite">
                                <li data-rule="length"><i class="fa-solid fa-circle-check"></i> At least 8 characters</li>
                                <li data-rule="letter"><i class="fa-solid fa-circle-check"></i> Includes a letter</li>
                                <li data-rule="number"><i class="fa-solid fa-circle-check"></i> Includes a number</li>
                            </ul>
                        </fieldset>

                        <p class="auth-alert is-error" id="clientError" role="alert" hidden></p>

                        <button type="submit" class="login-btn register-btn" id="registerBtn">
                            <span>Create Account</span>
                            <i class="fa-solid fa-user-plus"></i>
                        </button>

                        <div class="signup-link">
                            Already have an account? <a href="{{ route('login') }}" class="to-login" id="loginLink">Log in</a>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>
@endsection

@push('scripts')
<script>
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
    const idInput = document.getElementById('id_number');
    const licenseGroup = document.getElementById('licenseGroup');
    const licenseInput = document.getElementById('teaching_license_no');
    const backToRole = document.getElementById('backToRole');
    const roleStudentBtn = document.getElementById('roleStudent');
    const roleTeacherBtn = document.getElementById('roleTeacher');
    const registerForm = document.querySelector('.register-form');
    const registerBtn = document.getElementById('registerBtn');
    const clientError = document.getElementById('clientError');
    const panel = document.querySelector('.login-panel');
    const buttonLabel = registerBtn ? registerBtn.innerHTML : '';

    // Show one step (role choice or form) with a short fade
    function showStep(step) {
        [roleSelectionContainer, registerPanel].forEach(section => {
            const active = section === step;
            section.hidden = !active;
            section.classList.toggle('is-active', active);
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Toggle Password Visibility
    if (togglePassword && password) {
        const toggle = function () {
            const show = password.getAttribute('type') === 'password';
            password.setAttribute('type', show ? 'text' : 'password');
            togglePassword.classList.toggle('fa-eye-slash', show);
            togglePassword.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        };
        togglePassword.addEventListener('click', toggle);
        togglePassword.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle();
            }
        });
    }

    // Live password checklist (same rules as the server: 8+ characters, a letter and a number)
    const rules = {
        length: value => value.length >= 8,
        letter: value => /[A-Za-z]/.test(value),
        number: value => /\d/.test(value),
    };
    function updateRules() {
        const value = password ? password.value : '';
        document.querySelectorAll('#passwordRules li').forEach(item => {
            item.classList.toggle('is-met', rules[item.dataset.rule](value));
        });
    }
    if (password) {
        password.addEventListener('input', updateRules);
        updateRules();
    }

    // Role Selection Logic
    function selectRole(role, animate = true) {
        if (selectedRoleInput) {
            selectedRoleInput.value = role;
        }
        const facultyApprovalNote = document.getElementById('facultyApprovalNote');
        if (facultyApprovalNote) facultyApprovalNote.hidden = role !== 'teacher';

        if (role === 'student') {
            if (registerTitle) registerTitle.textContent = 'Register as a Student';
            if (registerSubtitle) registerSubtitle.textContent = 'Fill in your student details to create your account.';
            if (idNumberLabel) idNumberLabel.textContent = 'Student ID Number';
            if (yearLevelGroup) yearLevelGroup.hidden = false;
            if (yearLevelSelect) yearLevelSelect.setAttribute('required', 'required');
            // Students have no teaching license: hidden, and not sent.
            if (licenseGroup) licenseGroup.hidden = true;
            if (licenseInput) {
                licenseInput.required = false;
                licenseInput.disabled = true;
            }
            if (idInput) {
                idInput.placeholder = 'e.g., 20230123';
                idInput.pattern = '\\d{8}';
                idInput.title = 'Student ID must be exactly 8 digits.';
                idInput.maxLength = 8;
                idInput.inputMode = 'numeric';
            }
        } else if (role === 'teacher') {
            if (registerTitle) registerTitle.textContent = 'Register as a Teacher';
            if (registerSubtitle) registerSubtitle.textContent = 'Fill in your faculty details to create your account.';
            if (idNumberLabel) idNumberLabel.textContent = 'Faculty ID Number';
            if (yearLevelGroup) yearLevelGroup.hidden = true;
            if (yearLevelSelect) {
                yearLevelSelect.removeAttribute('required');
                yearLevelSelect.value = '';
            }
            // Teachers enter the number on their teaching license (required).
            if (licenseGroup) licenseGroup.hidden = false;
            if (licenseInput) {
                licenseInput.disabled = false;
                licenseInput.required = true;
            }
            if (idInput) {
                idInput.placeholder = 'e.g., T-00987';
                idInput.removeAttribute('pattern');
                idInput.removeAttribute('title');
                idInput.removeAttribute('maxlength');
                idInput.inputMode = 'text';
            }
        }

        if (animate) {
            showStep(registerPanel);
            const first = document.getElementById('first_name');
            if (first) setTimeout(() => first.focus({ preventScroll: true }), 150);
        }
    }

    // Re-apply saved role after a validation error (form is already shown)
    const restoredRole = @json(old('role'));
    if (restoredRole) selectRole(restoredRole, false);

    if (roleStudentBtn) roleStudentBtn.addEventListener('click', () => selectRole('student'));
    if (roleTeacherBtn) roleTeacherBtn.addEventListener('click', () => selectRole('teacher'));

    // Back to the role choice
    if (backToRole) {
        backToRole.addEventListener('click', function (e) {
            e.preventDefault();
            if (clientError) clientError.hidden = true;
            showStep(roleSelectionContainer);
        });
    }

    // Smooth transition to the Login and Landing pages
    document.querySelectorAll('.to-login, #roleCloseBtn').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            if (panel) panel.style.animation = 'slideOutRight 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards';
            setTimeout(() => {
                window.location.href = this.href;
            }, 400);
        });
    });

    // Fix missing panel and stuck button when using browser back button (bfcache restore)
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            if (panel) panel.style.animation = '';
            showStep(roleSelectionContainer);
            if (registerBtn) {
                registerBtn.disabled = false;
                registerBtn.innerHTML = buttonLabel;
            }
        }
    });

    // Check the form before sending it; show the first problem above the button
    if (registerForm && registerBtn) {
        registerForm.addEventListener('submit', function (e) {
            registerForm.querySelectorAll('.is-invalid').forEach(field => field.classList.remove('is-invalid'));

            const invalid = Array.from(registerForm.elements).find(field =>
                field.willValidate && !field.closest('[hidden]') && !field.checkValidity());

            if (invalid) {
                e.preventDefault();
                invalid.classList.add('is-invalid');
                const label = registerForm.querySelector('label[for="' + invalid.id + '"]');
                const name = label ? label.textContent.trim() : 'This field';
                const message = invalid.validity.valueMissing
                    ? name + ' is required.'
                    : (invalid.title || invalid.validationMessage);
                if (clientError) {
                    clientError.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> <span></span>';
                    clientError.querySelector('span').textContent = message;
                    clientError.hidden = false;
                }
                invalid.focus();
                return;
            }

            if (clientError) clientError.hidden = true;
            registerBtn.disabled = true;
            registerBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Creating account...</span>';
        });

        // Clear a field's error highlight once it is edited
        registerForm.addEventListener('input', e => e.target.classList.remove('is-invalid'));
        registerForm.addEventListener('change', e => e.target.classList.remove('is-invalid'));
    }
</script>
@endpush
