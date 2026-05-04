<?php

namespace App\Services\Storefront;

use App\Support\SupportTicketCategories;

class SupportPageService
{
    public function page(string $pageKey): ?array
    {
        $page = config("storefront-support.pages.{$pageKey}");

        if (! is_array($page)) {
            return null;
        }

        return [
            ...$page,
            'key' => $pageKey,
            'hero_actions' => $this->heroActions($pageKey),
            'view_data' => match ($pageKey) {
                'size-guide' => $this->sizeGuideData(),
                'shipping' => $this->shippingData(),
                'returns' => $this->returnsData(),
                'contact' => $this->contactData(),
                default => [],
            },
        ];
    }

    public function contactDetails(): array
    {
        $contact = config('storefront-support.contact', []);

        return [
            ...$contact,
            'phone_href' => 'tel:'.preg_replace('/\D+/', '', (string) ($contact['phone'] ?? '')),
            'general_mailto' => $this->mailto(
                'Ysabelle Retail Support Request',
                [
                    'Hello Ysabelle Retail Support,',
                    '',
                    'I need help with:',
                    '- Issue type:',
                    '- Order number or product name:',
                    '- Details:',
                    '',
                    'Thank you.',
                ]
            ),
        ];
    }

    private function heroActions(string $pageKey): array
    {
        return match ($pageKey) {
            'size-guide' => [
                ['label' => 'Browse Running Shoes', 'url' => route('storefront.shop', ['category' => 'running']), 'variant' => 'primary'],
                ['label' => 'Ask for Size Help', 'url' => route('storefront.support.contact'), 'variant' => 'secondary'],
            ],
            'shipping' => [
                ['label' => 'Shop With Free Shipping Goal', 'url' => route('storefront.shop'), 'variant' => 'primary'],
                ['label' => 'Contact Support', 'url' => route('storefront.support.contact'), 'variant' => 'secondary'],
            ],
            'returns' => [
                ['label' => 'Email Return Support', 'url' => $this->mailto('Return Request - Ysabelle Retail', ['Hello Ysabelle Retail Support,', '', 'I would like help with a return or exchange.', '', 'Order number:', 'Product name:', 'Concern:', '', 'Thank you.']), 'variant' => 'primary'],
                ['label' => 'Contact Support', 'url' => route('storefront.support.contact'), 'variant' => 'secondary'],
            ],
            'contact' => [],
            default => [],
        };
    }

