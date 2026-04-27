<?php

namespace App\Providers;

use App\Events\Admin\InventoryStockChanged;
use App\Events\Admin\OrderPlaced;
use App\Listeners\Admin\RecordInventoryActivity;
use App\Listeners\Admin\RecordOrderActivity;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderPlaced::class => [
            RecordOrderActivity::class,
        ],
        InventoryStockChanged::class => [
            RecordInventoryActivity::class,
        ],
    ];
}
