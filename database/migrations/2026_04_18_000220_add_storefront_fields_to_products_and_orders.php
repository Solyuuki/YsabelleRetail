<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('rating_average', 2, 1)->default(4.8)->after('compare_at_price');
            $table->unsignedInteger('review_count')->default(0)->after('rating_average');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_name')->nullable()->after('user_id');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('customer_phone')->nullable()->after('customer_email');
            $table->string('shipping_city')->nullable()->after('notes');
            $table->string('shipping_address_line')->nullable()->after('shipping_city');
            $table->string('shipping_postal_code')->nullable()->after('shipping_address_line');
            $table->string('payment_method')->nullable()->after('shipping_postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'customer_name',
                'customer_email',
                'customer_phone',
                'shipping_city',
                'shipping_address_line',
                'shipping_postal_code',
                'payment_method',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'rating_average',
                'review_count',
            ]);
        });
    }
};
