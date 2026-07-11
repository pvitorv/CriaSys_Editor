<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectWebController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/projects/create', [ProjectWebController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectWebController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}/editor', [ProjectWebController::class, 'editor'])->name('projects.editor');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/email', [ProfileController::class, 'updateEmail'])->name('profile.email');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/creator', [ProfileController::class, 'updateCreator'])->name('profile.creator');

    Route::get('/integrations', [IntegrationController::class, 'edit'])->name('integrations.edit');
    Route::put('/integrations/{provider}', [IntegrationController::class, 'update'])->name('integrations.update');
    Route::post('/integrations/{provider}/test', [IntegrationController::class, 'test'])->name('integrations.test');
    Route::delete('/integrations/{provider}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/pause', [AdminUserController::class, 'pause'])->name('users.pause');
        Route::post('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/alert', [AdminUserController::class, 'sendAlert'])->name('users.alert');
    });

    Route::prefix('api')->group(function () {
        require __DIR__.'/api.php';
    });
});
