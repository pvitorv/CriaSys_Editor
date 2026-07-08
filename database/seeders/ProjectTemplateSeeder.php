<?php

namespace Database\Seeders;

use App\Models\ProjectTemplate;
use Illuminate\Database\Seeder;

class ProjectTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'blank-16-9',
                'name' => 'Em branco 16:9',
                'description' => 'Projeto vazio para YouTube e apresentações horizontais.',
                'aspect_ratio' => '16:9',
                'slides' => [],
            ],
            [
                'slug' => 'blank-9-16',
                'name' => 'Em branco 9:16',
                'description' => 'Projeto vazio para Shorts, Reels e TikTok.',
                'aspect_ratio' => '9:16',
                'slides' => [],
            ],
            [
                'slug' => 'corporate-3-slides',
                'name' => 'Apresentação corporativa',
                'description' => 'Três slides com estrutura intro → conteúdo → encerramento.',
                'aspect_ratio' => '16:9',
                'slides' => [
                    [
                        'title' => 'Introdução',
                        'subtitle' => 'Título da apresentação',
                        'body_text' => 'Contexto inicial do tema.',
                        'narration_text' => 'Olá! Nesta apresentação vamos abordar os pontos principais do nosso assunto.',
                        'duration_seconds' => 6,
                        'transition_type' => 'fade',
                    ],
                    [
                        'title' => 'Desenvolvimento',
                        'subtitle' => 'Ponto central',
                        'body_text' => 'Detalhe o conteúdo principal aqui.',
                        'narration_text' => 'O ponto central é apresentar a solução de forma clara e objetiva para o público.',
                        'duration_seconds' => 8,
                        'transition_type' => 'fade',
                    ],
                    [
                        'title' => 'Encerramento',
                        'subtitle' => 'Conclusão',
                        'body_text' => 'Resumo e chamada para ação.',
                        'narration_text' => 'Para concluir, reforçamos os benefícios e convidamos você a entrar em contato.',
                        'duration_seconds' => 5,
                        'transition_type' => 'fade',
                    ],
                ],
            ],
            [
                'slug' => 'shorts-hook',
                'name' => 'Short vertical (hook)',
                'description' => 'Dois slides verticais: gancho + CTA.',
                'aspect_ratio' => '9:16',
                'slides' => [
                    [
                        'title' => 'Você sabia?',
                        'subtitle' => 'Gancho inicial',
                        'body_text' => '',
                        'narration_text' => 'Você sabia que dá para criar vídeos narrados em minutos, direto no seu computador?',
                        'duration_seconds' => 4,
                        'transition_type' => 'cut',
                    ],
                    [
                        'title' => 'Experimente agora',
                        'subtitle' => 'Chamada para ação',
                        'body_text' => '',
                        'narration_text' => 'Monte seus slides, gere a narração e exporte para as redes sociais. Simples assim!',
                        'duration_seconds' => 5,
                        'transition_type' => 'fade',
                    ],
                ],
            ],
        ];

        foreach ($templates as $template) {
            ProjectTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template + ['is_active' => true]
            );
        }
    }
}
