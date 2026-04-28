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
        'ai' => [
            'enabled' => env('STOREFRONT_ASSISTANT_AI_ENABLED', false),
            'provider' => env('STOREFRONT_ASSISTANT_AI_PROVIDER', 'ollama'),
            'ollama' => [
                'base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
                'model' => env('OLLAMA_MODEL', 'llama3.2:3b'),
                'timeout' => (int) env('OLLAMA_TIMEOUT', 8),
                'temperature' => (float) env('OLLAMA_TEMPERATURE', 0.2),
                'max_tokens' => (int) env('OLLAMA_MAX_TOKENS', 96),
            ],
        ],

        'prompt_chips' => [
            'Find running shoes',
            'Shoes under ₱3,000',
            'Black sneakers',
            'Check my cart',
            'Find similar by image',
        ],

        'visual_search' => [
            'embedding' => [
                'enabled' => env('STOREFRONT_VISUAL_SEARCH_EMBEDDINGS_ENABLED', true),
                'python_binary' => env('STOREFRONT_VISUAL_SEARCH_PYTHON', 'python'),
                'script' => env('STOREFRONT_VISUAL_SEARCH_SCRIPT') ?: base_path('tools/visual_search_embedding_service.py'),
                'model' => env('STOREFRONT_VISUAL_SEARCH_EMBEDDING_MODEL', 'openai/clip-vit-base-patch32'),
                'version' => env('STOREFRONT_VISUAL_SEARCH_EMBEDDING_VERSION', 'clip-b32-v1'),
                'timeout' => (int) env('STOREFRONT_VISUAL_SEARCH_EMBEDDING_TIMEOUT', 120),
            ],
            'thresholds' => [
                'strong_match' => (float) env('STOREFRONT_VISUAL_SEARCH_STRONG_MATCH', 0.92),
                'likely_match' => (float) env('STOREFRONT_VISUAL_SEARCH_LIKELY_MATCH', 0.86),
                'similar_match' => (float) env('STOREFRONT_VISUAL_SEARCH_SIMILAR_MATCH', 0.78),
                'min_candidate' => (float) env('STOREFRONT_VISUAL_SEARCH_MIN_CANDIDATE', 0.72),
                'shoe_probability_floor' => (float) env('STOREFRONT_VISUAL_SEARCH_SHOE_FLOOR', 0.5),
                'blur_floor' => (float) env('STOREFRONT_VISUAL_SEARCH_BLUR_FLOOR', 0.0035),
            ],
            'debug' => env('STOREFRONT_VISUAL_SEARCH_DEBUG', env('APP_ENV') === 'local'),
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
