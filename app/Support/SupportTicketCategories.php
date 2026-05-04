<?php

namespace App\Support;

class SupportTicketCategories
{
    public static function options(): array
    {
        return [
            'order-issue' => [
                'label' => 'Order Issue',
                'summary' => 'Use this for delivery concerns, checkout follow-up, or order detail mismatches.',
                'reference_label' => 'Order number',
                'reference_placeholder' => 'Example: YS-10425',
                'detail_label' => 'Issue details',
                'detail_placeholder' => 'Tell support what happened, what you expected, and any helpful context.',
                'mail_subject' => 'Support Request: Order Issue',
            ],
            'size-help' => [
                'label' => 'Size Help',
                'summary' => 'Best for fit guidance before or after purchase when you need a more grounded recommendation.',
                'reference_label' => 'Usual size or product name',
                'reference_placeholder' => 'Example: US 8.5 / Aurum Runner',
                'detail_label' => 'Fit details',
                'detail_placeholder' => 'Share your usual shoe size, foot width, and whether you like a snug or relaxed fit.',
                'mail_subject' => 'Support Request: Size Help',
            ],
            'return-request' => [
                'label' => 'Return Request',
                'summary' => 'Start here if you need help within the 14-day window for an eligible pair.',
                'reference_label' => 'Order number',
                'reference_placeholder' => 'Example: YS-10425',
                'detail_label' => 'Return details',
                'detail_placeholder' => 'Mention the item condition, the reason, and whether original packaging is available.',
                'mail_subject' => 'Support Request: Return Request',
            ],
            'product-inquiry' => [
                'label' => 'Product Inquiry',
                'summary' => 'Ask about a style, category, sizing direction, or use-case fit before ordering.',
                'reference_label' => 'Product name or category',
                'reference_placeholder' => 'Example: Maison Drift / Lifestyle shoes',
                'detail_label' => 'What do you need to know?',
                'detail_placeholder' => 'Ask about fit, use case, stock direction, or styling guidance.',
                'mail_subject' => 'Support Request: Product Inquiry',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::options());
    }

    public static function labelFor(string $category): string
    {
        return self::options()[$category]['label'] ?? 'Support Request';
    }

    public static function mailSubjectFor(string $category): string
    {
        return self::options()[$category]['mail_subject'] ?? 'Support Request';
    }
}
