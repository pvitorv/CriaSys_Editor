<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Creator\CreatorProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorProfileController extends Controller
{
    public function show(CreatorProfileService $profiles): JsonResponse
    {
        return response()->json($profiles->forUser(auth()->user()));
    }

    public function update(Request $request, CreatorProfileService $profiles): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'youtube' => ['nullable', 'url', 'max:500'],
            'instagram' => ['nullable', 'url', 'max:500'],
            'tiktok' => ['nullable', 'url', 'max:500'],
            'website' => ['nullable', 'url', 'max:500'],
            'subscribe_cta' => ['nullable', 'string', 'max:500'],
        ]);

        $clean = array_merge($profiles->defaults(), $data);
        foreach ($clean as $key => $value) {
            if ($value === '') {
                $clean[$key] = null;
            }
        }

        auth()->user()->update(['creator_profile' => $clean]);

        return response()->json([
            'message' => 'Perfil de creator atualizado.',
            'creator_profile' => $clean,
        ]);
    }
}
