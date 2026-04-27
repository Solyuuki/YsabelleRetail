<?php

namespace App\Listeners\Admin;

use App\Events\Admin\OrderPlaced;
use App\Services\Admin\AdminActivityLogger;

class RecordOrderActivity
{
    public function __construct(
        private readonly AdminActivityLogger $logger,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $this->logger->recordOrder(
            $event->order->loadMissing(['user', 'handledBy'])
        );
    }
}
