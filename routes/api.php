<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AudioTrackController;
use App\Http\Controllers\Api\DeploymentController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\ImageStudioController;
use App\Http\Controllers\Api\MediaLibraryController;
use App\Http\Controllers\Api\NarrationController;
use App\Http\Controllers\Api\ProjectBundleController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectStockLicenseController;
use App\Http\Controllers\Api\ProjectTemplateController;
use App\Http\Controllers\Api\RenderController;
use App\Http\Controllers\Api\SlideController;
use App\Http\Controllers\Api\SoundEffectController;
use App\Http\Controllers\Api\ThumbnailController;
use App\Http\Controllers\Api\ThumbnailFrameController;
use App\Http\Controllers\Api\TtsController;
use Illuminate\Support\Facades\Route;

Route::get('export-presets', [RenderController::class, 'presets']);
Route::get('tts/engines', [TtsController::class, 'engines']);
Route::get('tts/engines/{provider}/voices', [TtsController::class, 'voices']);
Route::get('project-templates', [ProjectTemplateController::class, 'index']);

Route::get('deployment', [DeploymentController::class, 'show']);

Route::post('projects/import-bundle', [ProjectBundleController::class, 'import']);
Route::post('projects/{project}/export-bundle', [ProjectBundleController::class, 'export']);

Route::apiResource('projects', ProjectController::class);
Route::post('projects/{project}/duplicate', [ProjectController::class, 'duplicate']);
Route::post('projects/{project}/archive', [ProjectController::class, 'archive']);
Route::post('projects/{project}/mark-exported', [ProjectController::class, 'markExported']);

Route::get('projects/{project}/slides', [SlideController::class, 'index']);
Route::post('projects/{project}/slides', [SlideController::class, 'store']);
Route::post('projects/{project}/slides/recalculate-durations', [SlideController::class, 'recalculateDurations']);
Route::post('projects/{project}/slides/apply-script', [SlideController::class, 'applyScript']);
Route::post('projects/{project}/slides/parse-script', [SlideController::class, 'parseScript']);
Route::put('projects/{project}/slides/reorder', [SlideController::class, 'reorder']);
Route::put('projects/{project}/slides/{slide}', [SlideController::class, 'update']);
Route::delete('projects/{project}/slides/{slide}', [SlideController::class, 'destroy']);

Route::get('projects/{project}/assets', [AssetController::class, 'index']);
Route::post('projects/{project}/assets/upload', [AssetController::class, 'upload']);
Route::delete('projects/{project}/assets/{asset}', [AssetController::class, 'destroy']);
Route::get('projects/{project}/assets/{asset}', [AssetController::class, 'serve'])->name('api.projects.assets');
Route::get('projects/{project}/files/{type}/{filename}', [AssetController::class, 'serveFile'])->name('api.projects.files');

Route::get('image-studio/catalog', [ImageStudioController::class, 'catalog']);
Route::get('projects/{project}/image-studio', [ImageStudioController::class, 'show']);
Route::put('projects/{project}/image-studio', [ImageStudioController::class, 'update']);
Route::post('projects/{project}/image-studio/export', [ImageStudioController::class, 'export']);
Route::post('projects/{project}/image-studio/remove-background', [ImageStudioController::class, 'removeBackground']);
Route::post('projects/{project}/image-studio/push-thumbnail', [ImageStudioController::class, 'pushThumbnail']);
Route::post('projects/{project}/image-studio/push-library', [ImageStudioController::class, 'pushLibrary']);
Route::get('projects/{project}/image-studio/frame-preview', [ImageStudioController::class, 'framePreview']);

Route::get('media/providers', [MediaLibraryController::class, 'providers']);
Route::get('media/suggest-query', [MediaLibraryController::class, 'suggestQuery']);
Route::get('media/search', [MediaLibraryController::class, 'search']);
Route::post('media/resolve-url', [MediaLibraryController::class, 'resolveUrl']);
Route::post('projects/{project}/media/import', [MediaLibraryController::class, 'import']);
Route::post('projects/{project}/media/import-url', [MediaLibraryController::class, 'importUrl']);

Route::get('projects/{project}/audio-tracks', [AudioTrackController::class, 'index']);
Route::post('projects/{project}/audio-tracks', [AudioTrackController::class, 'store']);
Route::put('projects/{project}/audio-tracks/{audioTrack}', [AudioTrackController::class, 'update']);
Route::delete('projects/{project}/audio-tracks/{audioTrack}', [AudioTrackController::class, 'destroy']);

