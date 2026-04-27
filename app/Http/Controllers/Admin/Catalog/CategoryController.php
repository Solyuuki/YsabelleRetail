<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\SaveCategoryRequest;
use App\Models\Catalog\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $categories = Category::query()
            ->withCount(['products', 'products as active_products_count' => fn ($query) => $query->where('status', 'active')])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('admin.catalog.categories.index', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.catalog.categories.create', [
            'category' => new Category([
                'is_active' => true,
                'sort_order' => 0,
            ]),
        ]);
    }

    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        $category = Category::query()->create($data);

        return redirect()
            ->route('admin.catalog.categories.edit', $category)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Category created',
                'message' => "{$category->name} is ready for product assignment.",
            ]);
    }

    public function edit(Category $category): View
    {
        return view('admin.catalog.categories.edit', [
            'category' => $category->loadCount(['products', 'products as active_products_count' => fn ($query) => $query->where('status', 'active')]),
        ]);
    }

    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        $category->update($data);

        return redirect()
            ->route('admin.catalog.categories.edit', $category)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Category updated',
                'message' => "{$category->name} was updated successfully.",
            ]);
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->where('status', 'active')->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'title' => 'Deletion blocked',
                'message' => 'Archive or reassign active products before deleting this category.',
            ]);
        }

        $category->delete();

        return redirect()
            ->route('admin.catalog.categories.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Category deleted',
                'message' => "{$category->name} was removed successfully.",
            ]);
    }
}
