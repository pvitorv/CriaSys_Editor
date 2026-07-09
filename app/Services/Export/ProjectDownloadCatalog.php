<?php

namespace App\Services\Export;

use App\Models\ExportPreset;
use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Support\Facades\File;

class ProjectDownloadCatalog
{
    public function __construct(private ProjectStorageService $storage) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(Project $project): array
    {
        $this->storage->ensureStructure($project);
        $project->load(['renderJobs', 'exportPackages', 'narrations']);

        $items = [];
        $seen = [];

        foreach ($project->renderJobs()->latest()->get() as $job) {
            $filename = $job->output_path ? basename($job->output_path) : null;
            $status = $job->status instanceof \BackedEnum ? $job->status->value : (string) $job->status;
            $ready = $status === 'completed' && $filename && file_exists($job->output_path);

            $items[] = [
                'id' => 'render-'.$job->id,
                'category' => 'video',
                'label' => 'Vídeo render — '.$job->preset,
                'format' => 'MP4',
                'status' => $ready ? 'ready' : $status,
                'progress' => (int) $job->progress,
                'filename' => $filename,
                'url' => $ready ? $this->fileUrl($project, 'exports', $filename) : null,
                'size' => $ready ? filesize($job->output_path) : null,
                'created_at' => $job->created_at?->toIso8601String(),
            ];
            if ($filename) {
                $seen[strtolower($filename)] = true;
            }
        }

        foreach ($project->exportPackages()->latest()->get() as $package) {
            $filename = $package->package_path ? basename($package->package_path) : null;
            $ready = $package->status === 'completed' && $filename && file_exists($package->package_path);

            $items[] = [
                'id' => 'package-'.$package->id,
                'category' => 'pacote',
                'label' => 'Pacote Premiere/Affinity #'.$package->id,
                'format' => 'ZIP',
                'status' => $ready ? 'ready' : $package->status,
                'progress' => $ready ? 100 : ($package->status === 'processing' ? 50 : 0),
                'filename' => $filename,
                'url' => $ready ? $this->fileUrl($project, 'exports', $filename) : null,
                'size' => $ready ? filesize($package->package_path) : null,
                'created_at' => $package->created_at?->toIso8601String(),
            ];
            if ($filename) {
                $seen[strtolower($filename)] = true;
            }
        }

        $narration = $project->latestNarration();
        if ($narration?->audio_path && file_exists($narration->audio_path)) {
            $filename = basename($narration->audio_path);
            $items[] = [
                'id' => 'narration-'.$narration->id,
                'category' => 'audio',
                'label' => 'Narração completa ('.($narration->engine ?? 'tts').')',
                'format' => strtoupper(pathinfo($filename, PATHINFO_EXTENSION) ?: 'MP3'),
                'status' => 'ready',
                'progress' => 100,
                'filename' => $filename,
                'url' => $this->fileUrl($project, 'audio', $filename),
                'size' => filesize($narration->audio_path),
                'created_at' => $narration->created_at?->toIso8601String(),
            ];
        }

        $thumbPath = $this->storage->thumbPath($project);
        if (file_exists($thumbPath)) {
            $filename = basename($thumbPath);
            $items[] = [
                'id' => 'thumb',
                'category' => 'imagem',
                'label' => 'Thumbnail do projeto',
                'format' => 'JPG',
                'status' => 'ready',
                'progress' => 100,
                'filename' => $filename,
                'url' => $this->fileUrl($project, 'thumbs', $filename),
                'size' => filesize($thumbPath),
                'created_at' => null,
            ];
        }

        $exportDir = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'exports';
        if (File::isDirectory($exportDir)) {
            foreach (File::files($exportDir) as $file) {
                $filename = $file->getFilename();
                $key = strtolower($filename);
                if (isset($seen[$key])) {
                    continue;
                }

                $ext = strtolower($file->getExtension());
                if (! in_array($ext, ['mp4', 'zip', 'srt', 'wav', 'mp3'], true)) {
                    continue;
                }

                $format = strtoupper($ext);
                $category = match ($ext) {
                    'mp4' => 'video',
                    'zip' => 'pacote',
                    'srt' => 'legenda',
                    default => 'audio',
                };

                $label = match (true) {
                    str_starts_with($filename, 'legendas') => 'Legendas SRT',
                    str_starts_with($filename, 'slides_psd_') => 'Slides PSD/PNG',
                    str_starts_with($filename, 'pacote_premiere_') => 'Pacote Premiere',
                    str_starts_with($filename, 'render_') => 'Vídeo render',
                    default => $filename,
                };

                $items[] = [
                    'id' => 'file-'.md5($filename),
                    'category' => $category,
                    'label' => $label,
                    'format' => $format,
                    'status' => 'ready',
                    'progress' => 100,
                    'filename' => $filename,
                    'url' => $this->fileUrl($project, 'exports', $filename),
                    'size' => $file->getSize(),
                    'created_at' => date('c', $file->getMTime()),
                ];
                $seen[$key] = true;
            }
        }

        return $items;
    }

    private function fileUrl(Project $project, string $type, string $filename): string
    {
        return '/api/projects/'.$project->id.'/files/'.$type.'/'.rawurlencode($filename);
    }
}
