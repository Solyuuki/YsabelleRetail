<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Support\SubmitSupportTicketRequest;
use App\Services\Support\SupportTicketService;
use App\Support\SupportTicketCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class SupportTicketController extends Controller
{
    public function store(
        SubmitSupportTicketRequest $request,
        SupportTicketService $supportTickets,
    ): JsonResponse|RedirectResponse {
        $result = $supportTickets->submit(
            payload: $request->validated(),
            user: $request->user(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $categoryLabel = SupportTicketCategories::labelFor($result->ticket->category);
        $fallbackMailto = $this->fallbackMailto($result->ticket, $categoryLabel);

        if ($request->expectsJson()) {
            if ($result->emailSent) {
                return response()->json([
                    'status' => 'sent',
                    'ticket_number' => $result->ticket->ticket_number,
                    'message' => "Support request sent. We'll reply through your email.",
                ], 201);
            }

            return response()->json([
                'status' => 'saved_email_failed',
                'ticket_number' => $result->ticket->ticket_number,
                'message' => "Support request saved, but we could not send the support email. Please email ysabelleretail@gmail.com and mention ticket {$result->ticket->ticket_number}.",
                'fallback_mailto' => $fallbackMailto,
            ]);
        }

        if ($result->emailSent) {
            return back()->with('toast', [
                'type' => 'success',
                'title' => 'Support request sent',
                'message' => "Support request sent. We'll reply through your email.",
            ]);
        }

        return back()->with('toast', [
            'type' => 'error',
            'title' => 'Support email unavailable',
            'message' => "Support request saved, but we could not send the support email. Please email ysabelleretail@gmail.com and mention ticket {$result->ticket->ticket_number}.",
        ])->withInput();
    }

    private function fallbackMailto($ticket, string $categoryLabel): string
    {
        $query = http_build_query(
            [
                'subject' => "{$categoryLabel} | {$ticket->ticket_number}",
                'body' => implode("\n", array_filter([
                    'Hello Ysabelle Retail Support,',
                    '',
                    "I am following up on support ticket {$ticket->ticket_number}.",
                    "Category: {$categoryLabel}",
                    "Name: {$ticket->name}",
                    "Reply email: {$ticket->reply_email}",
                    $ticket->reference ? "Reference: {$ticket->reference}" : null,
                    'Issue details:',
                    $ticket->message,
                    '',
                    'Thank you.',
                ])),
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        return 'mailto:'.config('storefront-support.contact.email')."?{$query}";
    }
}
