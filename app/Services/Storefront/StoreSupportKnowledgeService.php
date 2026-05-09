<?php

namespace App\Services\Storefront;

use App\Services\Storefront\Assistant\StorefrontAssistantIntentRouter;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Arr;

class StoreSupportKnowledgeService
{
    public function __construct(
        private readonly SupportPageService $supportPages,
        private readonly AuthFactory $auth,
    ) {}

    public function responseForIntent(string $intent, ?string $topic = null): array
    {
        return match ($intent) {
            StorefrontAssistantIntentRouter::INTENT_CHECKOUT => $this->checkoutResponse(),
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_SHIPPING => $this->shippingResponse(),
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_RETURNS => $this->returnsResponse(),
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_CONTACT => $this->contactResponse(),
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_SIZE_GUIDE => $this->sizeGuideResponse(),
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_LOCATION => $this->locationResponse(),
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_LOGIN => $this->loginResponse(),
            StorefrontAssistantIntentRouter::INTENT_SUPPORT_SIGNUP => $this->signupResponse(),
            StorefrontAssistantIntentRouter::INTENT_GUIDANCE_SITE_USE => $this->siteUseResponse(),
            StorefrontAssistantIntentRouter::INTENT_GUIDANCE_IMAGE_SEARCH => $this->imageSearchResponse(),
            StorefrontAssistantIntentRouter::INTENT_GUIDANCE_ORDERING_FLOW => $this->orderingFlowResponse(),
            StorefrontAssistantIntentRouter::INTENT_SUPPORT => $this->policyResponse($topic ?? 'care'),
            default => $this->contactResponse(),
        };
    }

    private function shippingResponse(): array
    {
        $summary = $this->pageSummary(
            'shipping',
            'The checkout summary remains the source of truth for shipping charges, while the shipping guide explains the delivery flow and timing ranges.'
        );

        return $this->response(
            answer: 'Shipping is free for orders above PHP 5,000, while orders below that threshold use a PHP 350 delivery fee. '.$summary,
            actions: [
                $this->supportAction('shipping', 'Shipping Info'),
                $this->supportAction('contact', 'Contact Support'),
            ],
        );
    }

    private function returnsResponse(): array
    {
        $summary = $this->pageSummary(
            'returns',
            'Returns follow a guided support review so the team can confirm the right next step.'
        );

        return $this->response(
            answer: 'Ysabelle Retail currently supports a 14-day return window for eligible concerns. '.$summary,
            actions: [
                $this->supportAction('returns', 'Returns Info'),
                $this->supportAction('contact', 'Contact Support'),
            ],
        );
    }

    private function contactResponse(): array
    {
        $contact = $this->supportPages->contactDetails();
        $hours = filled($contact['hours'] ?? null) ? ' Support hours are '.$contact['hours'].'.' : '';

        return $this->response(
            answer: 'You can reach Ysabelle Retail support through the Contact page, by email at '
                .($contact['email'] ?? 'the listed support email')
                .', or by phone at '
                .($contact['phone_display'] ?? $contact['phone'] ?? 'the listed support number')
                .'.'.$hours,
            actions: [
                $this->supportAction('contact', 'Contact Support'),
                $this->supportAction('shipping', 'Shipping Info'),
            ],
        );
    }

    private function sizeGuideResponse(): array
    {
        $summary = $this->pageSummary(
            'size-guide',
            'Start with your usual size, then refine by fit preferences and use case.'
        );

        return $this->response(
            answer: 'The Size Guide helps you compare your usual shoe size with fit notes and category guidance before checkout. '.$summary,
            actions: [
                $this->supportAction('size-guide', 'Open Size Guide'),
                $this->supportAction('contact', 'Contact Support'),
            ],
        );
    }

    private function locationResponse(): array
    {
        $contact = $this->supportPages->contactDetails();
        $hours = filled($contact['hours'] ?? null) ? ' Support hours are '.$contact['hours'].'.' : '';

        return $this->response(
            answer: 'The storefront currently lists one support hub at '
                .($contact['address'] ?? 'the support address on the Contact page')
                .'. Separate branch listings are not available in the current storefront data yet.'
                .$hours,
            actions: [
                $this->supportAction('contact', 'Contact Support'),
                $this->supportAction('shipping', 'Shipping Info'),
            ],
        );
    }

