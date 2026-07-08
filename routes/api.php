<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AudioTrackController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\MediaLibraryController;
use App\Http\Controllers\Api\NarrationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectTemplateController;
use App\Http\Controllers\Api\RenderController;
use App\Http\Controllers\Api\SlideController;
use App\Http\Controllers\Api\TtsController;
use Illuminate\Support\Facades\Route;

Route::get('export-presets', [RenderController::class, 'presets']);
Route::get('tts/engines', [TtsController::class, 'engines']);
Route::get('project-templates', [ProjectTemplateController::class, 'index']);

Route::apiResource('projects', ProjectController::class);
Route::post('projects/{project}/duplicate', [ProjectController::class, 'duplicate']);
Route::post('projects/{project}/archive', [ProjectController::class, 'archive']);

Route::get('projects/{project}/slides', [SlideController::class, 'index']);
Route::post('projects/{project}/slides', [SlideController::class, 'store']);
Route::post('projects/{project}/slides/apply-script', [SlideController::class, 'applyScript']);
Route::put('projects/{project}/slides/reorder', [SlideController::class, 'reorder']);
Route::put('projects/{project}/slides/{slide}', [SlideController::class, 'update']);
Route::delete('projects/{project}/slides/{slide}', [SlideController::class, 'destroy']);

Route::post('projects/{project}/assets/upload', [AssetController::class, 'upload']);
Route::get('projects/{project}/assets/{asset}', [AssetController::class, 'serve'])->name('api.projects.assets');
Route::get('projects/{project}/files/{type}/{filename}', [AssetController::class, 'serveFile'])->name('api.projects.files');

Route::get('media/search', [MediaLibraryController::class, 'search']);
Route::post('projects/{project}/media/import', [MediaLibraryController::class, 'import']);

Route::get('projects/{project}/audio-tracks', [AudioTrackController::class, 'index']);
Route::post('projects/{project}/audio-tracks', [AudioTrackController::class, 'store']);
Route::put('projects/{project}/audio-tracks/{audioTrack}', [AudioTrackController::class, 'update']);
Route::delete('projects/{project}/audio-tracks/{audioTrack}', [AudioTrackController::class, 'destroy']);

Route::get('projects/{project}/export-packages', [ExportController::class, 'index']);
Route::post('projects/{project}/export-packages', [ExportController::class, 'store']);
Route::get('projects/{project}/export-packages/{exportPackage}', [ExportController::class, 'show']);
Route::post('projects/{project}/subtitles', [ExportController::class, 'subtitles']);
Route::post('projects/{project}/export-psd', [ExportController::class, 'exportPsd']);

Route::get('projects/{project}/narration', [NarrationController::class, 'show']);
Route::post('projects/{project}/narration/generate', [NarrationController::class, 'generate']);
Route::post('projects/{project}/narration/sync', [NarrationController::class, 'sync']);

Route::get('projects/{project}/render-jobs', [RenderController::class, 'index']);
Route::post('projects/{project}/render-jobs', [RenderController::class, 'store']);
Route::get('projects/{project}/render-jobs/{renderJob}', [RenderController::class, 'show']);
Route::post('projects/{project}/render-jobs/{renderJob}/retry', [RenderController::class, 'retry']);
Route::post('projects/{project}/thumbnail', [RenderController::class, 'thumbnail']);

Route::get('alerts', [AlertController::class, 'index']);
Route::get('alerts/unread', [AlertController::class, 'unread']);
Route::post('alerts/{alert}/read', [AlertController::class, 'markRead']);
Route::post('alerts/read-all', [AlertController::class, 'markAllRead']);
