<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_tracks', function (Blueprint $table) {
            $table->boolean('loop_enabled')->default(true)->after('ducking_enabled');
            $table->json('clips')->nullable()->after('loop_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('audio_tracks', function (Blueprint $table) {
            $table->dropColumn(['loop_enabled', 'clips']);
        });
    }
};
