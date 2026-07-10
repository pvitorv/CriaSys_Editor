<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slides', function (Blueprint $table) {
            $table->string('duration_mode', 20)->default('narration')->after('duration_seconds');
            $table->float('video_duration_seconds')->nullable()->after('duration_mode');
        });
    }

    public function down(): void
    {
        Schema::table('slides', function (Blueprint $table) {
            $table->dropColumn(['duration_mode', 'video_duration_seconds']);
        });
    }
};
