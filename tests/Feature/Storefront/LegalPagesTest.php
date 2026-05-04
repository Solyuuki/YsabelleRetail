<?php

test('terms of use page renders clean store policy sections', function () {
    $this->get(route('storefront.terms'))
        ->assertOk()
        ->assertSeeText('Terms of Use')
        ->assertSeeText('general store policies')
        ->assertSeeText('Account use')
        ->assertSeeText('Orders and purchases')
        ->assertSeeText('Product information')
        ->assertSeeText('Returns and support')
        ->assertSeeText('Acceptable use')
        ->assertSeeText('Contact')
        ->assertSee('href="'.route('storefront.support.contact').'"', escape: false);
});

test('privacy policy page renders clean store privacy sections', function () {
    $this->get(route('storefront.privacy'))
        ->assertOk()
        ->assertSeeText('Privacy Policy')
        ->assertSeeText('general store-policy approach')
        ->assertSeeText('Information we collect')
        ->assertSeeText('How we use information')
        ->assertSeeText('Orders and support')
        ->assertSeeText('Account security')
        ->assertSeeText('Operational limits')
        ->assertSeeText('Contact')
        ->assertSee('href="'.route('storefront.support.contact').'"', escape: false);
});
