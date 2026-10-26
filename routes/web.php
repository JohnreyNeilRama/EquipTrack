<?php

use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEquipmentController;
use App\Http\Controllers\Admin\AdminMonitoringController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminPenaltiesController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\AdminReportsController;
use App\Http\Controllers\Admin\AdminRequestsController;
use App\Http\Controllers\Admin\AdminUsersController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Department\DepartmentDashboardController;

use App\Http\Controllers\Department\DepartmentEquipmentController;
use App\Http\Controllers\Department\DepartmentHistoryController;
use App\Http\Controllers\Department\DepartmentMonitoringController;
use App\Http\Controllers\Department\DepartmentNotificationController;
use App\Http\Controllers\Department\DepartmentPenaltiesController;
use App\Http\Controllers\Department\DepartmentProfileController;
use App\Http\Controllers\Department\DepartmentRequestsController;
use App\Http\Controllers\Department\DepartmentReturnsController;
use App\Http\Controllers\Department\DepartmentUsersController;
use App\Http\Controllers\User\UserDashboardController;
use App\Http\Controllers\User\UserEquipmentController;
use App\Http\Controllers\User\UserHistoryController;
use App\Http\Controllers\User\UserNotificationController;
use App\Http\Controllers\User\UserPenaltiesController;
use App\Http\Controllers\User\UserProfileController;
use App\Http\Controllers\User\UserRequestsController;
use App\Http\Controllers\User\UserReturnsController;
use App\Models\EquipmentCategory;
use Illuminate\Support\Facades\Route;

// Categories shared by mock pages (department lists, filters).
$catNames = fn () => ['dbCategories' => EquipmentCategory::orderBy('category_name')->pluck('category_name')->all()];

// Guest pages
Route::view('/', 'landing')->name('landing');
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');
Route::get('/register', [RegisteredUserController::class, 'show'])->name('register');
Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// auth.session signs a session out once its account's password changes
// elsewhere (Change Password or a reset); the session that made the change
// stays signed in.

