<?php

return [

    'projects_path' => env('CRIASYS_PROJECTS_PATH') ?: storage_path('app/criasys/projetos'),

    'exports_path' => env('CRIASYS_EXPORTS_PATH') ?: storage_path('app/criasys/exports'),

    'ffmpeg_path' => env('FFMPEG_PATH') ?: 'ffmpeg',

    'ffprobe_path' => env('FFPROBE_PATH') ?: 'ffprobe',

    'tts' => [
        'default_engine' => env('TTS_DEFAULT_ENGINE', 'edge'),
        'default_voice' => env('TTS_DEFAULT_VOICE', 'pt-BR-FranciscaNeural'),
        'voices' => [
            'pt-BR-FranciscaNeural' => 'Francisca (feminina)',
            'pt-BR-AntonioNeural' => 'Antonio (masculino)',
        ],
    ],

    'media' => [
        'pexels_api_key' => env('PEXELS_API_KEY'),
        'pixabay_api_key' => env('PIXABAY_API_KEY'),
        'unsplash_access_key' => env('UNSPLASH_ACCESS_KEY'),
    ],

];
