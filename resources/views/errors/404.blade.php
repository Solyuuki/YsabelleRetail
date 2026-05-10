@extends('errors.layout', [
    'title' => '404 | Ysabelle Retail',
    'status' => '404',
    'headline' => 'Page not found',
    'copy' => 'The page you requested is unavailable or may have moved. Return to the storefront and continue from a known route.',
    'primaryActionLabel' => 'Browse storefront',
    'primaryActionUrl' => route('storefront.home'),
])
