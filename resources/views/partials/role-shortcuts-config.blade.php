@php
    $shortcutConfig = [
        'routes' => [
            'guest' => route('storefront.home'),
            'user' => route('storefront.account.index'),
            'admin' => route('admin.dashboard'),
            'login' => route('login'),
        ],
        'user' => [
            'authenticated' => auth()->check(),
            'admin' => auth()->user()?->isAdmin() ?? false,
            'customer' => auth()->user()?->isCustomer() ?? false,
        ],
        'messages' => [
            'adminDenied' => 'Admin access requires an authorized admin account.',
            'guestDenied' => 'Guest mode does not sign you out. Returning to your active area instead.',
            'userDenied' => 'Customer access requires a signed-in customer account.',
        ],
    ];
@endphp

<script id="ys-role-shortcuts-config" type="application/json">
    {!! json_encode($shortcutConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
