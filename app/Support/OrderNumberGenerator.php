<?php

namespace App\Support;

use Illuminate\Support\Str;

class OrderNumberGenerator
{
    public function generate(string $prefix = 'YSB'): string
    {
        return "{$prefix}-".BusinessTime::now()->format('ymd').'-'.Str::upper(Str::random(6));
    }
}
