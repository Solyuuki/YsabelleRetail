@extends('errors.layout', [
    'title' => '429 | Ysabelle Retail',
    'status' => '429',
    'eyebrow' => 'Temporary cooldown',
    'headline' => 'Too many attempts detected',
    'copy' => 'We are temporarily slowing this action down to protect customer accounts, forms, and support flows. Please wait a moment before trying again.',
    'chips' => [
        'This cooldown is temporary.',
        'Protected actions stay rate-limited for account and form safety.',
    ],
    'guidanceTitle' => 'What to do next',
    'guidanceCopy' => 'Pause briefly, then retry once the cooldown passes. If the problem keeps happening during normal use, contact support so the team can help you finish safely.',
    'actions' => [
        ['label' => 'Return to storefront', 'url' => route('storefront.home'), 'variant' => 'primary'],
        ['label' => 'Contact support', 'url' => route('storefront.support.contact')],
    ],
])
