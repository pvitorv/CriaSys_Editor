<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('file_path');
            $table->string('file_hash')->nullable();
            $table->string('source')->nullable();
            $table->string('license_type')->nullable();
            $table->boolean('requires_attribution')->default(false);
            $table->text('attribution_text')->nullable();
            $table->string('original_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
