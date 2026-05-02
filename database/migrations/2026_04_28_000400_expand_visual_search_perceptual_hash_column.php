<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visual_search_index_entries', function (Blueprint $table): void {
            $table->string('perceptual_hash', 256)->change();
        });
    }

    public function down(): void
    {
        Schema::table('visual_search_index_entries', function (Blueprint $table): void {
            $table->string('perceptual_hash', 64)->change();
        });
    }
};
