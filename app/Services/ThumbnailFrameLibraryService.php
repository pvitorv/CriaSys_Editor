<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ThumbnailFrameLibraryService
{
    public function libraryPath(User $user): string
    {
        $dir = storage_path('app/criasys/users/'.$user->id);
        File::ensureDirectoryExists($dir);

        return $dir.DIRECTORY_SEPARATOR.'frame_library.json';
    }

    public function framesDir(User $user): string
    {
        $dir = storage_path('app/criasys/users/'.$user->id.DIRECTORY_SEPARATOR.'frames');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    public function load(User $user): array
    {
        $path = $this->libraryPath($user);
        if (! File::exists($path)) {
            return $this->defaultLibrary();
        }

        $data = json_decode(File::get($path), true);

        return is_array($data) ? array_merge($this->defaultLibrary(), $data) : $this->defaultLibrary();
    }

    public function save(User $user, array $library): void
    {
        File::put(
            $this->libraryPath($user),
            json_encode($library, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public function catalogForUser(?User $user): array
    {
        $systemCategories = config('thumbnail_frames.categories', []);
        $systemFrames = config('thumbnail_frames.frames', []);
        $library = $user ? $this->load($user) : $this->defaultLibrary();

        $hiddenFrames = array_flip($library['hidden_frames'] ?? []);
        $hiddenCategories = array_flip($library['hidden_categories'] ?? []);

        $categories = $systemCategories;
        foreach ($library['custom_categories'] ?? [] as $slug => $meta) {
            $categories[$slug] = $meta['label'] ?? $slug;
        }
        $categories['personalizado'] = $categories['personalizado'] ?? 'Minhas molduras';

        $frames = [];
        foreach ($systemFrames as $slug => $meta) {
            if ($slug === 'none' || isset($hiddenFrames[$slug])) {
                continue;
            }
            if (isset($hiddenCategories[$meta['category'] ?? 'basico'])) {
                continue;
            }
            $frames[] = $this->mapFrame($slug, $meta, $categories, false);
        }

        foreach ($library['custom_frames'] ?? [] as $slug => $meta) {
            if (isset($hiddenFrames[$slug])) {
                continue;
            }
            $frames[] = $this->mapFrame($slug, $meta, $categories, true, $user);
        }

        usort($frames, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $visibleCategories = [];
        foreach ($categories as $slug => $label) {
            if (isset($hiddenCategories[$slug])) {
                continue;
            }
            $hasFrames = collect($frames)->contains(fn ($f) => $f['category'] === $slug);
            if ($hasFrames || str_starts_with($slug, 'custom_cat_') || $slug === 'personalizado') {
                $visibleCategories[$slug] = $label;
            }
        }

        return [
            'frames' => $frames,
            'categories' => $visibleCategories,
            'all_categories' => $categories,
            'library' => [
                'hidden_frames' => $library['hidden_frames'] ?? [],
                'hidden_categories' => $library['hidden_categories'] ?? [],
                'custom_categories' => $library['custom_categories'] ?? [],
                'custom_frame_count' => count($library['custom_frames'] ?? []),
            ],
        ];
    }

    public function resolveFrameMeta(?User $user, string $slug): ?array
    {
        if ($slug === '' || $slug === 'none') {
            return null;
        }

        $system = config('thumbnail_frames.frames.'.$slug);
        if ($system) {
            if ($user) {
                $library = $this->load($user);
                if (in_array($slug, $library['hidden_frames'] ?? [], true)) {
                    return null;
                }
                $cat = $system['category'] ?? 'basico';
                if (in_array($cat, $library['hidden_categories'] ?? [], true)) {
                    return null;
                }
            }

            return $system;
        }

        if (! $user) {
            return null;
        }

        $library = $this->load($user);
        $custom = $library['custom_frames'][$slug] ?? null;
        if (! $custom || in_array($slug, $library['hidden_frames'] ?? [], true)) {
            return null;
        }

        return $custom;
    }

    public function hideFrame(User $user, string $slug): void
    {
        if ($slug === 'none') {
            return;
        }

        $library = $this->load($user);

        if (isset($library['custom_frames'][$slug])) {
            $this->deleteCustomFrameFiles($library['custom_frames'][$slug]);
            unset($library['custom_frames'][$slug]);
        } elseif (! in_array($slug, $library['hidden_frames'] ?? [], true)) {
            $library['hidden_frames'][] = $slug;
        }

        $this->save($user, $library);
    }

    public function restoreFrame(User $user, string $slug): void
    {
        $library = $this->load($user);
        $library['hidden_frames'] = array_values(array_filter(
            $library['hidden_frames'] ?? [],
            fn ($s) => $s !== $slug
        ));
        $this->save($user, $library);
    }

    public function hideCategory(User $user, string $categorySlug): void
    {
        if (in_array($categorySlug, ['all', 'basico', 'personalizado'], true)) {
            throw new \InvalidArgumentException('Esta categoria não pode ser removida.');
        }

        $library = $this->load($user);

        if (isset($library['custom_categories'][$categorySlug])) {
            foreach ($library['custom_frames'] ?? [] as $slug => $frame) {
                if (($frame['category'] ?? '') === $categorySlug) {
                    $this->deleteCustomFrameFiles($frame);
                    unset($library['custom_frames'][$slug]);
                }
            }
            unset($library['custom_categories'][$categorySlug]);
        } elseif (! in_array($categorySlug, $library['hidden_categories'] ?? [], true)) {
            $library['hidden_categories'][] = $categorySlug;
        }

        $this->save($user, $library);
    }

    public function restoreCategory(User $user, string $categorySlug): void
    {
        $library = $this->load($user);
        $library['hidden_categories'] = array_values(array_filter(
            $library['hidden_categories'] ?? [],
            fn ($s) => $s !== $categorySlug
        ));
        $this->save($user, $library);
    }

    public function createCategory(User $user, string $label): string
    {
        $library = $this->load($user);
        $slug = 'custom_cat_'.Str::lower(Str::random(8));
        $library['custom_categories'][$slug] = [
            'label' => trim($label),
            'created_at' => now()->toIso8601String(),
        ];
        $this->save($user, $library);

        return $slug;
    }

    public function createOverlayFrame(User $user, UploadedFile $file, string $name, ?string $categorySlug = null, ?string $description = null): array
    {
        $library = $this->load($user);
        $category = $categorySlug ?: 'personalizado';

        if ($category !== 'personalizado' && ! isset($library['custom_categories'][$category]) && ! config('thumbnail_frames.categories.'.$category)) {
            $category = 'personalizado';
        }

        $slug = 'custom_'.Str::lower(Str::random(10));
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        if (! in_array($ext, ['png', 'webp', 'jpg', 'jpeg'], true)) {
            $ext = 'png';
        }

        $filename = $slug.'.'.$ext;
        $path = $this->framesDir($user).DIRECTORY_SEPARATOR.$filename;
        $file->move(dirname($path), basename($path));

        $meta = [
            'name' => trim($name) ?: 'Minha moldura',
            'description' => $description ?? 'Moldura personalizada',
            'category' => $category,
            'type' => 'overlay_image',
            'style' => 'overlay_image',
            'image_path' => $path,
            'image_file' => $filename,
            'created_at' => now()->toIso8601String(),
        ];

        $library['custom_frames'][$slug] = $meta;
        $this->save($user, $library);

        return ['slug' => $slug, 'meta' => $meta];
    }

    public function createProceduralFrame(User $user, array $data): array
    {
        $library = $this->load($user);
        $slug = 'custom_'.Str::lower(Str::random(10));
        $category = $data['category'] ?? 'personalizado';

        $meta = [
            'name' => trim($data['name'] ?? 'Moldura custom'),
            'description' => $data['description'] ?? '',
            'category' => $category,
            'type' => 'procedural',
            'style' => $data['style'] ?? 'solid',
            'default_color' => $data['default_color'] ?? '#ffffff',
            'weight' => (float) ($data['weight'] ?? 1),
            'created_at' => now()->toIso8601String(),
        ];

        $library['custom_frames'][$slug] = $meta;
        $this->save($user, $library);

        return ['slug' => $slug, 'meta' => $meta];
    }

    public function frameImagePath(User $user, string $filename): ?string
    {
        $path = $this->framesDir($user).DIRECTORY_SEPARATOR.basename($filename);
        if (! File::exists($path)) {
            return null;
        }

        return $path;
    }

    private function defaultLibrary(): array
    {
        return [
            'version' => 1,
            'hidden_frames' => [],
            'hidden_categories' => [],
            'custom_categories' => [],
            'custom_frames' => [],
        ];
    }

    private function mapFrame(string $slug, array $meta, array $categories, bool $isCustom, ?User $user = null): array
    {
        $category = $meta['category'] ?? 'basico';
        $type = $meta['type'] ?? 'procedural';

        $item = [
            'slug' => $slug,
            'name' => $meta['name'] ?? $slug,
            'description' => $meta['description'] ?? '',
            'category' => $category,
            'category_label' => $categories[$category] ?? $category,
            'style' => $meta['style'] ?? 'solid',
            'default_color' => $meta['default_color'] ?? null,
            'creator' => $meta['creator'] ?? null,
            'is_custom' => $isCustom,
            'type' => $type,
            'can_delete' => $slug !== 'none',
        ];

        if ($isCustom && $type === 'overlay_image' && $user && ! empty($meta['image_file'])) {
            $item['preview_url'] = url('/api/thumbnail/frames/file/'.$meta['image_file']);
        }

        return $item;
    }

    private function deleteCustomFrameFiles(array $meta): void
    {
        $path = $meta['image_path'] ?? null;
        if ($path && File::exists($path)) {
            File::delete($path);
        }
    }
}
