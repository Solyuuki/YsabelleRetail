@extends('errors.layout', [
    'title' => '419 | Ysabelle Retail',
    'status' => '419',
    'eyebrow' => 'Security timeout',
    'headline' => 'Session expired',
    'copy' => 'Your security session is no longer active. This is normal after inactivity, a long pause, or opening an older page before submitting a protected action.',
    'chips' => [
        'No payment or account change is completed from an expired session.',
        'Refreshing or signing in again starts a fresh protected request.',
    ],
    'guidanceTitle' => 'How to recover safely',
    'guidanceCopy' => 'Return to the last page, refresh if needed, and sign in again before retrying protected actions such as account updates or secure forms.',
    'actions' => [
        ['label' => 'Sign in again', 'url' => route('login'), 'variant' => 'primary'],
        ['label' => 'Return to storefront', 'url' => route('storefront.home')],
        ['label' => 'Contact support', 'url' => route('storefront.support.contact')],
    ],
])
