<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tts\TtsEngineFactory;
use App\Services\Tts\TtsVoiceCatalog;
use Illuminate\Http\JsonResponse;

class TtsController extends Controller
{
    public function engines(TtsEngineFactory $factory): JsonResponse
    {
        return response()->json($factory->listEngines());
    }

    public function voices(string $provider, TtsVoiceCatalog $catalog): JsonResponse
    {
        return response()->json($catalog->voices($provider));
    }
}
