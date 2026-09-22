@extends('layouts.guest')

@section('title', 'EquipTrack')

@push('css')
<meta name="description" content="EquipTrack is a centralized platform for requesting, approving, borrowing, and returning school equipment.">
<link rel="stylesheet" href="{{ asset('ccs/landing.css') }}">
@endpush

@section('content')
    <nav class="site-nav">
        <div class="wrap">
            <a href="/" class="brand">
                <img src="{{ asset('images/EquipTrack_logo.png') }}" alt="EquipTrack">
            </a>

            <div class="nav-links" id="navLinks">
                <a href="#how-it-works">How it works</a>
                <a href="#roles">Who it's for</a>
                <a href="#departments">Departments</a>
                <div class="nav-actions">
                    <a href="/login" class="btn-outline">Log In</a>
                    <a href="/register" class="btn-primary">Sign Up</a>
                </div>
            </div>

            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="navLinks">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
    </nav>

    <section class="hero" id="home">
        <div class="wrap">
            <div class="hero-content">
                <span class="hero-eyebrow">
                    <i class="fa-solid fa-boxes-stacked"></i> Campus Equipment Management
                </span>

                <h1 class="hero-title">Borrow and return school equipment without the paperwork.</h1>

                <p class="hero-description">
                    EquipTrack replaces logbooks and follow-up emails with one system for requesting, approving, and tracking laptops, projectors, tools, and every other item your departments manage.
                </p>

                <div class="hero-actions">
                    <a href="/register" class="btn-primary">Get Started</a>
                    <a href="#how-it-works" class="btn-outline on-light">See how it works</a>
                </div>

                <div class="hero-roles">
                    <span class="role-chip"><i class="fa-solid fa-user-graduate"></i> Students &amp; Faculty</span>
                    <span class="role-chip"><i class="fa-solid fa-building"></i> Departments</span>
                    <span class="role-chip"><i class="fa-solid fa-user-shield"></i> Administrators</span>
                </div>
            </div>

            <div class="hero-visual reveal">
                <div class="hero-photo-frame">
                    <img src="{{ asset('images/uc_bg.jpg') }}" alt="University of Cebu, Main Campus">
                    <span class="hero-photo-caption">University of Cebu, Main Campus</span>
                </div>

                <div class="hero-status-card">
                    <div class="status-head">
                        <span>Equipment status</span>
                        <i class="fa-solid fa-boxes-stacked" style="color: var(--primary); font-size: 0.85rem;"></i>
                    </div>
                    <div class="status-row">
                        <div>
                            <span class="item-name">Projector Unit 14</span>
                            <span class="item-sub">College of Engineering</span>
                        </div>
                        <span class="pill pill-success">Available</span>
                    </div>
                    <div class="status-row">
                        <div>
                            <span class="item-name">Laptop Cart B</span>
                            <span class="item-sub">Due Oct 3</span>
                        </div>
                        <span class="pill pill-warning">Borrowed</span>
                    </div>
                    <div class="status-row">
                        <div>
                            <span class="item-name">Survey Kit 02</span>
                            <span class="item-sub">Pending review</span>
                        </div>
                        <span class="pill pill-info">Requested</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works">
        <div class="wrap">
            <div class="section-head center reveal">
                <span class="section-label">How it works</span>
                <h2 class="section-title">From request to return, in four steps</h2>
                <p class="section-subtitle">The same flow applies whether you're borrowing a projector for a class or an entire tool set for a lab.</p>
            </div>

            <div class="steps reveal">
                <div class="step">
                    <div class="step-number"><i class="fa-solid fa-file-circle-plus"></i></div>
                    <h3>Request</h3>
                    <p>Browse what's available and submit a request for the item and dates you need.</p>
                </div>
                <div class="step">
                    <div class="step-number"><i class="fa-solid fa-clipboard-check"></i></div>
                    <h3>Approve</h3>
                    <p>The owning department reviews the request and approves or declines it.</p>
                </div>
                <div class="step">
                    <div class="step-number"><i class="fa-solid fa-hand-holding"></i></div>
                    <h3>Borrow</h3>
                    <p>Pick up the item. Its status updates automatically so others know it's in use.</p>
                </div>
                <div class="step">
                    <div class="step-number"><i class="fa-solid fa-rotate-left"></i></div>
                    <h3>Return</h3>
                    <p>Return the item and the department logs its condition and closes the request.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="roles" class="surface-alt">
        <div class="wrap">
            <div class="section-head reveal">
                <span class="section-label">Who it's for</span>
                <h2 class="section-title">Built around three roles</h2>
                <p class="section-subtitle">Each account type sees only what's relevant to their work, from browsing equipment to managing a whole department's inventory.</p>
            </div>

            <div class="role-row role-student reveal">
                <div class="role-copy">
                    <div class="role-icon-badge"><i class="fa-solid fa-user-graduate"></i></div>
                    <h3>Students &amp; Faculty</h3>
                    <p>Check what's available before requesting, submit borrow requests in a few clicks, and keep track of due dates.</p>
                </div>
                <ul class="role-capabilities">
                    <li><i class="fa-solid fa-check"></i> Browse equipment by department in real time</li>
                    <li><i class="fa-solid fa-check"></i> Submit and track borrow requests</li>
                    <li><i class="fa-solid fa-check"></i> See due dates for items currently held</li>
                    <li><i class="fa-solid fa-check"></i> View full borrowing history</li>
                </ul>
            </div>

            <div class="role-row role-department role-alt reveal">
                <div class="role-copy">
                    <div class="role-icon-badge"><i class="fa-solid fa-building"></i></div>
                    <h3>Department Coordinators</h3>
                    <p>Manage your department's inventory, review incoming requests, and see exactly what's out and when it's due back.</p>
                </div>
                <ul class="role-capabilities">
                    <li><i class="fa-solid fa-check"></i> Maintain your department's equipment list</li>
                    <li><i class="fa-solid fa-check"></i> Approve or decline borrow requests</li>
                    <li><i class="fa-solid fa-check"></i> Monitor items currently on loan</li>
                    <li><i class="fa-solid fa-check"></i> Log condition on every return</li>
                </ul>
            </div>

            <div class="role-row role-admin reveal">
                <div class="role-copy">
                    <div class="role-icon-badge"><i class="fa-solid fa-user-shield"></i></div>
                    <h3>Administrators</h3>
                    <p>Oversee every department from one place, manage accounts and access, and review activity across the whole system.</p>
                </div>
                <ul class="role-capabilities">
                    <li><i class="fa-solid fa-check"></i> Oversight across all departments</li>
                    <li><i class="fa-solid fa-check"></i> Manage user accounts and access</li>
                    <li><i class="fa-solid fa-check"></i> Review system-wide reports</li>
                    <li><i class="fa-solid fa-check"></i> Audit borrow and return activity</li>
                </ul>
            </div>
        </div>
    </section>

    <section id="departments">
        <div class="wrap">
            <div class="section-head center reveal">
                <span class="section-label">Departments</span>
                <h2 class="section-title">Every department, one system</h2>
                <p class="section-subtitle">Each college manages its own inventory and approvals, all inside the same platform.</p>
            </div>

            <div class="dept-grid reveal">
                <div class="dept-item">
                    <img src="{{ asset('images/it_department.png') }}" alt="College of Computer Studies">
                    <span>Computer Studies</span>
                </div>
                <div class="dept-item">
                    <img src="{{ asset('images/engineering_department.jpg') }}" alt="College of Engineering">
                    <span>Engineering</span>
                </div>
                <div class="dept-item">
                    <img src="{{ asset('images/educ_department.jpg') }}" alt="College of Education">
                    <span>Education</span>
                </div>
                <div class="dept-item">
                    <img src="{{ asset('images/accountancy_department.jpg') }}" alt="College of Accountancy">
                    <span>Accountancy</span>
                </div>
                <div class="dept-item">
                    <img src="{{ asset('images/crim_department.jpg') }}" alt="College of Criminology">
                    <span>Criminology</span>
                </div>
                <div class="dept-item">
                    <img src="{{ asset('images/custom_department.jpg') }}" alt="Other departments">
                    <span>And more</span>
                </div>
            </div>
        </div>
    </section>

    <section class="surface-alt">
        <div class="wrap">
            <div class="section-head reveal">
                <span class="section-label">Why EquipTrack</span>
                <h2 class="section-title">Everything a logbook can't do</h2>
                <p class="section-subtitle">Automate the parts of equipment management that used to depend on paper and memory.</p>
            </div>

            <div class="bento reveal">
                <div class="bento-tile large">
                    <div class="tile-icon"><i class="fa-solid fa-chart-line"></i></div>
                    <div class="tile-body">
                        <h3>One dashboard for every department</h3>
                        <p>Monitor equipment across the whole school from a single, centralized view instead of separate spreadsheets per department.</p>
                    </div>
                </div>
                <div class="bento-tile">
                    <div class="tile-icon"><i class="fa-solid fa-bolt"></i></div>
                    <div class="tile-body">
                        <h3>Real-time availability</h3>
                        <p>See exactly what's free before submitting a request, no back-and-forth needed.</p>
                    </div>
                </div>
                <div class="bento-tile">
                    <div class="tile-icon"><i class="fa-solid fa-file-signature"></i></div>
                    <div class="tile-body">
                        <h3>Automated logs</h3>
                        <p>Every borrow and return is recorded automatically, no paper forms required.</p>
                    </div>
                </div>
                <div class="bento-tile">
                    <div class="tile-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="tile-body">
                        <h3>Built-in accountability</h3>
                        <p>Know who borrowed what and when it's due, so items make their way back on time.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-band">
        <div class="wrap reveal">
            <h2>Ready to put down the logbook?</h2>
            <p>Create an account or log in to start requesting equipment today.</p>
            <div class="cta-actions">
                <a href="/register" class="btn-primary">Get Started</a>
                <a href="/login" class="btn-outline">Log In</a>
            </div>
        </div>
    </section>

    <footer>
        <div class="wrap">
            <div class="footer-grid">
                <div class="footer-brand">
                    <img src="{{ asset('images/EquipTrack_logo.png') }}" alt="EquipTrack">
                    <p>A centralized platform for requesting, approving, borrowing, and returning school equipment.</p>
                </div>

                <div class="footer-col">
                    <h4>Platform</h4>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#how-it-works">How it works</a></li>
                        <li><a href="#departments">Departments</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Account</h4>
                    <ul>
                        <li><a href="/login">Log In</a></li>
                        <li><a href="/register">Sign Up</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Need help?</h4>
                    <p>Reach out to your department coordinator for questions about a specific request or item.</p>
                </div>
            </div>

            <div class="footer-bottom">
                <span>&copy; 2026 EquipTrack System. All rights reserved.</span>
                <span>University of Cebu, Main Campus</span>
            </div>
        </div>
    </footer>
@endsection

@push('scripts')
<script>
    (function () {
        var toggle = document.getElementById('navToggle');
        var links = document.getElementById('navLinks');

        if (toggle && links) {
            toggle.addEventListener('click', function () {
                var isOpen = links.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                toggle.innerHTML = isOpen
                    ? '<i class="fa-solid fa-xmark"></i>'
                    : '<i class="fa-solid fa-bars"></i>';
            });

            links.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    links.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.innerHTML = '<i class="fa-solid fa-bars"></i>';
                });
            });
        }

        var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if ('IntersectionObserver' in window && !prefersReducedMotion) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15 });

            document.querySelectorAll('.reveal').forEach(function (el) {
                observer.observe(el);
            });
        } else {
            document.querySelectorAll('.reveal').forEach(function (el) {
                el.classList.add('is-visible');
            });
        }
    })();
</script>
@endpush
