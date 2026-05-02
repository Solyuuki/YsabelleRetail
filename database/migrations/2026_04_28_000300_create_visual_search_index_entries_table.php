<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visual_search_index_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('image_url', 2048);
            $table->string('image_url_hash', 64);
            $table->string('image_role', 32)->default('primary');
            $table->string('feature_version', 16)->default('v1');
            $table->string('source_checksum', 64)->nullable();
            $table->string('perceptual_hash', 256);
            $table->json('color_histogram');
            $table->json('shape_profile_x');
            $table->json('shape_profile_y');
            $table->json('dominant_colors')->nullable();
            $table->decimal('mean_red', 6, 5);
            $table->decimal('mean_green', 6, 5);
            $table->decimal('mean_blue', 6, 5);
            $table->decimal('edge_density', 8, 6);
            $table->decimal('foreground_ratio', 8, 6);
            $table->decimal('aspect_ratio', 8, 6);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'image_url_hash']);
            $table->index(['product_id', 'feature_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visual_search_index_entries');
    }
};
