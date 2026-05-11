<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 120)->nullable();
            $table->text('body');
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'user_id']);
            $table->index(['product_id', 'is_visible', 'created_at']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('rating_average', 2, 1)->default(0)->change();
            $table->unsignedInteger('review_count')->default(0)->change();
        });

        DB::table('products')->update([
            'rating_average' => 0,
            'review_count' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('rating_average', 2, 1)->default(4.8)->change();
            $table->unsignedInteger('review_count')->default(0)->change();
        });

        Schema::dropIfExists('product_reviews');
    }
};
