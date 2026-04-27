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

    'assistant' => [
        'prompt_chips' => [
            'Find running shoes',
            'Shoes under ₱3,000',
            'Black sneakers',
            'Check my cart',
            'Find similar by image',
        ],

        'visual_search' => [
            'colors' => [
                'black' => 'Black',
                'white' => 'White',
                'ivory' => 'Ivory',
                'blue' => 'Blue',
                'graphite' => 'Graphite',
                'gold' => 'Gold',
                'volt' => 'Volt',
            ],
            'use_cases' => [
                'daily' => 'Daily use',
                'running' => 'Running',
                'walking' => 'Walking',
                'gym' => 'Gym / training',
                'performance' => 'Performance',
            ],
        ],

        'policies' => [
            'shipping' => 'Shipping is free for orders above PHP 5,000. Orders below that threshold use a PHP 350 delivery fee.',
            'returns' => 'Ysabelle Retail supports a 14-day return window for demo-ready storefront flows.',
            'authenticity' => 'All catalog items are presented as 100% genuine Ysabelle Retail merchandise.',
            'care' => 'Premium care includes sizing help, product guidance, and post-purchase assistance through the storefront helper.',
        ],
    ],
];
