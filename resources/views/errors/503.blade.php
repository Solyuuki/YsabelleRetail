@extends('errors.layout', [
    'title' => '503 | Ysabelle Retail',
    'status' => '503',
    'eyebrow' => 'Service maintenance',
    'headline' => 'We are preparing the store',
    'copy' => 'Ysabelle Retail is temporarily unavailable while maintenance is being completed. Please check back shortly for the full storefront experience.',
    'chips' => [
        'This is a temporary service window.',
        'The storefront brand experience is preserved while maintenance is active.',
    ],
    'guidanceTitle' => 'What to expect',
    'guidanceCopy' => 'Please try again shortly. If you need urgent assistance while maintenance is in progress, contact the support team directly.',
    'actions' => [
        ['label' => 'Try the storefront again', 'url' => route('storefront.home'), 'variant' => 'primary'],
        ['label' => 'Email support', 'url' => 'mailto:ysabelleretail@gmail.com'],
    ],
])
