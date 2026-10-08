<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Equipment monitoring for the signed-in department.
 *
 * Uses the same row logic as the admin monitoring page
 * (AdminMonitoringController::buildMonitoringRows), scoped to the equipment
 * this department owns — the same scope as the department Requests page:
 *
 * - One row per active loan (Borrowed / Overdue), so every borrower holding a
 *   unit of the department's equipment is listed, whichever department the
 *   borrower belongs to.
 * - Equipment with no active loan keeps a single row with its own status, so
 *   a user return is reflected here as soon as the transaction is Returned.
 */
class DepartmentMonitoringController extends Controller
{
    public function index(): View
    {
        $dbCategories = EquipmentCategory::orderBy('category_name')->pluck('category_name')->all();
        $dbMonitoring = $this->buildMonitoringRows();

        return view('department.monitoring', compact('dbCategories', 'dbMonitoring'));
    }

    private function buildMonitoringRows(): array
    {
        $departmentId = auth('dept')->user()->department_id;

        if (!$departmentId) {
            return [];
        }

        $equipmentList = Equipment::with('category')
            ->where('department_id', $departmentId)
            ->orderBy('equipment_id')
            ->get();

        if ($equipmentList->isEmpty()) {
            return [];
        }

        // Loans still out (Active / Overdue) for this department's equipment, keyed by equipment.
        $activeTransactions = BorrowTransaction::with([
            'request.user.student',
            'request.user.facultyMember',
        ])
            ->where('status', '!=', 'Returned')
            ->whereHas('request', function ($query) use ($departmentId) {
                $query->where('overall_status', 'Approved')
                    ->whereHas('equipment', fn ($equipment) => $equipment->where('department_id', $departmentId));
            })
            ->orderBy('transaction_id')
            ->get()
            ->groupBy(fn ($transaction) => $transaction->request?->equipment_id);

        $fmt = fn ($value) => $value ? Carbon::parse($value)->format('M d, Y') : '—';

        $rows = [];

        foreach ($equipmentList as $equipment) {
            $category = $equipment->category?->category_name ?: 'General';

            // Equipment picture from the equipment record (same resolution as the
            // admin monitoring page); falls back to the EquipTrack logo when empty.
            $rawImg = trim((string) ($equipment->image ?? ''));
            if ($rawImg === '') {
                $img = asset('images/EquipTrack_logo.png');
            } elseif (preg_match('/^(https?:\/\/|data:|\/storage\/)/i', $rawImg)) {
                $img = $rawImg;
            } else {
                $img = asset(ltrim($rawImg, '/'));
            }

            $loanRows = [];

            foreach ($activeTransactions->get($equipment->equipment_id, collect()) as $transaction) {
                $request = $transaction->request;
                $user = $request?->user;

                if (!$request || !$user) {
                    continue;
                }

                $idNumber = $user->role === 'Student'
                    ? ($user->student?->id_number ?? '')
                    : ($user->facultyMember?->faculty_id_number ?? '');

                $isOverdue = ($transaction->due_date
                    && Carbon::parse($transaction->due_date)->startOfDay()->lt(now()->startOfDay()))
                    || $transaction->status === 'Overdue';

                $loanRows[] = [
                    'equipment' => $equipment->name,
                    'category' => $category,
                    'borrower' => $user->fullName() ?: $user->email,
                    'id_number' => $idNumber,
                    'role' => ucfirst($user->role),
                    'status' => $isOverdue ? 'Overdue' : 'Borrowed',
                    'borrowDate' => $fmt($transaction->borrow_date),
                    'returnDate' => $fmt($transaction->due_date),
                    'img' => $img,
                ];
            }

            if ($loanRows) {
                array_push($rows, ...$loanRows);

                continue;
            }

            $rows[] = [
                'equipment' => $equipment->name,
                'category' => $category,
                'borrower' => '—',
                'id_number' => '—',
                'role' => '—',
                'status' => $equipment->status,
                'borrowDate' => '—',
                'returnDate' => '—',
                'img' => $img,
            ];
        }

        // Page-level row id (several loan rows can belong to the same equipment).
        foreach ($rows as $i => &$row) {
            $row['id'] = 'MON-' . ($i + 1);
        }
        unset($row);

        return $rows;
    }
}
