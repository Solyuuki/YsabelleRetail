<?php

namespace App\Services\Support;

use App\Models\Support\SupportTicket;

class SupportTicketSubmissionResult
{
    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly bool $emailSent,
    ) {}
}
