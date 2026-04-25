@php
    $adminDashboard = route('admin.dashboard');
    $customerDashboard = route('storefront.account.index');
    $storefrontHome = route('storefront.home');
    $loginRoute = route('login');
    $adminLoginRoute = \Illuminate\Support\Facades\Route::has('admin.login')
        ? route('admin.login')
        : route('login', ['intended' => $adminDashboard]);

    $appAuth = [
        'isAuthenticated' => auth()->check(),
        'isAdmin' => auth()->user()?->isAdmin() ?? false,
        'isCustomer' => auth()->user()?->isCustomer() ?? false,
        'routes' => [
            'adminDashboard' => $adminDashboard,
            'adminLogin' => $adminLoginRoute,
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
