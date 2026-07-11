<?php

namespace App\Services\Export;

use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Support\Facades\File;
use ZipArchive;

class PublishKitExporter
{
    public function __construct(
        private ProjectStorageService $storage,
        private PlatformPostDescriptionService $descriptions,
    ) {}

    /**
     * @return array{path: string, url: string, filename: string}
     */
    public function export(Project $project): array
    {
        $project->load(['slides', 'renderJobs']);

        $exportDir = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'exports';
        File::ensureDirectoryExists($exportDir);

        $descriptionPaths = $this->descriptions->saveToProject($project);
        $checklistPath = $this->writeChecklist($project, $exportDir);
        $readmePath = $this->writePublishReadme($project, $exportDir);

        $timestamp = now()->format('Ymd_His');
        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $project->name) ?: 'projeto';
        $kitName = "publish_kit_{$safeName}_{$timestamp}";
        $kitDir = $exportDir.DIRECTORY_SEPARATOR.$kitName;
        File::ensureDirectoryExists($kitDir);
        File::ensureDirectoryExists($kitDir.DIRECTORY_SEPARATOR.'descricoes');
        File::ensureDirectoryExists($kitDir.DIRECTORY_SEPARATOR.'videos');

        foreach ($descriptionPaths as $key => $path) {
            if (! File::exists($path)) {
                continue;
            }
            $destName = $key === 'creditos' ? 'creditos_materiais.txt' : basename($path);
            File::copy($path, $kitDir.DIRECTORY_SEPARATOR.'descricoes'.DIRECTORY_SEPARATOR.$destName);
        }

        File::copy($checklistPath, $kitDir.DIRECTORY_SEPARATOR.'CHECKLIST.md');
        File::copy($readmePath, $kitDir.DIRECTORY_SEPARATOR.'LEIA-ME.txt');

        foreach ($project->renderJobs()->where('status', 'completed')->latest()->get() as $job) {
            if ($job->output_path && File::exists($job->output_path)) {
                File::copy($job->output_path, $kitDir.DIRECTORY_SEPARATOR.'videos'.DIRECTORY_SEPARATOR.basename($job->output_path));
            }
        }

        $thumb = $this->storage->thumbPath($project);
        if (File::exists($thumb)) {
            File::copy($thumb, $kitDir.DIRECTORY_SEPARATOR.'thumbnail.jpg');
        }

        $zipPath = $exportDir.DIRECTORY_SEPARATOR."{$kitName}.zip";
        $this->createZip($kitDir, $zipPath);
        File::deleteDirectory($kitDir);

        return [
            'path' => $zipPath,
            'filename' => basename($zipPath),
            'url' => route('api.projects.files', [
                'project' => $project->id,
                'type' => 'exports',
                'filename' => basename($zipPath),
            ]),
        ];
    }

    private function writeChecklist(Project $project, string $exportDir): string
    {
        $platforms = config('publish_platforms', []);
        $lines = [
            '# Checklist de publicação — '.$project->name,
            '',
            'Gerado em '.now()->format('d/m/Y H:i').' pelo CriaSys Editor.',
            '',
            '## Antes de publicar',
            '',
            '- [ ] Vídeo renderizado e revisado na timeline',
            '- [ ] Legendas conferidas (CC na timeline)',
            '- [ ] Créditos de mídia verificados',
            '- [ ] Descrições editadas se necessário',
            '',
        ];

        foreach ($platforms as $slug => $meta) {
            $lines[] = '## '.$meta['name'];
            $lines[] = '';
            foreach ($meta['checklist'] as $step) {
                $lines[] = '- [ ] '.$step;
            }
            $file = $meta['description_file'] ?? ('descricao_'.$slug.'.txt');
            $lines[] = '- [ ] Arquivo: `descricoes/'.$file.'`';
            $lines[] = '';
        }

        $lines[] = '## Bundle completo';
        $lines[] = '';
        $lines[] = '- [ ] Exportar **Bundle completo (ZIP)** para backup ou importar em outro ambiente';
        $lines[] = '';

        $path = $exportDir.DIRECTORY_SEPARATOR.'CHECKLIST.md';
        File::put($path, implode("\n", $lines));

        return $path;
    }

    private function writePublishReadme(Project $project, string $exportDir): string
    {
        $content = implode("\n", [
            'CriaSys Editor — Publish Kit',
            '============================',
            '',
            'Projeto: '.$project->name,
            '',
            'Pastas:',
            '  descricoes/ — textos prontos por plataforma + créditos',
            '  videos/     — renders MP4 concluídos',
            '  CHECKLIST.md — passo a passo de publicação',
            '',
            'Edite as descrições no editor antes de gerar o kit se quiser personalizar.',
            '',
        ]);

        $path = $exportDir.DIRECTORY_SEPARATOR.'LEIA-ME_PUBLISH.txt';
        File::put($path, $content);

        return $path;
    }

    private function createZip(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar o ZIP do Publish Kit.');
        }

        foreach (File::allFiles($sourceDir) as $file) {
            $relative = str_replace($sourceDir.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $zip->addFile($file->getPathname(), str_replace('\\', '/', $relative));
        }

        $zip->close();
    }
}
