<?php

return [
    'contact' => [
        'email' => 'ysabelleretail@gmail.com',
        'phone' => '09766500867',
        'phone_display' => '0976 650 0867',
        'address' => 'Ysabelle Retail Support Hub, Bonifacio Global City, Taguig, Metro Manila, Philippines',
        'address_short' => 'Bonifacio Global City, Taguig',
        'hours' => 'Monday to Saturday, 10:00 AM to 6:00 PM',
    ],

    'pages' => [
        'size-guide' => [
            'route' => 'storefront.support.size-guide',
            'eyebrow' => 'Support',
            'title' => 'Size Guide',
            'description' => 'Use our fit studio to compare common numeric shoe sizes, shopper preferences, and category fit notes before you commit to checkout.',
            'summary' => 'Start with your usual shoe size, then refine the recommendation by use case and foot shape for a more realistic fit call.',
            'view' => 'size-guide',
        ],
        'shipping' => [
            'route' => 'storefront.support.shipping',
            'eyebrow' => 'Support',
            'title' => 'Shipping',
            'description' => 'Review the delivery flow, shipping threshold, and destination-based expectations in one clean support guide.',
            'summary' => 'The checkout summary remains the source of truth for shipping charges, while this page helps shoppers understand what happens next.',
            'view' => 'shipping',
        ],
        'returns' => [
            'route' => 'storefront.support.returns',
            'eyebrow' => 'Support',
            'title' => 'Returns',
            'description' => 'Follow a guided 14-day return path with clear conditions, action-specific instructions, and direct support contact options.',
            'summary' => 'Start with the action that matches your situation so support can review the right details without back-and-forth.',
            'view' => 'returns',
        ],
        'contact' => [
            'route' => 'storefront.support.contact',
            'eyebrow' => 'Support',
            'title' => 'Contact',
            'description' => 'Choose the support topic first, then send a real support request with the right context already prepared.',
            'summary' => 'This page sends real support requests to the team while keeping direct email and phone fallback options available when you need them.',
            'view' => 'contact',
        ],
    ],
];
