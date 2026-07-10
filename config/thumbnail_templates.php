<?php

return [
    'fonts' => [
        ['slug' => 'arial', 'label' => 'Arial', 'file' => 'C:\\Windows\\Fonts\\arial.ttf', 'unix' => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'],
        ['slug' => 'segoe', 'label' => 'Segoe UI', 'file' => 'C:\\Windows\\Fonts\\segoeui.ttf', 'unix' => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'],
        ['slug' => 'impact', 'label' => 'Impact', 'file' => 'C:\\Windows\\Fonts\\impact.ttf', 'unix' => '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'],
        ['slug' => 'georgia', 'label' => 'Georgia', 'file' => 'C:\\Windows\\Fonts\\georgia.ttf', 'unix' => '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf'],
    ],

    'defaults' => [
        'template' => 'classic',
        'slide_index' => 0,
        'title_text' => '',
        'subtitle_text' => '',
        'title_color' => '#ffffff',
        'subtitle_color' => '#e5e7eb',
        'accent_color' => '#8b5cf6',
        'background_color' => '#18181b',
        'font_family' => 'arial',
        'title_size' => 64,
        'subtitle_size' => 32,
        'brightness' => 0,
        'contrast' => 0,
        'overlay_opacity' => 45,
        'text_align' => 'center',
        'vertical_align' => 'center',
    ],

    'templates' => [
        'classic' => [
            'name' => 'Clássico',
            'description' => 'Imagem com overlay escuro e texto centralizado',
            'layout' => 'overlay_center',
        ],
        'bold_bottom' => [
            'name' => 'Título forte',
            'description' => 'Faixa inferior escura com título grande',
            'layout' => 'bar_bottom',
        ],
        'split' => [
            'name' => 'Split',
            'description' => 'Imagem à esquerda, bloco colorido com texto à direita',
            'layout' => 'split_right',
        ],
        'minimal' => [
            'name' => 'Minimal',
            'description' => 'Fundo sólido com texto limpo',
            'layout' => 'solid',
        ],
        'cinematic' => [
            'name' => 'Cinematográfico',
            'description' => 'Barras letterbox e vinheta suave',
            'layout' => 'letterbox',
        ],
        'vibrant' => [
            'name' => 'Vibrante',
            'description' => 'Faixa diagonal colorida com destaque',
            'layout' => 'diagonal_accent',
        ],
        'frame' => [
            'name' => 'Moldura',
            'description' => 'Borda colorida e texto no canto inferior',
            'layout' => 'border_frame',
        ],
        'gradient_top' => [
            'name' => 'Gradiente topo',
            'description' => 'Degradê do topo com título alto',
            'layout' => 'gradient_top',
        ],
    ],
];
