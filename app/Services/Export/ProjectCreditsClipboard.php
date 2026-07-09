<?php

namespace App\Services\Export;

use App\Models\Project;
use App\Services\ProjectStorageService;
use Illuminate\Support\Facades\File;

class ProjectCreditsClipboard
{
    public function __construct(
        private ProjectAttributionCatalog $catalog,
        private ProjectStorageService $storage,
    ) {}

    /**
     * Lista pronta para copiar/colar (uma linha por material da biblioteca).
     *
     * @return list<array{credit_line: string, type: string, source: string, used_in: list<string>}>
     */
    public function lines(Project $project): array
    {
        return collect($this->catalog->collect($project))
            ->map(fn (array $item) => [
                'credit_line' => $item['credit_line'],
                'type' => $item['type'],
                'source' => $item['source'],
                'used_in' => $item['used_in'],
            ])
            ->values()
            ->all();
    }

    public function asText(Project $project): string
    {
        $lines = $this->lines($project);

        if (empty($lines)) {
            return '';
        }

        return collect($lines)
            ->pluck('credit_line')
            ->unique()
            ->implode("\n");
    }

    /** Atualiza arquivo exports/creditos_copiar.txt após cada importação. */
    public function syncFile(Project $project): ?string
    {
        $text = $this->asText($project);
        if ($text === '') {
            return null;
        }

        $this->storage->ensureStructure($project);
        $path = $this->storage->projectPath($project).DIRECTORY_SEPARATOR.'exports'.DIRECTORY_SEPARATOR.'creditos_copiar.txt';
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $text."\n");

        return $path;
    }
}
