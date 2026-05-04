<?php

namespace App\Mail\Support;

use App\Models\Support\SupportTicket;
use App\Support\SupportTicketCategories;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SupportTicketSubmittedMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly SupportTicket $ticket
    ) {
    }

    public function envelope(): Envelope
    {
        $categoryLabel = SupportTicketCategories::labelFor($this->ticket->category);

        return new Envelope(
            subject: "{$categoryLabel} | {$this->ticket->ticket_number}",
            replyTo: [
                new Address($this->ticket->reply_email, $this->ticket->name),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.support.ticket-submitted',
        );
    }
}
