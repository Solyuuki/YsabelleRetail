<?php

namespace App\Services\Support;

use App\Mail\Support\SupportTicketSubmittedMail;
use App\Models\Support\SupportTicket;
use App\Models\User;
use App\Support\SupportTicketNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SupportTicketService
{
    public function __construct(
        private readonly SupportTicketNumberGenerator $ticketNumbers,
    ) {
    }

    public function submit(array $payload, ?User $user, ?string $ipAddress, ?string $userAgent): SupportTicketSubmissionResult
    {
        $ticket = DB::transaction(function () use ($payload, $user, $ipAddress, $userAgent): SupportTicket {
            return SupportTicket::query()->create([
                'ticket_number' => $this->generateUniqueTicketNumber(),
                'category' => $payload['category'],
                'name' => $payload['name'],
                'reply_email' => $payload['reply_email'],
                'reference' => $payload['reference'] ?: null,
                'message' => $payload['message'],
                'status' => 'new',
                'email_status' => 'pending',
                'email_error' => null,
                'user_id' => $user?->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        });

        try {
            Mail::to((string) config('storefront-support.contact.email'))
                ->send(new SupportTicketSubmittedMail($ticket));

            $this->markEmailStatus($ticket, 'sent');

            return new SupportTicketSubmissionResult($ticket->fresh(), true);
        } catch (Throwable $exception) {
            report($exception);
            $this->markEmailStatus($ticket, 'failed', Str::limit($exception->getMessage(), 1000));

            return new SupportTicketSubmissionResult($ticket->fresh(), false);
        }
    }

    private function generateUniqueTicketNumber(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $ticketNumber = $this->ticketNumbers->generate();

            if (! SupportTicket::query()->where('ticket_number', $ticketNumber)->exists()) {
                return $ticketNumber;
            }
        }

        throw new \RuntimeException('Unable to generate a unique support ticket number.');
    }

    private function markEmailStatus(SupportTicket $ticket, string $status, ?string $error = null): void
    {
        try {
            $ticket->forceFill([
                'email_status' => $status,
                'email_error' => $error,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