// Admin
Route::middleware(['auth:admin', 'auth.session', 'active'])->prefix('admin')->name('admin.')->group(function () use ($catNames) {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [AdminDashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/profile', [AdminProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [AdminProfileController::class, 'avatar'])->name('profile.avatar');
    Route::get('/equipment', [AdminEquipmentController::class, 'index'])->name('equipment');
    Route::get('/equipment/data', [AdminEquipmentController::class, 'data'])->name('equipment.data');
    Route::post('/equipment/save', [AdminEquipmentController::class, 'save'])->name('equipment.save');
    Route::post('/equipment/category', [AdminEquipmentController::class, 'addCategory'])->name('equipment.category');
    Route::post('/equipment/delete', [AdminEquipmentController::class, 'destroy'])->name('equipment.delete');
    Route::get('/requests', [AdminRequestsController::class, 'index'])->name('requests');
    Route::get('/requests/data', [AdminRequestsController::class, 'data'])->name('requests.data');
    Route::post('/requests/update-status', [AdminRequestsController::class, 'updateStatus'])->name('requests.update');
    Route::get('/users', [AdminUsersController::class, 'index'])->name('users');
    Route::post('/users/student', [AdminUsersController::class, 'addStudent'])->name('users.student');
    Route::post('/users/department', [AdminUsersController::class, 'addDepartment'])->name('users.department');
    Route::post('/users/toggle-status', [AdminUsersController::class, 'toggleStatus'])->name('users.toggle');
    Route::post('/users/delete', [AdminUsersController::class, 'delete'])->name('users.delete');
    Route::post('/users/profile-image', [AdminUsersController::class, 'updateProfileImage'])->name('users.image');
    Route::get('/monitoring', [AdminMonitoringController::class, 'index'])->name('monitoring');
    Route::get('/reports', [AdminReportsController::class, 'index'])->name('reports');
    Route::get('/reports/data', [AdminReportsController::class, 'data'])->name('reports.data');
    Route::post('/reports/generate', [AdminReportsController::class, 'generate'])->name('reports.generate');
    Route::get('/reports/export', [AdminReportsController::class, 'export'])->name('reports.export');
    Route::get('/penalties', [AdminPenaltiesController::class, 'index'])->name('penalties');
    Route::get('/penalties/data', [AdminPenaltiesController::class, 'data'])->name('penalties.data');
    Route::post('/penalties/store', [AdminPenaltiesController::class, 'store'])->name('penalties.store');
    Route::post('/penalties/update', [AdminPenaltiesController::class, 'update'])->name('penalties.update');
    Route::post('/penalties/settle', [AdminPenaltiesController::class, 'settle'])->name('penalties.settle');
    Route::post('/penalties/waive', [AdminPenaltiesController::class, 'waive'])->name('penalties.waive');
    Route::post('/penalties/proof/approve', [AdminPenaltiesController::class, 'approveProof'])->name('penalties.proof.approve');
    Route::post('/penalties/proof/reject', [AdminPenaltiesController::class, 'rejectProof'])->name('penalties.proof.reject');
    Route::get('/penalties/proof/file/{file}', [AdminPenaltiesController::class, 'proofFile'])->whereNumber('file')->name('penalties.proof');
    Route::get('/audit', [AdminAuditController::class, 'index'])->name('audit');
    Route::get('/audit/data', [AdminAuditController::class, 'data'])->name('audit.data');
    Route::get('/audit/export', [AdminAuditController::class, 'export'])->name('audit.export');
    Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/read', [AdminNotificationController::class, 'markRead'])->name('notifications.read');
});

// Department
Route::middleware(['auth:dept', 'auth.session', 'active'])->prefix('department')->name('department.')->group(function () use ($catNames) {
    Route::get('/dashboard', [DepartmentDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DepartmentDashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/profile', [DepartmentProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [DepartmentProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [DepartmentProfileController::class, 'avatar'])->name('profile.avatar');
    // Equipment assigned to this department (same records the admin manages).
    Route::get('/equipment', [DepartmentEquipmentController::class, 'index'])->name('equipment');
    Route::get('/equipment/data', [DepartmentEquipmentController::class, 'data'])->name('equipment.data');
    Route::post('/equipment/save', [DepartmentEquipmentController::class, 'save'])->name('equipment.save');
    Route::post('/equipment/delete', [DepartmentEquipmentController::class, 'destroy'])->name('equipment.delete');
    Route::get('/requests', [DepartmentRequestsController::class, 'index'])->name('requests');
    Route::get('/requests/data', [DepartmentRequestsController::class, 'data'])->name('requests.data');
    Route::post('/requests/update-status', [DepartmentRequestsController::class, 'updateStatus'])->name('requests.update');
    Route::get('/returns', [DepartmentReturnsController::class, 'index'])->name('returns');
    Route::get('/returns/data', [DepartmentReturnsController::class, 'data'])->name('returns.data');
    Route::post('/returns/confirm', [DepartmentReturnsController::class, 'confirm'])->name('returns.confirm');
    Route::post('/returns/reject', [DepartmentReturnsController::class, 'reject'])->name('returns.reject');
    Route::get('/penalties', [DepartmentPenaltiesController::class, 'index'])->name('penalties');
    Route::get('/penalties/data', [DepartmentPenaltiesController::class, 'data'])->name('penalties.data');
    Route::post('/penalties/update', [DepartmentPenaltiesController::class, 'update'])->name('penalties.update');
    Route::post('/penalties/settle', [DepartmentPenaltiesController::class, 'settle'])->name('penalties.settle');
    Route::post('/penalties/waive', [DepartmentPenaltiesController::class, 'waive'])->name('penalties.waive');
    Route::post('/penalties/proof/approve', [DepartmentPenaltiesController::class, 'approveProof'])->name('penalties.proof.approve');
    Route::post('/penalties/proof/reject', [DepartmentPenaltiesController::class, 'rejectProof'])->name('penalties.proof.reject');
    Route::get('/penalties/proof/file/{file}', [DepartmentPenaltiesController::class, 'proofFile'])->whereNumber('file')->name('penalties.proof');
    Route::get('/users', [DepartmentUsersController::class, 'index'])->name('users');
    Route::get('/users/data', [DepartmentUsersController::class, 'data'])->name('users.data');
    Route::get('/monitoring', [DepartmentMonitoringController::class, 'index'])->name('monitoring');
    Route::get('/history', [DepartmentHistoryController::class, 'index'])->name('history');
    Route::get('/history/data', [DepartmentHistoryController::class, 'data'])->name('history.data');
    Route::get('/notifications', [DepartmentNotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/read', [DepartmentNotificationController::class, 'markRead'])->name('notifications.read');
});

// User (Student / Faculty)
Route::middleware(['auth:user', 'auth.session', 'active'])->prefix('user')->name('user.')->group(function () {
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [UserDashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/profile', [UserProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [UserProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [UserProfileController::class, 'avatar'])->name('profile.avatar');
    Route::get('/equipment', [UserEquipmentController::class, 'index'])->name('equipment');
    Route::post('/equipment/borrow', [UserEquipmentController::class, 'store'])->name('borrow.store');
    Route::get('/requests', [UserRequestsController::class, 'index'])->name('requests');
    Route::get('/returns', [UserReturnsController::class, 'index'])->name('returns');
    Route::post('/returns', [UserReturnsController::class, 'store'])->name('returns.store');
    Route::get('/history', [UserHistoryController::class, 'index'])->name('history');
    Route::get('/penalties', [UserPenaltiesController::class, 'index'])->name('penalties');
    Route::get('/penalties/data', [UserPenaltiesController::class, 'data'])->name('penalties.data');
    Route::post('/penalties/proof', [UserPenaltiesController::class, 'submit'])->middleware('throttle:10,1')->name('penalties.submit');
    Route::get('/penalties/proof/file/{file}', [UserPenaltiesController::class, 'file'])->whereNumber('file')->name('penalties.file');
    Route::get('/notifications', [UserNotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/read', [UserNotificationController::class, 'markRead'])->name('notifications.read');
});
