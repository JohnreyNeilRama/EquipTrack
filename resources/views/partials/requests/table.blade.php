{{-- Shared Requests controls bar and table (Admin Requests + Department Borrow Requests). --}}
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
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
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
