<?php

namespace App\Events\Admin;

use App\Models\Inventory\StockMovement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryStockChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly StockMovement $movement,
        public readonly int $currentQuantity,
    ) {}
}
