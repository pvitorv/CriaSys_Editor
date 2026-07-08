<?php

namespace App\Enums;

enum ExportPresetType: string
{
    case YoutubeLandscape = 'youtube_landscape';
    case YoutubeShorts = 'youtube_shorts';
    case InstagramReels = 'instagram_reels';
    case InstagramStories = 'instagram_stories';
    case Tiktok = 'tiktok';
    case InstagramFeedSquare = 'instagram_feed_square';
    case Thumbnail = 'thumbnail';
}
