@php
    $storefrontHome = route('storefront.home');
    $loginRoute = route('login');
    $adminAccessRoute = app(\App\Support\Auth\AuthenticatedRedirector::class)->adminAccessUrl();
    $adminDashboard = auth()->user()?->isAdmin() ? route('admin.dashboard') : null;
    $customerDashboard = auth()->user()?->isCustomer() ? route('storefront.account.index') : null;

    $appAuth = [
        'isAuthenticated' => auth()->check(),
        'isAdmin' => auth()->user()?->isAdmin() ?? false,
        'isCustomer' => auth()->user()?->isCustomer() ?? false,
        'routes' => [
            'adminDashboard' => $adminDashboard,
            'adminAccess' => $adminAccessRoute,
            'login' => $loginRoute,
            'customerDashboard' => $customerDashboard,
            'storefront' => $storefrontHome,
        ],
        'messages' => [
            'adminDenied' => 'Admin access requires an authorized admin account.',
            'guestSignedIn' => 'You are currently signed in.',
            'userDenied' => 'Customer access requires a signed-in customer account.',
        ],
    ];
@endphp

<script>
    window.AppAuth = {!! json_encode($appAuth, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!};
</script>
