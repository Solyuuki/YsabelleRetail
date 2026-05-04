<?php

test('size guide renders an interactive fit studio experience', function () {
    $this->get(route('storefront.support.size-guide'))
        ->assertOk()
        ->assertSeeText('Choose your usual shoe size')
        ->assertSeeText('PH size guide')
        ->assertSeeText('Running')
        ->assertSeeText('Wide')
        ->assertSeeText('Aurum Runner')
        ->assertDontSeeText('US sizing')
        ->assertDontSeeText('Choose your usual US size')
        ->assertDontSeeText('Size Guide Shipping Returns Contact')
        ->assertSee('data-size-guide', escape: false);
});

test('shipping page renders the estimator, fee guidance, and delivery timeline', function () {
    $this->get(route('storefront.support.shipping'))
        ->assertOk()
        ->assertSeeText('Delivery estimator')
        ->assertSeeText('PHP 5,000+')
        ->assertSeeText('PHP 350')
        ->assertSeeText('Order Placed')
        ->assertSeeText('BGC / Metro Manila');
});

test('returns page renders action-based support paths with a real email draft action', function () {
    $this->get(route('storefront.support.returns'))
        ->assertOk()
        ->assertSeeText('Return item')
        ->assertSeeText('Step 1: Submit request')
        ->assertSeeText('Step 4: Ship item')
        ->assertSeeText('Exchange size')
        ->assertSeeText('Report issue')
        ->assertSee('mailto:ysabelleretail@gmail.com', escape: false);
});

test('contact page keeps one support hub and prioritizes quick support actions', function () {
    $response = $this->get(route('storefront.support.contact'))
        ->assertOk()
        ->assertDontSeeText('Email Support')
        ->assertSeeText('This form sends a real support request to the Ysabelle Retail team.')
        ->assertSeeText('Send Support Request')
        ->assertDontSeeText('Call Support')
        ->assertDontSeeText('Direct support details')
        ->assertDontSeeText('BGC support hub')
        ->assertDontSeeText('Before you send')
        ->assertDontSeeText('Response expectations')
        ->assertSeeText('Quick Support Actions')
        ->assertSeeText('Track an order')
        ->assertSeeText('Start a return')
        ->assertSeeText('Size guide')
        ->assertSeeText('Order status help')
        ->assertSeeText('What we need from you')
        ->assertSeeText('ysabelleretail@gmail.com')
        ->assertSeeText('0976 650 0867')
        ->assertSeeText('Bonifacio Global City, Taguig')
        ->assertSee('action="'.route('storefront.support.contact.store').'"', escape: false)
        ->assertSee('mailto:ysabelleretail@gmail.com', escape: false)
        ->assertSee('tel:09766500867', escape: false)
        ->assertSee('href="'.route('storefront.support.returns').'"', escape: false)
        ->assertSee('href="'.route('storefront.support.size-guide').'"', escape: false)
        ->assertSee('href="'.route('login', ['intended' => route('storefront.account.index')]).'"', escape: false)
        ->assertSee('data-contact-quick-action', escape: false)
        ->assertSee('data-quick-issue-id="order-issue"', escape: false);

    expect(substr_count($response->getContent(), 'href="mailto:ysabelleretail@gmail.com'))->toBe(1)
        ->and(substr_count($response->getContent(), 'href="tel:09766500867"'))->toBe(1);
});
