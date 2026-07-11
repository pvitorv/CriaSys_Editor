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

    /*
    | desktop — projetos ilimitados (limite = disco/RAM), import/export de pastas
    | online  — 1 projeto ativo por usuário; exportar e excluir para o próximo
    */
    'deployment' => env('CRIASYS_DEPLOYMENT', env('CRIASYS_PORTABLE', false) ? 'desktop' : 'desktop'),
    'online_max_active_projects' => (int) env('CRIASYS_ONLINE_MAX_ACTIVE_PROJECTS', 1),

    // Laragon/Windows: defina false se buscas externas (Openverse, TTS) falharem por SSL
    'http_verify_ssl' => env('HTTP_VERIFY_SSL', env('APP_ENV') === 'production'),

    'ffmpeg_path' => env('FFMPEG_PATH') ?: 'ffmpeg',

    'ffprobe_path' => env('FFPROBE_PATH') ?: 'ffprobe',

    'image_studio' => [
        'rembg_python' => env('REMBG_PYTHON'),
    ],

    // Windows/Laragon: php artisan serve nem sempre herda o PATH com Node.js
    'node_path' => env('NODE_PATH'),

    'tts' => [
        'default_engine' => env('TTS_DEFAULT_ENGINE', 'openai'),
        'default_voice' => env('TTS_DEFAULT_VOICE', 'pt-BR-FranciscaNeural'),
        'edge_python' => env('EDGE_TTS_PYTHON'),
        'piper_path' => env('PIPER_PATH', 'bin/piper/piper/piper.exe'),
        'piper_model' => env('PIPER_MODEL', 'bin/piper/pt_BR-faber-medium.onnx'),
        // Compat: id => caminho do modelo (usado como fallback)
        'piper_models' => [
            'pt-br-faber' => env('PIPER_MODEL', 'bin/piper/pt_BR-faber-medium.onnx'),
            'pt-br-edresson' => env('PIPER_MODEL_EDRESSON', 'bin/piper/pt_BR-edresson-low.onnx'),
        ],
        // Vozes com prosódia. length_scale > 1 = mais lento; sentence_silence = pausa entre frases.
        'piper_voices' => [
            'pt-br-narrador' => [
                'label' => 'Narrador grave pausado (estilo documentário)',
                'model' => env('PIPER_MODEL_EDRESSON', 'bin/piper/pt_BR-edresson-low.onnx'),
                'length_scale' => 1.32,
                'sentence_silence' => 0.85,
                'noise_scale' => 0.5,
            ],
            'pt-br-faber-calmo' => [
                'label' => 'Faber calma (narração pausada, feminina)',
                'model' => env('PIPER_MODEL', 'bin/piper/pt_BR-faber-medium.onnx'),
                'length_scale' => 1.22,
                'sentence_silence' => 0.7,
            ],
            'pt-br-faber' => [
                'label' => 'Faber (feminina, ritmo natural)',
                'model' => env('PIPER_MODEL', 'bin/piper/pt_BR-faber-medium.onnx'),
            ],
            'pt-br-edresson' => [
                'label' => 'Edresson (masculina, ritmo natural)',
                'model' => env('PIPER_MODEL_EDRESSON', 'bin/piper/pt_BR-edresson-low.onnx'),
            ],
        ],
        'elevenlabs_api_key' => env('ELEVENLABS_API_KEY'),
        'elevenlabs_voice_id' => env('ELEVENLABS_VOICE_ID', '21m00Tcm4TlvDq8ikWAM'),
        'openai_api_key' => env('OPENAI_API_KEY'),
        'openai_voice' => env('OPENAI_TTS_VOICE', 'nova'),
        'voices' => [
            'pt-BR-FranciscaNeural' => 'Francisca (feminina)',
            'pt-BR-AntonioNeural' => 'Antonio (masculino)',
            'pt-BR-ThalitaNeural' => 'Thalita (feminina)',
            'pt-BR-DonatoNeural' => 'Donato (masculino)',
        ],
        'engines' => [
            ['slug' => 'openai', 'name' => 'OpenAI TTS (barato, instantâneo)', 'price_hint' => '~US$ 0,015 / 1.000 caracteres', 'unavailable_note' => 'Conecte sua chave em Integrações'],
            ['slug' => 'piper', 'name' => 'Piper (grátis, rápido, robótico)', 'price_hint' => 'Grátis — roda no seu PC', 'unavailable_note' => 'Rode scripts/setup-piper.ps1'],
            ['slug' => 'edge', 'name' => 'Edge TTS (grátis, instável)', 'price_hint' => 'Grátis — Microsoft pode bloquear', 'unavailable_note' => null],
            ['slug' => 'elevenlabs', 'name' => 'ElevenLabs (premium / voz clonada)', 'price_hint' => 'Plano pago', 'unavailable_note' => 'Conecte sua chave em Integrações'],
        ],
    ],

    'media' => [
        'pexels_api_key' => env('PEXELS_API_KEY'),
        'pixabay_api_key' => env('PIXABAY_API_KEY'),
        'unsplash_access_key' => env('UNSPLASH_ACCESS_KEY'),
        'freesound_api_key' => env('FREESOUND_API_KEY'),
        'envato_api_token' => env('ENVATO_API_TOKEN'),
    ],

    'stock_providers' => [
        'envato' => [
            'name' => 'Envato Elements',
            'license_url' => 'https://elements.envato.com/license-terms',
            'project_hint' => 'Use o mesmo nome de projeto que você registra na Envato para este vídeo.',
        ],
        'storyblocks' => [
            'name' => 'Storyblocks',
            'license_url' => 'https://www.storyblocks.com/license',
            'project_hint' => 'Nome do projeto/cliente na Storyblocks.',
        ],
        'artgrid' => [
            'name' => 'Artgrid',
            'license_url' => 'https://artgrid.io/license',
            'project_hint' => 'Nome do projeto na Artgrid.',
        ],
        'custom' => [
            'name' => 'Outra licença paga',
            'license_url' => null,
            'project_hint' => 'Descreva a origem e o projeto licenciado.',
        ],
    ],

    'mixkit_catalog' => [
        ['id' => 'mixkit-1', 'title' => 'Ambient Corporate', 'tags' => 'corporate ambient calm', 'download_url' => 'https://assets.mixkit.co/music/preview/mixkit-serene-view-443.mp3', 'preview_url' => 'https://assets.mixkit.co/music/preview/mixkit-serene-view-443.mp3', 'original_url' => 'https://mixkit.co/free-stock-music/'],
        ['id' => 'mixkit-2', 'title' => 'Inspiring Cinematic', 'tags' => 'cinematic inspiring epic', 'download_url' => 'https://assets.mixkit.co/music/preview/mixkit-emotive-piano-563.mp3', 'preview_url' => 'https://assets.mixkit.co/music/preview/mixkit-emotive-piano-563.mp3', 'original_url' => 'https://mixkit.co/free-stock-music/'],
        ['id' => 'mixkit-3', 'title' => 'Upbeat Technology', 'tags' => 'tech upbeat modern', 'download_url' => 'https://assets.mixkit.co/music/preview/mixkit-tech-house-vibes-130.mp3', 'preview_url' => 'https://assets.mixkit.co/music/preview/mixkit-tech-house-vibes-130.mp3', 'original_url' => 'https://mixkit.co/free-stock-music/'],
    ],

    'mixkit_sfx_catalog' => [
        ['id' => 'sfx-1', 'title' => 'Cinematic Impact', 'tags' => 'impact cinematic boom', 'download_url' => 'https://assets.mixkit.co/active_storage/sfx/1143/1143-preview.mp3', 'preview_url' => 'https://assets.mixkit.co/active_storage/sfx/1143/1143-preview.mp3', 'original_url' => 'https://mixkit.co/free-sound-effects/impact/'],
        ['id' => 'sfx-2', 'title' => 'Whoosh Transition', 'tags' => 'whoosh transition swoosh', 'download_url' => 'https://assets.mixkit.co/active_storage/sfx/2908/2908-preview.mp3', 'preview_url' => 'https://assets.mixkit.co/active_storage/sfx/2908/2908-preview.mp3', 'original_url' => 'https://mixkit.co/free-sound-effects/whoosh/'],
        ['id' => 'sfx-3', 'title' => 'Notification Pop', 'tags' => 'notification pop ui', 'download_url' => 'https://assets.mixkit.co/active_storage/sfx/788/788-preview.mp3', 'preview_url' => 'https://assets.mixkit.co/active_storage/sfx/788/788-preview.mp3', 'original_url' => 'https://mixkit.co/free-sound-effects/notification/'],
    ],

];
