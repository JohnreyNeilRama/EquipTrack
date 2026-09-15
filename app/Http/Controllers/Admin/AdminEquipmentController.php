<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminEquipmentController extends Controller
{
    public function index(): View
    {
        $dbCategories = EquipmentCategory::orderBy('category_name')->get();
        $dbDepartments = Department::orderBy('department_name')->get();

        return view('admin.equipment', compact('dbCategories', 'dbDepartments'));
    }

    /** JSON feed for the card grid (legacy equipment.php?action=get_equipment). */
    public function data(): JsonResponse
    {
        $items = Equipment::with(['category', 'department'])
            ->orderByDesc('equipment_id')
            ->get()
            ->map(function (Equipment $e) {
                return [
                    'equipment_id' => $e->equipment_id,
                    'name' => $e->name,
                    'brand' => $e->brand,
                    'model' => $e->model,
                    'serial_number' => $e->serial_number,
                    'image' => $e->image,
                    'available_qty' => (int) $e->available_qty,
                    'total_qty' => (int) $e->total_qty,
                    'status' => $e->status,
                    'accessories_included' => $e->accessories_included,
                    'category_id' => $e->category_id,
                    'category_name' => $e->category?->category_name,
                    'department_id' => $e->department_id,
                    'department_name' => $e->department?->department_name,
                    'department_code' => $e->department?->department_code,
                ];
            });

        return response()->json(['success' => true, 'equipment' => $items]);
    }

    public function addCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim($data['category_name']);

        $exists = EquipmentCategory::whereRaw('LOWER(category_name) = ?', [strtolower($name)])->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'A category with this name already exists.'], 400);
        }

        $category = EquipmentCategory::create(['category_name' => $name]);

        return response()->json([
            'success' => true,
            'message' => 'Category added successfully!',
            'category' => [
                'category_id' => $category->category_id,
                'category_name' => $category->category_name,
            ],
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'equipment_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'brand' => ['required', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'serial_number' => ['required', 'string', 'max:50'],
            'image' => ['required', 'string'],
            'available_qty' => ['required', 'integer', 'min:0'],
            'total_qty' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Available', 'Unavailable', 'On Hold', 'Under Maintenance'])],
            'accessories_included' => ['nullable', 'string', 'max:150'],
            'category_id' => ['required', 'integer', Rule::exists('equipment_category', 'category_id')],
            'department_id' => ['required', 'integer', Rule::exists('department', 'department_id')],
        ]);

        $equipmentId = (int) ($data['equipment_id'] ?? 0);

        // Legacy stored whatever the JS posted (base64 data URI or URL). We decode
        // data URIs into real files so the DB keeps paths, matching migrated rows.
        $image = $this->normalizeImage($data['image'], $equipmentId ?: null);

        $dupe = Equipment::whereRaw('LOWER(serial_number) = ?', [strtolower($data['serial_number'])])
            ->when($equipmentId > 0, fn ($q) => $q->where('equipment_id', '!=', $equipmentId))
            ->exists();
        if ($dupe) {
            return response()->json([
                'success' => false,
                'message' => 'Serial Number "' . e($data['serial_number']) . '" is already registered to another equipment item.',
            ], 400);
        }

        if ($data['available_qty'] > $data['total_qty']) {
            return response()->json([
                'success' => false,
                'message' => 'Available Quantity cannot be greater than Total Quantity.',
            ], 400);
        }

        if ($equipmentId > 0) {
            $equipment = Equipment::find($equipmentId);
            if (!$equipment) {
                return response()->json(['success' => false, 'message' => 'Equipment not found.'], 404);
            }
            $equipment->update([...$data, 'image' => $image]);
            $this->audit($request, 'Updated equipment #' . $equipmentId . ' (' . $data['name'] . ')', 'equipment:' . $equipmentId);

            return response()->json(['success' => true, 'message' => 'Equipment details updated successfully!']);
        }

        $equipment = Equipment::create([...$data, 'image' => $image]);
        $this->audit($request, 'Registered equipment #' . $equipment->equipment_id . ' (' . $data['name'] . ')', 'equipment:' . $equipment->equipment_id);

        return response()->json(['success' => true, 'message' => 'Equipment registered successfully!']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['equipment_id' => ['required', 'integer', 'min:1']]);
        $equipment = Equipment::find($data['equipment_id']);
        if (!$equipment) {
            return response()->json(['success' => false, 'message' => 'Invalid equipment ID provided.'], 404);
        }

        // FIX vs legacy: that guard queried a nonexistent borrow_request.status column
        // and silently failed (mysqli exceptions off), letting equipment be deleted
        // while borrowed. Check the real status columns now.
        $active = BorrowRequest::where('equipment_id', $equipment->equipment_id)
            ->whereIn('overall_status', ['Pending', 'Approved'])
            ->exists();
        if ($active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete this equipment because it has active or pending borrow requests.',
            ], 400);
        }

        $imagePath = str_starts_with($equipment->image, '/storage/') ? substr($equipment->image, strlen('/storage/')) : null;
        $equipment->delete();
        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

        $this->audit($request, 'Deleted equipment #' . $data['equipment_id'], 'equipment:' . $data['equipment_id']);

        return response()->json(['success' => true, 'message' => 'Equipment removed from inventory successfully!']);
    }

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
            'admin_id' => auth('admin')->user()->admin_id,
            'action' => mb_substr($action, 0, 100),
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'affected_entity' => mb_substr($entity, 0, 100),
        ]);
    }
}
