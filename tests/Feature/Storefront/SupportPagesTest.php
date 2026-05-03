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

test('contact page renders the support hub with email, phone, and bgc location details', function () {
    $this->get(route('storefront.support.contact'))
        ->assertOk()
        ->assertSeeText('does not currently process a live contact form submission')
        ->assertSeeText('ysabelleretail@gmail.com')
        ->assertSeeText('09766500867')
        ->assertSeeText('Bonifacio Global City, Taguig')
        ->assertSee('mailto:ysabelleretail@gmail.com', escape: false)
        ->assertSee('tel:09766500867', escape: false);
});
