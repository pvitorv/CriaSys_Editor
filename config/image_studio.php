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

    'templates' => [
        'story_quote' => [
            'name' => 'Story citação',
            'description' => 'Overlay escuro + texto central',
            'preset' => 'ig_story',
            'group' => 'stories',
            'background' => ['color' => '#18181b', 'opacity' => 100],
            'objects' => [
                ['kind' => 'rect', 'name' => 'Faixa', 'x' => 0.05, 'y' => 0.38, 'w' => 0.9, 'h' => 0.24, 'fill' => '#000000', 'opacity' => 50],
                ['kind' => 'text', 'name' => 'Citação', 'x' => 0.5, 'y' => 0.5, 'text' => 'Sua citação aqui', 'fontSize' => 48, 'fill' => '#ffffff', 'fontFamily' => 'Georgia, serif', 'originX' => 'center', 'originY' => 'center'],
            ],
        ],
        'ig_promo_sale' => [
            'name' => 'Post promoção',
            'description' => 'Destaque vermelho + headline',
            'preset' => 'ig_feed_square',
            'group' => 'instagram',
            'background' => ['color' => '#fafafa', 'opacity' => 100],
            'objects' => [
                ['kind' => 'rect', 'name' => 'Faixa promo', 'x' => 0, 'y' => 0.72, 'w' => 1, 'h' => 0.28, 'fill' => '#dc2626', 'opacity' => 100],
                ['kind' => 'text', 'name' => 'Headline', 'x' => 0.5, 'y' => 0.82, 'text' => 'PROMOÇÃO', 'fontSize' => 72, 'fill' => '#ffffff', 'fontFamily' => 'Impact, Arial Black, sans-serif', 'originX' => 'center', 'originY' => 'center'],
                ['kind' => 'text', 'name' => 'Subtítulo', 'x' => 0.5, 'y' => 0.92, 'text' => 'Até 50% OFF', 'fontSize' => 36, 'fill' => '#fef2f2', 'fontFamily' => 'Arial, sans-serif', 'originX' => 'center', 'originY' => 'center'],
            ],
        ],
        'yt_thumb_bold' => [
            'name' => 'Thumbnail YouTube',
            'description' => 'Barra inferior + título impacto',
            'preset' => 'yt_thumb',
            'group' => 'youtube',
            'background' => ['color' => '#27272a', 'opacity' => 100],
            'objects' => [
                ['kind' => 'rect', 'name' => 'Barra inferior', 'x' => 0, 'y' => 0.78, 'w' => 1, 'h' => 0.22, 'fill' => '#7c3aed', 'opacity' => 95],
                ['kind' => 'text', 'name' => 'Título', 'x' => 0.04, 'y' => 0.86, 'text' => 'TÍTULO DO VÍDEO', 'fontSize' => 56, 'fill' => '#ffffff', 'fontFamily' => 'Impact, Arial Black, sans-serif', 'originX' => 'left', 'originY' => 'center'],
            ],
        ],
        'fb_event_card' => [
            'name' => 'Card evento Facebook',
            'description' => 'Data + título evento',
            'preset' => 'fb_feed',
            'group' => 'facebook',
            'background' => ['color' => '#1e3a5f', 'opacity' => 100],
            'objects' => [
                ['kind' => 'circle', 'name' => 'Destaque', 'x' => 0.08, 'y' => 0.5, 'r' => 0.12, 'fill' => '#2563eb', 'opacity' => 100],
                ['kind' => 'text', 'name' => 'Data', 'x' => 0.08, 'y' => 0.5, 'text' => '15\nJUL', 'fontSize' => 28, 'fill' => '#ffffff', 'fontFamily' => 'Arial, sans-serif', 'originX' => 'center', 'originY' => 'center'],
                ['kind' => 'text', 'name' => 'Evento', 'x' => 0.28, 'y' => 0.42, 'text' => 'Nome do evento', 'fontSize' => 42, 'fill' => '#ffffff', 'fontFamily' => 'Arial, sans-serif', 'originX' => 'left', 'originY' => 'top'],
                ['kind' => 'text', 'name' => 'Local', 'x' => 0.28, 'y' => 0.62, 'text' => 'Local · Horário', 'fontSize' => 22, 'fill' => '#93c5fd', 'fontFamily' => 'Arial, sans-serif', 'originX' => 'left', 'originY' => 'top'],
            ],
        ],
        'li_article_cover' => [
            'name' => 'Capa artigo LinkedIn',
            'description' => 'Minimalista corporativo',
            'preset' => 'li_feed',
            'group' => 'linkedin',
            'background' => ['color' => '#f8fafc', 'opacity' => 100],
            'objects' => [
                ['kind' => 'rect', 'name' => 'Barra accent', 'x' => 0, 'y' => 0, 'w' => 0.012, 'h' => 1, 'fill' => '#0a66c2', 'opacity' => 100],
                ['kind' => 'text', 'name' => 'Título', 'x' => 0.06, 'y' => 0.35, 'text' => 'Título do artigo', 'fontSize' => 44, 'fill' => '#0f172a', 'fontFamily' => 'Arial, sans-serif', 'originX' => 'left', 'originY' => 'top'],
                ['kind' => 'text', 'name' => 'Autor', 'x' => 0.06, 'y' => 0.72, 'text' => 'Por Seu Nome · Empresa', 'fontSize' => 20, 'fill' => '#64748b', 'fontFamily' => 'Arial, sans-serif', 'originX' => 'left', 'originY' => 'top'],
            ],
        ],
        'web_banner_cta' => [
            'name' => 'Banner CTA web',
            'description' => 'Leaderboard com botão',
            'preset' => 'web_banner_leader',
            'group' => 'web',
            'background' => ['color' => '#0f172a', 'opacity' => 100],
            'objects' => [
                ['kind' => 'text', 'name' => 'Headline', 'x' => 0.03, 'y' => 0.5, 'text' => 'Sua oferta especial', 'fontSize' => 22, 'fill' => '#ffffff', 'fontFamily' => 'Arial, sans-serif', 'originX' => 'left', 'originY' => 'center'],
                ['kind' => 'rect', 'name' => 'Botão', 'x' => 0.78, 'y' => 0.22, 'w' => 0.18, 'h' => 0.56, 'fill' => '#22c55e', 'opacity' => 100],
                ['kind' => 'text', 'name' => 'CTA', 'x' => 0.87, 'y' => 0.5, 'text' => 'Saiba mais', 'fontSize' => 14, 'fill' => '#ffffff', 'fontFamily' => 'Arial, sans-serif', 'originX' => 'center', 'originY' => 'center'],
            ],
        ],
    ],

    'defaults' => [
        'preset' => 'ig_feed_square',
        'background_color' => '#ffffff',
        'background_opacity' => 100,
    ],
];
