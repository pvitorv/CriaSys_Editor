<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Support\DeploymentMode;
use Illuminate\Validation\ValidationException;

class ProjectQuotaService
{
    /** Statuses that count toward the online active-project limit. */
    private const ACTIVE_STATUSES = ['active', 'exported'];

    public function canCreateProject(User $user): bool
    {
        $max = DeploymentMode::maxActiveProjects();
        if ($max === null) {
            return true;
        }

        return $this->activeProjectCount($user) < $max;
    }

    public function activeProjectCount(User $user): int
    {
        return $user->projects()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->count();
    }

    public function assertCanCreate(User $user): void
    {
        if ($this->canCreateProject($user)) {
            return;
        }

        throw ValidationException::withMessages([
            'name' => 'No modo online você pode ter apenas '
                .DeploymentMode::maxActiveProjects()
                .' projeto(s) ativo(s). Exporte e exclua o projeto atual antes de criar outro.',
        ]);
    }

    public function allowsDuplicate(): bool
    {
        return DeploymentMode::isDesktop();
    }

    public function assertCanDuplicate(User $user): void
    {
        if ($this->allowsDuplicate()) {
            return;
        }

        throw ValidationException::withMessages([
            'project' => 'Duplicar projetos está disponível apenas no app desktop.',
        ]);
    }

    public function isExported(Project $project): bool
    {
        if ($project->status === 'exported') {
            return true;
        }

        $settings = $project->settings ?? [];

        return ! empty($settings['exported_at']) || ! empty($settings['bundle_exported_at']);
    }

    public function markExported(Project $project): Project
    {
        $settings = $project->settings ?? [];
        $settings['exported_at'] = now()->toIso8601String();

        $project->update([
            'status' => 'exported',
            'settings' => $settings,
        ]);

        return $project->fresh();
    }

    public function deleteWarning(Project $project): ?string
    {
        if (DeploymentMode::isDesktop()) {
            return null;
        }

        if ($this->isExported($project)) {
            return null;
        }

        return 'Este projeto ainda não foi marcado como exportado. Baixe o kit ou bundle antes de excluir — os arquivos serão removidos do servidor.';
    }
}
