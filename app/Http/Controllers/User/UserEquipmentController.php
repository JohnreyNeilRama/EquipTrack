<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserEquipmentController extends Controller
{
    public function index(): View
    {
        $dbCategories = EquipmentCategory::orderBy('category_name')->get();

        $dbEquipment = Equipment::with(['category', 'department'])
            ->orderByDesc('equipment_id')
            ->get();

        $categoryCounts = [];
        foreach ($dbEquipment as $eq) {
            $cName = $eq->category?->category_name ?: 'Others';
            $categoryCounts[$cName] = ($categoryCounts[$cName] ?? 0) + 1;
        }

        return view('user.equipment', compact('dbCategories', 'dbEquipment', 'categoryCounts'));
    }

    /**
     * Mirrors the legacy submit_request endpoint's validation order, with the
     * same JSON shape the modal JS expects.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id' => ['required', 'integer', 'min:1'],
            'borrow_date' => ['required', 'date'],
            'return_date' => ['required', 'date', 'after_or_equal:borrow_date'],
            'purpose' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'return_date.after_or_equal' => 'Return date cannot be earlier than borrow date.',
        ]);

        $userId = auth('user')->user()->user_id;

        $result = DB::transaction(function () use ($request, $data, $userId) {
            $equipment = Equipment::where('equipment_id', $data['equipment_id'])
                ->lockForUpdate()
                ->first();

            if (!$equipment) {
                return ['code' => 404, 'message' => 'The selected equipment was not found in the database.'];
            }

            if (strtolower($equipment->status) !== 'available' || (int) $equipment->available_qty <= 0) {
                return ['code' => 400, 'message' => 'Sorry, this equipment is currently unavailable for borrowing.'];
            }

            $borrow = BorrowRequest::create([
                'user_id' => $userId,
                'equipment_id' => $equipment->equipment_id,
                'quantity' => 1,
                'purpose' => $data['purpose'],
                'notes' => $data['notes'] ?? null,
                'date_needed' => $data['borrow_date'],
                'borrow_date' => $data['borrow_date'],
                'return_date' => $data['return_date'],
                'due_date' => $data['return_date'],
            ]);

            // Legacy code inserted audit rows with columns that don't exist on the
            // live table (timestamp/missing ip_address); write to the real schema.
            AuditTrail::create([
                'user_id' => $userId,
                'action' => 'Submitted borrow request #' . $borrow->request_id . ' for ' . $equipment->name,
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'affected_entity' => 'borrow_request:' . $borrow->request_id,
            ]);

            return [
                'code' => 200,
                'success' => true,
                'request_id' => $borrow->request_id,
            ];
        });

        if (($result['code'] ?? 200) !== 200) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] === 404 ? 404 : 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your borrowing request has been submitted successfully!',
            'request_id' => $result['request_id'],
            'redirect' => route('user.requests'),
        ]);
    }
}
