<?php

namespace App\Support\Admin;

final class InventoryMovementType
{
    public const ADJUSTMENT = 'adjustment';
    public const BATCH_IMPORT = 'batch_import';
    public const ONLINE_SALE = 'online_sale';
    public const STOCK_IN = 'stock_in';
    public const STOCK_OUT = 'stock_out';
    public const WALK_IN_SALE = 'walk_in_sale';

    public static function labels(): array
    {
        return [
            self::STOCK_IN => 'Stock In',
            self::STOCK_OUT => 'Stock Out',
            self::ADJUSTMENT => 'Adjustment',
            self::ONLINE_SALE => 'Online Sale',
            self::WALK_IN_SALE => 'Walk-in Sale',
            self::BATCH_IMPORT => 'Batch Import',
        ];
    }

    public static function manualTypes(): array
    {
        return [
            self::STOCK_IN,
            self::STOCK_OUT,
            self::ADJUSTMENT,
        ];
    }
}
