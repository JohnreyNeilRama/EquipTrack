{{-- Shared Requests controls bar and table (Admin Requests + Department Borrow Requests).
     Optional: $showRequestId adds a leading Request ID column (pair it with the
     script's showRequestId); $combineUserRole shows the role under the user's
     name instead of in its own column (pair it with the script's option);
     $tableClass is a class hook for page styles. The search and filters
     (Status, and Student / Faculty Member role) sit in the shared filter card
     (ccs/filter-card.css). --}}
        <div class="filter-card-container">
            <div class="search-box-right-icon">
                <input type="text" id="searchRequests" placeholder="Search user or equipment" aria-label="Search requests">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <div class="filter-select-item">
                <select id="filterStatus" aria-label="Filter by status">
                    <option value="all">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="expired">Expired</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="filter-select-item">
                <select id="filterRole" aria-label="Filter by role">
                    <option value="all">All Roles</option>
                    <option value="student">Student</option>
                    <option value="faculty">Faculty Member</option>
                </select>
                <i class="fa-solid fa-chevron-down"></i>
            </div>
        </div>

        {{-- Requests Table Card --}}
        <div class="table-container card admin-table-card">
            <table @if (!empty($tableClass)) class="{{ $tableClass }}" @endif style="width: 100%;">
                <thead>
                    <tr>
                        @if (!empty($showRequestId))
                            <th>Request ID</th>
                            <th class="col-user">User</th>
                        @else
                            <th>User</th>
                        @endif
                        @if (empty($combineUserRole))
                            <th>Role</th>
                        @endif
                        <th>Equipment</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="action-column" style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="requestsTableBody">
                    {{-- Loaded dynamically via JavaScript --}}
                </tbody>
            </table>

            {{-- Pagination Footer --}}
            <div class="pagination-container" id="paginationContainer">
                <span class="pagination-info" id="paginationInfo">Showing 0 to 0 of 0 entries</span>
                <div class="pagination-buttons" id="paginationButtons">
                    {{-- Buttons added dynamically --}}
                </div>
            </div>
        </div>
