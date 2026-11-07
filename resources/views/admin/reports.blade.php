@extends('layouts.admin')

@section('title', 'EquipTrack - Reports Dashboard')

@push('css')
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminreports.css') }}?v={{ filemtime(public_path('admin/css/adminreports.css')) }}">
@endpush

@section('content')
<div class="reports-page">
        {{-- Page header --}}
        <header class="rp-header">
            <div class="rp-header-text">
                <h1 class="reports-page-title">Reports</h1>
                <p class="reports-page-subtitle">Generate, analyze, and export borrowing, return, inventory, user activity, equipment utilization, and transaction history reports.</p>
            </div>
        </header>

        {{-- 1. Generate a report: the filters drive the preview, the overview cards and the charts --}}
        <section class="white-card rp-card rp-builder" aria-labelledby="rpBuilderTitle">
            <div class="rp-card-head">
                <div class="rp-card-heading">
                    <span class="rp-card-icon" aria-hidden="true"><i class="fa-solid fa-sliders"></i></span>
                    <div>
                        <h2 class="rp-card-title" id="rpBuilderTitle">Generate a Report</h2>
                        <p class="rp-card-subtitle">Choose the report type and filters, then select Generate Report.</p>
                    </div>
                </div>
            </div>

            <form id="reportFilterForm" class="rp-filter-form">
                <div class="rp-filters">
                    <div class="filter-group rp-filter-type">
                        <label for="filterReportType">Report Type</label>
                        <select id="filterReportType" class="form-control select-control">
                            @foreach ($reportTypes as $typeKey => $typeLabel)
                                <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filterFrom">Date From</label>
                        <input type="date" id="filterFrom" class="form-control">
                    </div>
                    <div class="filter-group">
                        <label for="filterTo">Date To</label>
                        <input type="date" id="filterTo" class="form-control">
                    </div>
                    <div class="filter-group">
                        <label for="filterStatus">Status</label>
                        <select id="filterStatus" class="form-control select-control">
                            <option value="">All</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filterCategory">Category</label>
                        <select id="filterCategory" class="form-control select-control">
                            <option value="">All Categories</option>
                            @foreach ($dbCategories as $cat)
                                <option value="{{ $cat->category_id }}">{{ $cat->category_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="rp-filter-footer">
                    <p class="filters-note">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <span id="filterBasisNote"></span>
                    </p>
                    <div class="filters-actions">
                        <button type="button" id="btnResetFilters" class="btn btn-secondary">
                            <i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i> Reset Filters
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnGenerate">
                            <i class="fa-solid fa-gears" aria-hidden="true"></i> Generate Report
                        </button>
                    </div>
                </div>
            </form>
        </section>

        {{-- 2. Report preview with its export actions --}}
        <section class="white-card rp-card" aria-labelledby="rpPreviewTitle">
            <div class="rp-card-head">
                <div class="rp-card-heading">
                    <span class="rp-card-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass-chart"></i></span>
                    <div>
                        <h2 class="rp-card-title" id="rpPreviewTitle">Report Preview</h2>
                        <p class="rp-card-subtitle">These are the rows that Export PDF, Export CSV and Print include.</p>
                    </div>
                </div>
                <div class="preview-actions rp-export-group" role="group" aria-label="Export report">
                    <button type="button" class="btn-export" onclick="triggerExport('pdf')" title="Downloads the report as a PDF file.">
                        <i class="fa-solid fa-file-pdf" style="color: #ef4444;" aria-hidden="true"></i> Export PDF
                    </button>
                    <button type="button" class="btn-export" onclick="triggerExport('csv')" title="Downloads a CSV file that opens in Excel.">
                        <i class="fa-solid fa-file-excel" style="color: #10b981;" aria-hidden="true"></i> Export CSV
                    </button>
                    <button type="button" class="btn-export" onclick="triggerExport('print')" title="Opens a print-ready copy in a new tab.">

                        <i class="fa-solid fa-print" style="color: var(--primary-color);" aria-hidden="true"></i> Print Report
                    </button>
                </div>
            </div>

            <div class="report-meta" id="reportMeta"></div>

            <div class="table-container admin-table-card">
                {{-- Fixed column widths (set per report type by the script), so the
                     columns never move when the filters or the rows change --}}
                <table class="admin-report-table report-preview-table" id="previewTable">
                    <colgroup id="previewColgroup"></colgroup>
                    <thead>
                        <tr id="previewTableHead"></tr>
                    </thead>
                    <tbody id="previewTableBody">
                        <!-- Rendered dynamically -->
                    </tbody>
                </table>

                <!-- Pagination footer (same layout as the other Admin tables) -->
                <div class="pagination-container" id="paginationContainer">
                    <span class="pagination-info" id="paginationInfo"></span>
                    <div class="pagination-buttons" id="paginationButtons">
                        <!-- Rendered dynamically -->
                    </div>
                </div>
            </div>
        </section>

        {{-- 3. Overview: key figures and charts for the same filters --}}
        <section class="rp-section" aria-labelledby="rpOverviewTitle">
            <div class="rp-section-head">
                <h2 class="rp-section-title" id="rpOverviewTitle">Overview</h2>
                <p class="reports-scope-note" id="summaryScopeNote">Request, loan and chart figures follow the selected borrow-date range and category. Equipment availability shows current stock.</p>
            </div>

            <h3 class="rp-group-label">Borrowings &amp; Returns</h3>
            <div class="rp-kpi-grid">
                <div class="rp-kpi">
                    <span class="rp-kpi-icon blue" aria-hidden="true"><i class="fa-solid fa-chart-simple"></i></span>
                    <div class="rp-kpi-body">
                        <span class="rp-kpi-label">Total Borrowings</span>
                        <span class="rp-kpi-value" id="statBorrowings">0</span>
                        <span class="rp-kpi-caption">Approved borrow requests</span>
                    </div>
                </div>
                <div class="rp-kpi">
                    <span class="rp-kpi-icon indigo" aria-hidden="true"><i class="fa-solid fa-box"></i></span>
                    <div class="rp-kpi-body">
                        <span class="rp-kpi-label">Active Borrowings</span>
                        <span class="rp-kpi-value" id="statActive">0</span>
                        <span class="rp-kpi-caption" id="capActive">Equipment currently borrowed</span>
                    </div>
                </div>
                <div class="rp-kpi">
                    <span class="rp-kpi-icon orange" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <div class="rp-kpi-body">
                        <span class="rp-kpi-label">Overdue Cases</span>
                        <span class="rp-kpi-value" id="statOverdue">0</span>
                        <span class="rp-kpi-caption" id="capOverdue">Items past their due date</span>
                    </div>
                </div>
                <div class="rp-kpi">
                    <span class="rp-kpi-icon green" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
                    <div class="rp-kpi-body">
                        <span class="rp-kpi-label">Total Returns</span>
                        <span class="rp-kpi-value" id="statReturns">0</span>
                        <span class="rp-kpi-caption">Successfully returned equipment</span>
                    </div>
                </div>
            </div>

            <h3 class="rp-group-label">Requests &amp; Equipment</h3>
            <div class="rp-kpi-grid">
                <div class="rp-kpi">
                    <span class="rp-kpi-icon blue" aria-hidden="true"><i class="fa-solid fa-clipboard-list"></i></span>
                    <div class="rp-kpi-body">
                        <span class="rp-kpi-label">Total Requests</span>
                        <span class="rp-kpi-value" id="statRequests">0</span>
                        <span class="rp-kpi-caption">All borrow requests received</span>
                    </div>
                </div>
                <div class="rp-kpi">
                    <span class="rp-kpi-icon orange" aria-hidden="true"><i class="fa-solid fa-hourglass-half"></i></span>
                    <div class="rp-kpi-body">
                        <span class="rp-kpi-label">Pending Requests</span>
                        <span class="rp-kpi-value" id="statPending">0</span>
                        <span class="rp-kpi-caption">Awaiting review</span>
                    </div>
                </div>
                <div class="rp-kpi">
                    <span class="rp-kpi-icon red" aria-hidden="true"><i class="fa-solid fa-circle-xmark"></i></span>
                    <div class="rp-kpi-body">
                        <span class="rp-kpi-label">Rejected Requests</span>
                        <span class="rp-kpi-value" id="statRejected">0</span>
                        <span class="rp-kpi-caption">Requests that were declined</span>
                    </div>
                </div>
                <div class="rp-kpi">
                    <span class="rp-kpi-icon teal" aria-hidden="true"><i class="fa-solid fa-boxes-stacked"></i></span>
                    <div class="rp-kpi-body">
                        <span class="rp-kpi-label">Available Equipment</span>
                        <span class="rp-kpi-value" id="statAvailable">0</span>
                        <span class="rp-kpi-caption" id="capAvailable">Units available now</span>
                    </div>
                </div>
            </div>

            <h3 class="rp-group-label">Borrowing Trends</h3>
            <div class="rp-charts">
                <div class="white-card rp-chart-card rp-chart-wide">
                    <div class="rp-chart-head">
                        <div>
                            <h3 class="rp-chart-title">Monthly Borrowings</h3>
                            <span class="rp-chart-subtitle" id="monthlyRange"></span>
                        </div>
                        <i class="fa-solid fa-chart-bar" aria-hidden="true"></i>
                    </div>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                        <div class="chart-empty" id="monthlyEmpty" hidden>No borrowings in this period.</div>
                    </div>
                </div>

                <div class="white-card rp-chart-card">
                    <div class="rp-chart-head">
                        <h3 class="rp-chart-title">Borrowing by Category</h3>
                        <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
                    </div>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                        <div class="chart-empty" id="categoryEmpty" hidden>No borrowings to chart.</div>
                    </div>
                </div>

                <div class="white-card rp-chart-card rp-top-item">
                    <div class="rp-chart-head">
                        <h3 class="rp-chart-title">Most Borrowed Equipment</h3>
                        <i class="fa-solid fa-trophy" aria-hidden="true"></i>
                    </div>
                    <div class="rp-top-item-body">
                        <span class="rp-top-item-name" id="mostBorrowedName">—</span>
                        <span class="rp-top-item-caption" id="mostBorrowedCaption">0 borrowings</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- 4. Recent generated reports --}}
        <section class="white-card rp-card" aria-labelledby="rpRecentTitle">
            <div class="rp-card-head">
                <div class="rp-card-heading">
                    <span class="rp-card-icon" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    <div>
                        <h2 class="rp-card-title" id="rpRecentTitle">Recent Generated Reports</h2>
                        <p class="rp-card-subtitle"><strong id="statReports">0</strong> reports generated this month. Select Re-run to generate a report again with its saved filters.</p>
                    </div>
                </div>
            </div>

            <div class="table-container admin-table-card">
                <table class="admin-report-table recent-reports-table">
                    <colgroup>
                        <col class="w-recent-name">
                        <col class="w-recent-by">
                        <col class="w-recent-date">
                        <col class="w-recent-format">
                        <col class="w-recent-action">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Report Name</th>
                            <th>Generated By</th>
                            <th>Date Generated</th>
                            <th>File Format</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody id="recentTableBody">
                        <!-- Rendered dynamically -->
                    </tbody>
                </table>

                <!-- Pagination footer (same layout as the Report Preview and the other Admin tables) -->
                <div class="pagination-container" id="recentPaginationContainer">
                    <span class="pagination-info" id="recentPaginationInfo"></span>
                    <div class="pagination-buttons" id="recentPaginationButtons">
                        <!-- Rendered dynamically -->
                    </div>
                </div>
            </div>
        </section>
    </div>