    private function sizeGuideData(): array
    {
        return [
            'sizes' => ['6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10', '10.5', '11', '11.5', '12'],
            'use_cases' => [
                ['id' => 'running', 'label' => 'Running', 'detail' => 'Secure fit with movement room'],
                ['id' => 'casual', 'label' => 'Casual', 'detail' => 'Easy everyday comfort'],
                ['id' => 'training', 'label' => 'Training', 'detail' => 'Stable midfoot hold'],
                ['id' => 'walking', 'label' => 'Walking', 'detail' => 'Relaxed all-day wear'],
                ['id' => 'basketball', 'label' => 'Basketball', 'detail' => 'Containment for quick cuts'],
                ['id' => 'boots', 'label' => 'Boots', 'detail' => 'Room for thicker socks'],
            ],
            'foot_types' => [
                ['id' => 'narrow', 'label' => 'Narrow'],
                ['id' => 'regular', 'label' => 'Regular'],
                ['id' => 'wide', 'label' => 'Wide'],
            ],
            'visuals' => [
                'running' => [
                    'image' => 'images/products/running/aurum-runner.jpg',
                    'title' => 'Aurum Runner',
                    'tag' => 'Performance fit',
                    'copy' => 'Balanced cushioning and a secure collar for shoppers who prefer a responsive running fit.',
                ],
                'casual' => [
                    'image' => 'images/products/lifestyle-shoes/maison-drift.jpg',
                    'title' => 'Maison Drift',
                    'tag' => 'Lifestyle fit',
                    'copy' => 'A cleaner daily silhouette with a slightly more forgiving feel through the forefoot.',
                ],
                'training' => [
                    'image' => 'images/products/training-shoes/atlas-flex.jpg',
                    'title' => 'Atlas Flex',
                    'tag' => 'Training fit',
                    'copy' => 'Low-profile stability that works best when the midfoot stays locked without crushing the toes.',
                ],
                'walking' => [
                    'image' => 'images/products/slip-ons/quiet-cove.jpg',
                    'title' => 'Quiet Cove',
                    'tag' => 'Walking fit',
                    'copy' => 'Designed for longer casual wear with a roomier first feel than most performance pairs.',
                ],
                'basketball' => [
                    'image' => 'images/products/basketball-shoes/onyx-vector.jpg',
                    'title' => 'Onyx Vector',
                    'tag' => 'Court fit',
                    'copy' => 'A supportive upper and quick-stop traction profile that usually rewards a little toe-room.',
                ],
                'boots' => [
                    'image' => 'images/products/boots-high-cut/summit-forge.jpg',
                    'title' => 'Summit Forge',
                    'tag' => 'Boot fit',
                    'copy' => 'Structured shaft support with space planning for thicker socks and longer wear sessions.',
                ],
            ],
            'sample_shoes' => [
                [
                    'title' => 'Aurum Runner',
                    'use_case' => 'running',
                    'image' => 'images/products/running/aurum-runner.jpg',
                    'size_note' => 'Most shoppers start true to size. Wide feet usually prefer half-size up.',
                    'fit_note' => 'Secure heel, moderate forefoot room.',
                ],
                [
                    'title' => 'Maison Drift',
                    'use_case' => 'casual',
                    'image' => 'images/products/lifestyle-shoes/maison-drift.jpg',
                    'size_note' => 'Stays comfortable at your regular size unless you want a looser streetwear fit.',
                    'fit_note' => 'Relaxed collar and softer entry feel.',
                ],
                [
                    'title' => 'Summit Forge',
                    'use_case' => 'boots',
                    'image' => 'images/products/boots-high-cut/summit-forge.jpg',
                    'size_note' => 'Half-size up helps when pairing with thick socks or longer hikes.',
                    'fit_note' => 'Structured upper with firm ankle support.',
                ],
            ],
            'tips' => [
                'Measure later in the day when your feet are closest to normal wear conditions.',
                'If one foot runs larger, use the bigger measurement as your starting point.',
                'Always confirm product-page stock because fit guidance does not guarantee every size is available.',
            ],
        ];
    }

    private function shippingData(): array
    {
        return [
            'timeline' => [
                ['title' => 'Order Placed', 'detail' => 'You review the final total and receive checkout confirmation.'],
                ['title' => 'Processing', 'detail' => 'The team reviews stock, address details, and dispatch readiness.'],
                ['title' => 'Shipped', 'detail' => 'Tracking becomes useful once the parcel is scanned by the carrier.'],
                ['title' => 'Delivered', 'detail' => 'Arrival timing depends on destination coverage and carrier capacity.'],
            ],
            'locations' => [
                ['id' => 'bgc', 'label' => 'BGC / Metro Manila', 'window' => '1 to 3 business days after shipment', 'note' => 'Usually the fastest turnaround once the parcel leaves processing.'],
                ['id' => 'gma', 'label' => 'Greater Manila Area', 'window' => '2 to 4 business days after shipment', 'note' => 'A practical range for nearby city deliveries outside central Metro Manila.'],
                ['id' => 'luzon', 'label' => 'Provincial Luzon', 'window' => '3 to 5 business days after shipment', 'note' => 'Weather, handoff delays, and local coverage can stretch the upper end of the range.'],
                ['id' => 'visayas', 'label' => 'Visayas', 'window' => '4 to 7 business days after shipment', 'note' => 'Inter-island routing can add time, especially during volume spikes.'],
                ['id' => 'mindanao', 'label' => 'Mindanao', 'window' => '5 to 8 business days after shipment', 'note' => 'Treat this as a support estimate, not a guaranteed delivery promise.'],
            ],
            'trust_cards' => [
                ['title' => 'Tracking updates', 'detail' => 'Useful once the carrier scan is live after dispatch.'],
                ['title' => 'Address check', 'detail' => 'Checkout details matter because failed delivery attempts usually start with incomplete address info.'],
                ['title' => 'Confirmation review', 'detail' => 'The checkout summary remains the final reference for the shipping fee charged to your order.'],
            ],
        ];
    }

