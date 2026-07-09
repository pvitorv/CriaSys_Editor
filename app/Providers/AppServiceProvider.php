<?php

namespace App\Providers;

use App\Models\Project;
use App\Policies\ProjectPolicy;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($storagePath = env('LARAVEL_STORAGE_PATH')) {
            $this->app->useStoragePath($storagePath);
        }
    }

    public function boot(): void
    {
        $this->configureWritableProcessTemp();

        Gate::policy(Project::class, ProjectPolicy::class);

        Route::bind('project', function (string $value) {
            $user = auth()->user();
            abort_unless($user, 401);

            return Project::where('user_id', $user->id)->findOrFail($value);
        });

        foreach ([config('criasys.projects_path'), config('criasys.exports_path')] as $path) {
            File::ensureDirectoryExists($path);
        }
    }

    /**
     * php artisan serve no Windows usa C:\WINDOWS como temp — Symfony Process não consegue gravar lá.
     */
    private function configureWritableProcessTemp(): void
    {
        $tmp = storage_path('framework/process-tmp');
        File::ensureDirectoryExists($tmp);

        foreach (['TMP', 'TEMP', 'TMPDIR'] as $key) {
            putenv("{$key}={$tmp}");
            $_ENV[$key] = $tmp;
            $_SERVER[$key] = $tmp;
        }
    }
}
