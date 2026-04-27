<?php

test('the storefront home page loads successfully', function () {
    $this->get(route('storefront.home'))
        ->assertOk()
        ->assertSeeText('Nike Shoe V2');
});

test('guest users are redirected to login for admin routes', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('the login and register storefront pages are available', function () {
    $this->get(route('login'))->assertOk();
    $this->get(route('register'))->assertOk();
});
