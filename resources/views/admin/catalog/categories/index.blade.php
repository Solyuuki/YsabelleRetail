@extends('layouts.app', ['title' => 'Admin Categories'])

@section('content')
    <div class="mb-8">
        <p class="text-sm uppercase tracking-[0.3em] text-amber-300">Admin Catalog</p>
        <h1 class="mt-2 text-3xl font-semibold text-white">Category Operations</h1>
        <p class="mt-3 text-stone-300">Management routes now have their own admin namespace and can grow into full CRUD safely.</p>
    </div>

    <div class="overflow-hidden rounded-3xl border border-white/10 bg-white/5">
        <table class="min-w-full divide-y divide-white/10 text-left">
            <thead class="bg-white/5 text-xs uppercase tracking-[0.25em] text-stone-400">
                <tr>
                    <th class="px-6 py-4">Category</th>
                    <th class="px-6 py-4">Products</th>
                    <th class="px-6 py-4">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-sm text-stone-200">
                @forelse ($categories as $category)
                    <tr>
                        <td class="px-6 py-4">{{ $category->name }}</td>
                        <td class="px-6 py-4">{{ $category->products_count ?? 0 }}</td>
                        <td class="px-6 py-4">{{ $category->is_active ? 'Active' : 'Inactive' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-6 text-stone-400">No categories yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
