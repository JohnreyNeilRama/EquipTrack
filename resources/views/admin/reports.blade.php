@extends('layouts.admin')

@section('title', 'EquipTrack - Reports Dashboard')

@push('css')
<link rel="stylesheet" href="{{ asset('admin/css/admindashboard.css') }}">
<link rel="stylesheet" href="{{ asset('admin/css/adminreports.css') }}">
@endpush

@section('content')
<!-- Reports Header Section -->
        <div class="reports-header-section">
            <h1 class="reports-page-title">Reports</h1>
            <p class="reports-page-subtitle">Generate, analyze, and export borrowing, return, inventory, and user activity reports.</p>
        </div>

        <!-- Section Title for Summary Cards -->
        <div class="reports-section-title">Summary Cards</div>
        <p class="reports-scope-note" id="summaryScopeNote">Request, loan and chart figures follow the selected borrow-date range and category. Equipment availability shows current stock.</p>

        <!-- Summary Statistic Cards (all values come from the database) -->
        <div class="reports-stats-row">
            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value" id="statBorrowings">0</div>
                    <div class="report-stat-icon-box blue">
                        <i class="fa-solid fa-chart-simple"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Total Borrowings</div>
                    <div class="report-stat-caption">Approved borrow requests</div>
                </div>
            </div>

            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value" id="statReturns">0</div>
                    <div class="report-stat-icon-box green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Total Returns</div>
                    <div class="report-stat-caption">Successfully returned equipment</div>
                </div>
            </div>

            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value" id="statActive">0</div>
                    <div class="report-stat-icon-box indigo">
                        <i class="fa-solid fa-box"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Active Borrowings</div>
                    <div class="report-stat-caption" id="capActive">Equipment currently borrowed</div>
                </div>
            </div>

            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value" id="statOverdue">0</div>
                    <div class="report-stat-icon-box orange">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Overdue Cases</div>
                    <div class="report-stat-caption" id="capOverdue">Items past their due date</div>
                </div>
            </div>

            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value" id="statReports">0</div>
                    <div class="report-stat-icon-box teal">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Reports Generated</div>
                    <div class="report-stat-caption">Reports generated this month</div>
                </div>
            </div>
        </div>

        <div class="reports-stats-row four">
            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value" id="statRequests">0</div>
                    <div class="report-stat-icon-box blue">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Total Requests</div>
                    <div class="report-stat-caption">All borrow requests received</div>
                </div>
            </div>

            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value" id="statPending">0</div>
                    <div class="report-stat-icon-box orange">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Pending Requests</div>
                    <div class="report-stat-caption">Awaiting review</div>
                </div>
            </div>

            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value" id="statRejected">0</div>
                    <div class="report-stat-icon-box red">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Rejected Requests</div>
                    <div class="report-stat-caption">Requests that were declined</div>
                </div>
            </div>

            <div class="report-stat-card">
                <div class="report-stat-header">
                    <div class="report-stat-value" id="statAvailable">0</div>
                    <div class="report-stat-icon-box teal">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
                <div class="report-stat-info">
                    <div class="report-stat-label">Available Equipment</div>
                    <div class="report-stat-caption" id="capAvailable">Units available now</div>
                </div>
            </div>
        </div>

        <!-- Reports Layout Grid (All full-width horizontal cards) -->
        <div class="reports-layout-grid">
            <!-- Charts Analytics Card -->
            <div class="reports-analytics-card">
                <div class="analytics-section">
                    <div class="analytics-section-title">
                        <span>Monthly Borrowings <small class="analytics-range" id="monthlyRange"></small></span>
                        <i class="fa-solid fa-chart-bar" style="color: var(--primary-color);"></i>
                    </div>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                        <div class="chart-empty" id="monthlyEmpty" hidden>No borrowings in this period.</div>
                    </div>
                </div>

                <div class="analytics-section">
                    <div class="analytics-section-title">
                        <span>Borrowing by Category</span>
                        <i class="fa-solid fa-chart-pie" style="color: var(--primary-color);"></i>
                    </div>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                        <div class="chart-empty" id="categoryEmpty" hidden>No borrowings to chart.</div>
                    </div>
                </div>

                <div class="analytics-section most-borrowed">
                    <div class="analytics-section-title-centered">Most Borrowed Equipment</div>
                    <div style="font-size: 18px; font-weight: 700; color: var(--primary-color); margin-top: 6px;" id="mostBorrowedName">—</div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;" id="mostBorrowedCaption">0 borrowings</div>
                </div>
            </div>

            <!-- Generate Report Panel -->
            <div class="white-card">
                <div class="white-card-header">
                    <div class="white-card-title">
                        <i class="fa-solid fa-sliders" style="color: var(--primary-color);"></i> Generate Report Filters
                    </div>
                </div>
                <form id="reportFilterForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="filterFrom">Date From</label>
                            <input type="date" id="filterFrom" class="form-control">
                        </div>
                        <div class="filter-group">
                            <label for="filterTo">Date To</label>
                            <input type="date" id="filterTo" class="form-control">
                        </div>
                        <div class="filter-group">
                            <label for="filterReportType">Report Type</label>
                            <select id="filterReportType" class="form-control select-control">
                                @foreach ($reportTypes as $typeKey => $typeLabel)
                                    <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                                @endforeach
                            </select>
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
                    <p class="filters-note" id="filterBasisNote"></p>
                    <div class="filters-actions">
                        <button type="submit" class="btn btn-primary" id="btnGenerate">
                            <i class="fa-solid fa-gears"></i> Generate Report
                        </button>
                        <button type="button" id="btnResetFilters" class="btn btn-secondary">
                            <i class="fa-solid fa-arrow-rotate-left"></i> Reset Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Report Preview Card -->
            <div class="white-card">
                <div class="white-card-header">
                    <div class="white-card-title">
                        <i class="fa-solid fa-magnifying-glass-chart" style="color: var(--primary-color);"></i> Report Preview
                    </div>
                    <div class="preview-actions">
                        <button type="button" class="btn-export" onclick="triggerExport('pdf')" title="Opens a print-ready page. Choose “Save as PDF” in the print dialog.">
                            <i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i> Export PDF
                        </button>
                        <button type="button" class="btn-export" onclick="triggerExport('csv')" title="Downloads a CSV file that opens in Excel.">
                            <i class="fa-solid fa-file-excel" style="color: #10b981;"></i> Export CSV
                        </button>
                        <button type="button" class="btn-export" onclick="triggerExport('print')">
                            <i class="fa-solid fa-print" style="color: var(--primary-color);"></i> Print Report
                        </button>
                    </div>
                </div>

                <div class="report-meta" id="reportMeta"></div>

                <div class="table-container admin-table-card">
                    <table>
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
            </div>

            <!-- Recent Generated Reports Section -->
            <div class="white-card">
                <div class="white-card-header">
                    <div class="white-card-title">
                        <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary-color);"></i> Recent Generated Reports
                    </div>
                    <button type="button" class="btn-view-all" id="btnViewAll" hidden>
                        View All Reports <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>

                <div class="table-container admin-table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>Report Name</th>
                                <th>Generated By</th>
                                <th>Date Generated</th>
                                <th>File Format</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="recentTableBody">
                            <!-- Rendered dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<!-- Toast Success Notification Container -->
    <div class="toast-notification" id="toastNotification">
        <div class="toast-content">
            <div style="width: 32px; height: 32px; border-radius: 50%; background-color: rgba(16, 185, 129, 0.1); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 16px;" id="reportToastIcon">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div style="display: flex; flex-direction: column; gap: 2px;">
                <span style="font-weight: 700; font-size: 14px; color: var(--text-main);" id="reportToastTitle">Notification Title</span>
                <span style="font-size: 12.5px; color: var(--text-muted);" id="toastMessage">Notification details message goes here.</span>
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
    const RECENT_PREVIEW = 5;
    const PALETTE = ['#385585', '#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#14b8a6', '#8b5cf6', '#64748b'];

    let payload = @json($report);
    let currentPage = 1;
    let showAllRecent = false;
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

        byId('monthlyRange').textContent = charts.monthly.range ? '(' + charts.monthly.range + ')' : '';
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
        meta.innerHTML =
            '<strong>' + escapeHTML(report.title) + '</strong>' +
            '<span>' + report.total + (report.total === 1 ? ' record' : ' records') + '</span>' +
            '<span>' + escapeHTML(describeFilters(payload.filters, report.basis)) + '</span>' +
            '<span>As of ' + escapeHTML(payload.generatedAt) + '</span>';
    }

    function renderCell(column, value, index) {
        const text = (value === null || value === undefined || value === '') ? '—' : value;
        if (column.kind === 'status') {
            return '<td><span class="status-pill ' + slug(text) + '">' + escapeHTML(text) + '</span></td>';
        }
        if (column.kind === 'num') {
            return '<td class="col-num">' + escapeHTML(text) + '</td>';
        }
        return '<td>' + (index === 0 ? '<strong>' + escapeHTML(text) + '</strong>' : escapeHTML(text)) + '</td>';
    }

    function renderTable() {
        const report = payload.report;
        const head = byId('previewTableHead');
        const body = byId('previewTableBody');
        const info = byId('paginationInfo');
        const buttons = byId('paginationButtons');

        head.innerHTML = report.columns
            .map((c) => '<th' + (c.kind === 'num' ? ' class="col-num"' : '') + '>' + escapeHTML(c.label) + '</th>')
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
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        const wrap = byId('paginationButtons');
        wrap.innerHTML = '';

        function add(label, page, cls, isHTML) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-page' + (cls ? ' ' + cls : '');
            if (cls === 'disabled') btn.disabled = true;
            if (isHTML) btn.innerHTML = label; else btn.textContent = label;
            if (!cls.includes('disabled') && page !== currentPage) {
                btn.addEventListener('click', () => { currentPage = page; renderTable(); });
            }
            wrap.appendChild(btn);
        }

        add('<i class="fa-solid fa-angle-left"></i>', currentPage - 1, currentPage === 1 ? 'disabled' : '', true);
        for (let i = 1; i <= totalPages; i++) add(String(i), i, currentPage === i ? 'active' : '', false);
        add('<i class="fa-solid fa-angle-right"></i>', currentPage + 1, currentPage === totalPages ? 'disabled' : '', true);
    }

    // ----- recent generated reports --------------------------------------
    function renderRecent() {
        const body = byId('recentTableBody');
        const toggle = byId('btnViewAll');
        const rows = payload.recent || [];

        toggle.hidden = rows.length <= RECENT_PREVIEW;
        toggle.innerHTML = showAllRecent
            ? 'Show Fewer <i class="fa-solid fa-arrow-up"></i>'
            : 'View All Reports <i class="fa-solid fa-arrow-right"></i>';

        if (rows.length === 0) {
            body.innerHTML =
                '<tr><td colspan="5" class="empty-cell"><i class="fa-solid fa-folder-open"></i>' +
                'No generated reports found.<span>Reports you generate or export will be listed here.</span></td></tr>';
            return;
        }

        const visible = showAllRecent ? rows : rows.slice(0, RECENT_PREVIEW);
        body.innerHTML = visible.map((row, i) =>
            '<tr>' +
                '<td><strong>' + escapeHTML(row.name) + '</strong><span class="recent-detail">' + escapeHTML(row.detail) + '</span></td>' +
                '<td>' + escapeHTML(row.by) + '</td>' +
                '<td>' + escapeHTML(row.date) + '</td>' +
                '<td><span class="format-tag">' + escapeHTML(row.format) + '</span></td>' +
                '<td style="text-align: center;"><button type="button" class="btn-action-outline view" data-rerun="' + i + '">' +
                    '<i class="fa-solid fa-rotate-right"></i> Re-run</button></td>' +
            '</tr>'
        ).join('');
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
            if (seq === loadSeq) applyPayload(data);
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

    byId('btnViewAll').addEventListener('click', () => {
        showAllRecent = !showAllRecent;
        renderRecent();
    });

    byId('recentTableBody').addEventListener('click', async (event) => {
        const button = event.target.closest('[data-rerun]');
        if (!button) return;

        const row = (showAllRecent ? payload.recent : payload.recent.slice(0, RECENT_PREVIEW))[Number(button.dataset.rerun)];
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

        if (format === 'csv') {
            window.location.href = url;
            toast('Export Started', 'Your CSV file is downloading. It opens in Excel.', 'success');
        } else {
            const win = window.open(url, '_blank');
            if (!win) {
                toast('Pop-up Blocked', 'Allow pop-ups for this site to open the print view.', 'error');
                return;
            }
            toast(format === 'pdf' ? 'Opening PDF View' : 'Opening Print View',
                format === 'pdf' ? 'Choose “Save as PDF” as the destination in the print dialog.' : 'The print dialog opens in the new tab.',
                'info');
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
