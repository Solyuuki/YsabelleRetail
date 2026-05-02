<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupportPageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $pageKey = (string) $request->route('page');
        $page = config("storefront-support.pages.{$pageKey}");

        if (! is_array($page)) {
            throw new NotFoundHttpException;
        }

        return view('storefront.support.show', [
            'page' => $page,
        ]);
    }
}
