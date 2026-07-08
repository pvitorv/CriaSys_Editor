<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tts\TtsEngineFactory;
use Illuminate\Http\JsonResponse;

class TtsController extends Controller
{
    public function engines(TtsEngineFactory $factory): JsonResponse
    {
        return response()->json($factory->listEngines());
    }
}
