@extends('errors.layout', [
    'title' => '404 | Ysabelle Retail',
    'status' => '404',
    'eyebrow' => 'Page unavailable',
    'headline' => 'Page not found',
    'copy' => 'This page may have moved or no longer exists. Return to the storefront and continue from a known route.',
    'chips' => [
        'The link may be outdated.',
        'Your account and cart state were not changed by this page lookup.',
    ],
    'guidanceTitle' => 'Get back on track',
    'guidanceCopy' => 'Continue shopping from the storefront, reopen the last page you trusted, or contact support if you followed a link that should still exist.',
    'actions' => [
        ['label' => 'Browse storefront', 'url' => route('storefront.home'), 'variant' => 'primary'],
        ['label' => 'Open support', 'url' => route('storefront.support.contact')],
    ],
])
