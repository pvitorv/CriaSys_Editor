<?php

namespace App\Services\Export;

use App\Models\Project;

/**
 * Gera créditos + descrições por plataforma automaticamente (mídia importada pela biblioteca do app).
 */
class ProjectPublishAutoSyncService
{
    public function __construct(
        private ProjectCreditsClipboard $credits,
        private PlatformPostDescriptionService $descriptions,
        private AssetAttributionRepairService $repair,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sync(Project $project): array
    {
        $project->refresh();
        $repaired = $this->repair->repairProject($project);

        $lines = $this->credits->lines($project);
        $this->credits->syncFile($project);
        $savedPaths = $this->descriptions->saveToProject($project);
        $allDescriptions = $this->descriptions->generateAll($project);

        $files = [];
        foreach ($savedPaths as $key => $path) {
            $files[$key] = [
                'filename' => basename($path),
                'url' => route('api.projects.files', [
                    'project' => $project->id,
                    'type' => 'exports',
                    'filename' => basename($path),
                ]),
            ];
        }

        $settings = $project->settings ?? [];
        $settings['publish'] = [
            'auto' => count($lines) > 0,
            'synced_at' => now()->toIso8601String(),
            'materials_count' => count($lines),
        ];
        $project->update(['settings' => $settings]);

        return [
            'auto' => true,
            'materials_count' => count($lines),
            'credits_text' => $this->credits->asText($project),
            'credits_lines' => $lines,
            'descriptions' => $allDescriptions,
            'files' => $files,
            'message' => count($lines) > 0
                ? 'Créditos e descrições de publicação gerados automaticamente (já incluídos nos arquivos de exportação).'
                : ($repaired > 0
                    ? 'Créditos reparados — abra Exportar para ver as descrições atualizadas.'
                    : 'Descrições atualizadas. Importe mídia pela Biblioteca para incluir créditos automaticamente.'),
        ];
    }
}
