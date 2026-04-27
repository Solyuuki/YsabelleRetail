<?php

namespace App\Support;

use Illuminate\Support\Str;

class OrderNumberGenerator
{
    public function generate(string $prefix = 'YSB'): string
    {
        return "{$prefix}-".now()->format('ymd').'-'.Str::upper(Str::random(6));
    }
}
