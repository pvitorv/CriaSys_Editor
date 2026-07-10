<?php

/**
 * Catálogo de molduras — independente dos modelos de layout.
 * Cada moldura é desenhada por cima da composição final.
 */
return [
    'default' => 'none',

    'categories' => [
        'basico' => 'Básico',
        'minimalista' => 'Minimalista',
        'moderno' => 'Moderno',
        'cinema' => 'Cinema & TV',
        'vintage' => 'Vintage & retrô',
        'neon' => 'Neon & glow',
        'luxo' => 'Luxo & premium',
        'social' => 'Redes sociais',
        'tech' => 'Tech & gaming',
        'corporativo' => 'Corporativo',
        'esportes' => 'Esportes & ação',
        'podcast' => 'Podcast & áudio',
        'criativo' => 'Criativo',
        'artistico' => 'Artístico',
        'editorial' => 'Editorial',
        'youtube_br' => 'Canais YouTube BR',
        'personalizado' => 'Minhas molduras',
    ],

    'defaults' => [
        'frame_slug' => 'none',
        'frame_color' => '#ffffff',
        'frame_secondary_color' => '#ef4444',
        'frame_width' => 28,
        'frame_opacity' => 100,
        'frame_inset' => 12,
    ],

    'frames' => [
        'none' => ['name' => 'Sem moldura', 'category' => 'basico', 'description' => 'Nenhuma borda'],

        // —— Básico ——
        'thin_white' => ['name' => 'Fina branca', 'category' => 'basico', 'style' => 'solid', 'weight' => 0.35],
        'thin_black' => ['name' => 'Fina preta', 'category' => 'basico', 'style' => 'solid', 'weight' => 0.35, 'default_color' => '#000000'],
        'thin_gray' => ['name' => 'Fina cinza', 'category' => 'basico', 'style' => 'solid', 'weight' => 0.35, 'default_color' => '#71717a'],
        'thick_solid' => ['name' => 'Borda grossa', 'category' => 'basico', 'style' => 'solid', 'weight' => 1.2],
        'extra_thick' => ['name' => 'Borda extra grossa', 'category' => 'basico', 'style' => 'solid', 'weight' => 2.0],
        'double_line' => ['name' => 'Linha dupla', 'category' => 'basico', 'style' => 'double'],
        'triple_line' => ['name' => 'Linha tripla', 'category' => 'basico', 'style' => 'triple'],
        'dashed' => ['name' => 'Tracejada', 'category' => 'basico', 'style' => 'dashed'],
        'dotted' => ['name' => 'Pontilhada', 'category' => 'basico', 'style' => 'dotted'],
        'inset_mat' => ['name' => 'Matte interno', 'category' => 'basico', 'style' => 'inset_mat'],
        'beveled_classic' => ['name' => 'Chanfrada 3D', 'category' => 'basico', 'style' => 'beveled_3d'],
        'depth_shadow' => ['name' => 'Sombra profunda', 'category' => 'basico', 'style' => 'depth_shadow'],
        'hairline' => ['name' => 'Hairline fina', 'category' => 'basico', 'style' => 'thin_hairline'],
        'corner_dots' => ['name' => 'Pontos nos cantos', 'category' => 'basico', 'style' => 'corner_dots'],

        // —— Minimalista ——
        'minimal_white' => ['name' => 'Branco puro', 'category' => 'minimalista', 'style' => 'thin_hairline', 'default_color' => '#ffffff'],
        'minimal_black' => ['name' => 'Preto puro', 'category' => 'minimalista', 'style' => 'thin_hairline', 'default_color' => '#18181b'],
        'minimal_offset' => ['name' => 'Offset suave', 'category' => 'minimalista', 'style' => 'offset_shadow'],
        'minimal_inset' => ['name' => 'Recuo clean', 'category' => 'minimalista', 'style' => 'inset_mat', 'weight' => 0.5],
        'minimal_rounded' => ['name' => 'Arredondada leve', 'category' => 'minimalista', 'style' => 'rounded', 'weight' => 0.5],
        'minimal_dual' => ['name' => 'Dupla fina', 'category' => 'minimalista', 'style' => 'double', 'weight' => 0.4],
        'minimal_gallery' => ['name' => 'Galeria minimal', 'category' => 'minimalista', 'style' => 'gallery_white'],
        'minimal_spotlight' => ['name' => 'Spotlight suave', 'category' => 'minimalista', 'style' => 'spotlight_vignette'],

        // —— Moderno ——
        'rounded_modern' => ['name' => 'Cantos arredondados', 'category' => 'moderno', 'style' => 'rounded'],
        'rounded_thick' => ['name' => 'Arredondada grossa', 'category' => 'moderno', 'style' => 'rounded_thick'],
        'pill_inset' => ['name' => 'Pílula inset', 'category' => 'moderno', 'style' => 'pill_inset'],
        'minimal_offset_mod' => ['name' => 'Offset minimal', 'category' => 'moderno', 'style' => 'offset_shadow'],
        'side_bars' => ['name' => 'Barras laterais', 'category' => 'moderno', 'style' => 'side_bars'],
        'top_bottom_bars' => ['name' => 'Faixas topo/base', 'category' => 'moderno', 'style' => 'top_bottom_bars'],
        'gradient_border' => ['name' => 'Borda degradê', 'category' => 'moderno', 'style' => 'gradient_border'],
        'split_duotone' => ['name' => 'Duotone split', 'category' => 'moderno', 'style' => 'split_duotone'],
        'glass_modern' => ['name' => 'Glass morphism', 'category' => 'moderno', 'style' => 'glass_border'],
        'nested_modern' => ['name' => 'Moldura dupla inset', 'category' => 'moderno', 'style' => 'nested_frame'],
        'glow_soft' => ['name' => 'Glow suave', 'category' => 'moderno', 'style' => 'glow_soft'],
        'gradient_vignette' => ['name' => 'Vinheta colorida', 'category' => 'moderno', 'style' => 'gradient_vignette'],
        'holographic' => ['name' => 'Holográfica', 'category' => 'moderno', 'style' => 'holographic_border'],
        'dual_corner' => ['name' => 'Cantos duplos', 'category' => 'moderno', 'style' => 'dual_corner_accent'],

        // —— Cinema ——
        'letterbox_frame' => ['name' => 'Letterbox cinema', 'category' => 'cinema', 'style' => 'letterbox'],
        'film_strip' => ['name' => 'Filme 35mm', 'category' => 'cinema', 'style' => 'film_strip'],
        'broadcast' => ['name' => 'Broadcast TV', 'category' => 'cinema', 'style' => 'broadcast'],
        'viewfinder' => ['name' => 'Viewfinder', 'category' => 'cinema', 'style' => 'viewfinder'],
        'rec_dot' => ['name' => 'REC gravação', 'category' => 'cinema', 'style' => 'rec_dot'],
        'scope_235' => ['name' => 'Scope 2.35:1', 'category' => 'cinema', 'style' => 'scope_bars'],
        'cinematic_ultra' => ['name' => 'Ultra wide cinematic', 'category' => 'cinema', 'style' => 'cinematic_ultra'],
        'monitor_bezel' => ['name' => 'Monitor bezel', 'category' => 'cinema', 'style' => 'monitor_bezel'],
        'breaking_news' => ['name' => 'Breaking news', 'category' => 'cinema', 'style' => 'breaking_news', 'default_color' => '#dc2626'],
        'timecode_bars' => ['name' => 'Timecode bars', 'category' => 'cinema', 'style' => 'data_hud'],
        'director_view' => ['name' => 'Director view', 'category' => 'cinema', 'style' => 'viewfinder'],
        'anamorphic' => ['name' => 'Anamórfico', 'category' => 'cinema', 'style' => 'scope_bars'],

        // —— Vintage ——
        'polaroid' => ['name' => 'Polaroid', 'category' => 'vintage', 'style' => 'polaroid'],
        'polaroid_faded' => ['name' => 'Polaroid desbotado', 'category' => 'vintage', 'style' => 'polaroid'],
        'vintage_corners' => ['name' => 'Cantos ornamentais', 'category' => 'vintage', 'style' => 'ornate_corners'],
        'ticket_stub' => ['name' => 'Ticket / ingresso', 'category' => 'vintage', 'style' => 'ticket'],
        'scotch_tape' => ['name' => 'Fitas adesivas', 'category' => 'vintage', 'style' => 'scotch_tape'],
        'newspaper' => ['name' => 'Jornal', 'category' => 'vintage', 'style' => 'newspaper'],
        'sepia_vignette' => ['name' => 'Vinheta sépia', 'category' => 'vintage', 'style' => 'vignette_warm'],
        'vhs_retro' => ['name' => 'VHS retrô', 'category' => 'vintage', 'style' => 'vhs_retro'],
        'stamp_seal' => ['name' => 'Selo carimbo', 'category' => 'vintage', 'style' => 'stamp_seal', 'default_color' => '#991b1b'],
        'postcard' => ['name' => 'Cartão postal', 'category' => 'vintage', 'style' => 'double', 'default_color' => '#fef3c7'],
        'film_grain_frame' => ['name' => 'Grão de filme', 'category' => 'vintage', 'style' => 'vignette_warm'],

        // —— Neon ——
        'neon_solid' => ['name' => 'Neon sólido', 'category' => 'neon', 'style' => 'neon_glow', 'default_color' => '#22d3ee'],
        'neon_pink' => ['name' => 'Neon rosa', 'category' => 'neon', 'style' => 'neon_glow', 'default_color' => '#ec4899'],
        'neon_green' => ['name' => 'Neon verde', 'category' => 'neon', 'style' => 'neon_glow', 'default_color' => '#4ade80'],
        'neon_purple' => ['name' => 'Neon roxo', 'category' => 'neon', 'style' => 'neon_glow', 'default_color' => '#a855f7'],
        'neon_orange' => ['name' => 'Neon laranja', 'category' => 'neon', 'style' => 'neon_glow', 'default_color' => '#f97316'],
        'neon_double' => ['name' => 'Neon duplo', 'category' => 'neon', 'style' => 'neon_double'],
        'cyber_grid' => ['name' => 'Cyber grid', 'category' => 'neon', 'style' => 'cyber_grid'],
        'rgb_gaming' => ['name' => 'RGB gaming', 'category' => 'neon', 'style' => 'rgb_segments'],
        'pulse_ring' => ['name' => 'Anel pulsante', 'category' => 'neon', 'style' => 'pulse_ring'],
        'glitch_chroma' => ['name' => 'Glitch cromático', 'category' => 'neon', 'style' => 'glitch_chroma'],
        'neon_cyan_magenta' => ['name' => 'Ciano + magenta', 'category' => 'neon', 'style' => 'neon_double', 'default_color' => '#06b6d4'],

        // —— Luxo ——
        'gold_classic' => ['name' => 'Ouro clássico', 'category' => 'luxo', 'style' => 'gold_double', 'default_color' => '#d4af37'],
        'gold_ornate' => ['name' => 'Ouro ornamental', 'category' => 'luxo', 'style' => 'gold_ornate', 'default_color' => '#c9a227'],
        'gold_thin' => ['name' => 'Ouro fino', 'category' => 'luxo', 'style' => 'gold_double', 'default_color' => '#e8c547', 'weight' => 0.5],
        'silver_chrome' => ['name' => 'Prata chrome', 'category' => 'luxo', 'style' => 'chrome', 'default_color' => '#c0c0c0'],
        'platinum' => ['name' => 'Platina', 'category' => 'luxo', 'style' => 'chrome', 'default_color' => '#e5e4e2'],
        'rose_gold' => ['name' => 'Rose gold', 'category' => 'luxo', 'style' => 'gold_double', 'default_color' => '#b76e79'],
        'marble_mat' => ['name' => 'Marble matte', 'category' => 'luxo', 'style' => 'marble_mat'],
        'luxury_inset' => ['name' => 'Inset premium', 'category' => 'luxo', 'style' => 'luxury_inset'],
        'velvet_black' => ['name' => 'Veludo preto', 'category' => 'luxo', 'style' => 'luxury_inset', 'default_color' => '#1a1a1a'],
        'diamond_luxe' => ['name' => 'Diamante luxo', 'category' => 'luxo', 'style' => 'diamond_corners', 'default_color' => '#d4af37'],

        // —— Social ——
        'instagram_round' => ['name' => 'Estilo Instagram', 'category' => 'social', 'style' => 'ig_gradient_ring'],
        'instagram_minimal' => ['name' => 'IG minimal', 'category' => 'social', 'style' => 'rounded', 'weight' => 0.6],
        'tiktok_glitch' => ['name' => 'TikTok glitch', 'category' => 'social', 'style' => 'tiktok_offset'],
        'tiktok_neon' => ['name' => 'TikTok neon', 'category' => 'social', 'style' => 'neon_glow', 'default_color' => '#ff0050'],
        'youtube_red' => ['name' => 'YouTube accent', 'category' => 'social', 'style' => 'yt_accent', 'default_color' => '#ff0000'],
        'youtube_dark' => ['name' => 'YouTube dark', 'category' => 'social', 'style' => 'yt_accent', 'default_color' => '#212121'],
        'stories_ring' => ['name' => 'Stories ring', 'category' => 'social', 'style' => 'stories_gradient'],
        'safe_zone' => ['name' => 'Safe zone guide', 'category' => 'social', 'style' => 'safe_zone'],
        'linkedin_pro' => ['name' => 'LinkedIn pro', 'category' => 'social', 'style' => 'corporate_accent', 'default_color' => '#0a66c2'],
        'twitter_x' => ['name' => 'X / Twitter', 'category' => 'social', 'style' => 'solid', 'default_color' => '#000000', 'weight' => 0.8],
        'facebook_blue' => ['name' => 'Facebook blue', 'category' => 'social', 'style' => 'gradient_border', 'default_color' => '#1877f2'],
        'pinterest_pin' => ['name' => 'Pinterest', 'category' => 'social', 'style' => 'rounded_thick', 'default_color' => '#e60023'],

        // —— Tech ——
        'corner_brackets' => ['name' => 'Colchetes HUD', 'category' => 'tech', 'style' => 'corner_brackets'],
        'crosshair' => ['name' => 'Crosshair', 'category' => 'tech', 'style' => 'crosshair'],
        'scanlines' => ['name' => 'Scanlines CRT', 'category' => 'tech', 'style' => 'scanlines'],
        'circuit' => ['name' => 'Circuito', 'category' => 'tech', 'style' => 'circuit_corners'],
        'data_frame' => ['name' => 'Data HUD', 'category' => 'tech', 'style' => 'data_hud'],
        'matrix_green' => ['name' => 'Matrix verde', 'category' => 'tech', 'style' => 'cyber_grid', 'default_color' => '#00ff41'],
        'terminal_hud' => ['name' => 'Terminal HUD', 'category' => 'tech', 'style' => 'data_hud', 'default_color' => '#22c55e'],
        'barcode_strip' => ['name' => 'Barcode strip', 'category' => 'tech', 'style' => 'barcode_strip'],
        'pixel_border' => ['name' => 'Pixel art', 'category' => 'tech', 'style' => 'dotted'],
        'server_rack' => ['name' => 'Server rack', 'category' => 'tech', 'style' => 'side_bars', 'default_color' => '#27272a'],

        // —— Corporativo ——
        'corp_clean' => ['name' => 'Corporativo clean', 'category' => 'corporativo', 'style' => 'corporate_accent', 'default_color' => '#1e40af'],
        'corp_navy' => ['name' => 'Navy business', 'category' => 'corporativo', 'style' => 'corporate_accent', 'default_color' => '#1e3a5f'],
        'corp_teal' => ['name' => 'Teal profissional', 'category' => 'corporativo', 'style' => 'corporate_accent', 'default_color' => '#0d9488'],
        'corp_minimal' => ['name' => 'Corporativo minimal', 'category' => 'corporativo', 'style' => 'thin_hairline', 'default_color' => '#334155'],
        'corp_presentation' => ['name' => 'Apresentação slide', 'category' => 'corporativo', 'style' => 'nested_frame', 'default_color' => '#2563eb'],
        'corp_ribbon' => ['name' => 'Faixa corporativa', 'category' => 'corporativo', 'style' => 'ribbon_corner', 'default_color' => '#1d4ed8'],
        'corp_dual_bar' => ['name' => 'Dual bar brand', 'category' => 'corporativo', 'style' => 'top_bottom_bars'],
        'corp_glass' => ['name' => 'Glass corporate', 'category' => 'corporativo', 'style' => 'glass_border'],

        // —— Esportes ——
        'sport_stripe' => ['name' => 'Listra esportiva', 'category' => 'esportes', 'style' => 'sport_diagonal', 'default_color' => '#ef4444'],
        'sport_dynamic' => ['name' => 'Dinâmica ação', 'category' => 'esportes', 'style' => 'diagonal_stripes'],
        'sport_bold' => ['name' => 'Bold impact', 'category' => 'esportes', 'style' => 'comic', 'default_color' => '#fbbf24'],
        'sport_neon' => ['name' => 'Neon esportivo', 'category' => 'esportes', 'style' => 'neon_glow', 'default_color' => '#facc15'],
        'sport_scoreboard' => ['name' => 'Placar', 'category' => 'esportes', 'style' => 'top_bottom_bars', 'default_color' => '#15803d'],
        'sport_racing' => ['name' => 'Racing checkered', 'category' => 'esportes', 'style' => 'halftone_edge'],
        'sport_energy' => ['name' => 'Energia radial', 'category' => 'esportes', 'style' => 'spotlight_vignette', 'default_color' => '#ea580c'],

        // —— Podcast ——
        'podcast_wave' => ['name' => 'Waveform', 'category' => 'podcast', 'style' => 'podcast_wave', 'default_color' => '#8b5cf6'],
        'podcast_mic' => ['name' => 'Estúdio mic', 'category' => 'podcast', 'style' => 'rec_dot', 'default_color' => '#6366f1'],
        'podcast_minimal' => ['name' => 'Podcast minimal', 'category' => 'podcast', 'style' => 'rounded', 'default_color' => '#a78bfa'],
        'podcast_vinyl' => ['name' => 'Vinil áudio', 'category' => 'podcast', 'style' => 'pulse_ring', 'default_color' => '#1f2937'],
        'podcast_eq' => ['name' => 'Equalizador', 'category' => 'podcast', 'style' => 'side_bars', 'default_color' => '#7c3aed'],
        'podcast_dark' => ['name' => 'Dark studio', 'category' => 'podcast', 'style' => 'luxury_inset', 'default_color' => '#4c1d95'],

        // —— Criativo ——
        'diagonal_stripes' => ['name' => 'Listras diagonais', 'category' => 'criativo', 'style' => 'diagonal_stripes'],
        'zigzag' => ['name' => 'Zigzag', 'category' => 'criativo', 'style' => 'zigzag'],
        'star_corners' => ['name' => 'Estrelas nos cantos', 'category' => 'criativo', 'style' => 'star_corners'],
        'diamond_cut' => ['name' => 'Corte diamante', 'category' => 'criativo', 'style' => 'diamond_corners'],
        'brush_stroke' => ['name' => 'Pincelada', 'category' => 'criativo', 'style' => 'brush_edges'],
        'torn_paper' => ['name' => 'Papel rasgado', 'category' => 'criativo', 'style' => 'torn_paper'],
        'comic_pop' => ['name' => 'Quadrinhos pop', 'category' => 'criativo', 'style' => 'comic'],
        'rainbow' => ['name' => 'Arco-íris', 'category' => 'criativo', 'style' => 'rainbow'],
        'confetti' => ['name' => 'Confete', 'category' => 'criativo', 'style' => 'confetti_dots'],
        'pop_art' => ['name' => 'Pop art dots', 'category' => 'criativo', 'style' => 'halftone_edge'],
        'graffiti' => ['name' => 'Grafite spray', 'category' => 'criativo', 'style' => 'brush_edges', 'default_color' => '#f472b6'],
        'sticker_pack' => ['name' => 'Adesivo', 'category' => 'criativo', 'style' => 'offset_shadow', 'default_color' => '#fde047'],

        // —— Artístico ——
        'art_watercolor' => ['name' => 'Aquarela', 'category' => 'artistico', 'style' => 'brush_edges', 'default_color' => '#93c5fd'],
        'art_oil' => ['name' => 'Óleo clássico', 'category' => 'artistico', 'style' => 'gold_ornate', 'default_color' => '#78350f'],
        'art_museum' => ['name' => 'Moldura museu', 'category' => 'artistico', 'style' => 'gold_ornate', 'default_color' => '#92400e'],
        'art_canvas' => ['name' => 'Tela canvas', 'category' => 'artistico', 'style' => 'inset_mat', 'default_color' => '#fef9c3'],
        'art_frost' => ['name' => 'Gelo cristal', 'category' => 'artistico', 'style' => 'frost_ice', 'default_color' => '#bae6fd'],
        'art_fire' => ['name' => 'Chamas quentes', 'category' => 'artistico', 'style' => 'fire_warm', 'default_color' => '#f97316'],
        'art_ink' => ['name' => 'Tinta sumi-e', 'category' => 'artistico', 'style' => 'thin_hairline', 'default_color' => '#1c1917'],
        'art_splash' => ['name' => 'Splash colorido', 'category' => 'artistico', 'style' => 'gradient_vignette'],

        // —— Editorial ——
        'magazine_bleed' => ['name' => 'Bleed revista', 'category' => 'editorial', 'style' => 'magazine_bleed'],
        'magazine_vogue' => ['name' => 'Capa Vogue', 'category' => 'editorial', 'style' => 'headline_bar', 'default_color' => '#000000'],
        'headline_bar' => ['name' => 'Barra manchete', 'category' => 'editorial', 'style' => 'headline_bar'],
        'column_gutter' => ['name' => 'Colunas editorial', 'category' => 'editorial', 'style' => 'column_gutter'],
        'photo_credit' => ['name' => 'Crédito foto', 'category' => 'editorial', 'style' => 'photo_credit'],
        'gallery_white' => ['name' => 'Galeria branca', 'category' => 'editorial', 'style' => 'gallery_white', 'default_color' => '#ffffff'],
        'newspaper_head' => ['name' => 'Manchete jornal', 'category' => 'editorial', 'style' => 'newspaper'],
        'editorial_red' => ['name' => 'Editorial vermelho', 'category' => 'editorial', 'style' => 'magazine_bleed', 'default_color' => '#dc2626'],
        'tabloid' => ['name' => 'Tabloide', 'category' => 'editorial', 'style' => 'headline_bar', 'default_color' => '#b91c1c'],

        // —— Canais YouTube BR — Ei Nerd ——
        'yt_einerd_comic' => ['name' => 'Ei Nerd · Comic pop', 'category' => 'youtube_br', 'style' => 'comic_yellow_red', 'default_color' => '#facc15', 'creator' => 'Ei Nerd'],
        'yt_einerd_burst' => ['name' => 'Ei Nerd · Burst rays', 'category' => 'youtube_br', 'style' => 'ray_burst', 'default_color' => '#a855f7', 'creator' => 'Ei Nerd'],
        'yt_einerd_vs' => ['name' => 'Ei Nerd · VS split', 'category' => 'youtube_br', 'style' => 'vs_diagonal_split', 'default_color' => '#ef4444', 'creator' => 'Ei Nerd'],
        'yt_einerd_speech' => ['name' => 'Ei Nerd · Speech bubble', 'category' => 'youtube_br', 'style' => 'speech_bubble_corner', 'default_color' => '#ffffff', 'creator' => 'Ei Nerd'],
        'yt_einerd_halftone' => ['name' => 'Ei Nerd · Halftone geek', 'category' => 'youtube_br', 'style' => 'halftone_edge', 'default_color' => '#000000', 'creator' => 'Ei Nerd'],
        'yt_einerd_yellow' => ['name' => 'Ei Nerd · Amarelo nerd', 'category' => 'youtube_br', 'style' => 'comic', 'default_color' => '#fbbf24', 'creator' => 'Ei Nerd'],
        'yt_einerd_purple' => ['name' => 'Ei Nerd · Roxo pop', 'category' => 'youtube_br', 'style' => 'neon_glow', 'default_color' => '#9333ea', 'creator' => 'Ei Nerd'],
        'yt_einerd_red_bar' => ['name' => 'Ei Nerd · Faixa vermelha', 'category' => 'youtube_br', 'style' => 'headline_bar', 'default_color' => '#dc2626', 'creator' => 'Ei Nerd'],

        // —— Ei Nerd · Balões HQ / mangá ——
        'yt_einerd_bubble_round' => ['name' => 'Ei Nerd · Balão clássico', 'category' => 'youtube_br', 'style' => 'comic_bubble_round', 'default_color' => '#facc15', 'creator' => 'Ei Nerd'],
        'yt_einerd_bubble_shout' => ['name' => 'Ei Nerd · Grito HQ', 'category' => 'youtube_br', 'style' => 'comic_bubble_shout', 'default_color' => '#ef4444', 'creator' => 'Ei Nerd'],
        'yt_einerd_bubble_thought' => ['name' => 'Ei Nerd · Pensamento', 'category' => 'youtube_br', 'style' => 'comic_bubble_thought', 'default_color' => '#a855f7', 'creator' => 'Ei Nerd'],
        'yt_einerd_manga' => ['name' => 'Ei Nerd · Mangá', 'category' => 'youtube_br', 'style' => 'manga_bubble', 'default_color' => '#ffffff', 'creator' => 'Ei Nerd'],
        'yt_einerd_manga_scream' => ['name' => 'Ei Nerd · Mangá impacto', 'category' => 'youtube_br', 'style' => 'manga_scream', 'default_color' => '#f97316', 'creator' => 'Ei Nerd'],
        'yt_einerd_bubble_double' => ['name' => 'Ei Nerd · Diálogo duplo', 'category' => 'youtube_br', 'style' => 'comic_bubble_double', 'default_color' => '#fbbf24', 'creator' => 'Ei Nerd'],
        'yt_einerd_narrator' => ['name' => 'Ei Nerd · Caixa narrador', 'category' => 'youtube_br', 'style' => 'comic_narrator_box', 'default_color' => '#9333ea', 'creator' => 'Ei Nerd'],
        'yt_einerd_hq_panel' => ['name' => 'Ei Nerd · Painel HQ', 'category' => 'youtube_br', 'style' => 'comic_panel_bubbles', 'default_color' => '#000000', 'creator' => 'Ei Nerd'],

        // —— Nerd de Negócios ——
        'yt_ndn_navy' => ['name' => 'Nerd Negócios · Navy pro', 'category' => 'youtube_br', 'style' => 'ndn_navy_brand', 'default_color' => '#1e3a8a', 'creator' => 'Nerd de Negócios'],
        'yt_ndn_clean' => ['name' => 'Nerd Negócios · Clean', 'category' => 'youtube_br', 'style' => 'corporate_accent', 'default_color' => '#1d4ed8', 'creator' => 'Nerd de Negócios'],
        'yt_ndn_green' => ['name' => 'Nerd Negócios · Growth', 'category' => 'youtube_br', 'style' => 'ndn_growth_stripe', 'default_color' => '#16a34a', 'creator' => 'Nerd de Negócios'],
        'yt_ndn_minimal' => ['name' => 'Nerd Negócios · Minimal', 'category' => 'youtube_br', 'style' => 'thin_hairline', 'default_color' => '#334155', 'creator' => 'Nerd de Negócios'],
        'yt_ndn_dark' => ['name' => 'Nerd Negócios · Dark pro', 'category' => 'youtube_br', 'style' => 'luxury_inset', 'default_color' => '#0f172a', 'creator' => 'Nerd de Negócios'],
        'yt_ndn_gold' => ['name' => 'Nerd Negócios · Gold premium', 'category' => 'youtube_br', 'style' => 'gold_double', 'default_color' => '#ca8a04', 'creator' => 'Nerd de Negócios'],
        'yt_ndn_glass' => ['name' => 'Nerd Negócios · Glass', 'category' => 'youtube_br', 'style' => 'glass_border', 'default_color' => '#2563eb', 'creator' => 'Nerd de Negócios'],
        'yt_ndn_chart' => ['name' => 'Nerd Negócios · Infográfico', 'category' => 'youtube_br', 'style' => 'side_bars', 'default_color' => '#1e40af', 'creator' => 'Nerd de Negócios'],

        // —— Código Fonte TV ——
        'yt_cftv_terminal' => ['name' => 'Código Fonte · Terminal', 'category' => 'youtube_br', 'style' => 'corner_brackets', 'default_color' => '#22c55e', 'creator' => 'Código Fonte TV'],
        'yt_cftv_ide' => ['name' => 'Código Fonte · IDE window', 'category' => 'youtube_br', 'style' => 'ide_titlebar', 'default_color' => '#27272a', 'creator' => 'Código Fonte TV'],
        'yt_cftv_syntax' => ['name' => 'Código Fonte · Syntax', 'category' => 'youtube_br', 'style' => 'rgb_segments', 'creator' => 'Código Fonte TV'],
        'yt_cftv_orange' => ['name' => 'Código Fonte · Laranja dev', 'category' => 'youtube_br', 'style' => 'cftv_orange_brackets', 'default_color' => '#f97316', 'creator' => 'Código Fonte TV'],
        'yt_cftv_matrix' => ['name' => 'Código Fonte · Matrix', 'category' => 'youtube_br', 'style' => 'cyber_grid', 'default_color' => '#00ff41', 'creator' => 'Código Fonte TV'],
        'yt_cftv_dark_code' => ['name' => 'Código Fonte · Dark code', 'category' => 'youtube_br', 'style' => 'nested_frame', 'default_color' => '#06b6d4', 'creator' => 'Código Fonte TV'],
        'yt_cftv_scan' => ['name' => 'Código Fonte · Scan CRT', 'category' => 'youtube_br', 'style' => 'scanlines', 'default_color' => '#4ade80', 'creator' => 'Código Fonte TV'],
        'yt_cftv_data' => ['name' => 'Código Fonte · HUD data', 'category' => 'youtube_br', 'style' => 'data_hud', 'default_color' => '#38bdf8', 'creator' => 'Código Fonte TV'],

        // —— Mano Devyn ——
        'yt_mdevyn_horror' => ['name' => 'Mano Devyn · Horror', 'category' => 'youtube_br', 'style' => 'horror_crimson', 'default_color' => '#7f1d1d', 'creator' => 'Mano Devyn'],
        'yt_mdevyn_blood' => ['name' => 'Mano Devyn · Blood edge', 'category' => 'youtube_br', 'style' => 'comic', 'default_color' => '#991b1b', 'creator' => 'Mano Devyn'],
        'yt_mdevyn_noir' => ['name' => 'Mano Devyn · Noir', 'category' => 'youtube_br', 'style' => 'cinematic_ultra', 'creator' => 'Mano Devyn'],
        'yt_mdevyn_crimson' => ['name' => 'Mano Devyn · Crimson glow', 'category' => 'youtube_br', 'style' => 'neon_glow', 'default_color' => '#dc2626', 'creator' => 'Mano Devyn'],
        'yt_mdevyn_mystery' => ['name' => 'Mano Devyn · Mistério', 'category' => 'youtube_br', 'style' => 'spotlight_vignette', 'default_color' => '#450a0a', 'creator' => 'Mano Devyn'],
        'yt_mdevyn_rec' => ['name' => 'Mano Devyn · REC found', 'category' => 'youtube_br', 'style' => 'rec_dot', 'default_color' => '#525252', 'creator' => 'Mano Devyn'],
        'yt_mdevyn_vhs' => ['name' => 'Mano Devyn · VHS creep', 'category' => 'youtube_br', 'style' => 'vhs_retro', 'creator' => 'Mano Devyn'],
        'yt_mdevyn_torn' => ['name' => 'Mano Devyn · Papel rasgado', 'category' => 'youtube_br', 'style' => 'torn_paper', 'creator' => 'Mano Devyn'],

        // —— Professor Ricardo Marcilio ——
        'yt_prm_academic' => ['name' => 'Prof. Marcilio · Acadêmico', 'category' => 'youtube_br', 'style' => 'corporate_accent', 'default_color' => '#1d4ed8', 'creator' => 'Prof. Ricardo Marcilio'],
        'yt_prm_chalk' => ['name' => 'Prof. Marcilio · Lousa', 'category' => 'youtube_br', 'style' => 'chalkboard_edu', 'default_color' => '#166534', 'creator' => 'Prof. Ricardo Marcilio'],
        'yt_prm_whiteboard' => ['name' => 'Prof. Marcilio · Whiteboard', 'category' => 'youtube_br', 'style' => 'gallery_white', 'creator' => 'Prof. Ricardo Marcilio'],
        'yt_prm_formula' => ['name' => 'Prof. Marcilio · Fórmulas', 'category' => 'youtube_br', 'style' => 'corner_brackets', 'default_color' => '#2563eb', 'creator' => 'Prof. Ricardo Marcilio'],
        'yt_prm_education' => ['name' => 'Prof. Marcilio · Edu bar', 'category' => 'youtube_br', 'style' => 'headline_bar', 'default_color' => '#1e40af', 'creator' => 'Prof. Ricardo Marcilio'],
        'yt_prm_geometry' => ['name' => 'Prof. Marcilio · Geometria', 'category' => 'youtube_br', 'style' => 'diamond_corners', 'default_color' => '#2563eb', 'creator' => 'Prof. Ricardo Marcilio'],
        'yt_prm_classroom' => ['name' => 'Prof. Marcilio · Sala de aula', 'category' => 'youtube_br', 'style' => 'rounded', 'default_color' => '#3b82f6', 'creator' => 'Prof. Ricardo Marcilio'],
        'yt_prm_clean_blue' => ['name' => 'Prof. Marcilio · Azul clean', 'category' => 'youtube_br', 'style' => 'nested_frame', 'default_color' => '#1d4ed8', 'creator' => 'Prof. Ricardo Marcilio'],
    ],
];
