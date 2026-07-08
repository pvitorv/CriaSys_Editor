<?php

namespace App\Jobs;

use App\Enums\RenderStatus;
use App\Models\ExportPackage;
use App\Services\Export\ProjectPackageExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExportPackageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $exportPackageId,
        public string $presetSlug = 'youtube_landscape',
    ) {}

    public function handle(ProjectPackageExporter $exporter): void
    {
        $package = ExportPackage::with('project.slides')->findOrFail($this->exportPackageId);
        $package->update(['status' => 'processing']);

        try {
            $path = $exporter->export($package->project, $this->presetSlug);
            $package->update([
                'status' => 'completed',
                'package_path' => $path,
            ]);
        } catch (\Throwable $e) {
            Log::error('ExportPackageJob failed', ['id' => $package->id, 'error' => $e->getMessage()]);
            $package->update(['status' => 'failed']);
            throw $e;
        }
    }
}
