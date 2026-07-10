<?php

return [
    'version' => 1,

    'export_formats' => [
        ['id' => 'png', 'label' => 'PNG (transparência)', 'ext' => 'png', 'mime' => 'image/png', 'hint' => 'Photoshop, Affinity, web'],
        ['id' => 'jpg', 'label' => 'JPG (foto)', 'ext' => 'jpg', 'mime' => 'image/jpeg', 'hint' => 'Redes sociais, sites'],
        ['id' => 'svg', 'label' => 'SVG (vetor)', 'ext' => 'svg', 'mime' => 'image/svg+xml', 'hint' => 'CorelDRAW, Affinity, Illustrator'],
        ['id' => 'psd', 'label' => 'PSD (camadas)', 'ext' => 'psd', 'mime' => 'application/vnd.adobe.photoshop', 'hint' => 'Photoshop, Affinity Photo'],
        ['id' => 'pdf', 'label' => 'PDF (impressão)', 'ext' => 'pdf', 'mime' => 'application/pdf', 'hint' => 'CorelDRAW, Affinity, impressão'],
        ['id' => 'json', 'label' => 'Projeto CriaSys (.json)', 'ext' => 'json', 'mime' => 'application/json', 'hint' => 'Reabrir e editar depois'],
    ],

    'groups' => [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
        'twitter' => 'X / Twitter',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'pinterest' => 'Pinterest',
        'whatsapp' => 'WhatsApp',
        'web' => 'Sites & banners',
        'stories' => 'Stories / vertical',
        'print' => 'Impressão',
    ],

    'presets' => [
        // Instagram
        'ig_feed_square' => ['name' => 'Feed quadrado', 'group' => 'instagram', 'width' => 1080, 'height' => 1080, 'icon' => '◎'],
        'ig_feed_portrait' => ['name' => 'Feed retrato 4:5', 'group' => 'instagram', 'width' => 1080, 'height' => 1350, 'icon' => '▯'],
        'ig_feed_landscape' => ['name' => 'Feed paisagem', 'group' => 'instagram', 'width' => 1080, 'height' => 566, 'icon' => '▭'],
        'ig_story' => ['name' => 'Story / Reels', 'group' => 'instagram', 'width' => 1080, 'height' => 1920, 'icon' => '▲'],
        'ig_profile' => ['name' => 'Foto de perfil', 'group' => 'instagram', 'width' => 320, 'height' => 320, 'icon' => '◉'],

        // Facebook
        'fb_feed' => ['name' => 'Post feed', 'group' => 'facebook', 'width' => 1200, 'height' => 630, 'icon' => 'f'],
        'fb_cover' => ['name' => 'Capa página', 'group' => 'facebook', 'width' => 820, 'height' => 312, 'icon' => '▬'],
        'fb_story' => ['name' => 'Story', 'group' => 'facebook', 'width' => 1080, 'height' => 1920, 'icon' => '▲'],
        'fb_event' => ['name' => 'Evento', 'group' => 'facebook', 'width' => 1920, 'height' => 1080, 'icon' => '📅'],
        'fb_ad_square' => ['name' => 'Anúncio quadrado', 'group' => 'facebook', 'width' => 1080, 'height' => 1080, 'icon' => '□'],

        // LinkedIn
        'li_feed' => ['name' => 'Post feed', 'group' => 'linkedin', 'width' => 1200, 'height' => 627, 'icon' => 'in'],
        'li_cover' => ['name' => 'Capa perfil', 'group' => 'linkedin', 'width' => 1584, 'height' => 396, 'icon' => '▬'],
        'li_article' => ['name' => 'Artigo / blog', 'group' => 'linkedin', 'width' => 1280, 'height' => 720, 'icon' => '📄'],
        'li_ad' => ['name' => 'Anúncio', 'group' => 'linkedin', 'width' => 1200, 'height' => 628, 'icon' => '◎'],

        // X / Twitter
        'x_post' => ['name' => 'Post', 'group' => 'twitter', 'width' => 1600, 'height' => 900, 'icon' => '𝕏'],
        'x_header' => ['name' => 'Capa perfil', 'group' => 'twitter', 'width' => 1500, 'height' => 500, 'icon' => '▬'],
        'x_card' => ['name' => 'Card link', 'group' => 'twitter', 'width' => 800, 'height' => 418, 'icon' => '🔗'],

        // YouTube
        'yt_thumb' => ['name' => 'Thumbnail 16:9', 'group' => 'youtube', 'width' => 1280, 'height' => 720, 'icon' => '▶'],
        'yt_thumb_hd' => ['name' => 'Thumbnail Full HD', 'group' => 'youtube', 'width' => 1920, 'height' => 1080, 'icon' => '▶'],
        'yt_banner' => ['name' => 'Banner canal', 'group' => 'youtube', 'width' => 2560, 'height' => 1440, 'icon' => '▬'],
        'yt_shorts' => ['name' => 'Shorts vertical', 'group' => 'youtube', 'width' => 1080, 'height' => 1920, 'icon' => '▲'],

        // TikTok
        'tt_video' => ['name' => 'Vídeo / capa', 'group' => 'tiktok', 'width' => 1080, 'height' => 1920, 'icon' => '♪'],
        'tt_profile' => ['name' => 'Foto perfil', 'group' => 'tiktok', 'width' => 200, 'height' => 200, 'icon' => '◉'],

        // Pinterest
        'pin_standard' => ['name' => 'Pin padrão 2:3', 'group' => 'pinterest', 'width' => 1000, 'height' => 1500, 'icon' => '📌'],
        'pin_square' => ['name' => 'Pin quadrado', 'group' => 'pinterest', 'width' => 1000, 'height' => 1000, 'icon' => '□'],

        // WhatsApp
        'wa_status' => ['name' => 'Status', 'group' => 'whatsapp', 'width' => 1080, 'height' => 1920, 'icon' => '💬'],

        // Web & banners
        'web_hero' => ['name' => 'Hero site 1920', 'group' => 'web', 'width' => 1920, 'height' => 800, 'icon' => '🌐'],
        'web_banner_leader' => ['name' => 'Leaderboard 728×90', 'group' => 'web', 'width' => 728, 'height' => 90, 'icon' => '▭'],
        'web_banner_medium' => ['name' => 'Medium rectangle 300×250', 'group' => 'web', 'width' => 300, 'height' => 250, 'icon' => '▢'],
        'web_banner_sky' => ['name' => 'Skyscraper 160×600', 'group' => 'web', 'width' => 160, 'height' => 600, 'icon' => '▯'],
        'web_banner_wide' => ['name' => 'Wide banner 970×250', 'group' => 'web', 'width' => 970, 'height' => 250, 'icon' => '▬'],
        'web_og' => ['name' => 'Open Graph / SEO', 'group' => 'web', 'width' => 1200, 'height' => 630, 'icon' => '🔗'],
        'web_email_header' => ['name' => 'E-mail header', 'group' => 'web', 'width' => 600, 'height' => 200, 'icon' => '✉'],

        // Stories genérico
        'story_vertical' => ['name' => 'Story 9:16', 'group' => 'stories', 'width' => 1080, 'height' => 1920, 'icon' => '▲'],
        'story_vertical_hd' => ['name' => 'Story Full HD 9:16', 'group' => 'stories', 'width' => 1080, 'height' => 1920, 'icon' => '▲'],

        // Impressão
        'print_a4' => ['name' => 'A4 retrato 300dpi', 'group' => 'print', 'width' => 2480, 'height' => 3508, 'icon' => '🖨'],
        'print_a5' => ['name' => 'A5 retrato 300dpi', 'group' => 'print', 'width' => 1748, 'height' => 2480, 'icon' => '🖨'],
        'print_poster' => ['name' => 'Poster 50×70 cm', 'group' => 'print', 'width' => 5906, 'height' => 8268, 'icon' => '🖼'],
    ],

    'defaults' => [
        'preset' => 'ig_feed_square',
        'background_color' => '#ffffff',
        'background_opacity' => 100,
    ],
];
