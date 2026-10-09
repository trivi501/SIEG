<?php

use App\Http\Controllers\Settings\PermissionController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    // Usuarios - solo Super Admin
    Route::get('settings/users', [UserController::class, 'index'])->name('users.index')->middleware('permission:users-index');
    Route::get('settings/users/create', [UserController::class, 'create'])->name('users.create')->middleware('permission:users-create');
    Route::post('settings/users', [UserController::class, 'store'])->name('users.store')->middleware('permission:users-create');
    Route::get('settings/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit')->middleware('permission:users-edit');
    Route::patch('settings/users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('permission:users-edit');
    Route::delete('settings/users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('permission:users-delete');

    // Roles - solo Super Admin
    Route::get('settings/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:roles-index');
    Route::get('settings/roles/create', [RoleController::class, 'create'])->name('roles.create')->middleware('permission:roles-create');
    Route::post('settings/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:roles-create');
    Route::get('settings/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit')->middleware('permission:roles-edit');
    Route::patch('settings/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:roles-edit');
    Route::delete('settings/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:roles-delete');

    // Permisos - solo Super Admin
    Route::get('settings/permissions', [PermissionController::class, 'index'])->name('permissions.index')->middleware('permission:permisos-index');
    Route::get('settings/permissions/create', [PermissionController::class, 'create'])->name('permissions.create')->middleware('permission:permisos-create');
    Route::post('settings/permissions', [PermissionController::class, 'store'])->name('permissions.store')->middleware('permission:permisos-create');
    Route::get('settings/permissions/{permission}/edit', [PermissionController::class, 'edit'])->name('permissions.edit')->middleware('permission:permisos-edit');
    Route::patch('settings/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update')->middleware('permission:permisos-edit');
    Route::delete('settings/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy')->middleware('permission:permisos-delete');
});