    private function returnsData(): array
    {
        return [
            'flow' => [
                ['label' => 'Day 1 to 14', 'detail' => 'Raise the concern within 14 days of delivery.'],
                ['label' => 'Review', 'detail' => 'Support may request the order number and photos.'],
                ['label' => 'Prepare Item', 'detail' => 'Keep the pair clean, unworn, and resale-ready.'],
                ['label' => 'Resolution', 'detail' => 'Support confirms the next step for return, exchange, or issue review.'],
            ],
            'conditions' => [
                'Request support within 14 days of delivery.',
                'Item should remain clean, unworn, and resale-ready.',
                'Original packaging is preferred whenever available.',
                'Support may ask for order details and photos before advising the next step.',
            ],
            'actions' => [
                'return-item' => [
                    'title' => 'Return item',
                    'summary' => 'Follow the return process before sending anything back.',
                    'process_steps' => [
                        [
                            'title' => 'Step 1: Submit request',
                            'detail' => 'Email support with your order details.',
                        ],
                        [
                            'title' => 'Step 2: Wait for review',
                            'detail' => 'We check the item and return window.',
                        ],
                        [
                            'title' => 'Step 3: Receive instructions',
                            'detail' => 'Wait for packing and routing steps.',
                        ],
                        [
                            'title' => 'Step 4: Ship item',
                            'detail' => 'Send it only after approval.',
                        ],
                    ],
                    'details' => [
                        'Order number, product name, and reason.',
                        'Item condition and fitting history.',
                        'Photos if support requests them.',
                    ],
                    'mailto' => $this->mailto('Return Item Request - Ysabelle Retail', ['Hello Ysabelle Retail Support,', '', 'I want to return an item.', '', 'Order number:', 'Product name:', 'Reason for return:', 'Condition of item:', '', 'Thank you.']),
                ],
                'exchange-size' => [
                    'title' => 'Exchange size',
                    'summary' => 'Use this when the style works but the fit does not feel right and you want size guidance first.',
                    'details_label' => 'What support needs from you',
                    'details' => [
                        'Share your usual shoe size and the size you received.',
                        'Mention whether the fit felt tight in length, width, or overall volume.',
                        'Availability still depends on stock at the time support reviews the request.',
                    ],
                    'mailto' => $this->mailto('Size Exchange Request - Ysabelle Retail', ['Hello Ysabelle Retail Support,', '', 'I want help with a size exchange.', '', 'Order number:', 'Product name:', 'Received size:', 'Preferred size:', 'Fit notes:', '', 'Thank you.']),
                ],
                'report-issue' => [
                    'title' => 'Report issue',
                    'summary' => 'Choose this path for quality concerns, wrong item concerns, or delivery-related product issues.',
                    'details_label' => 'What support needs from you',
                    'details' => [
                        'Describe the issue clearly and attach photos when you email support.',
                        'Include the order number and when the issue was first noticed.',
                        'Support may advise whether the case should be handled as a return review or a separate issue review.',
                    ],
                    'mailto' => $this->mailto('Product Issue Report - Ysabelle Retail', ['Hello Ysabelle Retail Support,', '', 'I need to report an item issue.', '', 'Order number:', 'Product name:', 'Issue noticed:', 'Photos available:', '', 'Thank you.']),
                ],
            ],
        ];
    }

    private function contactData(): array
    {
        return [
            'issues' => SupportTicketCategories::options(),
        ];
    }

    private function mailto(string $subject, array $bodyLines): string
    {
        $email = (string) config('storefront-support.contact.email');
        $query = http_build_query(
            [
                'subject' => $subject,
                'body' => implode("\n", $bodyLines),
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        return "mailto:{$email}?{$query}";
    }
}
