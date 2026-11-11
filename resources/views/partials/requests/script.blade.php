{{--
    Shared Requests table script (Admin Requests + Department Borrow Requests).

    Expects:
      $party      'admin' or 'dept' — the reviewer viewing the page
      $dataUrl    JSON feed polled for live updates
      $updateUrl  Approve / Reject endpoint for that reviewer
      $dbRequests initial rows (App\Services\RequestTableRows)

    Full table (no $limit): paged on the server, 25 rows a page. Search and
    the Status / Role filters are sent to $dataUrl (?paged=1&page=…&search=…
    &status=…&role=…), so they cover every request, not just one page.
    Status starts on "Active" (finished requests hidden). Pass $requestPage
    (RequestTableRows::page) for the first page's counts and page numbers.

    Optional (compact "recent requests" table, e.g. the Department dashboard):
      $limit          show only the newest N requests, without search, filter
                      or pagination (default: null, the full paginated table)
      $showRequestId  add a leading Request ID column (default: false)
      $combineUserRole show the role under the user's name (Return Confirmation
                      style) instead of in its own Role column (default: false)
      $stackEquipmentTag show the "Expensive" tag on its own line under the
                      equipment name instead of beside it (default: false)
      $truncateEquipment keep the equipment name on one line, ending in "…"
                      (full name on hover and in the details modal); needs
                      $stackEquipmentTag (default: false)
--}}
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const CSRF_TOKEN = @json(csrf_token());
        const URL_DATA = @json($dataUrl);
        const URL_UPDATE = @json($updateUrl);

        // Which reviewer is viewing: decides whose approval status gates the
        // action buttons, and which party an Expensive request still awaits.
        const PARTY = @json($party);
        const OWN_STATUS_KEY = PARTY === 'admin' ? 'adminStatus' : 'deptStatus';
        const AWAITING_LABEL = PARTY === 'admin' ? 'Awaiting Department' : 'Awaiting Admin';

        // Compact mode: newest LIMIT rows only, optional Request ID column
        const LIMIT = @json($limit ?? null);
        const SHOW_REQUEST_ID = @json($showRequestId ?? false);
        const COMBINE_USER_ROLE = @json($combineUserRole ?? false);
        const STACK_EQUIPMENT_TAG = @json($stackEquipmentTag ?? false);
        const TRUNCATE_EQUIPMENT = STACK_EQUIPMENT_TAG && @json($truncateEquipment ?? false);
        const COLUMN_COUNT = (SHOW_REQUEST_ID ? 7 : 6) - (COMBINE_USER_ROLE ? 1 : 0);
        // The compact layouts give the User cell a class hook and the full
        // name as a tooltip, so the page can shorten long names with "…".
        const TRUNCATE_USER = Boolean(LIMIT || SHOW_REQUEST_ID);

        // Initial requests dataset loaded directly from the database
        let requests = @json($dbRequests, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        // Full table: one page at a time from the server (see the note above).
        const PAGED = !LIMIT;
        let serverPage = @json($requestPage['pagination'] ?? null);
        let statusCounts = @json($requestPage['counts'] ?? null);
        let loadSeq = 0;

        // DOM Elements
        const tableBody = document.getElementById('requestsTableBody');
        const searchInput = document.getElementById('searchRequests');
        const filterSelect = document.getElementById('filterStatus');
        // Student / Faculty Member filter (Admin Requests and Department Borrow Requests)
        const filterRole = document.getElementById('filterRole');

        // Pagination Elements
        const paginationContainer = document.getElementById('paginationContainer');
        const paginationInfo = document.getElementById('paginationInfo');
        const paginationButtons = document.getElementById('paginationButtons');

        // Pagination state
        let currentPage = 1;
        const pageSize = 10;

        // Modals
        const detailsModal = document.getElementById('detailsModal');
        const closeDetailsBtn = document.getElementById('closeDetailsBtn');
        const rejectReasonModal = document.getElementById('rejectReasonModal');
        const closeRejectReasonBtn = document.getElementById('closeRejectReasonBtn');
        const rejectReasonForm = document.getElementById('rejectReasonForm');
        const rejectRequestIdInput = document.getElementById('rejectRequestId');
        const rejectReasonInput = document.getElementById('rejectReasonInput');

        // The feed address: this page with the current search and filters
        // (full table), the newest LIMIT rows (compact table), or everything.
        function dataUrl() {
            const url = new URL(URL_DATA, window.location.origin);
            if (PAGED) {
                url.searchParams.set('paged', '1');
                url.searchParams.set('page', String(currentPage));
                url.searchParams.set('status', filterSelect ? filterSelect.value : 'all');
                url.searchParams.set('role', filterRole ? filterRole.value : 'all');
                const query = searchInput ? searchInput.value.trim() : '';
                if (query !== '') url.searchParams.set('search', query);
            } else if (LIMIT) {
                url.searchParams.set('limit', String(LIMIT));
            }
            return url.toString();
        }

        // Function to fetch latest requests from backend (live polling & after updates)
        function loadRequests() {
            // Only the newest answer counts (typing a search sends several).
            const seq = ++loadSeq;
            fetch(dataUrl(), { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    if (seq !== loadSeq) return;
                    if (data.success && Array.isArray(data.requests)) {
                        requests = data.requests;
                        if (PAGED && data.pagination) {
                            serverPage = data.pagination;
                            currentPage = data.pagination.page;
                            statusCounts = data.counts || statusCounts;
                        }
                        renderTable();
                        updateDashboardStats();
                    }
                })
                .catch(err => console.error('Error fetching requests:', err));
        }

        // Auto-refresh every 20 seconds while the tab is visible; a hidden tab
        // waits, then catches up as soon as it is shown again.
        setInterval(() => { if (!document.hidden) loadRequests(); }, 20000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) loadRequests();
        });

        // The reviewer can act only on requests their role handles (the admin:
        // Expensive only; the department: every request for its equipment),
        // while the request is pending AND this reviewer has not reviewed it yet.
        // An Expensive request stays Pending after one approval, until the other
        // party approves too.
        function reviewerCanAct(req) {
            return req.canReview !== false
                && (req.status || '').toLowerCase() === 'pending'
                && (req[OWN_STATUS_KEY] || 'Pending').toLowerCase() === 'pending';
        }

        function partyLabel(status) {
            const s = (status || 'Pending');
            const cls = 'party-' + s.toLowerCase();
            return `<span class="${escapeHTML(cls)}">${escapeHTML(s)}</span>`;
        }

        // "Admin: Approved · Department: Pending" for Expensive requests only.
        function approvalsHtml(req) {
            if (!req.requiresBothApprovals) return '';
            return `
                <div class="approval-parties">
                    <span class="approval-party">Admin: ${partyLabel(req.adminStatus)}</span>
                    <span class="approval-party">Department: ${partyLabel(req.deptStatus)}</span>
                </div>
            `;
        }

        // Render Table function
        function renderTable() {
            const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const filter = filterSelect ? filterSelect.value : 'all';
            const role = filterRole ? filterRole.value : 'all';
            tableBody.innerHTML = '';

            // Full table: the server already searched, filtered and cut the
            // page. Compact table: the newest rows as they arrived.
            const filtered = PAGED ? requests : requests.filter(req => {
                const matchesSearch = (req.user || '').toLowerCase().includes(query) ||
                                      (req.equipment || '').toLowerCase().includes(query) ||
                                      (req.role || '').toLowerCase().includes(query);
                const matchesFilter = filter === 'all' || (req.status || '').toLowerCase() === filter;
                const matchesRole = role === 'all' || (req.role || '').toLowerCase().includes(role);
                return matchesSearch && matchesFilter && matchesRole;
            });

            if (filtered.length === 0) {
                const filtersInUse = query !== '' || filter !== 'active' || role !== 'all';
                const emptyText = LIMIT ? 'No recent borrow requests found.'
                    : (PAGED && !filtersInUse
                        ? 'No active requests. Choose "All Status" to see finished ones.'
                        : 'No requests found matching your query.');
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="${COLUMN_COUNT}" class="no-requests-message">
                            <i class="fa-solid fa-folder-open"></i>
                            ${emptyText}
                        </td>
                    </tr>
                `;
                if (paginationContainer) paginationContainer.style.display = 'none';
                return;
            }

            const perPage = PAGED && serverPage ? serverPage.perPage : pageSize;
            const totalEntries = PAGED && serverPage ? serverPage.total : filtered.length;
            const totalPages = PAGED && serverPage ? serverPage.lastPage : Math.ceil(totalEntries / pageSize);

            if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

            const startIndex = (currentPage - 1) * perPage;
            const endIndex = PAGED ? startIndex + filtered.length : Math.min(startIndex + pageSize, totalEntries);
            // Rows arrive newest first, so the compact table shows the newest LIMIT
            const paginatedRequests = PAGED ? filtered
                : (LIMIT ? filtered.slice(0, LIMIT) : filtered.slice(startIndex, endIndex));

            if (paginationContainer) paginationContainer.style.display = 'flex';

            paginatedRequests.forEach(req => {
                const tr = document.createElement('tr');
                tr.className = 'admin-table-row';

                // Click row opens details modal (excluding clicking action buttons)
                tr.addEventListener('click', (e) => {
                    if (!e.target.closest('.action-buttons') && !e.target.closest('button')) {
                        openDetailsModal(req.id);
                    }
                });

                // Action buttons cells
                let actionHtml = '';
                if (reviewerCanAct(req)) {
                    actionHtml = `
                        <div class="action-buttons" style="justify-content: center; gap: 8px;">
                            <button class="btn-approve" onclick="approveRequest(${req.id})">Approve</button>
                            <button class="btn-reject" onclick="triggerRejectReason(${req.id})">Reject</button>
                        </div>
                    `;
                } else {
                    const awaiting = req.status.toLowerCase() === 'pending'
                        ? `<span class="awaiting-note">${AWAITING_LABEL}</span><br>`
                        : '';
                    actionHtml = `
                        ${awaiting}
                        <button class="btn-view-only" onclick="openDetailsModal(${req.id})">
                            <i class="fa-solid fa-eye"></i> View
                        </button>
                    `;
                }

                // Format status text
                const statusClass = 'status-' + req.status.toLowerCase();
                const qtyBadge = req.quantity > 1 ? ` <span style="font-size: 11px; opacity: 0.8;">(${req.quantity} pcs)</span>` : '';
                const classTag = req.requiresBothApprovals ? `<span class="class-tag-expensive">Expensive</span>` : '';

                tr.innerHTML = `
                    ${SHOW_REQUEST_ID ? `<td class="col-request-id">#${escapeHTML(req.id)}</td>` : ''}
                    <td${TRUNCATE_USER ? ' class="col-user"' : ''}>
                        <div class="requester-info">
                            <span class="requester-name"${TRUNCATE_USER ? ` title="${escapeHTML(req.user)}"` : ''}>${escapeHTML(req.user)}</span>
                            ${COMBINE_USER_ROLE ? `<span class="requester-role">${escapeHTML(req.role)}</span>` : ''}
                        </div>
                    </td>
                    ${COMBINE_USER_ROLE ? '' : `<td>${escapeHTML(req.role)}</td>`}
                    ${TRUNCATE_EQUIPMENT
                        ? `<td class="col-equipment-truncate"><div class="equipment-stack"><span class="equipment-stack-name equipment-line"><span class="equipment-name-text" title="${escapeHTML(req.equipment)}">${escapeHTML(req.equipment)}</span>${qtyBadge}</span>${classTag}</div></td>`
                        : STACK_EQUIPMENT_TAG
                        ? `<td><div class="equipment-stack"><span class="equipment-stack-name">${escapeHTML(req.equipment)}${qtyBadge}</span>${classTag}</div></td>`
                        : `<td>${escapeHTML(req.equipment)}${qtyBadge}${classTag}</td>`}
                    <td${TRUNCATE_USER ? ' class="col-date"' : ''}>${escapeHTML(req.date)}</td>
                    <td>
                        <span class="status-badge ${statusClass}">${escapeHTML(req.status)}</span>
                        ${approvalsHtml(req)}
                    </td>
                    <td class="action-cell" style="text-align: center;">${actionHtml}</td>
                `;
                tableBody.appendChild(tr);
            });

            if (paginationInfo) paginationInfo.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${totalEntries} entries`;
            if (paginationButtons) renderPaginationButtons(totalPages);
        }

        // Render pagination buttons dynamically (same behavior as Admin Users)
        function renderPaginationButtons(totalPages) {
            paginationButtons.innerHTML = '';

            // Full table: ask the server for that page; otherwise re-slice.
            const goTo = (page) => {
                currentPage = page;
                if (PAGED) loadRequests(); else renderTable();
            };

            const prevBtn = document.createElement('button');
            prevBtn.className = 'btn-page';
            prevBtn.innerHTML = '<i class="fa-solid fa-angle-left"></i>';
            prevBtn.setAttribute('aria-label', 'Previous page');
            prevBtn.disabled = currentPage === 1;
            prevBtn.addEventListener('click', () => goTo(currentPage - 1));
            paginationButtons.appendChild(prevBtn);

            // Up to 5 page numbers around the current page.
            let start = Math.max(1, currentPage - 2);
            const end = Math.min(totalPages, start + 4);
            start = Math.max(1, end - 4);

            for (let i = start; i <= end; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.className = `btn-page ${currentPage === i ? 'active' : ''}`;
                pageBtn.textContent = i;
                pageBtn.addEventListener('click', () => goTo(i));
                paginationButtons.appendChild(pageBtn);
            }

            const nextBtn = document.createElement('button');
            nextBtn.className = 'btn-page';
            nextBtn.innerHTML = '<i class="fa-solid fa-angle-right"></i>';
            nextBtn.setAttribute('aria-label', 'Next page');
            nextBtn.disabled = currentPage >= totalPages;
            nextBtn.addEventListener('click', () => goTo(currentPage + 1));
            paginationButtons.appendChild(nextBtn);
        }

        // Approve Request function
        window.approveRequest = async function(id) {
            const req = requests.find(r => r.id === id);
            const ok = await equipConfirm({
                title: 'Approve this request?',
                message: req ? `${req.user} — ${req.equipment}` : 'Are you sure you want to approve this equipment request?',
                confirmText: 'Approve',
                tone: 'primary',
            });
            if (!ok) return;

            const formData = new FormData();
            formData.append('_token', CSRF_TOKEN);
            formData.append('request_id', id);
            formData.append('status', 'Approved');

            fetch(URL_UPDATE, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotification(
                        data.overallStatus === 'Approved' ? 'Request Approved' : 'Approval Recorded',
                        data.message || 'Request approved successfully!',
                        'success'
                    );
                    detailsModal.classList.remove('show');
                    loadRequests();
                } else {
                    showNotification('Action Failed', data.message || 'Failed to approve request.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showNotification('Error', 'Network error occurred.', 'error');
            });
        };

        // Trigger Rejection Reason modal
        window.triggerRejectReason = function(id) {
            rejectRequestIdInput.value = id;
            rejectReasonInput.value = '';
            rejectReasonModal.classList.add('show');
        };

        // Rejection reason form submission
        rejectReasonForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const id = parseInt(rejectRequestIdInput.value);
            const reason = rejectReasonInput.value.trim();

            if (!id || !reason) {
                showNotification('Input Error', 'Please provide a valid rejection reason.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('_token', CSRF_TOKEN);
            formData.append('request_id', id);
            formData.append('status', 'Rejected');
            formData.append('reject_reason', reason);

            fetch(URL_UPDATE, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotification('Request Rejected', data.message || 'Request rejected.', 'error');
                    rejectReasonModal.classList.remove('show');
                    detailsModal.classList.remove('show');
                    loadRequests();
                } else {
                    showNotification('Action Failed', data.message || 'Failed to reject request.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showNotification('Error', 'Network error occurred.', 'error');
            });
        });

        // Open Details Modal
        window.openDetailsModal = function(id) {
            const req = requests.find(r => r.id === id);
            if (!req) return;

            // Populate modal user details
            document.getElementById('modalUserAvatar').src = req.avatar || "{{ url('/avatar') }}?name=" + encodeURIComponent(req.user);
            const modalUserName = document.getElementById('modalUserName');
            modalUserName.textContent = req.user;
            modalUserName.title = req.user || '';
            document.getElementById('modalUserRole').textContent = req.role;

            document.getElementById('modalEqImg').src = req.img;

            const statusBadge = document.getElementById('modalStatusBadge');
            statusBadge.textContent = req.status;
            statusBadge.className = 'status-badge status-' + req.status.toLowerCase();

            const approvalsBox = document.getElementById('modalApprovals');
            if (req.requiresBothApprovals) {
                approvalsBox.innerHTML = `
                    <span class="class-tag-expensive" style="margin: 8px 0 0;">Expensive · needs both approvals</span>
                    ${approvalsHtml(req)}
                `;
                approvalsBox.style.display = 'block';
            } else {
                approvalsBox.innerHTML = '';
                approvalsBox.style.display = 'none';
            }

            document.getElementById('modalEqName').value = req.equipment;
            document.getElementById('modalEqCategory').value = req.category;
            document.getElementById('modalEqQty').value = req.quantity + ' pc(s)';
            document.getElementById('modalReqDate').value = req.fullDate;
            document.getElementById('modalBorrowDate').value = req.borrowDate;
            document.getElementById('modalDueDate').value = req.dueDate;
            document.getElementById('modalPurpose').value = req.purpose;
            document.getElementById('modalInstructor').value = req.instructor || 'N/A';
            document.getElementById('modalNotes').value = req.notes || 'None';

            const reasonGroup = document.getElementById('modalReasonGroup');
            const reasonLabel = document.getElementById('modalReasonLabel');
            const reasonBox = document.getElementById('modalReason');
            const cancelled = req.status.toLowerCase() === 'cancelled';
            const expired = req.status.toLowerCase() === 'expired';
            reasonLabel.textContent = cancelled ? 'Cancelled' : (expired ? 'Expired' : 'Reason for Rejection');
            reasonLabel.classList.toggle('text-danger', !cancelled && !expired);
            reasonBox.classList.toggle('text-danger-control', !cancelled && !expired);
            if (req.status.toLowerCase() === 'rejected') {
                document.getElementById('modalReason').value = req.rejectReason || 'No reason provided.';
                reasonGroup.style.display = 'block';
            } else if (req.status.toLowerCase() === 'cancelled') {
                document.getElementById('modalReason').value = 'Cancelled by the borrower' + (req.cancelledAt ? ' on ' + req.cancelledAt : '') + ', before it was reviewed.';
                reasonGroup.style.display = 'block';
            } else if (expired) {
                document.getElementById('modalReason').value = 'Not reviewed before ' + req.expiresAt + ', its return deadline, so it can no longer be approved.';
                reasonGroup.style.display = 'block';
            } else {
                reasonGroup.style.display = 'none';
            }

            // Populate action buttons in footer
            const footer = document.getElementById('modalActionsFooter');
            if (reviewerCanAct(req)) {
                footer.innerHTML = `
                    <button class="btn-modal-close" id="modalCancelBtn">Close</button>
                    <button class="btn-modal-reject-trigger" onclick="triggerRejectReason(${req.id})">Reject</button>
                    <button class="btn-modal-approve" onclick="approveRequest(${req.id})">Approve</button>
                `;
            } else {
                footer.innerHTML = `
                    <button class="btn-modal-close" style="width: 100%;" id="modalCancelBtn">Close</button>
                `;
            }

            // Add close listener for modal close button
            document.getElementById('modalCancelBtn').addEventListener('click', () => {
                detailsModal.classList.remove('show');
            });

            detailsModal.classList.add('show');
        };

        // Close modals functions
        closeDetailsBtn.addEventListener('click', () => {
            detailsModal.classList.remove('show');
        });
        detailsModal.addEventListener('click', (e) => {
            if (e.target === detailsModal) {
                detailsModal.classList.remove('show');
            }
        });

        closeRejectReasonBtn.addEventListener('click', () => {
            rejectReasonModal.classList.remove('show');
        });
        rejectReasonModal.addEventListener('click', (e) => {
            if (e.target === rejectReasonModal) {
                rejectReasonModal.classList.remove('show');
            }
        });

        // Helper to escape HTML values
        function escapeHTML(str) {
            if (!str) return '';
            return String(str).replace(/[&<>'"]/g,
                tag => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                }[tag] || tag)
            );
        }

        // Sync stats to the admin dashboard in localStorage, and let the page
        // refresh anything derived from the rows (e.g. the department's summary cards).
        // counts: requests per status in the whole list (full table only),
        // for summary cards; requests: the rows shown.
        function updateDashboardStats() {
            if (PARTY === 'admin') {
                const pendingCount = statusCounts
                    ? statusCounts.Pending
                    : requests.filter(r => r.status.toLowerCase() === 'pending').length;
                try { localStorage.setItem('dashboard-pending-count', pendingCount); } catch (e) { /* storage blocked */ }
            }
            document.dispatchEvent(new CustomEvent('requests:updated', { detail: { requests, counts: statusCounts } }));
        }

        // Event Listeners for search & filters (absent in the compact table).
        // Full table: back to page 1 and ask the server; typing waits a moment.
        let searchTimer = null;
        const onFiltersChanged = (wait) => {
            currentPage = 1;
            if (!PAGED) { renderTable(); return; }
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadRequests, wait);
        };
        if (searchInput) searchInput.addEventListener('input', () => onFiltersChanged(300));
        if (filterSelect) filterSelect.addEventListener('change', () => onFiltersChanged(0));
        if (filterRole) filterRole.addEventListener('change', () => onFiltersChanged(0));

        // Initial render
        renderTable();
        updateDashboardStats();
    });
</script>
