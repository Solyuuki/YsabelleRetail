<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('primary_image_url', 2048)->nullable()->after('description');
            $table->string('image_alt')->nullable()->after('primary_image_url');
            $table->json('image_gallery')->nullable()->after('image_alt');
            $table->unsignedSmallInteger('featured_rank')->nullable()->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'primary_image_url',
                'image_alt',
                'image_gallery',
                'featured_rank',
            ]);
        });
    }
};
