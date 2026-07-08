<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('music');
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path')->nullable();
            $table->decimal('volume', 3, 2)->default(0.5);
            $table->decimal('start_at', 8, 2)->default(0);
            $table->boolean('ducking_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_tracks');
    }
};
