<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectStockLicense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectStockLicenseController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $providers = config('criasys.stock_providers', []);

        return response()->json([
            'registrations' => $project->stockLicenses()->latest()->get(),
            'providers' => collect($providers)->map(fn ($meta, $slug) => array_merge($meta, ['slug' => $slug])),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $providers = array_keys(config('criasys.stock_providers', []));

        $data = $request->validate([
            'provider' => ['required', 'string', 'in:'.implode(',', $providers)],
            'project_title' => ['required', 'string', 'max:255'],
            'license_url' => ['nullable', 'url', 'max:500'],
            'license_note' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($data['is_default'] ?? false) {
            $project->stockLicenses()->update(['is_default' => false]);
        }

        $registration = $project->stockLicenses()->create([
            'provider' => $data['provider'],
            'project_title' => $data['project_title'],
            'license_url' => $data['license_url'] ?? null,
            'license_note' => $data['license_note'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? ! $project->stockLicenses()->exists()),
        ]);

        app(\App\Services\Export\ProjectPublishAutoSyncService::class)->sync($project);

        return response()->json($registration, 201);
    }

    public function update(Request $request, Project $project, ProjectStockLicense $stockLicense): JsonResponse
    {
        abort_unless($stockLicense->project_id === $project->id, 404);

        $data = $request->validate([
            'project_title' => ['sometimes', 'string', 'max:255'],
            'license_url' => ['nullable', 'url', 'max:500'],
            'license_note' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($data['is_default'] ?? false) {
            $project->stockLicenses()->where('id', '!=', $stockLicense->id)->update(['is_default' => false]);
        }

        $stockLicense->update($data);
        app(\App\Services\Export\ProjectPublishAutoSyncService::class)->sync($project);

        return response()->json($stockLicense->fresh());
    }

    public function destroy(Project $project, ProjectStockLicense $stockLicense): JsonResponse
    {
        abort_unless($stockLicense->project_id === $project->id, 404);

        $stockLicense->delete();
        app(\App\Services\Export\ProjectPublishAutoSyncService::class)->sync($project);

        return response()->json(['message' => 'Registro de licença removido.']);
    }

    /** Vincula uploads locais (Envato baixados manualmente) à licença cadastrada. */
    public function applyToLocalAssets(Project $project, ProjectStockLicense $stockLicense): JsonResponse
    {
        abort_unless($stockLicense->project_id === $project->id, 404);

        $project->load('slides');
        $usedPaths = collect($project->slides)->flatMap(fn ($s) => array_filter([$s->image_path, $s->video_path]))
            ->map(fn ($p) => str_replace('\\', '/', strtolower($p)))->all();

        $count = 0;
        foreach ($project->assets()->whereNull('stock_license_id')->get() as $asset) {
            $path = str_replace('\\', '/', strtolower($asset->file_path));
            if (! in_array($path, $usedPaths, true)) {
                continue;
            }

            $attr = \App\Services\MediaLibrary\MediaAttribution::forPaidSubscription($stockLicense, $asset);
            $asset->update([
                'stock_license_id' => $stockLicense->id,
                'source' => $stockLicense->provider,
                'license_type' => match ($stockLicense->provider) {
                    'envato' => \App\Enums\LicenseType::Envato->value,
                    'storyblocks' => \App\Enums\LicenseType::Storyblocks->value,
                    'artgrid' => \App\Enums\LicenseType::Artgrid->value,
                    default => \App\Enums\LicenseType::CustomLicensed->value,
                },
                'attribution_text' => $attr['attribution_text'],
                'requires_attribution' => true,
            ]);
            $count++;
        }

        app(\App\Services\Export\ProjectPublishAutoSyncService::class)->sync($project);

        return response()->json([
            'message' => "{$count} arquivo(s) vinculado(s) à licença {$stockLicense->providerLabel()}.",
            'updated' => $count,
        ]);
    }
}
