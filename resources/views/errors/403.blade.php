@extends('errors.layout', [
    'title' => '403 | Ysabelle Retail',
    'status' => '403',
    'headline' => 'Access denied',
    'copy' => 'This area is reserved for a different access level. Sign in with the correct role or return to an allowed page.',
    'primaryActionLabel' => 'Go to sign in',
    'primaryActionUrl' => route('login'),
])
