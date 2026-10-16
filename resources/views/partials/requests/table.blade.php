{{-- Shared Requests controls bar and table (Admin Requests + Department Borrow Requests).
     Optional: $showRequestId adds a leading Request ID column (pair it with the
     script's showRequestId); $combineUserRole shows the role under the user's
     name instead of in its own column (pair it with the script's option);
     $tableClass is a class hook for page styles. --}}
        {{-- Controls bar (Search & Filters) --}}
        <div class="controls-bar">
            <div class="controls-left">
                <div class="search-box-wrapper">
                    <input type="text" id="searchRequests" placeholder="Search by user or equipment...">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <div class="filter-select-wrapper">
                    <select id="filterStatus">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <i class="fa-solid fa-chevron-down"></i>
                </div>
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
