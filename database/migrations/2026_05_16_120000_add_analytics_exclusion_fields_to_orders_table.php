<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->boolean('exclude_from_analytics')->default(false)->after('metadata')->index();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('analytics_exclusion_reason')->nullable()->after('exclude_from_analytics');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'exclude_from_analytics',
                'analytics_exclusion_reason',
            ]);
        });
    }
};
