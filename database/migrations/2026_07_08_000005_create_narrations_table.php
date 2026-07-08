<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('narrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('engine')->default('edge');
            $table->string('voice')->nullable();
            $table->longText('full_script')->nullable();
            $table->string('audio_path')->nullable();
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->json('segments')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('narrations');
    }
};
