<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('body_text')->nullable();
            $table->string('image_path')->nullable();
            $table->json('text_style')->nullable();
            $table->decimal('duration_seconds', 8, 2)->default(5);
            $table->string('transition_type')->default('fade');
            $table->text('narration_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides');
    }
};
