<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminMonitoringController extends Controller
{
    public function index(): View
    {
        $dbCategories = EquipmentCategory::orderBy('category_name')->pluck('category_name')->all();

        $dbDepartments = [];
        foreach (Department::orderBy('department_name')->get() as $d) {
            // Priority of head image mirrors legacy: matching-name account → head/chair/dean role → dept image
            $headImg = DepartmentAccount::where('department_id', $d->department_id)
                ->whereNotNull('profile_image')->where('profile_image', '!=', '')
                ->orderByRaw("CASE WHEN LOWER(full_name) = LOWER(?) THEN 1 WHEN LOWER(role) LIKE '%head%' THEN 2 WHEN LOWER(role) LIKE '%chair%' THEN 3 WHEN LOWER(role) LIKE '%dean%' THEN 4 ELSE 5 END, dept_acc_id ASC", [$d->department_head])
                ->value('profile_image');

            if (!$headImg && !empty($d->profile_image)) {
                $headImg = $d->profile_image;
            }

            $displayName = $d->department_head ?: $d->department_name;
            $img = $headImg ?: 'https://ui-avatars.com/api/?name=' . urlencode($displayName) . '&background=385585&color=fff&size=300&bold=true';

            $dbDepartments[] = [
                'department_id' => (int) $d->department_id,
                'department_name' => $d->department_name,
                'department_code' => $d->department_code ?? '',
                'college' => $d->college ?? '',
                'department_head' => $d->department_head ?? '',
                'profile_image' => $headImg,
                'title' => $d->department_name,
                'name' => $d->department_name,
                'image' => $img,
            ];
        }

        $dbRequests = $this->buildMonitoringRows();

        return view('admin.monitoring', compact('dbCategories', 'dbDepartments', 'dbRequests'));
    }

    /**
     * Live monitoring rows: one per equipment, showing the current borrower and
     * borrow status (Borrowed / Overdue) or the equipment's own status when it
     * is not out on loan. Returned items fall back to the equipment row, so a
     * user return is reflected here immediately.
     */
    private function buildMonitoringRows(): array
    {
        $rows = [];

        $equipmentList = Equipment::with(['category', 'department'])
            ->orderBy('equipment_id')
            ->get();

        if ($equipmentList->isEmpty()) {
            return $rows;
        }

        // Transactions that are still out on loan (Active / Overdue), keyed by equipment.
        $activeTransactions = BorrowTransaction::with([
            'request.user.student',
            'request.user.facultyMember',
        ])
            ->where('status', '!=', 'Returned')
            ->whereHas('request', fn ($query) => $query->where('overall_status', 'Approved'))
            ->orderBy('transaction_id')
            ->get()
            ->groupBy(fn ($transaction) => $transaction->request?->equipment_id);

        foreach ($equipmentList as $equipment) {
            $rawImg = trim((string) ($equipment->image ?? ''));
            if ($rawImg === '') {
                $img = asset('images/EquipTrack_logo.png');
            } elseif (preg_match('/^(https?:\/\/|data:|\/storage\/)/i', $rawImg)) {
                $img = $rawImg;
            } else {
                $img = asset(ltrim($rawImg, '/'));
            }

            $transaction = $activeTransactions->get($equipment->equipment_id)?->last();
            $request = $transaction?->request;
            $user = $request?->user;

            if ($transaction && $request && $user) {
                $fullName = $user->fullName() ?: $user->email;
                $avatar = trim((string) ($user->profile_image ?? ''));
                if ($avatar === '') {
                    $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fullName ?: 'User') . '&background=385585&color=fff';
                }

                $idNumber = $user->role === 'Student'
                    ? ($user->student?->id_number ?? '')
                    : ($user->facultyMember?->faculty_id_number ?? '');

                $isOverdue = $transaction->due_date
                    && Carbon::parse($transaction->due_date)->startOfDay()->lt(now()->startOfDay());

                $rows[] = [
                    'id' => (int) $equipment->equipment_id,
                    'user' => $fullName ?: 'Unknown User',
                    'role' => ucfirst($user->role),
                    'id_number' => $idNumber,
                    'equipment' => $equipment->name,
                    'category' => $equipment->category?->category_name ?: 'General',
                    'department' => $equipment->department?->department_name ?? '',
                    'status' => $isOverdue ? 'Overdue' : 'Borrowed',
                    'borrowDate' => $transaction->borrow_date ? Carbon::parse($transaction->borrow_date)->format('M d, Y') : '—',
                    'dueDate' => $transaction->due_date ? Carbon::parse($transaction->due_date)->format('M d, Y') : '—',
                    'date' => $request->date_requested ? Carbon::parse($request->date_requested)->format('M d, Y') : '—',
                    'purpose' => $request->purpose ?: 'N/A',
                    'notes' => $request->notes ?: 'None',
                    'img' => $img,
                    'avatar' => $avatar,
                ];

                continue;
            }

            $rows[] = [
                'id' => (int) $equipment->equipment_id,
                'user' => '—',
                'role' => '—',
                'id_number' => '—',
                'equipment' => $equipment->name,
                'category' => $equipment->category?->category_name ?: 'General',
                'department' => $equipment->department?->department_name ?? '',
                'status' => $equipment->status,
                'borrowDate' => '—',
                'dueDate' => '—',
                'date' => '—',
                'purpose' => '—',
                'notes' => '—',
                'img' => $img,
                'avatar' => '—',
            ];
        }

        return $rows;
    }

}