<!-- Toast Success Notification Container -->
    <div class="toast-notification" id="toastNotification">
        <div class="toast-content">
            <div style="width: 32px; height: 32px; border-radius: 50%; background-color: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 16px;" id="reportToastIcon">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div style="display: flex; flex-direction: column; gap: 2px;">
                <span style="font-weight: 700; font-size: 14px; color: var(--text-main);" id="reportToastTitle">Notification Title</span>
                <span style="font-size: 12px; color: var(--text-muted);" id="toastMessage">Notification details message goes here.</span>
            </div>
        </div>
    </div>

    <!-- Scripts -->
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    // ---------------------------------------------------------------------
    // Reports page
    //
    // Every number, chart and table row is rendered from the database through
    // the admin reports endpoints (borrow requests, loans, equipment). The
    // server renders the first payload so the page never flashes sample data.
    // ---------------------------------------------------------------------
    const URLS = {
        data: @json(route('admin.reports.data')),
        generate: @json(route('admin.reports.generate')),
        exportFile: @json(route('admin.reports.export'))
    };
    const CSRF_TOKEN = @json(csrf_token());
    const STATUS_OPTIONS = @json($reportStatuses);
    const DATE_BASIS = @json($reportBasis);
    const PER_PAGE = 10;
    // Recent Generated Reports: rows per page (the server keeps the latest 25).
    const RECENT_PER_PAGE = 10;
    const PALETTE = ['#385585', '#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#14b8a6', '#8b5cf6', '#64748b'];

    let payload = @json($report);
    let currentPage = 1;
    let recentPage = 1;
    let monthlyChart = null;
    let categoryChart = null;
    let toastTimeout;
    let loadSeq = 0;

    const byId = (id) => document.getElementById(id);
    const form = byId('reportFilterForm');
    const controls = {
        from: byId('filterFrom'),
        to: byId('filterTo'),
        type: byId('filterReportType'),
        status: byId('filterStatus'),
        category: byId('filterCategory')
    };

    // ----- helpers -------------------------------------------------------
    function escapeHTML(value) {
        if (value === null || value === undefined) return '';
        return String(value).replace(/[&<>'"]/g, (tag) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
        }[tag] || tag));
    }

    function setText(id, value) {
        const el = byId(id);
        if (el) el.textContent = (value === null || value === undefined) ? 0 : value;
    }

    function units(count) {
        return count + (count === 1 ? ' unit' : ' units');
    }

    function slug(value) {
        return String(value || '').toLowerCase().trim().replace(/\s+/g, '-');
    }

    // "2026-10-09" -> "Oct 09, 2026" without any timezone shifting.
    function fmtDate(iso) {
        const parts = String(iso || '').split('-').map(Number);
        if (parts.length !== 3 || parts.some(isNaN)) return '';
        return new Date(parts[0], parts[1] - 1, parts[2])
            .toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }

    function describeFilters(filters, basis) {
        const parts = [];
        if (filters.from || filters.to) {
            parts.push((basis || 'Date') + ': ' + (filters.from ? fmtDate(filters.from) : 'earliest') +
                ' to ' + (filters.to ? fmtDate(filters.to) : 'latest'));
        }
        if (filters.status) parts.push('Status: ' + filters.status);
        if (filters.category_name) parts.push('Category: ' + filters.category_name);
        return parts.length ? parts.join(' | ') : 'No filters (all records)';
    }

    function hasActiveFilters(filters) {
        return Boolean(filters.from || filters.to || filters.status || filters.category);
    }

    // Toast (supports success / info / error)
    function toast(title, message, type) {
        const box = byId('toastNotification');
        const icon = byId('reportToastIcon');
        clearTimeout(toastTimeout);
        byId('reportToastTitle').textContent = title;
        byId('toastMessage').textContent = message;

        const styles = {
            success: ['rgba(16, 185, 129, 0.1)', '#10b981', 'fa-circle-check'],
            info: ['rgba(59, 130, 246, 0.1)', '#3b82f6', 'fa-circle-info'],
            error: ['rgba(239, 68, 68, 0.1)', '#ef4444', 'fa-circle-xmark']
        };
        const style = styles[type] || styles.success;
        icon.style.backgroundColor = style[0];
        icon.style.color = style[1];
        icon.innerHTML = '<i class="fa-solid ' + style[2] + '"></i>';

        box.classList.add('show');
        toastTimeout = setTimeout(() => box.classList.remove('show'), 4000);
    }
    window.showNotification = toast;

    // ----- summary cards -------------------------------------------------
    function renderSummary(summary) {
        const scopeType = payload.filters.type;
        const scopeNote = {
            'return': 'Total Returns follows the selected return-date range. Other request, loan and chart figures follow the borrow-date range and category. Equipment availability shows current stock.',
            'overdue': 'Overdue Cases follows the selected due-date range. Other request, loan and chart figures follow the borrow-date range and category. Equipment availability shows current stock.'
        }[scopeType];
        setText('summaryScopeNote', scopeNote || 'Request, loan and chart figures follow the selected borrow-date range and category. Equipment availability shows current stock.');
        setText('statBorrowings', summary.borrowings);
        setText('statReturns', summary.returns);
        setText('statActive', summary.active);
        setText('capActive', units(summary.activeUnits) + ' currently on loan');
        setText('statOverdue', summary.overdue);
        setText('capOverdue', units(summary.overdueUnits) + ' past the due date');
        setText('statReports', summary.reportsThisMonth);
        setText('statRequests', summary.requests);
        setText('statPending', summary.pending);
        setText('statRejected', summary.rejected);
        setText('statAvailable', summary.availableUnits);
        setText('capAvailable', 'of ' + units(summary.totalUnits) + ' in stock (current)');
    }

    // ----- charts --------------------------------------------------------
    function chartTheme() {
        const dark = document.documentElement.classList.contains('dark-theme');
        return {
            tick: dark ? '#94a3b8' : '#64748b',
            grid: dark ? 'rgba(51, 65, 85, 0.4)' : 'rgba(226, 232, 240, 0.6)',
            bar: dark ? '#60a5fa' : '#385585'
        };
    }

    const tooltipStyle = {
        backgroundColor: '#1e293b',
        titleColor: '#f8fafc',
        bodyColor: '#f8fafc',
        borderColor: '#334155',
        borderWidth: 1,
        padding: 10,
        cornerRadius: 8
    };

    function ensureCharts() {
        if (typeof Chart === 'undefined') return false;
        if (monthlyChart) return true;

        Chart.defaults.font.family = "'Inter', sans-serif";
        const theme = chartTheme();

        monthlyChart = new Chart(byId('monthlyChart').getContext('2d'), {
            type: 'bar',
            data: { labels: [], datasets: [{ label: 'Borrowings', data: [], backgroundColor: theme.bar, borderRadius: 6, borderSkipped: false }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: tooltipStyle },
                scales: {
                    x: { grid: { display: false }, ticks: { color: theme.tick, font: { size: 10 } } },
                    y: { beginAtZero: true, grid: { color: theme.grid, borderDash: [4, 4] }, ticks: { color: theme.tick, font: { size: 10 }, precision: 0 } }
                }
            }
        });

        categoryChart = new Chart(byId('categoryChart').getContext('2d'), {
            type: 'doughnut',
            data: { labels: [], datasets: [{ data: [], backgroundColor: PALETTE, borderWidth: 0, hoverOffset: 4 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: theme.tick, font: { size: 10 }, boxWidth: 8, padding: 12 } },
                    tooltip: tooltipStyle
                }
            }
        });

        return true;
    }

    function applyChartTheme() {
        if (!monthlyChart) return;
        const theme = chartTheme();
        monthlyChart.options.scales.x.ticks.color = theme.tick;
        monthlyChart.options.scales.y.ticks.color = theme.tick;
        monthlyChart.options.scales.y.grid.color = theme.grid;
        monthlyChart.data.datasets[0].backgroundColor = theme.bar;
        monthlyChart.update();
        categoryChart.options.plugins.legend.labels.color = theme.tick;
        categoryChart.update();
    }

    function renderCharts(charts) {
        const monthlyEmpty = !charts.monthly.values.some((v) => v > 0);
        const categoryEmpty = charts.category.values.length === 0;

        byId('monthlyRange').textContent = charts.monthly.range || '';
        byId('monthlyEmpty').hidden = !monthlyEmpty;
        byId('categoryEmpty').hidden = !categoryEmpty;
        // Keep the canvases in the layout (visibility, not display) so Chart.js
        // never has to re-measure them after an empty result.
        byId('monthlyChart').style.visibility = monthlyEmpty ? 'hidden' : 'visible';
        byId('categoryChart').style.visibility = categoryEmpty ? 'hidden' : 'visible';

        if (!ensureCharts()) {
            // Chart.js could not be loaded (offline). Everything else still works.
            if (!monthlyEmpty) { byId('monthlyEmpty').textContent = 'Charts are unavailable right now.'; byId('monthlyEmpty').hidden = false; byId('monthlyChart').hidden = true; }
            if (!categoryEmpty) { byId('categoryEmpty').textContent = 'Charts are unavailable right now.'; byId('categoryEmpty').hidden = false; byId('categoryChart').hidden = true; }
        } else {
            monthlyChart.data.labels = charts.monthly.labels;
            monthlyChart.data.datasets[0].data = charts.monthly.values;
            monthlyChart.update();

            categoryChart.data.labels = charts.category.labels;
            categoryChart.data.datasets[0].data = charts.category.values;
            categoryChart.data.datasets[0].backgroundColor = charts.category.labels.map((_, i) => PALETTE[i % PALETTE.length]);
            categoryChart.update();
        }

        const top = charts.mostBorrowed;
        const scoped = payload.filters.from || payload.filters.to ? 'in the selected period' : 'overall';
        if (top) {
            byId('mostBorrowedName').textContent = top.name;
            byId('mostBorrowedCaption').textContent = top.count + (top.count === 1 ? ' borrowing' : ' borrowings') +
                ' · ' + units(top.units) + ' ' + scoped;
        } else {
            byId('mostBorrowedName').textContent = '—';
            byId('mostBorrowedCaption').textContent = '0 borrowings ' + scoped;
        }
    }

    // ----- report table --------------------------------------------------
    function renderMeta() {
        const report = payload.report;
        const meta = byId('reportMeta');
        // Each fact under its own label: Report, Records, Filters, Generated.
        const item = (label, value, cls) =>
            '<div class="rm-item' + (cls ? ' ' + cls : '') + '"><span class="rm-label">' + label + '</span>' +
            '<span class="rm-value" title="' + escapeHTML(value) + '">' + escapeHTML(value) + '</span></div>';
        meta.innerHTML =
            item('Report', report.title, 'rm-report') +
            item('Records', report.total + (report.total === 1 ? ' record' : ' records')) +
            item('Filters', describeFilters(payload.filters, report.basis), 'rm-filters') +
            item('Generated', payload.generatedAt) +
            (report.note ? '<p class="report-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> ' + escapeHTML(report.note) + '</p>' : '');
    }

    // Fixed column widths for the Report Preview: each column's minimum width
    // in px, from its content (a date like "Nov 06, 2026", an ID, the report's
    // longest status badge, a readable name) plus cell padding. A report's
    // columns get these as percentages of the table, so spare width is shared
    // in the same proportions; together they are the table's minimum width
    // (below it the table scrolls sideways, like the Department tables). Set
    // once per report type, so changing filters or rows never moves a column.
    // Names, descriptions etc. end in "…" when longer than their column.
    // (Measured: a date needs 104px of text, the longest header words such as
    // "Cancelled" or "Utilization" about 76px; cells add 20px of padding.)
    const COLUMN_WIDTHS = {
        penalty_id: 105, transaction: 115, borrower: 165, id_number: 100, role: 80,
        equipment: 130, serial: 130, category: 90, department: 150, type: 110,
        description: 180, amount: 100, recorded: 125, recorded_by: 150, settled: 125,
        condition: 105, last_borrowed: 125,
        // Number columns: wide enough for their header's longest word
        qty: 55, total: 60, on_loan: 60, loan_days: 60, days_overdue: 85, available: 90,
        requests: 90, approved: 95, rejected: 88, pending: 85, cancelled: 96, returned: 90,
        borrowed: 95, overdue: 85, times: 95, units: 95, utilization: 96
    };
    const DATE_WIDTH = 125;
    const NUM_WIDTH = 90;
    const DEFAULT_WIDTH = 120;
    // Status column, by report: fits the report's longest badge (e.g. "Under
    // Maintenance"); Borrowing's rare "Reservation Cancelled" ends in "…" (full text on hover).
    const STATUS_WIDTHS = { borrowing: 172, return: 156, inventory: 195, utilization: 125, transactions: 156, penalties: 125 };
    const STATUS_WIDTH = 156;

    function columnWidth(column, type) {
        if (column.kind === 'status') return STATUS_WIDTHS[type] || STATUS_WIDTH;
        if (COLUMN_WIDTHS[column.key]) return COLUMN_WIDTHS[column.key];
        if (/_date$/.test(column.key)) return DATE_WIDTH;
        if (column.kind === 'num') return NUM_WIDTH;
        return DEFAULT_WIDTH;
    }

    function applyColumnWidths(columns, type) {
        const widths = columns.map((c) => columnWidth(c, type));
        const total = widths.reduce((sum, w) => sum + w, 0);
        byId('previewColgroup').innerHTML = widths
            .map((w) => '<col style="width: ' + (w / total * 100).toFixed(3) + '%">')
            .join('');
        byId('previewTable').style.minWidth = total + 'px';
    }

    // Column classes shared by the header and the cells: numbers stay compact
    // and centred; dates, ID numbers and statuses stay on one line.
    function columnClass(column) {
        const classes = [];
        if (column.kind === 'num') classes.push('col-num');
        if (column.kind === 'status' || column.key === 'id_number' || column.key === 'transaction' || column.key === 'last_borrowed' || /_date$/.test(column.key)) {
            classes.push('col-nowrap');
        }
        if (column.key === 'borrower') classes.push('col-borrower-truncate');
        if (column.key === 'equipment') classes.push('col-equipment-truncate');
        return classes.join(' ');
    }

    function renderCell(column, value, index) {
        const text = (value === null || value === undefined || value === '') ? '—' : value;
        if (column.kind === 'status') {
            return '<td class="col-nowrap col-status"><span class="status-pill ' + slug(text) + '" title="' + escapeHTML(text) + '">' + escapeHTML(text) + '</span></td>';
        }
        if (column.kind === 'num') {
            return '<td class="col-num" title="' + escapeHTML(text) + '">' + escapeHTML(text) + '</td>';
        }
        // Borrower names stay on one line, ending in "…" (full name on hover).
        // The printed report and CSV export always show the full name.
        if (column.key === 'borrower') {
            const name = index === 0 ? '<strong>' + escapeHTML(text) + '</strong>' : escapeHTML(text);
            return '<td class="col-borrower-truncate"><span class="report-borrower-name" title="' + escapeHTML(text) + '">' + name + '</span></td>';
        }
        // Equipment names stay on one line, ending in "…" (full name on hover).
        // The printed report and CSV export always show the full name.
        if (column.key === 'equipment') {
            const name = index === 0 ? '<strong>' + escapeHTML(text) + '</strong>' : escapeHTML(text);
            return '<td class="col-equipment-truncate"><span class="report-equipment-name" title="' + escapeHTML(text) + '">' + name + '</span></td>';
        }
        // Every other cell: one line, ending in "…" when long (full text on hover).
        const cls = columnClass(column);
        return '<td' + (cls ? ' class="' + cls + '"' : '') + ' title="' + escapeHTML(text) + '">'
            + (index === 0 ? '<strong>' + escapeHTML(text) + '</strong>' : escapeHTML(text)) + '</td>';
    }

    function renderTable() {
        const report = payload.report;
        const head = byId('previewTableHead');
        const body = byId('previewTableBody');
        const info = byId('paginationInfo');
        const buttons = byId('paginationButtons');

        applyColumnWidths(report.columns, payload.filters && payload.filters.type);
        head.innerHTML = report.columns
            .map((c) => {
                const cls = columnClass(c);
                // "Student/Faculty ID" may break after the "/" (on screen only; exports keep the label).
                return '<th' + (cls ? ' class="' + cls + '"' : '') + ' title="' + escapeHTML(c.label) + '">'
                    + escapeHTML(c.label).replace(/\//g, '/<wbr>') + '</th>';
            })
            .join('');

        if (report.rows.length === 0) {
            const filtered = hasActiveFilters(payload.filters);
            body.innerHTML =
                '<tr><td colspan="' + report.columns.length + '" class="empty-cell">' +
                '<i class="fa-solid fa-folder-open"></i>' +
                (filtered
                    ? 'No reports found for the selected filters.<span>Try a wider date range or a different status or category.</span>' +
                      '<button type="button" class="btn btn-secondary empty-clear" data-clear-filters>Clear Filters</button>'
                    : 'No records found for this report yet.<span>Records will appear here as equipment is requested and borrowed.</span>') +
                '</td></tr>';
            info.textContent = 'Showing 0 to 0 of 0 entries';
            buttons.innerHTML = '';
            byId('paginationContainer').style.display = 'none';
            return;
        }

        const totalPages = Math.ceil(report.rows.length / PER_PAGE);
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = (currentPage - 1) * PER_PAGE;
        const end = Math.min(start + PER_PAGE, report.rows.length);

        body.innerHTML = report.rows.slice(start, end).map((row) =>
            '<tr>' + report.columns.map((c, i) => renderCell(c, row[c.key], i)).join('') + '</tr>'
        ).join('');

        info.textContent = 'Showing ' + (start + 1) + ' to ' + end + ' of ' + report.rows.length + ' entries';
        byId('paginationContainer').style.display = 'flex';
        renderPagination(byId('paginationButtons'), totalPages, currentPage, (page) => { currentPage = page; renderTable(); });
    }

    // Pagination buttons (previous, page numbers, next), shared by the Report
    // Preview and Recent Generated Reports tables.
    function renderPagination(wrap, totalPages, page, goTo) {
        wrap.innerHTML = '';

        function add(label, target, cls, isHTML) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-page' + (cls ? ' ' + cls : '');
            if (cls === 'disabled') btn.disabled = true;
            if (isHTML) btn.innerHTML = label; else btn.textContent = label;
            if (cls === 'active') btn.setAttribute('aria-current', 'page');
            if (!cls.includes('disabled') && target !== page) {
                btn.addEventListener('click', () => goTo(target));
            }
            wrap.appendChild(btn);
        }

        add('<i class="fa-solid fa-angle-left" aria-label="Previous page"></i>', page - 1, page === 1 ? 'disabled' : '', true);
        for (let i = 1; i <= totalPages; i++) add(String(i), i, page === i ? 'active' : '', false);
        add('<i class="fa-solid fa-angle-right" aria-label="Next page"></i>', page + 1, page === totalPages ? 'disabled' : '', true);
    }

    // ----- recent generated reports --------------------------------------
    function renderRecent() {
        const body = byId('recentTableBody');
        const container = byId('recentPaginationContainer');
        const rows = payload.recent || [];

        if (rows.length === 0) {
            body.innerHTML =
                '<tr><td colspan="5" class="empty-cell"><i class="fa-solid fa-folder-open"></i>' +
                'No generated reports found.<span>Reports you generate or export will be listed here.</span></td></tr>';
            container.style.display = 'none';
            return;
        }

        const totalPages = Math.ceil(rows.length / RECENT_PER_PAGE);
        if (recentPage > totalPages) recentPage = totalPages;
        if (recentPage < 1) recentPage = 1;

        const start = (recentPage - 1) * RECENT_PER_PAGE;
        const end = Math.min(start + RECENT_PER_PAGE, rows.length);

        // data-rerun is the row's position in the full list, so Re-run works on every page.
        body.innerHTML = rows.slice(start, end).map((row, i) =>
            '<tr>' +
                '<td><strong class="recent-name" title="' + escapeHTML(row.name) + '">' + escapeHTML(row.name) + '</strong>' +
                    '<span class="recent-detail" title="' + escapeHTML(row.detail) + '">' + escapeHTML(row.detail) + '</span></td>' +
                '<td title="' + escapeHTML(row.by) + '">' + escapeHTML(row.by) + '</td>' +
                '<td title="' + escapeHTML(row.date) + '">' + escapeHTML(row.date) + '</td>' +
                '<td><span class="format-tag">' + escapeHTML(row.format) + '</span></td>' +
                '<td class="col-action"><button type="button" class="btn-action-outline view" data-rerun="' + (start + i) + '">' +
                    '<i class="fa-solid fa-rotate-right"></i> Re-run</button></td>' +
            '</tr>'
        ).join('');

        byId('recentPaginationInfo').textContent = 'Showing ' + (start + 1) + ' to ' + end + ' of ' + rows.length + ' entries';
        container.style.display = 'flex';
        renderPagination(byId('recentPaginationButtons'), totalPages, recentPage, (page) => { recentPage = page; renderRecent(); });
    }

    // ----- filters -------------------------------------------------------
    function syncControls() {
        const type = controls.type.value;
        const options = STATUS_OPTIONS[type] || [];
        const selected = controls.status.value;
        const basis = DATE_BASIS[type];

        controls.status.innerHTML = '<option value="">All</option>' +
            options.map((s) => '<option value="' + escapeHTML(s) + '">' + escapeHTML(s) + '</option>').join('');
        controls.status.value = options.indexOf(selected) !== -1 ? selected : '';
        controls.status.disabled = options.length === 0;

        controls.from.disabled = basis === null;
        controls.to.disabled = basis === null;
        if (basis === null) {
            controls.from.value = '';
            controls.to.value = '';
        }

        const notes = [];
        notes.push(basis ? 'Date range filters by ' + basis.toLowerCase() + '.' : 'Date range does not apply: the inventory shows current stock.');
        if (options.length === 0) notes.push('This report has no status filter.');
        byId('filterBasisNote').textContent = notes.join(' ');
    }

    function setControls(filters) {
        controls.type.value = filters.type;
        controls.from.value = filters.from || '';
        controls.to.value = filters.to || '';
        controls.category.value = filters.category ? String(filters.category) : '';
        controls.status.value = filters.status || '';
        syncControls();
        controls.status.value = filters.status || '';
    }

    function readFilters() {
        return {
            type: controls.type.value,
            from: controls.from.value,
            to: controls.to.value,
            status: controls.status.value,
            category: controls.category.value
        };
    }

    function queryString(filters) {
        const params = new URLSearchParams();
        ['type', 'from', 'to', 'status', 'category'].forEach((key) => {
            if (filters[key]) params.set(key, filters[key]);
        });
        return params.toString();
    }

    async function request(url, options) {
        const response = await fetch(url, options);
        let data = null;
        try { data = await response.json(); } catch (e) { /* non-JSON error page */ }

        if (!response.ok || !data || !data.success) {
            let message = 'Unable to load the report. Please try again.';
            if (data && data.errors) message = Object.values(data.errors).flat()[0] || message;
            else if (data && data.message) message = data.message;
            throw new Error(message);
        }
        return data;
    }

    // Each section renders on its own, so a problem in one (for example a chart)
    // can never stop the table, its empty state or the filters from updating.
    function safely(label, fn) {
        try { fn(); } catch (error) { console.error('Reports: could not render ' + label, error); }
    }

    function applyPayload(next) {
        // Rows must be a list; coerce defensively so an empty or odd payload never breaks the table.
        next.report.rows = Array.isArray(next.report.rows) ? next.report.rows : Object.values(next.report.rows || {});
        payload = next;
        currentPage = 1;
        safely('filters', () => setControls(payload.filters));
        safely('table', () => { renderMeta(); renderTable(); });
        safely('summary', () => renderSummary(payload.summary));
        safely('charts', () => renderCharts(payload.charts));
        safely('recent', renderRecent);
    }

    async function load(filters, log) {
        const button = byId('btnGenerate');
        const seq = ++loadSeq;
        button.disabled = true;
        try {
            const data = log
                ? await request(URLS.generate, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify(filters)
                })
                : await request(URLS.data + '?' + queryString(filters), { headers: { 'Accept': 'application/json' } });
            // A newer request supersedes this one; never let a late response overwrite it.
            if (seq === loadSeq) {
                // A generated report is added at the top of Recent Generated Reports: show it.
                if (log) recentPage = 1;
                applyPayload(data);
            }
            return data;
        } finally {
            if (seq === loadSeq) button.disabled = false;
        }
    }

    // Refreshes only the "Reports Generated" card and the recent list (after an export).
    async function refreshMeta() {
        try {
            const data = await request(URLS.data + '?' + queryString(payload.filters), { headers: { 'Accept': 'application/json' } });
            payload.summary = data.summary;
            payload.recent = data.recent;
            renderSummary(payload.summary);
            renderRecent();
        } catch (e) { /* the preview stays as it is */ }
    }

    // ----- events --------------------------------------------------------
    controls.type.addEventListener('change', () => {
        controls.status.value = '';
        syncControls();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const filters = readFilters();

        if (filters.from && filters.to && filters.to < filters.from) {
            toast('Invalid Date Range', 'The “Date To” cannot be earlier than “Date From”.', 'error');
            return;
        }

        try {
            const data = await load(filters, true);
            const total = data.report.total;
            toast('Report Generated', total === 0
                ? 'No reports found for the selected filters.'
                : 'Compiled ' + total + (total === 1 ? ' record' : ' records') + ' for the ' + data.report.title + '.',
                total === 0 ? 'info' : 'success');
        } catch (error) {
            toast('Report Failed', error.message, 'error');
        }
    });

    async function resetFilters() {
        // Clear the inputs straight away so the form is usable even if the reload is slow or fails.
        setControls({ type: 'borrowing', from: '', to: '', status: '', category: '' });
        try {
            await load({ type: 'borrowing' }, false);
            toast('Filters Reset', 'All report filters have been cleared.', 'info');
        } catch (error) {
            toast('Reset Failed', error.message, 'error');
        }
    }

    byId('btnResetFilters').addEventListener('click', resetFilters);

    // "Clear Filters" button shown inside the no-results message.
    byId('previewTableBody').addEventListener('click', (event) => {
        if (event.target.closest('[data-clear-filters]')) resetFilters();
    });

    byId('recentTableBody').addEventListener('click', async (event) => {
        const button = event.target.closest('[data-rerun]');
        if (!button) return;

        const row = (payload.recent || [])[Number(button.dataset.rerun)];
        if (!row) return;

        setControls({
            type: row.filters.type,
            from: row.filters.from,
            to: row.filters.to,
            status: row.filters.status,
            category: row.filters.category
        });

        try {
            const data = await load(readFilters(), true);
            toast('Report Re-run', data.report.title + ' was generated again with the saved filters.', 'success');
            byId('reportMeta').scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (error) {
            toast('Report Failed', error.message, 'error');
        }
    });

    // Exports always use the filters of the report currently previewed.
    window.triggerExport = function (format) {
        if (!payload.report.rows.length) {
            toast('Nothing to Export', 'There are no records to export. Adjust the filters and generate the report first.', 'error');
            return;
        }

        const url = URLS.exportFile + '?' + queryString(payload.filters) + '&format=' + encodeURIComponent(format);

        if (format === 'csv' || format === 'pdf') {
            // Files: the server answers with a download, so this page stays as it is.
            window.location.href = url;
            toast('Export Started', format === 'pdf'
                ? 'Your PDF file is downloading.'
                : 'Your CSV file is downloading. It opens in Excel.', 'success');
        } else {
            // Print: the print view opens fully detached from this tab ("noopener"),
            // so the browser runs it on its own and its print dialog can't freeze
            // this page. (Opened with a plain window.open, the new tab shared this
            // tab's process and its automatic print dialog blocked EquipTrack.)
            window.open(url, '_blank', 'noopener');
            toast('Opening Print View', 'The print dialog opens in the new tab. You can keep using EquipTrack here.', 'info');
        }

        setTimeout(refreshMeta, 1500);
    };

    // Keep chart colours in step with the light / dark theme toggle.
    new MutationObserver(applyChartTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    // First paint comes from the server payload.
    applyPayload(payload);
})();
</script>
@endpush
