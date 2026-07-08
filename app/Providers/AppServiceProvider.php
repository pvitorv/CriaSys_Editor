<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ([config('criasys.projects_path'), config('criasys.exports_path')] as $path) {
            File::ensureDirectoryExists($path);
        }
    }
}
