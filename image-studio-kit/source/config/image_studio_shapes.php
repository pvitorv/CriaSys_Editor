<?php

/**
 * Formas geométricas — uma forma por tipo (cor/contorno no painel do objeto).
 * Grupos: básicas, vazadas, irregulares, ovais, polígonos, 3D, molduras, linhas.
 */
return [
    'groups' => [
        'formas_basicas' => 'Retângulos & quadrados',
        'formas_vazadas' => 'Formas vazadas (contorno)',
        'formas_irregulares' => 'Formas irregulares',
        'formas_ovais' => 'Círculos & ovais',
        'formas_poligonos' => 'Polígonos & estrelas',
        'formas_3d' => 'Formas 3D',
        'formas_molduras' => 'Molduras & bordas',
        'linhas' => 'Linhas & setas',
    ],

    'elements' => [
        // —— Básicas (sólidas) ——
        ['slug' => 'shape_rect', 'name' => 'Retângulo', 'group' => 'formas_basicas', 'type' => 'rect', 'icon' => '▭', 'fill' => '#ffffff'],
        ['slug' => 'shape_square', 'name' => 'Quadrado', 'group' => 'formas_basicas', 'type' => 'square', 'icon' => '□', 'fill' => '#ffffff'],
        ['slug' => 'shape_rounded', 'name' => 'Retângulo arredondado', 'group' => 'formas_basicas', 'type' => 'rounded_rect', 'icon' => '▢', 'fill' => '#ffffff', 'rx' => 24],
        ['slug' => 'shape_pill', 'name' => 'Cápsula / pílula', 'group' => 'formas_basicas', 'type' => 'pill', 'icon' => '⬭', 'fill' => '#ffffff'],

        // —— Vazadas ——
        ['slug' => 'shape_rect_outline', 'name' => 'Retângulo vazado', 'group' => 'formas_vazadas', 'type' => 'rect_outline', 'icon' => '▭', 'stroke' => '#ffffff', 'strokeWidth' => 8],
        ['slug' => 'shape_square_outline', 'name' => 'Quadrado vazado', 'group' => 'formas_vazadas', 'type' => 'square_outline', 'icon' => '□', 'stroke' => '#ffffff', 'strokeWidth' => 8],
        ['slug' => 'shape_rounded_outline', 'name' => 'Arredondado vazado', 'group' => 'formas_vazadas', 'type' => 'rounded_rect_outline', 'icon' => '▢', 'stroke' => '#ffffff', 'strokeWidth' => 8, 'rx' => 24],
        ['slug' => 'shape_pill_outline', 'name' => 'Cápsula vazada', 'group' => 'formas_vazadas', 'type' => 'pill_outline', 'icon' => '⬭', 'stroke' => '#ffffff', 'strokeWidth' => 8],
        ['slug' => 'shape_ring', 'name' => 'Círculo vazado', 'group' => 'formas_vazadas', 'type' => 'ring', 'icon' => '◯', 'stroke' => '#ffffff', 'strokeWidth' => 10],
        ['slug' => 'shape_triangle_outline', 'name' => 'Triângulo vazado', 'group' => 'formas_vazadas', 'type' => 'triangle_outline', 'icon' => '△', 'stroke' => '#ffffff', 'strokeWidth' => 8],
        ['slug' => 'shape_star_outline', 'name' => 'Estrela vazada', 'group' => 'formas_vazadas', 'type' => 'star_outline', 'icon' => '☆', 'stroke' => '#ffffff', 'strokeWidth' => 6],
        ['slug' => 'shape_hex_outline', 'name' => 'Hexágono vazado', 'group' => 'formas_vazadas', 'type' => 'polygon_outline', 'icon' => '⬡', 'stroke' => '#ffffff', 'strokeWidth' => 8, 'sides' => 6],

        // —— Irregulares ——
        ['slug' => 'shape_parallelogram', 'name' => 'Paralelogramo', 'group' => 'formas_irregulares', 'type' => 'parallelogram', 'icon' => '▱', 'fill' => '#ffffff'],
        ['slug' => 'shape_parallelogram_outline', 'name' => 'Paralelogramo vazado', 'group' => 'formas_irregulares', 'type' => 'parallelogram_outline', 'icon' => '▱', 'stroke' => '#ffffff', 'strokeWidth' => 8],
        ['slug' => 'shape_trapezoid', 'name' => 'Trapézio', 'group' => 'formas_irregulares', 'type' => 'trapezoid', 'icon' => '⏢', 'fill' => '#ffffff'],
        ['slug' => 'shape_trapezoid_outline', 'name' => 'Trapézio vazado', 'group' => 'formas_irregulares', 'type' => 'trapezoid_outline', 'icon' => '⏢', 'stroke' => '#ffffff', 'strokeWidth' => 8],
        ['slug' => 'shape_diamond', 'name' => 'Losango', 'group' => 'formas_irregulares', 'type' => 'diamond', 'icon' => '◆', 'fill' => '#ffffff'],
        ['slug' => 'shape_diamond_outline', 'name' => 'Losango vazado', 'group' => 'formas_irregulares', 'type' => 'diamond_outline', 'icon' => '◇', 'stroke' => '#ffffff', 'strokeWidth' => 8],
        ['slug' => 'shape_chevron', 'name' => 'Chevron', 'group' => 'formas_irregulares', 'type' => 'chevron', 'icon' => '›', 'fill' => '#ffffff'],

        // —— Ovais ——
        ['slug' => 'shape_circle', 'name' => 'Círculo', 'group' => 'formas_ovais', 'type' => 'circle', 'icon' => '●', 'fill' => '#ffffff'],
        ['slug' => 'shape_oval_h', 'name' => 'Oval horizontal', 'group' => 'formas_ovais', 'type' => 'ellipse', 'icon' => '⬭', 'fill' => '#ffffff', 'rxRatio' => 0.6, 'ryRatio' => 0.35],
        ['slug' => 'shape_oval_v', 'name' => 'Oval vertical', 'group' => 'formas_ovais', 'type' => 'ellipse', 'icon' => '⬯', 'fill' => '#ffffff', 'rxRatio' => 0.35, 'ryRatio' => 0.6],
        ['slug' => 'shape_semicircle', 'name' => 'Semicírculo', 'group' => 'formas_ovais', 'type' => 'semicircle', 'icon' => '◖', 'fill' => '#ffffff'],
        ['slug' => 'shape_arc', 'name' => 'Arco', 'group' => 'formas_ovais', 'type' => 'arc', 'icon' => '◠', 'stroke' => '#ffffff', 'strokeWidth' => 10],

        // —— Polígonos ——
        ['slug' => 'shape_triangle', 'name' => 'Triângulo', 'group' => 'formas_poligonos', 'type' => 'triangle', 'icon' => '▲', 'fill' => '#ffffff'],
        ['slug' => 'shape_pentagon', 'name' => 'Pentágono', 'group' => 'formas_poligonos', 'type' => 'polygon', 'icon' => '⬠', 'fill' => '#ffffff', 'sides' => 5],
        ['slug' => 'shape_hexagon', 'name' => 'Hexágono', 'group' => 'formas_poligonos', 'type' => 'polygon', 'icon' => '⬡', 'fill' => '#ffffff', 'sides' => 6],
        ['slug' => 'shape_octagon', 'name' => 'Octógono', 'group' => 'formas_poligonos', 'type' => 'polygon', 'icon' => '⯃', 'fill' => '#ffffff', 'sides' => 8],
        ['slug' => 'shape_star', 'name' => 'Estrela', 'group' => 'formas_poligonos', 'type' => 'star', 'icon' => '★', 'fill' => '#ffffff'],
        ['slug' => 'shape_star_4', 'name' => 'Estrela 4 pontas', 'group' => 'formas_poligonos', 'type' => 'star', 'icon' => '✦', 'fill' => '#ffffff', 'spikes' => 4],
        ['slug' => 'shape_cross', 'name' => 'Cruz / plus', 'group' => 'formas_poligonos', 'type' => 'cross', 'icon' => '✚', 'fill' => '#ffffff'],

        // —— 3D ——
        ['slug' => 'shape_cube', 'name' => 'Cubo 3D', 'group' => 'formas_3d', 'type' => 'cube', 'icon' => '⬛', 'fill' => '#ffffff'],
        ['slug' => 'shape_cube_outline', 'name' => 'Cubo 3D vazado', 'group' => 'formas_3d', 'type' => 'cube_outline', 'icon' => '⬜', 'stroke' => '#ffffff', 'strokeWidth' => 4],
        ['slug' => 'shape_cylinder', 'name' => 'Cilindro 3D', 'group' => 'formas_3d', 'type' => 'cylinder', 'icon' => '⬭', 'fill' => '#ffffff'],
        ['slug' => 'shape_cone', 'name' => 'Cone 3D', 'group' => 'formas_3d', 'type' => 'cone', 'icon' => '▲', 'fill' => '#ffffff'],
        ['slug' => 'shape_pyramid', 'name' => 'Pirâmide 3D', 'group' => 'formas_3d', 'type' => 'pyramid', 'icon' => '△', 'fill' => '#ffffff'],
        ['slug' => 'shape_sphere', 'name' => 'Esfera 3D', 'group' => 'formas_3d', 'type' => 'sphere', 'icon' => '◉', 'fill' => '#ffffff'],

        // —— Molduras (montar bordas no canvas) ——
        ['slug' => 'shape_frame_rect', 'name' => 'Moldura retangular', 'group' => 'formas_molduras', 'type' => 'frame_rect', 'icon' => '▣', 'stroke' => '#ffffff', 'strokeWidth' => 24, 'sizeRatio' => 0.92],
        ['slug' => 'shape_frame_rounded', 'name' => 'Moldura arredondada', 'group' => 'formas_molduras', 'type' => 'frame_rounded', 'icon' => '▣', 'stroke' => '#ffffff', 'strokeWidth' => 24, 'sizeRatio' => 0.92, 'rx' => 32],
        ['slug' => 'shape_frame_inner', 'name' => 'Borda interna fina', 'group' => 'formas_molduras', 'type' => 'frame_rect', 'icon' => '▢', 'stroke' => '#ffffff', 'strokeWidth' => 6, 'sizeRatio' => 0.95],
        ['slug' => 'shape_corner_bracket', 'name' => 'Cantoneira', 'group' => 'formas_molduras', 'type' => 'corner_bracket', 'icon' => '⌜', 'stroke' => '#ffffff', 'strokeWidth' => 10],
        ['slug' => 'shape_bar_h', 'name' => 'Barra horizontal', 'group' => 'formas_molduras', 'type' => 'bar_h', 'icon' => '▬', 'fill' => '#ffffff'],
        ['slug' => 'shape_bar_v', 'name' => 'Barra vertical', 'group' => 'formas_molduras', 'type' => 'bar_v', 'icon' => '▮', 'fill' => '#ffffff'],
        ['slug' => 'shape_letterbox', 'name' => 'Letterbox (cinema)', 'group' => 'formas_molduras', 'type' => 'letterbox', 'icon' => '▭', 'fill' => '#000000'],

        // —— Linhas ——
        ['slug' => 'shape_line_h', 'name' => 'Linha horizontal', 'group' => 'linhas', 'type' => 'line', 'icon' => '—', 'stroke' => '#ffffff', 'strokeWidth' => 6],
        ['slug' => 'shape_line_v', 'name' => 'Linha vertical', 'group' => 'linhas', 'type' => 'line_vertical', 'icon' => '|', 'stroke' => '#ffffff', 'strokeWidth' => 6],
        ['slug' => 'shape_line_dashed', 'name' => 'Linha tracejada', 'group' => 'linhas', 'type' => 'line_dashed', 'icon' => '┄', 'stroke' => '#ffffff', 'strokeWidth' => 6],
        ['slug' => 'shape_arrow', 'name' => 'Seta', 'group' => 'linhas', 'type' => 'arrow', 'icon' => '→', 'fill' => '#ffffff'],
        ['slug' => 'shape_arrow_outline', 'name' => 'Seta vazada', 'group' => 'linhas', 'type' => 'arrow_outline', 'icon' => '➔', 'stroke' => '#ffffff', 'strokeWidth' => 6],
    ],
];
