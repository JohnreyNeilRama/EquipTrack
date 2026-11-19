<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Support\DepartmentScope;
use App\Services\AuditLogger;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentUnit;
use App\Services\EquipmentUnitForm;
use App\Support\EquipmentImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $departmentId = DepartmentScope::id();

        if (!$departmentId) {
            return response()->json(['success' => true, 'equipment' => []]);
        }

        $items = Equipment::with(['category', 'units' => fn ($q) => $q->withExists('transactions as was_lent')])
            ->forDepartment($departmentId)
            ->orderByDesc('equipment_id')
            ->get()
            ->map(function (Equipment $e) {
                return [
                    'id' => $e->equipment_id,
                    'name' => $e->name,
                    'brand' => $e->brand,
                    'model' => $e->model,
                    'serial_number' => $e->serial_number,
                    // Its units' serial numbers (else codes), and every one for search.
                    'unit_labels' => $e->unitLabels(),
                    'unit_search' => $e->unitSearchText(),
                    'imgUrl' => $e->image,
                    'available' => (int) $e->available_qty,
                    'total' => (int) $e->total_qty,
                    'status' => $e->status,
                    'category' => $e->category?->category_name ?: 'General',
                    'categoryId' => $e->category_id,
                    'accessories' => $e->accessories_included,
                    'classification' => $e->classification ?: 'Not Expensive',
                    'units' => EquipmentUnit::forForm($e->units),
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
        $departmentId = DepartmentScope::id();

        if (!$departmentId) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not linked to a department.',
            ], 400);
        }

        $isNew = (int) $request->input('equipment_id', 0) <= 0;
        // The window sends its units (EquipmentUnitForm); older clients send
        // one serial number and typed quantities instead.
        $unitMode = $request->boolean('units_mode') || $request->has('units');

        $data = $request->validate([
            'equipment_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:100'],
            'brand' => ['required', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'serial_number' => [Rule::requiredIf(!$unitMode), 'nullable', 'string', 'max:50'],
            // Chosen when the department registers equipment, so an Expensive
            // item goes to Admin Review too. On edits it is ignored: only the
            // admin changes the classification of existing equipment, so a
            // department can never downgrade an item to skip Admin Review.
            'classification' => [Rule::requiredIf($isNew), 'nullable', 'string', Rule::in(Equipment::CLASSIFICATIONS)],
            'image' => ['nullable', 'string'],
            'available_qty' => [Rule::requiredIf(!$unitMode), 'integer', 'min:0'],
            'total_qty' => [Rule::requiredIf(!$unitMode), 'integer', 'min:0'],
            'status' => ['required', Rule::in(Equipment::STATUSES)],
            'category_id' => ['required', 'integer', Rule::exists('equipment_category', 'category_id')],
            'accessories_included' => ['nullable', 'string', 'max:150'],
        ] + EquipmentUnitForm::rules(), EquipmentUnitForm::messages());

        $equipmentId = (int) ($data['equipment_id'] ?? 0);

        // Scoped lookup: equipment owned by another department is "not found".
        $equipment = $equipmentId > 0
            ? DepartmentScope::equipment()->whereKey($equipmentId)->first()
            : null;

        if ($equipmentId > 0 && !$equipment) {
            return response()->json([
                'success' => false,
                'message' => 'Equipment not found for your department.',
            ], 404);
        }

        $plan = null;
        if ($unitMode) {
            // Department accounts can't change an item's classification, so
            // an edit is checked against the saved one.
            $classification = $equipment ? ($equipment->classification ?: Equipment::CLASS_NOT_EXPENSIVE) : $data['classification'];
            $plan = EquipmentUnitForm::check($data, $equipment, $classification);
            if (is_string($plan)) {
                return response()->json(['success' => false, 'message' => $plan], 400);
            }
        } else {
            if ($data['available_qty'] > $data['total_qty']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Available Quantity cannot be greater than Total Quantity.',
                ], 400);
            }

            if (EquipmentUnitForm::serialTaken($data['serial_number'], $equipmentId ?: null)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Serial Number "' . e($data['serial_number']) . '" is already registered to another equipment item.',
                ], 400);
            }
        }

        $image = EquipmentImage::normalize($data['image'] ?? '', $equipmentId ?: null);

        if ($equipment && $data['status'] === Equipment::STATUS_RETIRED && !$equipment->isRetired()
            && ($reason = $equipment->retireBlocker())) {
            return response()->json(['success' => false, 'message' => $reason], 400);
        }

        if (!$equipment && $data['status'] === Equipment::STATUS_RETIRED) {
            return response()->json([
                'success' => false,
                'message' => 'New equipment can\'t be registered as Retired. Choose another status.',
            ], 400);
        }

        if ($equipment) {
            $summary = DB::transaction(function () use ($equipment, $data, $image, $unitMode, $plan) {
                $equipment->update([
                    'name' => $data['name'],
                    'brand' => $data['brand'],
                    // Left out by older clients: keep the saved value.
                    'model' => array_key_exists('model', $data) ? $data['model'] : $equipment->model,
                    'image' => $image,
                    'status' => $data['status'],
                    'category_id' => $data['category_id'],
                    'accessories_included' => array_key_exists('accessories_included', $data) ? $data['accessories_included'] : $equipment->accessories_included,
                ] + ($unitMode ? [] : [
                    'serial_number' => $data['serial_number'],
                    'available_qty' => $data['available_qty'],
                    'total_qty' => $data['total_qty'],
                ]));

                return $unitMode ? EquipmentUnitForm::apply($equipment, $plan) : null;
            });

            $this->audit($request, 'Updated equipment #' . $equipment->equipment_id . ' (' . $data['name'] . ')', 'equipment:' . $equipment->equipment_id, $summary);

            return response()->json(['success' => true, 'message' => 'Equipment details updated successfully!']);
        }

        [$equipment, $summary] = DB::transaction(function () use ($data, $image, $unitMode, $plan, $departmentId) {
            $equipment = Equipment::create([
                'name' => $data['name'],
                'brand' => $data['brand'],
                'model' => $data['model'] ?? null,
                'classification' => $data['classification'],
                'image' => $image,
                'status' => $data['status'],
                'category_id' => $data['category_id'],
                'accessories_included' => $data['accessories_included'] ?? null,
                'department_id' => $departmentId,
            ] + ($unitMode
                // Units come from the window: the item starts with none.
                ? ['available_qty' => 0, 'total_qty' => 0]
                : ['serial_number' => $data['serial_number'], 'available_qty' => $data['available_qty'], 'total_qty' => $data['total_qty']]));

            return [$equipment, $unitMode ? EquipmentUnitForm::apply($equipment, $plan) : null];
        });

        $this->audit($request, 'Registered equipment #' . $equipment->equipment_id . ' (' . $data['name'] . ')', 'equipment:' . $equipment->equipment_id, $summary);

        return response()->json(['success' => true, 'message' => 'Equipment registered successfully!']);
    }

    /**
     * Removes one of the signed-in department's equipment records. Same rule
     * as the admin delete: only equipment that was never requested can be
     * deleted; anything with borrowing history is retired instead.
     * Equipment owned by another department is "not found".
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['equipment_id' => ['required', 'integer', 'min:1']]);

        $departmentId = DepartmentScope::id();
        $equipment = $departmentId
            ? DepartmentScope::equipment()->whereKey($data['equipment_id'])->first()
            : null;

        if (!$equipment) {
            return response()->json(['success' => false, 'message' => 'Equipment not found for your department.'], 404);
        }

        // Equipment that was ever requested keeps its history: it is retired,
        // not deleted (the database refuses the delete as well).
        if ($reason = $equipment->deleteBlocker()) {
            return response()->json(['success' => false, 'message' => $reason], 400);
        }

        $imagePath = str_starts_with((string) $equipment->image, '/storage/') ? substr($equipment->image, strlen('/storage/')) : null;
        $name = $equipment->name;

        $equipment->delete();

        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

        $this->audit($request, 'Deleted equipment #' . $data['equipment_id'] . ' (' . $name . ')', 'equipment:' . $data['equipment_id']);

        return response()->json(['success' => true, 'message' => 'Equipment removed from inventory successfully!']);
    }

    private function audit(Request $request, string $action, string $entity, ?string $details = null): void
    {
        AuditLogger::log($action, $entity, $details, AuditLogger::actorFor('dept', auth('dept')->user()), $request);
    }

}
