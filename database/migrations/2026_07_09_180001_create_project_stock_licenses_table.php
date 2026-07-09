<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_stock_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // envato, storyblocks, artgrid, custom
            $table->string('project_title'); // nome do projeto na plataforma (ex.: Envato)
            $table->string('license_url')->nullable();
            $table->text('license_note')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('stock_license_id')->nullable()->after('project_id')
                ->constrained('project_stock_licenses')->nullOnDelete();
            $table->string('item_title')->nullable()->after('source');
            $table->string('item_external_id')->nullable()->after('item_title');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_license_id');
            $table->dropColumn(['item_title', 'item_external_id']);
        });

        Schema::dropIfExists('project_stock_licenses');
    }
};
