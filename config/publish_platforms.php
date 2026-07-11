<?php

return [
    'youtube' => [
        'name' => 'YouTube',
        'icon' => '▶',
        'max_chars' => 5000,
        'checklist' => [
            'Renderize o vídeo horizontal (16:9) na aba Exportar',
            'Baixe descricao_youtube.txt e cole na descrição do upload',
            'Anexe thumbnail_youtube.jpg como capa',
            'Verifique créditos em creditos_materiais.txt',
            'Publique ou agende no YouTube Studio',
        ],
        'description_file' => 'descricao_youtube.txt',
    ],
    'youtube_shorts' => [
        'name' => 'YouTube Shorts',
        'icon' => '▲',
        'max_chars' => 2200,
        'checklist' => [
            'Renderize preset YouTube Shorts (9:16)',
            'Use descricao_youtube_shorts.txt na descrição',
            'Thumbnail vertical opcional (thumbnail_shorts.jpg)',
            'Marque como Short no upload se necessário',
        ],
        'description_file' => 'descricao_youtube_shorts.txt',
    ],
    'tiktok' => [
        'name' => 'TikTok',
        'icon' => '♪',
        'max_chars' => 2200,
        'checklist' => [
            'Renderize vídeo vertical 9:16',
            'Copie descricao_tiktok.txt para a legenda',
            'Adicione hashtags sugeridas no final',
            'Publique pelo app ou TikTok Studio',
        ],
        'description_file' => 'descricao_tiktok.txt',
    ],
    'instagram_reels' => [
        'name' => 'Instagram Reels',
        'icon' => '◎',
        'max_chars' => 2200,
        'checklist' => [
            'Renderize vídeo vertical 9:16',
            'Cole descricao_instagram_reels.txt na legenda',
            'Escolha capa no frame inicial ou thumbnail',
            'Compartilhe também nos Stories se quiser',
        ],
        'description_file' => 'descricao_instagram_reels.txt',
    ],
    'instagram_feed' => [
        'name' => 'Instagram Feed',
        'icon' => '▣',
        'max_chars' => 2200,
        'checklist' => [
            'Use vídeo quadrado ou 4:5 conforme preset',
            'Cole descricao_instagram_feed.txt',
            'Verifique créditos antes de publicar',
        ],
        'description_file' => 'descricao_instagram_feed.txt',
    ],
];
