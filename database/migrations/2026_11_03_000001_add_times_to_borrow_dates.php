<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Due deadlines: borrowers still choose dates only, but a due date now means
// "by the return deadline" (config equiptrack.return_deadline) on that day, so
// the pickup and due columns change from DATE to DATETIME (Philippine
// wall-clock time, like the dates were). Existing records keep their dates:
// pickups at the start of the day, due dates at 23:59, so nothing that was on
// time becomes overdue. borrow_transaction.return_date (the day it came back)
// stays a DATE.
return new class extends Migration
{
    /** Columns changed, and the ones that hold due dates (set to 23:59). */
    private const COLUMNS = [
        'borrow_request' => ['date_needed', 'borrow_date', 'return_date', 'due_date'],
        'borrow_transaction' => ['borrow_date', 'due_date'],
    ];

    private const DUE_COLUMNS = [
        'borrow_request' => ['return_date', 'due_date'],
        'borrow_transaction' => ['due_date'],
    ];

    public function up(): void
    {
        // ->change() works on MySQL and on SQLite (the test database).
        Schema::table('borrow_request', function (Blueprint $table) {
            $table->dateTime('date_needed')->change();
            $table->dateTime('borrow_date')->nullable()->change();
            $table->dateTime('return_date')->nullable()->change();
            $table->dateTime('due_date')->nullable()->change();
        });

        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->dateTime('borrow_date')->change();
            $table->dateTime('due_date')->change();
        });

        $this->endWholeDayDueDates();
    }

    /** Whole-day due dates (read as midnight) mean "by the end of that day": 23:59. */
    public function endWholeDayDueDates(): void
    {
        foreach (self::DUE_COLUMNS as $table => $columns) {
            $key = $table === 'borrow_request' ? 'request_id' : 'transaction_id';

            foreach ($columns as $column) {
                DB::table($table)->whereNotNull($column)->orderBy($key)
                    ->select([$key, $column])
                    ->each(function ($row) use ($table, $key, $column) {
                        $value = (string) $row->{$column};
                        if (substr($value, 11, 8) === '' || substr($value, 11, 8) === '00:00:00') {
                            DB::table($table)->where($key, $row->{$key})
                                ->update([$column => substr($value, 0, 10) . ' 23:59:00']);
                        }
                    });
            }
        }
    }

    public function down(): void
    {
        Schema::table('borrow_request', function (Blueprint $table) {
            $table->date('date_needed')->change();
            $table->date('borrow_date')->nullable()->change();
            $table->date('return_date')->nullable()->change();
            $table->date('due_date')->nullable()->change();
        });

        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->date('borrow_date')->change();
            $table->date('due_date')->change();
        });
    }
};
