<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\EquipmentCategory;
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

        return view('admin.monitoring', compact('dbCategories', 'dbDepartments'));
    }
}
