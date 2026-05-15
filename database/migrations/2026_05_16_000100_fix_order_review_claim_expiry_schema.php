<?php

use App\Services\Orders\ReviewClaimExpiryRepairService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_review_claims')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE order_review_claims MODIFY expires_at DATETIME NOT NULL');
        }

        app(ReviewClaimExpiryRepairService::class)->repair();
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_review_claims')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE order_review_claims MODIFY expires_at TIMESTAMP NULL DEFAULT NULL');
        }
    }
};
