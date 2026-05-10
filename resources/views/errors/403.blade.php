@extends('errors.layout', [
    'title' => '403 | Ysabelle Retail',
    'status' => '403',
    'eyebrow' => 'Protected area',
    'headline' => 'Access denied',
    'copy' => 'This page is reserved for a different access level. Sign in with the correct account or return to a storefront page that is available to you.',
    'chips' => [
        'Your current session may not have the required role.',
        'Customer data stays protected when access is denied.',
    ],
    'guidanceTitle' => 'Safe recovery',
    'guidanceCopy' => 'If you expected access here, sign in with the correct account first. Otherwise, continue through the storefront or contact support for help.',
    'actions' => [
        ['label' => 'Return to storefront', 'url' => route('storefront.home'), 'variant' => 'primary'],
        ['label' => 'Go to sign in', 'url' => route('login')],
        ['label' => 'Contact support', 'url' => route('storefront.support.contact')],
    ],
])
