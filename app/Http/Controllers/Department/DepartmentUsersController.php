<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Department "Users" page: the students and faculty members registered under
 * the signed-in department. Everything comes from the database and is scoped to
 * the logged-in department account's department_id, so other departments'
 * users are never sent to the browser.
 */
class DepartmentUsersController extends Controller
{
    public function index(): View
    {
        return view('department.users', ['dbUsers' => $this->rows()]);
    }

    /** JSON feed the page polls so the table stays current. */
    public function data(): JsonResponse
    {
        return response()->json(['success' => true, 'users' => $this->rows()]);
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        $departmentId = auth('dept')->user()->department_id;

        // An account that isn't assigned to a department has no users in scope.
        if (!$departmentId) {
            return [];
        }

        return UserAccount::with(['student', 'facultyMember', 'department'])
            ->where('department_id', $departmentId)
            ->orderByDesc('user_id')
            ->get()
            ->map(function (UserAccount $account) {
                $isStudent = strtolower((string) $account->role) === 'student';
                $profile = $isStudent ? $account->student : $account->facultyMember;

                $fullName = $account->fullName();
                if ($fullName === '') {
                    $fullName = ucfirst(explode('@', (string) $account->email)[0]);
                }

                $idNumber = $isStudent ? ($profile?->id_number ?? '') : ($profile?->faculty_id_number ?? '');
                if ($idNumber === '') {
                    $idNumber = 'USR-' . sprintf('%04d', $account->user_id);
                }

                $attainment = $isStudent
                    ? ($profile?->year_level ?? '')
                    : ($profile?->highest_educational_attainment ?? '');

                return [
                    'id' => (int) $account->user_id,
                    'fullName' => $fullName,
                    'idNumber' => $idNumber,
                    'userType' => $isStudent ? 'Student' : 'Faculty Member',
                    'attainment' => trim((string) $attainment) !== '' ? $attainment : 'N/A',
                    // Anything other than Active (e.g. Deactivated) counts as Inactive.
                    'status' => strcasecmp((string) ($account->status ?: 'Active'), 'Active') === 0 ? 'Active' : 'Inactive',
                    'dateRegistered' => $account->date_created
                        ? Carbon::parse($account->date_created)->format('M d, Y')
                        : '—',
                    // Extra fields for the View Details modal (same data the admin modal shows).
                    'email' => (string) $account->email,
                    'address' => trim((string) ($profile?->address ?? '')) !== '' ? $profile->address : 'N/A',
                    'department' => $account->department?->department_name ?: 'N/A',
                    'attainmentLabel' => $isStudent ? 'Year Level' : 'Highest Attainment',
                    'createdAt' => $account->date_created
                        ? Carbon::parse($account->date_created)->format('M j, Y, g:i A')
                        : 'Database Record',
                    'lastOnline' => $account->last_online
                        ? Carbon::parse($account->last_online)->format('M j, Y, g:i A')
                        : 'Offline / Never',
                    'profileImage' => $account->profile_image ?: null,
                ];
            })
            ->all();
    }
}
