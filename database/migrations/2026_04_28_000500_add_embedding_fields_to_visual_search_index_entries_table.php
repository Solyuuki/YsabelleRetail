<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visual_search_index_entries', function (Blueprint $table): void {
            $table->string('image_path', 2048)->nullable()->after('image_url');
            $table->json('embedding_vector')->nullable()->after('height');
            $table->json('embedding_crops')->nullable()->after('embedding_vector');
            $table->string('embedding_model', 191)->nullable()->after('embedding_crops');
            $table->string('embedding_version', 64)->nullable()->after('embedding_model');
            $table->string('index_version_key', 191)->nullable()->after('embedding_version');
            $table->decimal('shoe_confidence', 8, 6)->nullable()->after('index_version_key');
            $table->decimal('blur_score', 10, 6)->nullable()->after('shoe_confidence');
            $table->timestamp('embedding_generated_at')->nullable()->after('blur_score');

            $table->index(['embedding_model', 'embedding_version'], 'visual_search_embedding_model_version_index');
        });
    }

    public function down(): void
    {
        Schema::table('visual_search_index_entries', function (Blueprint $table): void {
            $table->dropIndex('visual_search_embedding_model_version_index');
            $table->dropColumn([
                'image_path',
                'embedding_vector',
                'embedding_crops',
                'embedding_model',
                'embedding_version',
                'index_version_key',
                'shoe_confidence',
                'blur_score',
                'embedding_generated_at',
            ]);
        });
    }
};
