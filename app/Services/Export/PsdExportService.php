<?php

namespace App\Services\Export;

use App\Models\ExportPreset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use App\Services\Render\SlideImageRenderer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use ZipArchive;

class PsdExportService
{
    public function __construct(
        private ProjectStorageService $storage,
        private SlideImageRenderer $renderer,
    ) {}

    /**
     * Exporta slides como PSD (ImageMagick) ou PNG+manifest em ZIP.
     */
    public function exportZip(Project $project, string $presetSlug = 'youtube_landscape'): string
    {
        $this->storage->ensureStructure($project);
        $project->load('slides');

        if ($project->slides->isEmpty()) {
            throw new \RuntimeException('Projeto sem slides para exportar.');
        }

        $preset = ExportPreset::where('slug', $presetSlug)->firstOrFail();
        $exportDir = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'exports';
        $workDir = $exportDir.DIRECTORY_SEPARATOR.'psd_'.now()->format('Ymd_His');
        File::ensureDirectoryExists($workDir);

        $manifest = ['slides' => [], 'format' => 'psd_or_png', 'preset' => $presetSlug];
        $magick = $this->resolveMagickBinary();

        foreach ($project->slides as $index => $slide) {
            $base = sprintf('slide_%03d', $index + 1);
            $pngPath = $workDir.DIRECTORY_SEPARATOR.$base.'.png';
            $this->renderer->render($slide, $preset, $pngPath);

            $psdPath = $workDir.DIRECTORY_SEPARATOR.$base.'.psd';
            $exportedAs = 'png';

            if ($magick && $this->convertPngToPsd($magick, $pngPath, $psdPath)) {
                File::delete($pngPath);
                $exportedAs = 'psd';
                $fileRef = $base.'.psd';
            } else {
                $fileRef = $base.'.png';
            }

            $manifest['slides'][] = [
                'order' => $slide->order,
                'file' => $fileRef,
                'title' => $slide->title,
                'type' => $exportedAs,
            ];
        }

        File::put($workDir.DIRECTORY_SEPARATOR.'layers.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        File::put(
            $workDir.DIRECTORY_SEPARATOR.'README_PSD.txt',
            "Export CriaSys Editor\n\n".
            "- Arquivos .psd: abrir no Photoshop/Affinity Photo\n".
            "- Arquivos .png: importar como camada se PSD não disponível\n".
            "- layers.json: metadados dos slides\n\n".
            "ImageMagick necessário para PSD nativo. Sem ele, PNGs são incluídos."
        );

        $zipPath = $exportDir.DIRECTORY_SEPARATOR.'slides_psd_'.now()->format('Ymd_His').'.zip';
        $this->createZip($workDir, $zipPath);
        File::deleteDirectory($workDir);

        return $zipPath;
    }

    private function resolveMagickBinary(): ?string
    {
        foreach (['magick', 'convert'] as $binary) {
            $result = Process::timeout(5)->run([$binary, '-version']);
            if ($result->successful()) {
                return $binary;
            }
        }

        return null;
    }

    private function convertPngToPsd(string $magick, string $pngPath, string $psdPath): bool
    {
        $args = $magick === 'magick'
            ? [$magick, 'convert', $pngPath, $psdPath]
            : [$magick, $pngPath, $psdPath];

        $result = Process::timeout(60)->run($args);

        return $result->successful() && file_exists($psdPath);
    }

    private function createZip(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar o arquivo ZIP.');
        }

        foreach (File::allFiles($sourceDir) as $file) {
            $relative = str_replace($sourceDir.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $zip->addFile($file->getPathname(), str_replace('\\', '/', $relative));
        }

        $zip->close();
    }
}
