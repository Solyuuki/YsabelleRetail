<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\SupportPageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupportPageController extends Controller
{
    public function __construct(
        private readonly SupportPageService $supportPageService
    ) {}

    public function __invoke(Request $request): View
    {
        $pageKey = (string) $request->route('page');
        $page = $this->supportPageService->page($pageKey);

        if (! is_array($page)) {
            throw new NotFoundHttpException;
        }

        return view('storefront.support.show', [
            'page' => $page,
            'supportContact' => $this->supportPageService->contactDetails(),
        ]);
    }
}
