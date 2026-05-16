<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'analytics_exclusion_reason')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('analytics_exclusion_reason')->nullable()->after('exclude_from_analytics');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'analytics_exclusion_reason')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('analytics_exclusion_reason');
            });
        }
    }
};
