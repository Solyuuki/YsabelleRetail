<?php

namespace App\Mail\Orders;

use App\Models\Orders\OrderReviewClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WalkInReviewClaimMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly OrderReviewClaim $claim,
        public readonly string $claimUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Claim your Ysabelle Retail walk-in purchase ({$this->claim->order->order_number})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.orders.walk-in-review-claim',
        );
    }
}
