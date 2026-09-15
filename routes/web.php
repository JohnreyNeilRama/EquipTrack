<?php

use App\Http\Controllers\Admin\AdminEquipmentController;
use App\Http\Controllers\Admin\AdminMonitoringController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\AdminRequestsController;
use App\Http\Controllers\Admin\AdminUsersController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Department\DepartmentProfileController;
use App\Http\Controllers\User\UserEquipmentController;
use App\Http\Controllers\User\UserProfileController;
use App\Http\Controllers\User\UserRequestsController;
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

// Admin
Route::middleware(['auth:admin', 'active'])->prefix('admin')->name('admin.')->group(function () use ($catNames) {
    Route::view('/dashboard', 'admin.dashboard')->name('dashboard');
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
    Route::get('/reports', fn () => view('admin.reports', $catNames()))->name('reports');
    Route::view('/audit', 'admin.audit')->name('audit');
});

// Department
Route::middleware(['auth:dept', 'active'])->prefix('department')->name('department.')->group(function () use ($catNames) {
    Route::view('/dashboard', 'department.dashboard')->name('dashboard');
    Route::get('/profile', [DepartmentProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [DepartmentProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [DepartmentProfileController::class, 'avatar'])->name('profile.avatar');
    Route::get('/equipment', fn () => view('department.equipment', $catNames()))->name('equipment');
    Route::get('/requests', fn () => view('department.requests', $catNames()))->name('requests');
    Route::view('/users', 'department.users')->name('users');
    Route::get('/monitoring', fn () => view('department.monitoring', $catNames()))->name('monitoring');
    Route::get('/history', fn () => view('department.history', $catNames()))->name('history');
});

// User (Student / Faculty)
Route::middleware(['auth:user', 'active'])->prefix('user')->name('user.')->group(function () {
    Route::view('/dashboard', 'user.dashboard')->name('dashboard');
    Route::get('/profile', [UserProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [UserProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [UserProfileController::class, 'avatar'])->name('profile.avatar');
    Route::get('/equipment', [UserEquipmentController::class, 'index'])->name('equipment');
    Route::post('/equipment/borrow', [UserEquipmentController::class, 'store'])->name('borrow.store');
    Route::get('/requests', [UserRequestsController::class, 'index'])->name('requests');
    Route::view('/returns', 'user.returns')->name('returns');
    Route::view('/history', 'user.history')->name('history');
});
