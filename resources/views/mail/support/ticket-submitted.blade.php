@php
    use App\Support\SupportTicketCategories;

    $categoryLabel = SupportTicketCategories::labelFor($ticket->category);
@endphp

<h1>New support ticket received</h1>

<p><strong>Ticket number:</strong> {{ $ticket->ticket_number }}</p>
<p><strong>Category:</strong> {{ $categoryLabel }}</p>
<p><strong>Name:</strong> {{ $ticket->name }}</p>
<p><strong>Reply email:</strong> {{ $ticket->reply_email }}</p>
@if ($ticket->reference)
    <p><strong>Order or reference:</strong> {{ $ticket->reference }}</p>
@endif
<p><strong>Submitted:</strong> {{ $ticket->created_at?->timezone(config('app.timezone'))->format('F j, Y g:i A') }}</p>
<p><strong>Issue details:</strong></p>
<p>{!! nl2br(e($ticket->message)) !!}</p>
