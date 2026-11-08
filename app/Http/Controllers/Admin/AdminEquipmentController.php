<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use Illuminate\Database\QueryException;
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
        // Manage Categories window: every category with how much equipment uses it.
        $categoryList = $this->categoryList();

        return view('admin.equipment', compact('dbCategories', 'dbDepartments', 'categoryList'));
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
                    'classification' => $e->classificationLabel(),
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
        $this->audit($request, 'Added category "' . $name . '"', 'category:' . $category->category_id);

        return response()->json([
            'success' => true,
            'message' => 'Category added successfully!',
            'category' => [
                'category_id' => $category->category_id,
                'category_name' => $category->category_name,
            ],
            'categories' => $this->categoryList(),
        ]);
    }

    /** Rename a category. Its equipment keeps it (only the name changes). */
    public function updateCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer'],
            'category_name' => ['required', 'string', 'max:255'],
        ]);

        $category = EquipmentCategory::find($data['category_id']);
        if (!$category) {
            return response()->json(['success' => false, 'message' => 'This category no longer exists. Refresh the page and try again.'], 404);
        }

        $name = trim($data['category_name']);
        $old = $category->category_name;

        $taken = EquipmentCategory::whereRaw('LOWER(category_name) = ?', [strtolower($name)])
            ->where('category_id', '!=', $category->category_id)
            ->exists();
        if ($taken) {
            return response()->json(['success' => false, 'message' => 'A category with this name already exists.'], 400);
        }

        if ($name !== $old) {
            $category->update(['category_name' => $name]);
            $this->audit($request, 'Renamed category "' . $old . '" to "' . $name . '"', 'category:' . $category->category_id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Category renamed to "' . $name . '".',
            'categories' => $this->categoryList(),
        ]);
    }

    /**
     * Delete a category that no equipment uses. A category in use is never
     * deleted (its equipment would lose its category): the Admin is told how
     * many items use it and to move them first. The database enforces the
     * same rule (equipment.category_id ON DELETE RESTRICT).
     */
    public function deleteCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer'],
        ]);

        $category = EquipmentCategory::find($data['category_id']);
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'This category was already deleted.',
                'categories' => $this->categoryList(),
            ], 404);
        }

        $inUse = Equipment::where('category_id', $category->category_id)->count();
        if ($inUse > 0) {
            return response()->json([
                'success' => false,
                'message' => $this->inUseMessage($category->category_name, $inUse),
                'categories' => $this->categoryList(),
            ], 409);
        }

        try {
            $category->delete();
        } catch (QueryException $e) {
            // Equipment was assigned to it in the meantime: the database refused.
            return response()->json([
                'success' => false,
                'message' => $this->inUseMessage($category->category_name, max(1, Equipment::where('category_id', $category->category_id)->count())),
                'categories' => $this->categoryList(),
            ], 409);
        }

        $this->audit($request, 'Deleted category "' . $category->category_name . '"', 'category:' . $category->category_id);

        return response()->json([
            'success' => true,
            'message' => 'Category "' . $category->category_name . '" deleted.',
            'categories' => $this->categoryList(),
        ]);
    }

    private function inUseMessage(string $name, int $count): string
    {
        return '"' . $name . '" can\'t be deleted because ' . $count . ' equipment ' . ($count === 1 ? 'item uses' : 'items use')
            . ' it. Move ' . ($count === 1 ? 'that item' : 'those items') . ' to another category first (edit the equipment), then delete the category.';
    }

    /**
     * Categories A–Z with how many equipment items use each.
     *
     * @return array<int, array{category_id: int, category_name: string, equipment_count: int}>
     */
    private function categoryList(): array
    {
        return EquipmentCategory::withCount('equipment')
            ->orderBy('category_name')
            ->get()
            ->map(fn (EquipmentCategory $category) => [
                'category_id' => (int) $category->category_id,
                'category_name' => $category->category_name,
                'equipment_count' => (int) $category->equipment_count,
            ])
            ->all();
    }

    public function save(Request $request): JsonResponse
    {
        $isNew = (int) $request->input('equipment_id', 0) <= 0;

        $data = $request->validate([
            'equipment_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'brand' => ['required', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'serial_number' => ['required', 'string', 'max:50'],
            'image' => ['nullable', 'string'],
            'available_qty' => ['required', 'integer', 'min:0'],
            'total_qty' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Available', 'Unavailable', 'On Hold', 'Under Maintenance'])],
            // Required for new equipment. When an edit omits it (older clients),
            // the record keeps its saved classification; a value that is sent
            // must always be one of the two options.
            'classification' => [Rule::requiredIf($isNew), 'string', Rule::in(Equipment::CLASSIFICATIONS)],
            'accessories_included' => ['nullable', 'string', 'max:150'],
            'category_id' => ['required', 'integer', Rule::exists('equipment_category', 'category_id')],
            'department_id' => ['required', 'integer', Rule::exists('department', 'department_id')],
        ]);

        $equipmentId = (int) ($data['equipment_id'] ?? 0);

        // Legacy stored whatever the JS posted (base64 data URI or URL). We decode
        // data URIs into real files so the DB keeps paths, matching migrated rows.
        $image = $this->normalizeImage($data['image'] ?? '', $equipmentId ?: null);

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
