<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentUnit;
use App\Services\EquipmentUnitForm;
use App\Support\EquipmentImage;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $items = Equipment::with(['category', 'department', 'units' => fn ($q) => $q->withExists('transactions as was_lent')])
            ->orderByDesc('equipment_id')
            ->get()
            ->map(function (Equipment $e) {
                return [
                    'equipment_id' => $e->equipment_id,
                    'name' => $e->name,
                    'brand' => $e->brand,
                    'model' => $e->model,
                    'serial_number' => $e->serial_number,
                    // Its units' serial numbers (else codes), and every one for search.
                    'unit_labels' => $e->unitLabels(),
                    'unit_search' => $e->unitSearchText(),
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
                    'units' => EquipmentUnit::forForm($e->units),
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

        // The window sends its units (EquipmentUnitForm); older clients send
        // one serial number and typed quantities instead.
        $unitMode = $request->boolean('units_mode') || $request->has('units');

        $data = $request->validate([
            'equipment_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'brand' => ['required', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'serial_number' => [Rule::requiredIf(!$unitMode), 'nullable', 'string', 'max:50'],
            'image' => ['nullable', 'string'],
            'available_qty' => [Rule::requiredIf(!$unitMode), 'integer', 'min:0'],
            'total_qty' => [Rule::requiredIf(!$unitMode), 'integer', 'min:0'],
            'status' => ['required', Rule::in(Equipment::STATUSES)],
            // Required for new equipment. When an edit omits it (older clients),
            // the record keeps its saved classification; a value that is sent
            // must always be one of the two options.
            'classification' => [Rule::requiredIf($isNew), 'string', Rule::in(Equipment::CLASSIFICATIONS)],
            'accessories_included' => ['nullable', 'string', 'max:150'],
            'category_id' => ['required', 'integer', Rule::exists('equipment_category', 'category_id')],
            'department_id' => ['required', 'integer', Rule::exists('department', 'department_id')],
        ] + EquipmentUnitForm::rules(), EquipmentUnitForm::messages());

        $equipmentId = (int) ($data['equipment_id'] ?? 0);
        $equipment = $equipmentId > 0 ? Equipment::find($equipmentId) : null;
        if ($equipmentId > 0 && !$equipment) {
            return response()->json(['success' => false, 'message' => 'Equipment not found.'], 404);
        }

        $plan = null;
        if ($unitMode) {
            $plan = EquipmentUnitForm::check($data, $equipment, $data['classification'] ?? $equipment?->classification ?? Equipment::CLASS_NOT_EXPENSIVE);
            if (is_string($plan)) {
                return response()->json(['success' => false, 'message' => $plan], 400);
            }
        } else {
            if (EquipmentUnitForm::serialTaken($data['serial_number'], $equipmentId ?: null)) {
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
        }

        // Legacy stored whatever the JS posted (base64 data URI or URL). We decode
        // data URIs into real files so the DB keeps paths, matching migrated rows.
        $image = EquipmentImage::normalize($data['image'] ?? '', $equipmentId ?: null);
        $fields = ['image' => $image] + Arr::except($data, ['units', 'remove_units', 'image']);

        if ($equipment) {
            if ($reason = $this->retireRefusal($equipment, $data['status'])) {
                return response()->json(['success' => false, 'message' => $reason], 400);
            }
            $summary = DB::transaction(function () use ($equipment, $fields, $unitMode, $plan) {
                $equipment->update($fields);

                return $unitMode ? EquipmentUnitForm::apply($equipment, $plan) : null;
            });
            $this->audit($request, 'Updated equipment #' . $equipmentId . ' (' . $data['name'] . ')', 'equipment:' . $equipmentId, $summary);

            return response()->json(['success' => true, 'message' => 'Equipment details updated successfully!']);
        }

        if ($data['status'] === Equipment::STATUS_RETIRED) {
            return response()->json(['success' => false, 'message' => 'New equipment can\'t be registered as Retired. Choose another status.'], 400);
        }

        [$equipment, $summary] = DB::transaction(function () use ($fields, $unitMode, $plan) {
            if (!$unitMode) {
                return [Equipment::create($fields), null];
            }
            // Units come from the window: the item starts with none.
            $equipment = Equipment::create(['available_qty' => 0, 'total_qty' => 0] + $fields);

            return [$equipment, EquipmentUnitForm::apply($equipment, $plan)];
        });
        $this->audit($request, 'Registered equipment #' . $equipment->equipment_id . ' (' . $data['name'] . ')', 'equipment:' . $equipment->equipment_id, $summary);

        return response()->json(['success' => true, 'message' => 'Equipment registered successfully!']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['equipment_id' => ['required', 'integer', 'min:1']]);
        $equipment = Equipment::find($data['equipment_id']);
        if (!$equipment) {
            return response()->json(['success' => false, 'message' => 'Invalid equipment ID provided.'], 404);
        }

        // Equipment that was ever requested keeps its history: it is retired,
        // not deleted (the database refuses the delete as well).
        if ($reason = $equipment->deleteBlocker()) {
            return response()->json(['success' => false, 'message' => $reason], 400);
        }

        $imagePath = str_starts_with($equipment->image, '/storage/') ? substr($equipment->image, strlen('/storage/')) : null;
        $equipment->delete();
        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

        $this->audit($request, 'Deleted equipment #' . $data['equipment_id'], 'equipment:' . $data['equipment_id']);

        return response()->json(['success' => true, 'message' => 'Equipment removed from inventory successfully!']);
    }

    /** Why the item can't be set to Retired now (only checked when it becomes Retired). */
    private function retireRefusal(Equipment $equipment, string $newStatus): ?string
    {
        if ($newStatus !== Equipment::STATUS_RETIRED || $equipment->isRetired()) {
            return null;
        }

        return $equipment->retireBlocker();
    }

    private function audit(Request $request, string $action, string $entity, ?string $details = null): void
    {
        AuditLogger::log($action, $entity, $details, AuditLogger::actorFor('admin', auth('admin')->user()), $request);
    }
}
