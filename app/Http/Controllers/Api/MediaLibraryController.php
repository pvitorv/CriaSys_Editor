<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\MediaLibrary\PexelsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaLibraryController extends Controller
{
    public function __construct(private PexelsService $pexels) {}

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $results = $this->pexels->searchPhotos($data['query'], $data['page'] ?? 1);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['results' => $results]);
    }

    public function import(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'array'],
            'photo.id' => ['required'],
            'photo.download_url' => ['required', 'url'],
        ]);

        try {
            $asset = $this->pexels->downloadToProject($project, $data['photo']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($asset, 201);
    }
}
