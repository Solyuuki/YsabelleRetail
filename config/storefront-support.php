<?php

return [
    'pages' => [
        'size-guide' => [
            'route' => 'storefront.support.size-guide',
            'eyebrow' => 'Support',
            'title' => 'Size Guide',
            'description' => 'Choose your usual US shoe size with confidence, then use our fit notes to fine-tune the best match for each silhouette.',
            'summary' => [
                'title' => 'How Ysabelle sizing works',
                'body' => 'The storefront catalog is presented in standard US shoe sizes. Most pairs are designed to fit true to size, with fit notes below to help when you are between sizes or shopping for a specific use case.',
            ],
            'highlights' => [
                'US sizes across the current catalog generally range from 6 to 12.',
                'Running and training shoes are best matched with a secure performance fit.',
                'Lifestyle, walking, and slip-on pairs can feel more relaxed through the forefoot.',
            ],
            'sections' => [
                [
                    'title' => 'Catalog size ranges',
                    'content' => [
                        'Women\'s and unisex-adjacent lifestyle silhouettes commonly appear in US 6 to 11.',
                        'Performance-led categories such as running, training, basketball, and boots commonly appear in US 7 to 12.',
                        'Always review the available sizes on the product page because some styles run in a narrower range than others.',
                    ],
                ],
                [
                    'title' => 'Fit guidance',
                    'content' => [
                        'If you are between sizes for running, training, or basketball shoes, most shoppers are better served by sizing up for toe-room during movement.',
                        'If you prefer a more tailored lifestyle fit and usually wear thin socks, your regular size is typically the best starting point.',
                        'For boots or high-cut shoes, leave enough room for thicker socks without letting the heel lift excessively while walking.',
                    ],
                ],
                [
                    'title' => 'Before you choose a size',
                    'content' => [
                        'Measure your foot at the end of the day when swelling is closest to normal wear conditions.',
                        'Compare both feet and fit to the larger measurement if you are not exactly symmetrical.',
                        'Use the product detail page stock selector to confirm your size is available before starting checkout.',
                    ],
                ],
            ],
        ],
        'shipping' => [
            'route' => 'storefront.support.shipping',
            'eyebrow' => 'Support',
            'title' => 'Shipping',
            'description' => 'Shipping expectations are kept clear at checkout so customers can review timing, threshold benefits, and destination details before placing an order.',
            'summary' => [
                'title' => 'Delivery overview',
                'body' => 'Ysabelle Retail currently presents shipping for local storefront flows. Orders at PHP 5,000 and above qualify for free shipping, while lower subtotals use the existing PHP 350 delivery fee shown during checkout.',
            ],
            'highlights' => [
                'Free shipping applies when the cart subtotal reaches PHP 5,000.',
                'Orders below the threshold currently show a PHP 350 shipping fee.',
                'Final shipping cost is always reflected in the server-side checkout summary before the order is placed.',
            ],
            'sections' => [
                [
                    'title' => 'Processing and dispatch',
                    'content' => [
                        'Orders are typically reviewed within one business day before dispatch preparation begins.',
                        'During peak launches, holidays, or weather disruption, handling time may take longer than usual.',
                        'Customers should rely on the checkout and order summary pages as the authoritative record of shipping charges and order progress.',
                    ],
                ],
                [
                    'title' => 'Delivery expectations',
                    'content' => [
                        'Metro destinations usually arrive faster than provincial shipments, but exact timing can vary by carrier capacity and destination coverage.',
                        'Address accuracy matters. Double-check your city, address line, and postal code during checkout to reduce failed delivery attempts.',
                        'If a delivery attempt cannot be completed, support may ask you to confirm the address before the shipment is reprocessed.',
                    ],
                ],
            ],
        ],
        'returns' => [
            'route' => 'storefront.support.returns',
            'eyebrow' => 'Support',
            'title' => 'Returns',
            'description' => 'Our storefront experience already promises 14-Day Returns, so this page explains how that window is intended to work in a clear and practical way.',
            'summary' => [
                'title' => '14-day return window',
                'body' => 'Return requests should be raised within 14 days of delivery for eligible pairs that remain clean, unworn outdoors, and complete with original packaging when possible.',
            ],
            'highlights' => [
                'Requests should be raised within 14 days of delivery.',
                'Items should be in clean, resale-ready condition.',
                'Support may ask for order details and photos before confirming next steps.',
            ],
            'sections' => [
                [
                    'title' => 'Eligible return conditions',
                    'content' => [
                        'Shoes should show minimal handling wear and should not be used in a way that prevents normal quality inspection.',
                        'Original box, tags, and included accessories help the return process move faster, especially for premium or limited silhouettes.',
                        'Pairs returned with obvious outdoor wear, heavy damage, or missing essential components may be declined after review.',
                    ],
                ],
                [
                    'title' => 'How to start a return',
                    'content' => [
                        'Prepare your order number, the product name, and a short explanation of the issue or fit concern.',
                        'Contact support through the contact page so the team can confirm eligibility and advise the next practical step.',
                        'Do not send items back without instructions, because routing and verification may differ depending on the order context.',
                    ],
                ],
            ],
        ],
        'contact' => [
            'route' => 'storefront.support.contact',
            'eyebrow' => 'Support',
            'title' => 'Contact',
            'description' => 'Reach the support team for sizing help, order questions, shipping concerns, or return guidance through clear and non-misleading contact paths.',
            'summary' => [
                'title' => 'How to reach support',
                'body' => 'This storefront does not currently process a live contact form submission on the public site, so the contact page is intentionally informational and directs shoppers to clear support paths instead of pretending to send a request.',
            ],
            'highlights' => [
                'General support: care@ysabelle-retail.example',
                'Order and delivery questions: include your order number in the subject line.',
                'Sizing guidance: mention the shoe category, your usual US size, and whether you prefer a snug or relaxed fit.',
            ],
            'sections' => [
                [
                    'title' => 'Support categories',
                    'content' => [
                        'Sizing and fit guidance for running, lifestyle, training, basketball, walking, slip-on, and boot categories.',
                        'Shipping and delivery clarifications for threshold eligibility, address corrections, and order progress questions.',
                        'Returns assistance for customers reviewing the 14-day window or preparing item-condition details before sending a request.',
                    ],
                ],
                [
                    'title' => 'Before you contact us',
                    'content' => [
                        'For order questions, include the order number, the name used during checkout, and the issue you need resolved.',
                        'For product questions, include the style name, category, and the size or fit concern you want help with.',
                        'For policy questions, review the shipping and returns pages first so support can focus on the part that remains unclear.',
                    ],
                ],
            ],
            'actions' => [
                [
                    'label' => 'Email Support',
                    'href' => 'mailto:care@ysabelle-retail.example?subject=Ysabelle%20Retail%20Support',
                    'variant' => 'primary',
                ],
                [
                    'label' => 'Review Returns Policy',
                    'route' => 'storefront.support.returns',
                    'variant' => 'secondary',
                ],
            ],
        ],
    ],
];