    private function loginResponse(): array
    {
        return $this->response(
            answer: 'To log in, open Sign in, enter your email and password, then submit the form. If you do not have an account yet, use Create Account first.',
            actions: [
                ['label' => 'Login', 'type' => 'link', 'url' => route('login')],
                ['label' => 'Create Account', 'type' => 'link', 'url' => route('register')],
            ],
        );
    }

    private function signupResponse(): array
    {
        return $this->response(
            answer: 'To create an account, open Create Account, enter your email, display name, password, and password confirmation, then submit the form. If you already have an account, use Sign in instead.',
            actions: [
                ['label' => 'Create Account', 'type' => 'link', 'url' => route('register')],
                ['label' => 'Login', 'type' => 'link', 'url' => route('login')],
            ],
        );
    }

    private function siteUseResponse(): array
    {
        return $this->response(
            answer: 'You can browse the shop, open product pages for details, add pairs to your cart, and continue to checkout when you are ready. The storefront also includes Visual Search plus dedicated Size Guide, Shipping, Returns, and Contact pages if you need help.',
            actions: [
                ['label' => 'Browse Products', 'type' => 'link', 'url' => route('storefront.shop')],
                ['label' => 'Start Image Search', 'type' => 'panel', 'target' => 'visual-search'],
                $this->supportAction('size-guide', 'Open Size Guide'),
            ],
        );
    }

    private function imageSearchResponse(): array
    {
        return $this->response(
            answer: 'To use image search, open Visual Search, upload a clear shoe photo or screenshot, add optional refinements like color or category, then submit. Review the suggested matches and refine again if you need a closer result.',
            actions: [
                ['label' => 'Start Image Search', 'type' => 'panel', 'target' => 'visual-search'],
                ['label' => 'Browse Products', 'type' => 'link', 'url' => route('storefront.shop')],
            ],
        );
    }

    private function orderingFlowResponse(): array
    {
        return $this->response(
            answer: 'To order, browse or search the catalog, open a product, choose a size if needed, and add it to your cart. Then open the cart, continue to checkout, sign in if required, and complete the shipping and payment details shown there.',
            actions: [
                ['label' => 'Browse Products', 'type' => 'link', 'url' => route('storefront.shop')],
                ['label' => 'Open Cart', 'type' => 'link', 'url' => route('storefront.cart.index')],
                $this->checkoutAction(),
            ],
        );
    }

    private function checkoutResponse(): array
    {
        $signedIn = $this->auth->guard('web')->check();

        return $this->response(
            answer: 'To check out, review your cart, continue to checkout, and complete the required shipping details. '
                .($signedIn
                    ? 'You can go straight to the checkout form from your cart.'
                    : 'You will need to sign in with a customer account before placing the order.')
                .' The checkout flow currently shows Cash on Delivery and Card (simulated) as the available payment options.',
            actions: [
                ['label' => 'Open Cart', 'type' => 'link', 'url' => route('storefront.cart.index')],
                $this->checkoutAction(),
            ],
        );
    }

    private function policyResponse(string $topic): array
    {
        $policies = config('storefront.assistant.policies', []);
        $answer = Arr::get($policies, $topic, 'I can help with shipping, returns, size guidance, contact details, checkout help, and catalog image search.');

        return $this->response(
            answer: $answer,
            actions: [
                $this->supportAction('contact', 'Contact Support'),
                $this->supportAction('shipping', 'Shipping Info'),
            ],
        );
    }

    private function supportAction(string $pageKey, string $label): array
    {
        return [
            'label' => $label,
            'type' => 'link',
            'url' => route($this->supportRouteName($pageKey)),
        ];
    }

    private function checkoutAction(): array
    {
        $signedIn = $this->auth->guard('web')->check();

        return [
            'label' => $signedIn ? 'Go to Checkout' : 'Login',
            'type' => 'link',
            'url' => $signedIn ? route('storefront.checkout.create') : route('login'),
        ];
    }

    private function supportRouteName(string $pageKey): string
    {
        $page = $this->supportPages->page($pageKey);

        return (string) Arr::get($page, 'route', 'storefront.support.contact');
    }

    private function pageSummary(string $pageKey, string $fallback): string
    {
        $page = $this->supportPages->page($pageKey);

        return (string) Arr::get($page, 'summary', $fallback);
    }

    private function response(string $answer, array $actions): array
    {
        return [
            'answer' => $answer,
            'products' => [],
            'actions' => $actions,
        ];
    }
}
