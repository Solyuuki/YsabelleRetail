<?php

use App\Mail\Support\SupportTicketSubmittedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function validSupportPayload(array $overrides = []): array
{
    return array_merge([
        'category' => 'order-issue',
        'name' => 'Jamie Shopper',
        'reply_email' => 'jamie@example.com',
        'reference' => 'YS-10425',
        'message' => 'My delivery arrived with the wrong size and I need help resolving it.',
        'website' => '',
    ], $overrides);
}

test('valid support request stores a support ticket', function () {
    Mail::fake();

    $response = $this->postJson(route('storefront.support.contact.store'), validSupportPayload());

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'sent');

    $this->assertDatabaseHas('support_tickets', [
        'category' => 'order-issue',
        'name' => 'Jamie Shopper',
        'reply_email' => 'jamie@example.com',
        'reference' => 'YS-10425',
        'status' => 'new',
        'email_status' => 'sent',
    ]);
});

test('valid support request sends a mail notification', function () {
    Mail::fake();

    $this->postJson(route('storefront.support.contact.store'), validSupportPayload())
        ->assertCreated();

    Mail::assertSent(SupportTicketSubmittedMail::class, function (SupportTicketSubmittedMail $mail): bool {
        return $mail->hasTo('ysabelleretail@gmail.com')
            && $mail->hasReplyTo('jamie@example.com');
    });
});

test('invalid reply email is rejected for support requests', function () {
    Mail::fake();

    $this->postJson(route('storefront.support.contact.store'), validSupportPayload([
        'reply_email' => 'not-an-email',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reply_email']);
});

test('missing support message is rejected', function () {
    Mail::fake();

    $this->postJson(route('storefront.support.contact.store'), validSupportPayload([
        'message' => '',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['message']);
});

test('support ticket number is generated for valid requests', function () {
    Mail::fake();

    $response = $this->postJson(route('storefront.support.contact.store'), validSupportPayload())
        ->assertCreated();

    expect($response->json('ticket_number'))->toMatch('/^YR-SUP-\d{8}-\d{4}$/');
});

test('support ticket is saved with failed email status when smtp delivery fails', function () {
    Mail::shouldReceive('to')
        ->once()
        ->with('ysabelleretail@gmail.com')
        ->andReturnSelf();
    Mail::shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('SMTP unavailable'));

    $response = $this->postJson(route('storefront.support.contact.store'), validSupportPayload());

    $response
        ->assertOk()
        ->assertJsonPath('status', 'saved_email_failed');

    $this->assertDatabaseHas('support_tickets', [
        'category' => 'order-issue',
        'reply_email' => 'jamie@example.com',
        'email_status' => 'failed',
    ]);
});
