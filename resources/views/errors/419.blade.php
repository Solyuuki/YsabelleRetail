@extends('errors.layout', [
    'title' => '419 | Ysabelle Retail',
    'status' => '419',
    'headline' => 'Session expired',
    'copy' => 'Your session is no longer valid. Refresh the page or sign in again before continuing with protected actions.',
    'primaryActionLabel' => 'Sign in again',
    'primaryActionUrl' => route('login'),
])
