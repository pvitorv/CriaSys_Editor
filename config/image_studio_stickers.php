<?php

/**
 * Emojis, blobs/slimes e adesivos virais — estilo Canva (Unicode + formas orgânicas).
 */
return [
    'groups' => [
        'emojis' => 'Emojis & reações',
        'blobs' => 'Blobs & slimes',
        'adesivos' => 'Adesivos virais',
        'formas_extras' => 'Formas decorativas',
    ],

    'elements' => [
        // —— Emojis (viral / Reels / TikTok) ——
        ['slug' => 'emoji_fire', 'name' => 'Fogo', 'group' => 'emojis', 'type' => 'emoji', 'char' => '🔥', 'icon' => '🔥', 'fontSize' => 128],
        ['slug' => 'emoji_100', 'name' => '100', 'group' => 'emojis', 'type' => 'emoji', 'char' => '💯', 'icon' => '💯', 'fontSize' => 120],
        ['slug' => 'emoji_sparkles', 'name' => 'Brilhos', 'group' => 'emojis', 'type' => 'emoji', 'char' => '✨', 'icon' => '✨', 'fontSize' => 120],
        ['slug' => 'emoji_star_eyes', 'name' => 'Olhos estrela', 'group' => 'emojis', 'type' => 'emoji', 'char' => '🤩', 'icon' => '🤩', 'fontSize' => 120],
        ['slug' => 'emoji_rocket', 'name' => 'Foguete', 'group' => 'emojis', 'type' => 'emoji', 'char' => '🚀', 'icon' => '🚀', 'fontSize' => 120],
        ['slug' => 'emoji_party', 'name' => 'Festa', 'group' => 'emojis', 'type' => 'emoji', 'char' => '🎉', 'icon' => '🎉', 'fontSize' => 120],
        ['slug' => 'emoji_clap', 'name' => 'Palmas', 'group' => 'emojis', 'type' => 'emoji', 'char' => '👏', 'icon' => '👏', 'fontSize' => 120],
        ['slug' => 'emoji_point_up', 'name' => 'Apontar', 'group' => 'emojis', 'type' => 'emoji', 'char' => '👆', 'icon' => '👆', 'fontSize' => 120],
        ['slug' => 'emoji_point_down', 'name' => 'Apontar baixo', 'group' => 'emojis', 'type' => 'emoji', 'char' => '👇', 'icon' => '👇', 'fontSize' => 120],
        ['slug' => 'emoji_eyes', 'name' => 'Olhos', 'group' => 'emojis', 'type' => 'emoji', 'char' => '👀', 'icon' => '👀', 'fontSize' => 120],
        ['slug' => 'emoji_mind_blown', 'name' => 'Explodindo', 'group' => 'emojis', 'type' => 'emoji', 'char' => '🤯', 'icon' => '🤯', 'fontSize' => 120],
        ['slug' => 'emoji_cry', 'name' => 'Chorando', 'group' => 'emojis', 'type' => 'emoji', 'char' => '😭', 'icon' => '😭', 'fontSize' => 120],
        ['slug' => 'emoji_laugh', 'name' => 'Rindo', 'group' => 'emojis', 'type' => 'emoji', 'char' => '😂', 'icon' => '😂', 'fontSize' => 120],
        ['slug' => 'emoji_skull', 'name' => 'Caveira', 'group' => 'emojis', 'type' => 'emoji', 'char' => '💀', 'icon' => '💀', 'fontSize' => 120],
        ['slug' => 'emoji_heart', 'name' => 'Coração', 'group' => 'emojis', 'type' => 'emoji', 'char' => '❤️', 'icon' => '❤️', 'fontSize' => 120],
        ['slug' => 'emoji_heart_fire', 'name' => 'Coração fogo', 'group' => 'emojis', 'type' => 'emoji', 'char' => '❤️‍🔥', 'icon' => '❤️‍🔥', 'fontSize' => 108],
        ['slug' => 'emoji_thumbs_up', 'name' => 'Joinha', 'group' => 'emojis', 'type' => 'emoji', 'char' => '👍', 'icon' => '👍', 'fontSize' => 120],
        ['slug' => 'emoji_thumbs_down', 'name' => 'Polegar baixo', 'group' => 'emojis', 'type' => 'emoji', 'char' => '👎', 'icon' => '👎', 'fontSize' => 120],
        ['slug' => 'emoji_money', 'name' => 'Dinheiro', 'group' => 'emojis', 'type' => 'emoji', 'char' => '💰', 'icon' => '💰', 'fontSize' => 120],
        ['slug' => 'emoji_shock', 'name' => 'Choque', 'group' => 'emojis', 'type' => 'emoji', 'char' => '😱', 'icon' => '😱', 'fontSize' => 120],
        ['slug' => 'emoji_cool', 'name' => 'Cool', 'group' => 'emojis', 'type' => 'emoji', 'char' => '😎', 'icon' => '😎', 'fontSize' => 120],
        ['slug' => 'emoji_angry', 'name' => 'Bravo', 'group' => 'emojis', 'type' => 'emoji', 'char' => '😡', 'icon' => '😡', 'fontSize' => 120],
        ['slug' => 'emoji_thinking', 'name' => 'Pensando', 'group' => 'emojis', 'type' => 'emoji', 'char' => '🤔', 'icon' => '🤔', 'fontSize' => 120],
        ['slug' => 'emoji_speech', 'name' => 'Balão', 'group' => 'emojis', 'type' => 'emoji', 'char' => '💬', 'icon' => '💬', 'fontSize' => 120],
        ['slug' => 'emoji_warning', 'name' => 'Alerta', 'group' => 'emojis', 'type' => 'emoji', 'char' => '⚠️', 'icon' => '⚠️', 'fontSize' => 120],
        ['slug' => 'emoji_check', 'name' => 'Check', 'group' => 'emojis', 'type' => 'emoji', 'char' => '✅', 'icon' => '✅', 'fontSize' => 120],
        ['slug' => 'emoji_cross', 'name' => 'X vermelho', 'group' => 'emojis', 'type' => 'emoji', 'char' => '❌', 'icon' => '❌', 'fontSize' => 120],
        ['slug' => 'emoji_new', 'name' => 'NEW', 'group' => 'emojis', 'type' => 'emoji', 'char' => '🆕', 'icon' => '🆕', 'fontSize' => 108],
        ['slug' => 'emoji_free', 'name' => 'FREE', 'group' => 'emojis', 'type' => 'emoji', 'char' => '🆓', 'icon' => '🆓', 'fontSize' => 108],
        ['slug' => 'emoji_sos', 'name' => 'SOS', 'group' => 'emojis', 'type' => 'emoji', 'char' => '🆘', 'icon' => '🆘', 'fontSize' => 108],

        // —— Blobs / slimes ——
        ['slug' => 'blob_yellow', 'name' => 'Slime amarelo', 'group' => 'blobs', 'type' => 'blob', 'icon' => '◐', 'fill' => '#fde047', 'variant' => 1],
        ['slug' => 'blob_green', 'name' => 'Slime verde', 'group' => 'blobs', 'type' => 'blob', 'icon' => '◐', 'fill' => '#4ade80', 'variant' => 2],
        ['slug' => 'blob_pink', 'name' => 'Slime rosa', 'group' => 'blobs', 'type' => 'blob', 'icon' => '◐', 'fill' => '#f472b6', 'variant' => 3],
        ['slug' => 'blob_blue', 'name' => 'Slime azul', 'group' => 'blobs', 'type' => 'blob', 'icon' => '◐', 'fill' => '#60a5fa', 'variant' => 4],
        ['slug' => 'blob_violet', 'name' => 'Slime violeta', 'group' => 'blobs', 'type' => 'blob', 'icon' => '◐', 'fill' => '#a78bfa', 'variant' => 5],
        ['slug' => 'blob_orange', 'name' => 'Slime laranja', 'group' => 'blobs', 'type' => 'blob', 'icon' => '◐', 'fill' => '#fb923c', 'variant' => 6],
        ['slug' => 'blob_white', 'name' => 'Blob branco', 'group' => 'blobs', 'type' => 'blob', 'icon' => '◐', 'fill' => '#ffffff', 'variant' => 1],
        ['slug' => 'blob_black', 'name' => 'Blob preto', 'group' => 'blobs', 'type' => 'blob', 'icon' => '◐', 'fill' => '#18181b', 'variant' => 2],

        // —— Adesivos texto (Unicode grande) ——
        ['slug' => 'sticker_new', 'name' => 'NEW', 'group' => 'adesivos', 'type' => 'emoji', 'char' => 'NEW', 'icon' => 'NEW', 'fontSize' => 96, 'fill' => '#ef4444'],
        ['slug' => 'sticker_hot', 'name' => 'HOT', 'group' => 'adesivos', 'type' => 'emoji', 'char' => 'HOT', 'icon' => 'HOT', 'fontSize' => 96, 'fill' => '#f97316'],
        ['slug' => 'sticker_sale', 'name' => 'SALE', 'group' => 'adesivos', 'type' => 'emoji', 'char' => 'SALE', 'icon' => 'SALE', 'fontSize' => 88, 'fill' => '#22c55e'],
        ['slug' => 'sticker_wow', 'name' => 'WOW', 'group' => 'adesivos', 'type' => 'emoji', 'char' => 'WOW!', 'icon' => 'WOW', 'fontSize' => 88, 'fill' => '#eab308'],
        ['slug' => 'sticker_oops', 'name' => 'OOPS', 'group' => 'adesivos', 'type' => 'emoji', 'char' => 'OOPS', 'icon' => 'OOPS', 'fontSize' => 88, 'fill' => '#f472b6'],
        ['slug' => 'sticker_arrow_down', 'name' => '↓ Link', 'group' => 'adesivos', 'type' => 'emoji', 'char' => '👇', 'icon' => '👇', 'fontSize' => 140],

        // —— Formas decorativas ——
        ['slug' => 'heart_red', 'name' => 'Coração', 'group' => 'formas_extras', 'type' => 'heart', 'icon' => '♥', 'fill' => '#ef4444'],
        ['slug' => 'heart_white', 'name' => 'Coração branco', 'group' => 'formas_extras', 'type' => 'heart', 'icon' => '♥', 'fill' => '#ffffff'],
        ['slug' => 'burst_yellow', 'name' => 'Explosão', 'group' => 'formas_extras', 'type' => 'burst', 'icon' => '✦', 'fill' => '#fde047'],
        ['slug' => 'burst_white', 'name' => 'Explosão branca', 'group' => 'formas_extras', 'type' => 'burst', 'icon' => '✦', 'fill' => '#ffffff'],
        ['slug' => 'cloud_white', 'name' => 'Nuvem', 'group' => 'formas_extras', 'type' => 'cloud', 'icon' => '☁', 'fill' => '#ffffff'],
        ['slug' => 'speech_white', 'name' => 'Balão fala', 'group' => 'formas_extras', 'type' => 'speech', 'icon' => '💭', 'fill' => '#ffffff'],
        ['slug' => 'speech_dark', 'name' => 'Balão escuro', 'group' => 'formas_extras', 'type' => 'speech', 'icon' => '💭', 'fill' => '#27272a'],
        ['slug' => 'badge_red', 'name' => 'Selo', 'group' => 'formas_extras', 'type' => 'badge', 'icon' => '⬡', 'fill' => '#dc2626'],
        ['slug' => 'arrow_white', 'name' => 'Seta', 'group' => 'formas_extras', 'type' => 'arrow', 'icon' => '→', 'fill' => '#ffffff', 'stroke' => '#ffffff'],
        ['slug' => 'arrow_yellow', 'name' => 'Seta amarela', 'group' => 'formas_extras', 'type' => 'arrow', 'icon' => '→', 'fill' => '#fde047', 'stroke' => '#fde047'],
    ],
];
