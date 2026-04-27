<?php

namespace App\Listeners\Admin;

use App\Events\Admin\InventoryStockChanged;
use App\Services\Admin\AdminActivityLogger;

class RecordInventoryActivity
{
    public function __construct(
        private readonly AdminActivityLogger $logger,
    ) {}

    public function handle(InventoryStockChanged $event): void
    {
        $this->logger->recordInventory(
            $event->movement,
            $event->currentQuantity,
        );
    }
}