Route::get('projects/{project}/sound-effects', [SoundEffectController::class, 'index']);
Route::post('projects/{project}/sound-effects', [SoundEffectController::class, 'store']);
Route::put('projects/{project}/sound-effects/{soundEffect}', [SoundEffectController::class, 'update']);
Route::delete('projects/{project}/sound-effects/{soundEffect}', [SoundEffectController::class, 'destroy']);

Route::get('projects/{project}/platform-descriptions', [ExportController::class, 'platformDescriptions']);
Route::post('projects/{project}/platform-descriptions', [ExportController::class, 'savePlatformDescriptions']);
Route::get('projects/{project}/credits', [ExportController::class, 'credits']);
Route::post('projects/{project}/publish/sync', [ExportController::class, 'syncPublish']);

Route::get('projects/{project}/stock-licenses', [ProjectStockLicenseController::class, 'index']);
Route::post('projects/{project}/stock-licenses', [ProjectStockLicenseController::class, 'store']);
Route::put('projects/{project}/stock-licenses/{stockLicense}', [ProjectStockLicenseController::class, 'update']);
Route::delete('projects/{project}/stock-licenses/{stockLicense}', [ProjectStockLicenseController::class, 'destroy']);
Route::post('projects/{project}/stock-licenses/{stockLicense}/apply-local', [ProjectStockLicenseController::class, 'applyToLocalAssets']);
Route::get('projects/{project}/export-packages', [ExportController::class, 'index']);
Route::get('projects/{project}/downloads', [ExportController::class, 'downloads']);
Route::post('projects/{project}/export-packages', [ExportController::class, 'store']);
Route::get('projects/{project}/export-packages/{exportPackage}', [ExportController::class, 'show']);
Route::post('projects/{project}/subtitles', [ExportController::class, 'subtitles']);
Route::post('projects/{project}/export-psd', [ExportController::class, 'exportPsd']);

Route::get('projects/{project}/narration', [NarrationController::class, 'show']);
Route::post('projects/{project}/narration/preview', [NarrationController::class, 'preview']);
Route::post('projects/{project}/narration/generate', [NarrationController::class, 'generate']);
Route::post('projects/{project}/narration/sync', [NarrationController::class, 'sync']);

Route::put('projects/{project}/narration', [NarrationController::class, 'update']);

Route::get('thumbnail/templates', [ThumbnailController::class, 'templates']);
Route::get('thumbnail/frames/library', [ThumbnailFrameController::class, 'library']);
Route::post('thumbnail/frames/categories', [ThumbnailFrameController::class, 'storeCategory']);
Route::delete('thumbnail/frames/categories/{slug}', [ThumbnailFrameController::class, 'destroyCategory']);
Route::post('thumbnail/frames/categories/{slug}/restore', [ThumbnailFrameController::class, 'restoreCategory']);
Route::post('thumbnail/frames', [ThumbnailFrameController::class, 'store']);
Route::delete('thumbnail/frames/{slug}', [ThumbnailFrameController::class, 'destroy']);
Route::post('thumbnail/frames/{slug}/restore', [ThumbnailFrameController::class, 'restore']);
Route::get('thumbnail/frames/file/{filename}', [ThumbnailFrameController::class, 'serveFile']);
Route::get('projects/{project}/thumbnail', [ThumbnailController::class, 'show']);
Route::put('projects/{project}/thumbnail', [ThumbnailController::class, 'update']);
Route::post('projects/{project}/thumbnail/upload', [ThumbnailController::class, 'uploadImage']);
Route::post('projects/{project}/thumbnail/generate', [ThumbnailController::class, 'generate']);

Route::get('projects/{project}/render-jobs', [RenderController::class, 'index']);
Route::post('projects/{project}/render-jobs', [RenderController::class, 'store']);
Route::get('projects/{project}/render-jobs/{renderJob}', [RenderController::class, 'show']);
Route::post('projects/{project}/render-jobs/{renderJob}/retry', [RenderController::class, 'retry']);
Route::post('projects/{project}/thumbnail', [RenderController::class, 'thumbnail']);

Route::get('alerts', [AlertController::class, 'index']);
Route::get('alerts/unread', [AlertController::class, 'unread']);
Route::post('alerts/{alert}/read', [AlertController::class, 'markRead']);
Route::post('alerts/read-all', [AlertController::class, 'markAllRead']);
