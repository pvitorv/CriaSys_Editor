<?php

namespace App\Services\Export;

use App\Models\ExportPreset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use App\Services\Render\SlideImageRenderer;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ProjectPackageExporter
{
    public function __construct(
        private ProjectStorageService $storage,
        private SlideImageRenderer $renderer,
        private SrtGenerator $srtGenerator,
        private PremiereXmlGenerator $premiereXml,
    ) {}

    public function export(Project $project, string $presetSlug = 'youtube_landscape'): string
    {
        $this->storage->ensureStructure($project);
        $project->load(['slides', 'assets', 'audioTracks']);

        $preset = ExportPreset::where('slug', $presetSlug)->firstOrFail();
        $timestamp = now()->format('Ymd_His');
        $exportDir = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'exports';
        File::ensureDirectoryExists($exportDir);
        $packageName = "pacote_{$timestamp}";
        $basePath = $exportDir.DIRECTORY_SEPARATOR.$packageName;

        $dirs = ['slides', 'audio'];
        foreach ($dirs as $dir) {
            File::ensureDirectoryExists($basePath.DIRECTORY_SEPARATOR.$dir);
        }

        $timelineSlides = [];
        $totalDuration = 0;

        foreach ($project->slides as $index => $slide) {
            $filename = sprintf('%03d.png', $index + 1);
            $path = $basePath.DIRECTORY_SEPARATOR.'slides'.DIRECTORY_SEPARATOR.$filename;
            $this->renderer->render($slide, $preset, $path);

            $duration = (float) $slide->duration_seconds;
            $totalDuration += $duration;

            $timelineSlides[] = [
                'order' => $slide->order,
                'file' => 'slides/'.$filename,
                'duration_seconds' => $duration,
                'transition' => $slide->transition_type,
                'title' => $slide->title,
            ];
        }

        $narration = $project->latestNarration();
        $narrationDest = null;
        $musicDest = null;

        if ($narration?->audio_path && file_exists($narration->audio_path)) {
            $narrationDest = $basePath.DIRECTORY_SEPARATOR.'audio'.DIRECTORY_SEPARATOR.'narracao.wav';
            $this->convertToWav($narration->audio_path, $narrationDest);
        }

        $musicTrack = $project->audioTracks()->where('type', 'music')->first();
        if ($musicTrack?->file_path && file_exists($musicTrack->file_path)) {
            $musicDest = $basePath.DIRECTORY_SEPARATOR.'audio'.DIRECTORY_SEPARATOR.'trilha.mp3';
            File::copy($musicTrack->file_path, $musicDest);
        }

        $srtContent = $this->srtGenerator->generate($project, $narration);
        File::put($basePath.DIRECTORY_SEPARATOR.'legendas.srt', $srtContent);

        $thumbSource = $this->storage->thumbPath($project);
        if (file_exists($thumbSource)) {
            File::copy($thumbSource, $basePath.DIRECTORY_SEPARATOR.'thumbnail.jpg');
        } else {
            $firstSlide = $project->slides->first();
            if ($firstSlide) {
                $thumbPreset = ExportPreset::where('slug', 'thumbnail')->first() ?? $preset;
                $this->renderer->render($firstSlide, $thumbPreset, $basePath.DIRECTORY_SEPARATOR.'thumbnail.jpg');
            }
        }

        $timeline = [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'preset' => $presetSlug,
            'width' => $preset->width,
            'height' => $preset->height,
            'total_duration' => $totalDuration,
            'slides' => $timelineSlides,
            'audio' => [
                'narracao' => $narrationDest ? 'audio/narracao.wav' : null,
                'trilha' => $musicDest ? 'audio/trilha.mp3' : null,
            ],
        ];

        File::put(
            $basePath.DIRECTORY_SEPARATOR.'timeline.json',
            json_encode($timeline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        File::put(
            $basePath.DIRECTORY_SEPARATOR.'premiere.xml',
            $this->premiereXml->generate($project, $timeline, $preset->width, $preset->height)
        );

        File::put($basePath.DIRECTORY_SEPARATOR.'credits.txt', $this->buildCredits($project));

        $descDir = $basePath.DIRECTORY_SEPARATOR.'descricoes';
        File::ensureDirectoryExists($descDir);
        $descService = app(PlatformPostDescriptionService::class);
        foreach ($descService->generateAll($project) as $key => $data) {
            File::put($descDir.DIRECTORY_SEPARATOR.$key.'.txt', $data['description']);
        }
        File::put(
            $descDir.DIRECTORY_SEPARATOR.'creditos_materiais.txt',
            app(ProjectAttributionCatalog::class)->creditsBlock($project, "\n")
        );

        File::put($basePath.DIRECTORY_SEPARATOR.'README.txt', $this->buildReadme($project->name));

        $zipPath = $exportDir.DIRECTORY_SEPARATOR."pacote_premiere_{$timestamp}.zip";
        $this->createZip($basePath, $zipPath);
        File::deleteDirectory($basePath);

        return $zipPath;
    }

    private function createZip(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar o arquivo ZIP do pacote.');
        }

        foreach (File::allFiles($sourceDir) as $file) {
            $relative = str_replace($sourceDir.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $zip->addFile($file->getPathname(), str_replace('\\', '/', $relative));
        }

        $zip->close();
    }

    private function convertToWav(string $source, string $dest): void
    {
        $ffmpeg = config('criasys.ffmpeg_path');
        $result = \Illuminate\Support\Facades\Process::timeout(120)->run([
            $ffmpeg, '-y', '-i', $source, '-acodec', 'pcm_s16le', '-ar', '44100', $dest,
        ]);

        if (! $result->successful()) {
            File::copy($source, $dest);
        }
    }

    private function buildCredits(Project $project): string
    {
        $catalog = app(ProjectAttributionCatalog::class);
        $items = $catalog->collect($project);

        if (empty($items)) {
            return "Nenhuma atribuição obrigatória para este projeto.\n";
        }

        $lines = ["CRÉDITOS — {$project->name}", str_repeat('=', 40), ''];

        foreach ($items as $item) {
            $lines[] = '- '.$item['credit_line'];
            if (! empty($item['used_in'])) {
                $lines[] = '  Onde: '.implode(', ', $item['used_in']);
            }
            $lines[] = '';
        }

        $lines[] = 'Descrições prontas por plataforma: pasta descricoes/';

        return implode("\n", $lines);
    }

    private function buildReadme(string $projectName): string
    {
        return <<<TXT
PACOTE DE EXPORT — {$projectName}
================================

Conteúdo:
- slides/       Sequência PNG (001.png, 002.png, ...)
- audio/        Narração (WAV) e trilha (MP3)
- legendas.srt  Legendas sincronizadas
- timeline.json Metadados de duração e transições
- premiere.xml  Importação no Premiere Pro (FCP7 XML)
- credits.txt   Atribuições de assets
- descricoes/   Textos prontos para YouTube, TikTok, Instagram (com créditos)
- thumbnail.jpg Miniatura do projeto

Premiere Pro:
1. Importe premiere.xml ou arraste a pasta slides/
2. Importe audio/narracao.wav e audio/trilha.mp3
3. Importe legendas.srt via Legendas > Importar

Affinity / Photoshop:
- Use os PNGs em slides/ como sequência de imagens
TXT;
    }
}
