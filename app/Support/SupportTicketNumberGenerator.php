<?php

namespace App\Support;

class SupportTicketNumberGenerator
{
    public function generate(): string
    {
        $suffix = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        return 'YR-SUP-'.BusinessTime::now()->format('Ymd')."-{$suffix}";
    }
}
