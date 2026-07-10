<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_tracks', function (Blueprint $table) {
            $table->decimal('trim_in', 8, 2)->default(0)->after('start_at');
            $table->decimal('trim_out', 8, 2)->nullable()->after('trim_in');
            $table->decimal('source_duration', 8, 2)->nullable()->after('trim_out');
        });

        Schema::table('sound_effects', function (Blueprint $table) {
            $table->decimal('trim_in', 8, 2)->default(0)->after('start_at');
            $table->decimal('trim_out', 8, 2)->nullable()->after('trim_in');
            $table->decimal('source_duration', 8, 2)->nullable()->after('trim_out');
            $table->decimal('clip_duration', 8, 2)->nullable()->after('source_duration');
        });

        Schema::table('narrations', function (Blueprint $table) {
            $table->decimal('trim_in', 8, 2)->default(0)->after('duration_seconds');
            $table->decimal('trim_out', 8, 2)->nullable()->after('trim_in');
        });
    }

    public function down(): void
    {
        Schema::table('narrations', function (Blueprint $table) {
            $table->dropColumn(['trim_in', 'trim_out']);
        });

        Schema::table('sound_effects', function (Blueprint $table) {
            $table->dropColumn(['trim_in', 'trim_out', 'source_duration', 'clip_duration']);
        });

        Schema::table('audio_tracks', function (Blueprint $table) {
            $table->dropColumn(['trim_in', 'trim_out', 'source_duration']);
        });
    }
};
