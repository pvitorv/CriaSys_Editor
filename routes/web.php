<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/projects/create', [ProjectWebController::class, 'create'])->name('projects.create');
Route::post('/projects', [ProjectWebController::class, 'store'])->name('projects.store');
Route::get('/projects/{project}/editor', [ProjectWebController::class, 'editor'])->name('projects.editor');
