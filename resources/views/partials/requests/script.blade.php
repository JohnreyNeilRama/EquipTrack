{{--
    Shared Requests table script (Admin Requests + Department Borrow Requests).

    Expects:
      $party      'admin' or 'dept' — the reviewer viewing the page
      $dataUrl    JSON feed polled for live updates
      $updateUrl  Approve / Reject endpoint for that reviewer
      $dbRequests initial rows (App\Services\RequestTableRows)

    Optional (compact "recent requests" table, e.g. the Department dashboard):
      $limit          show only the newest N requests, without search, filter
                      or pagination (default: null, the full paginated table)
      $showRequestId  add a leading Request ID column (default: false)
      $combineUserRole show the role under the user's name (Return Confirmation
                      style) instead of in its own Role column (default: false)
      $stackEquipmentTag show the "Expensive" tag on its own line under the
                      equipment name instead of beside it (default: false)
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
        const COLUMN_COUNT = (SHOW_REQUEST_ID ? 7 : 6) - (COMBINE_USER_ROLE ? 1 : 0);
        // The compact layouts give the User cell a class hook and the full
        // name as a tooltip, so the page can shorten long names with "…".
        const TRUNCATE_USER = Boolean(LIMIT || SHOW_REQUEST_ID);

        // Initial requests dataset loaded directly from the database
        let requests = @json($dbRequests, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        // DOM Elements
        const tableBody = document.getElementById('requestsTableBody');
        const searchInput = document.getElementById('searchRequests');
        const filterSelect = document.getElementById('filterStatus');

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

        // Function to fetch latest requests from backend (live polling & after updates)
        function loadRequests() {
            fetch(URL_DATA)
                .then(res => res.json())
                .then(data => {
                    if (data.success && Array.isArray(data.requests)) {
                        requests = data.requests;
                        renderTable();
                        updateDashboardStats();
                    }
                })
                .catch(err => console.error('Error fetching requests:', err));
        }

        // Auto-refresh requests every 5 seconds for seamless automatic updating
        setInterval(loadRequests, 5000);

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
            tableBody.innerHTML = '';

            const filtered = requests.filter(req => {
                const matchesSearch = (req.user || '').toLowerCase().includes(query) ||
                                      (req.equipment || '').toLowerCase().includes(query) ||
                                      (req.role || '').toLowerCase().includes(query);
                const matchesFilter = filter === 'all' || (req.status || '').toLowerCase() === filter;
                return matchesSearch && matchesFilter;
            });

            if (filtered.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="${COLUMN_COUNT}" class="no-requests-message">
                            <i class="fa-solid fa-folder-open"></i>
                            ${LIMIT ? 'No recent borrow requests found.' : 'No requests found matching your query.'}
                        </td>
                    </tr>
                `;
                if (paginationContainer) paginationContainer.style.display = 'none';
                return;
            }

            const totalEntries = filtered.length;
            const totalPages = Math.ceil(totalEntries / pageSize);

            if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, totalEntries);
            // Rows arrive newest first, so the compact table shows the newest LIMIT
            const paginatedRequests = LIMIT ? filtered.slice(0, LIMIT) : filtered.slice(startIndex, endIndex);

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
                    ${STACK_EQUIPMENT_TAG
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

            const prevBtn = document.createElement('button');
            prevBtn.className = 'btn-page';
            prevBtn.innerHTML = '<i class="fa-solid fa-angle-left"></i>';
            prevBtn.disabled = currentPage === 1;
            prevBtn.addEventListener('click', () => {
                currentPage--;
                renderTable();
            });
            paginationButtons.appendChild(prevBtn);

            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.className = `btn-page ${currentPage === i ? 'active' : ''}`;
                pageBtn.textContent = i;
                pageBtn.addEventListener('click', () => {
                    currentPage = i;
                    renderTable();
                });
                paginationButtons.appendChild(pageBtn);
            }

            const nextBtn = document.createElement('button');
            nextBtn.className = 'btn-page';
            nextBtn.innerHTML = '<i class="fa-solid fa-angle-right"></i>';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.addEventListener('click', () => {
                currentPage++;
                renderTable();
            });
            paginationButtons.appendChild(nextBtn);
        }

        // Approve Request function
        window.approveRequest = function(id) {
            if (!confirm('Are you sure you want to approve this equipment request?')) return;

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
            document.getElementById('modalUserAvatar').src = req.avatar || "https://ui-avatars.com/api/?name=" + encodeURIComponent(req.user);
            document.getElementById('modalUserName').textContent = req.user;
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
            document.getElementById('modalNotes').value = req.notes || 'None';

            const reasonGroup = document.getElementById('modalReasonGroup');
            if (req.status.toLowerCase() === 'rejected') {
                document.getElementById('modalReason').value = req.rejectReason || 'No reason provided.';
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
        function updateDashboardStats() {
            if (PARTY === 'admin') {
                const pendingCount = requests.filter(r => r.status.toLowerCase() === 'pending').length;
                localStorage.setItem('dashboard-pending-count', pendingCount);
            }
            document.dispatchEvent(new CustomEvent('requests:updated', { detail: { requests } }));
        }

        // Event Listeners for search & filters (absent in the compact table)
        if (searchInput) searchInput.addEventListener('input', () => {
            currentPage = 1;
            renderTable();
        });
        if (filterSelect) filterSelect.addEventListener('change', () => {
            currentPage = 1;
            renderTable();
        });

        // Initial render
        renderTable();
        updateDashboardStats();
    });
</script>
