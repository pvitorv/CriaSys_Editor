<?php

/**
 * Rotas originais do CriaSys Editor (routes/api.php).
 * No novo produto: autenticar todas; trocar {project} por workspace efêmero
 * ou remover persistência (show/update) e manter catalog + export + rembg.
 */

use App\Http\Controllers\Api\ImageStudioController;

Route::get('image-studio/catalog', [ImageStudioController::class, 'catalog']);
Route::get('projects/{project}/image-studio', [ImageStudioController::class, 'show']);
Route::put('projects/{project}/image-studio', [ImageStudioController::class, 'update']);
Route::post('projects/{project}/image-studio/export', [ImageStudioController::class, 'export']);
Route::post('projects/{project}/image-studio/remove-background', [ImageStudioController::class, 'removeBackground']);
Route::post('projects/{project}/image-studio/push-thumbnail', [ImageStudioController::class, 'pushThumbnail']);
Route::post('projects/{project}/image-studio/push-library', [ImageStudioController::class, 'pushLibrary']);
Route::get('projects/{project}/image-studio/frame-preview', [ImageStudioController::class, 'framePreview']);
