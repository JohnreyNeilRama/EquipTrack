<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Indexes on the columns the busiest pages filter and sort by, so they stay
// fast as requests, loans and audit entries pile up over the semesters.
// Indexes only: no columns are added and no data changes.
//
// - borrow_request.overall_status / dept_status: review queues, dashboards,
//   counts, expiring stale requests.
// - borrow_transaction (status, due_date): Monitoring, Returns, Releases,
//   overdue checks and reminder emails (open loans by due date).
// - audit_trail.created_at / action: the Audit Trail's date range, sorting
//   and Action filter (matched on the start of the action text).
return new class extends Migration
{
    /** table => [index name => columns] */
    private array $indexes = [
        'borrow_request' => [
            'idx_borrow_request_overall_status' => ['overall_status'],
            'idx_borrow_request_dept_status' => ['dept_status'],
        ],
        'borrow_transaction' => [
            'idx_borrow_transaction_status_due' => ['status', 'due_date'],
        ],
        'audit_trail' => [
            'idx_audit_trail_created_at' => ['created_at'],
            'idx_audit_trail_action' => ['action'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes) {
                foreach ($indexes as $name => $columns) {
                    if (!Schema::hasIndex($table, $name)) {
                        $blueprint->index($columns, $name);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes) {
                foreach (array_keys($indexes) as $name) {
                    if (Schema::hasIndex($table, $name)) {
                        $blueprint->dropIndex($name);
                    }
                }
            });
        }
    }
};
