<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_tracks', function (Blueprint $table) {
            $table->unsignedTinyInteger('track_slot')->default(0)->after('type');
            $table->unique(['project_id', 'type', 'track_slot']);
        });

        Schema::create('sound_effects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path')->nullable();
            $table->decimal('start_at', 8, 2)->default(0);
            $table->decimal('volume', 3, 2)->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sound_effects');

        Schema::table('audio_tracks', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'type', 'track_slot']);
            $table->dropColumn('track_slot');
        });
    }
};
