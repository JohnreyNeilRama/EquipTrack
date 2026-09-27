<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Department Equipment page.
 *
 * This page reads the same `equipment` records the admin manages (there is no
 * separate department inventory): every query is scoped to the signed-in
 * department account's department_id, so equipment assigned to another
 * department is never listed or editable here. Because the records are shared,
 * admin changes - including reassigning an equipment item to another
 * department - show up on this page automatically, and borrow/return quantity
 * updates stay in sync.
 */
class DepartmentEquipmentController extends Controller
{
    public function index(): View
    {
        return view('department.equipment', [
            'dbCategories' => EquipmentCategory::orderBy('category_name')->pluck('category_name')->all(),
            'dbCategoryMap' => EquipmentCategory::orderBy('category_name')
                ->get(['category_id', 'category_name'])
                ->map(fn ($category) => [
                    'category_id' => $category->category_id,
                    'category_name' => $category->category_name,
                ])->all(),
            'departmentName' => auth('dept')->user()?->department?->department_name ?? 'Your Department',
        ]);
    }

    /** JSON feed for the card grid: only the signed-in department's equipment. */
    public function data(): JsonResponse
    {
        $departmentId = $this->departmentId();

        if (!$departmentId) {
            return response()->json(['success' => true, 'equipment' => []]);
        }

        $items = Equipment::with('category')
            ->where('department_id', $departmentId)
            ->orderByDesc('equipment_id')
            ->get()
            ->map(function (Equipment $e) {
                return [
                    'id' => $e->equipment_id,
                    'name' => $e->name,
                    'brand' => $e->brand,
                    'model' => $e->model,
                    'serial_number' => $e->serial_number,
                    'imgUrl' => $e->image,
                    'available' => (int) $e->available_qty,
                    'total' => (int) $e->total_qty,
                    'status' => $e->status,
                    'category' => $e->category?->category_name ?: 'General',
                    'categoryId' => $e->category_id,
                    'accessories' => $e->accessories_included,
                ];
            });

        return response()->json(['success' => true, 'equipment' => $items]);
    }
    /**
     * Create/update an equipment record for the signed-in department. The
     * department is never read from the request, so a department account can
     * neither register equipment for nor move equipment into another
     * department. Writes go to the same `equipment` record the admin sees.
     */
    public function save(Request $request): JsonResponse
    {
        $departmentId = $this->departmentId();

        if (!$departmentId) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not linked to a department.',
            ], 400);
        }

        $data = $request->validate([
            'equipment_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:100'],
            'brand' => ['required', 'string', 'max:50'],
            'serial_number' => ['required', 'string', 'max:50'],
            'image' => ['nullable', 'string'],
            'available_qty' => ['required', 'integer', 'min:0'],
            'total_qty' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Available', 'Unavailable', 'On Hold', 'Under Maintenance'])],
            'category_id' => ['required', 'integer', Rule::exists('equipment_category', 'category_id')],
            'accessories_included' => ['nullable', 'string', 'max:150'],
        ]);

        $equipmentId = (int) ($data['equipment_id'] ?? 0);

        // Scoped lookup: equipment owned by another department is "not found".
        $equipment = $equipmentId > 0
            ? Equipment::where('department_id', $departmentId)->where('equipment_id', $equipmentId)->first()
            : null;

        if ($equipmentId > 0 && !$equipment) {
            return response()->json([
                'success' => false,
                'message' => 'Equipment not found for your department.',
            ], 404);
        }

        if ($data['available_qty'] > $data['total_qty']) {
            return response()->json([
                'success' => false,
                'message' => 'Available Quantity cannot be greater than Total Quantity.',
            ], 400);
        }

        $dupe = Equipment::whereRaw('LOWER(serial_number) = ?', [strtolower($data['serial_number'])])
            ->when($equipmentId > 0, fn ($q) => $q->where('equipment_id', '!=', $equipmentId))
            ->exists();

        if ($dupe) {
            return response()->json([
                'success' => false,
                'message' => 'Serial Number "' . e($data['serial_number']) . '" is already registered to another equipment item.',
            ], 400);
        }

        $image = $this->normalizeImage($data['image'] ?? '', $equipmentId ?: null);

        if ($equipment) {
            $equipment->update([
                'name' => $data['name'],
                'brand' => $data['brand'],
                'serial_number' => $data['serial_number'],
                'image' => $image,
                'available_qty' => $data['available_qty'],
                'total_qty' => $data['total_qty'],
                'status' => $data['status'],
                'category_id' => $data['category_id'],
                'accessories_included' => $data['accessories_included'] ?? $equipment->accessories_included,
            ]);

            $this->audit($request, 'Updated equipment #' . $equipment->equipment_id . ' (' . $data['name'] . ')', 'equipment:' . $equipment->equipment_id);

            return response()->json(['success' => true, 'message' => 'Equipment details updated successfully!']);
        }

        $equipment = Equipment::create([
            'name' => $data['name'],
            'brand' => $data['brand'],
            'serial_number' => $data['serial_number'],
            'image' => $image,
            'available_qty' => $data['available_qty'],
            'total_qty' => $data['total_qty'],
            'status' => $data['status'],
            'category_id' => $data['category_id'],
            'accessories_included' => $data['accessories_included'] ?? null,
            'department_id' => $departmentId,
        ]);

        $this->audit($request, 'Registered equipment #' . $equipment->equipment_id . ' (' . $data['name'] . ')', 'equipment:' . $equipment->equipment_id);

        return response()->json(['success' => true, 'message' => 'Equipment registered successfully!']);
    }

    /** Department the signed-in department account belongs to. */
    private function departmentId(): ?int
    {
        $departmentId = auth('dept')->user()?->department_id;

        return $departmentId ? (int) $departmentId : null;
    }

    /** Mirrors the admin equipment image handling (base64 data URI -> file). */
    private function normalizeImage(string $image, ?int $equipmentId): string
    {
        $image = trim($image);

        if (!preg_match('/^data:image\/([a-z+]+);base64,(.+)$/is', $image, $m)) {
            return $image; // URL or existing /storage path
        }

        $binary = base64_decode($m[2], true);
        if ($binary === false || strlen($binary) > 5 * 1024 * 1024) {
            abort(400, 'Image must be a valid file of 5MB or less.');
        }

        $ext = match (strtolower($m[1])) {
            'jpeg', 'jpg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            'gif' => 'gif',
            default => 'img',
        };

        $filename = 'images/equipment-' . ($equipmentId ?: 'new-' . bin2hex(random_bytes(4))) . '.' . $ext;
        Storage::disk('public')->put($filename, $binary);

        return '/storage/' . $filename;
    }

    private function audit(Request $request, string $action, string $entity): void
    {
        AuditTrail::create([
            'dept_acc_id' => auth('dept')->user()?->dept_acc_id,
            'action' => mb_substr($action, 0, 100),
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'affected_entity' => mb_substr($entity, 0, 100),
        ]);
    }

}
