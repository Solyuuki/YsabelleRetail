@extends('errors.layout', [
    'title' => '500 | Ysabelle Retail',
    'status' => '500',
    'headline' => 'Something went wrong',
    'copy' => 'The request could not be completed right now. Please try again shortly or return to the storefront while the issue is resolved.',
    'primaryActionLabel' => 'Try again later',
    'primaryActionUrl' => route('storefront.home'),
])
