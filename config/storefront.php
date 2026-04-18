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
            'description' => 'Orders over ₱5,000',
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

    'product_media' => [
        'aurum-runner' => [
            'card' => '/images/storefront/products/aurum-card.png',
            'detail' => '/images/storefront/products/aurum-detail.png',
            'hero' => '/images/storefront/products/aurum-hero.png',
        ],
        'shadow-stride' => [
            'card' => '/images/storefront/products/shadow-card.png',
            'detail' => '/images/storefront/products/shadow-card.png',
            'hero' => '/images/storefront/products/shadow-card.png',
        ],
        'ivory-prestige' => [
            'card' => '/images/storefront/products/ivory-card.png',
            'detail' => '/images/storefront/products/ivory-card.png',
            'hero' => '/images/storefront/products/ivory-card.png',
        ],
        'volt-edge' => [
            'card' => '/images/storefront/products/volt-card.png',
            'detail' => '/images/storefront/products/volt-card.png',
            'hero' => '/images/storefront/products/volt-card.png',
        ],
        'azure-velocity' => [
            'card' => '/images/storefront/products/cobalt-card.png',
            'detail' => '/images/storefront/products/cobalt-card.png',
            'hero' => '/images/storefront/products/cobalt-card.png',
        ],
    ],
];
