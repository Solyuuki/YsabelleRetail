<?php

return [
    'navigation' => [
        [
            'label' => 'Home',
            'route' => 'storefront.home',
        ],
        [
            'label' => 'Shop',
            'route' => 'storefront.shop',
        ],
        [
            'label' => 'Running',
            'route' => 'storefront.shop',
            'params' => ['category' => 'running'],
        ],
        [
            'label' => 'Sneakers',
            'route' => 'storefront.shop',
            'params' => ['category' => 'sneakers'],
        ],
    ],

    'trust_marks' => [
        [
            'label' => 'Free Shipping',
            'description' => 'Orders over PHP 5,000',
        ],
        [
            'label' => '14-Day Returns',
            'description' => 'Hassle-free policy',
        ],
        [
            'label' => 'Authenticity',
            'description' => '100% genuine',
        ],
        [
            'label' => 'Premium Care',
            'description' => 'White-glove service',
        ],
    ],

    'footer' => [
        'shop' => [
            ['label' => 'All Shoes', 'route' => 'storefront.shop'],
            ['label' => 'Running', 'route' => 'storefront.shop', 'params' => ['category' => 'running']],
            ['label' => 'Sneakers', 'route' => 'storefront.shop', 'params' => ['category' => 'sneakers']],
            ['label' => 'Performance', 'route' => 'storefront.shop', 'params' => ['category' => 'performance']],
        ],
        'support' => [
            ['label' => 'Size Guide', 'href' => '#size-guide'],
            ['label' => 'Shipping', 'href' => '#shipping'],
            ['label' => 'Returns', 'href' => '#returns'],
            ['label' => 'Contact', 'href' => '#contact'],
        ],
    ],
];
