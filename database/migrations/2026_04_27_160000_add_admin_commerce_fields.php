<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('cost_price', 12, 2)->nullable()->after('compare_at_price');
            $table->string('supplier_name')->nullable()->after('cost_price');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('source')->default('online')->after('user_id')->index();
            $table->foreignId('handled_by_user_id')->nullable()->after('source')->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('handled_by_user_id');
            $table->dropColumn(['source', 'metadata']);
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn(['cost_price', 'supplier_name']);
        });
    }
};
