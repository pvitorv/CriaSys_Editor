<?php

return [

    'projects_path' => env('CRIASYS_PROJECTS_PATH') ?: (
        env('CRIASYS_DATA_PATH')
            ? env('CRIASYS_DATA_PATH').DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'criasys'.DIRECTORY_SEPARATOR.'projetos'
            : storage_path('app/criasys/projetos')
    ),

    'exports_path' => env('CRIASYS_EXPORTS_PATH') ?: (
        env('CRIASYS_DATA_PATH')
            ? env('CRIASYS_DATA_PATH').DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'criasys'.DIRECTORY_SEPARATOR.'exports'
            : storage_path('app/criasys/exports')
    ),

    'portable' => env('CRIASYS_PORTABLE', false),
    'data_path' => env('CRIASYS_DATA_PATH'),

    'ffmpeg_path' => env('FFMPEG_PATH') ?: 'ffmpeg',

    'ffprobe_path' => env('FFPROBE_PATH') ?: 'ffprobe',

    'tts' => [
        'default_engine' => env('TTS_DEFAULT_ENGINE', 'edge'),
        'default_voice' => env('TTS_DEFAULT_VOICE', 'pt-BR-FranciscaNeural'),
        'coqui_python' => env('COQUI_PYTHON'),
        'elevenlabs_api_key' => env('ELEVENLABS_API_KEY'),
        'elevenlabs_voice_id' => env('ELEVENLABS_VOICE_ID', '21m00Tcm4TlvDq8ikWAM'),
        'openai_api_key' => env('OPENAI_API_KEY'),
        'openai_voice' => env('OPENAI_TTS_VOICE', 'nova'),
        'voices' => [
            'pt-BR-FranciscaNeural' => 'Francisca (feminina)',
            'pt-BR-AntonioNeural' => 'Antonio (masculino)',
        ],
        'engines' => [
            ['slug' => 'edge', 'name' => 'Edge TTS (gratuito)', 'unavailable_note' => null],
            ['slug' => 'coqui', 'name' => 'Coqui XTTS (local)', 'unavailable_note' => 'Instale pip install TTS e defina COQUI_PYTHON no .env'],
            ['slug' => 'elevenlabs', 'name' => 'ElevenLabs (pago)', 'unavailable_note' => 'Defina ELEVENLABS_API_KEY no .env'],
            ['slug' => 'openai', 'name' => 'OpenAI TTS (pago)', 'unavailable_note' => 'Defina OPENAI_API_KEY no .env'],
        ],
    ],

    'media' => [
        'pexels_api_key' => env('PEXELS_API_KEY'),
        'pixabay_api_key' => env('PIXABAY_API_KEY'),
        'unsplash_access_key' => env('UNSPLASH_ACCESS_KEY'),
    ],

    'mixkit_catalog' => [
        ['id' => 'mixkit-1', 'title' => 'Ambient Corporate', 'tags' => 'corporate ambient calm', 'download_url' => 'https://assets.mixkit.co/music/preview/mixkit-serene-view-443.mp3', 'original_url' => 'https://mixkit.co/free-stock-music/'],
        ['id' => 'mixkit-2', 'title' => 'Inspiring Cinematic', 'tags' => 'cinematic inspiring epic', 'download_url' => 'https://assets.mixkit.co/music/preview/mixkit-emotive-piano-563.mp3', 'original_url' => 'https://mixkit.co/free-stock-music/'],
        ['id' => 'mixkit-3', 'title' => 'Upbeat Technology', 'tags' => 'tech upbeat modern', 'download_url' => 'https://assets.mixkit.co/music/preview/mixkit-tech-house-vibes-130.mp3', 'original_url' => 'https://mixkit.co/free-stock-music/'],
    ],

];
