@extends('errors.layout', [
    'title' => '500 | Ysabelle Retail',
    'status' => '500',
    'eyebrow' => 'Unexpected interruption',
    'headline' => 'Something went wrong',
    'copy' => 'The request could not be completed right now. No private system details are shown here, and you can safely return to the storefront or contact support if the issue continues.',
    'chips' => [
        'Sensitive technical details stay hidden in production.',
        'You can retry later without using this page as a dead end.',
    ],
    'guidanceTitle' => 'Safe recovery',
    'guidanceCopy' => 'Start from the storefront again, or contact support if you were in the middle of an important customer task and need help continuing.',
    'actions' => [
        ['label' => 'Return to storefront', 'url' => route('storefront.home'), 'variant' => 'primary'],
        ['label' => 'Contact support', 'url' => route('storefront.support.contact')],
        ['label' => 'Email support', 'url' => 'mailto:ysabelleretail@gmail.com'],
    ],
])
