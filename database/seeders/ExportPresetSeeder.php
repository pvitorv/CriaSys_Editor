<?php

namespace Database\Seeders;

use App\Models\ExportPreset;
use Illuminate\Database\Seeder;

class ExportPresetSeeder extends Seeder
{
    public function run(): void
    {
        $presets = [
            ['name' => 'YouTube Landscape', 'slug' => 'youtube_landscape', 'width' => 1920, 'height' => 1080, 'aspect_ratio' => '16:9', 'max_duration' => null, 'platform' => 'youtube'],
            ['name' => 'YouTube Shorts', 'slug' => 'youtube_shorts', 'width' => 1080, 'height' => 1920, 'aspect_ratio' => '9:16', 'max_duration' => 60, 'platform' => 'youtube'],
            ['name' => 'Instagram Reels', 'slug' => 'instagram_reels', 'width' => 1080, 'height' => 1920, 'aspect_ratio' => '9:16', 'max_duration' => 90, 'platform' => 'instagram'],
            ['name' => 'Instagram Stories', 'slug' => 'instagram_stories', 'width' => 1080, 'height' => 1920, 'aspect_ratio' => '9:16', 'max_duration' => 60, 'platform' => 'instagram'],
            ['name' => 'TikTok', 'slug' => 'tiktok', 'width' => 1080, 'height' => 1920, 'aspect_ratio' => '9:16', 'max_duration' => 180, 'platform' => 'tiktok'],
            ['name' => 'Instagram Feed', 'slug' => 'instagram_feed_square', 'width' => 1080, 'height' => 1080, 'aspect_ratio' => '1:1', 'max_duration' => null, 'platform' => 'instagram'],
            ['name' => 'Thumbnail', 'slug' => 'thumbnail', 'width' => 1280, 'height' => 720, 'aspect_ratio' => '16:9', 'max_duration' => null, 'platform' => 'youtube'],
        ];

        foreach ($presets as $preset) {
            ExportPreset::updateOrCreate(['slug' => $preset['slug']], $preset);
        }
    }
}
