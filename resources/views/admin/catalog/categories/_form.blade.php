@if ($errors->any())
    <div class="ys-admin-form-error">
        <ul class="space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="space-y-6" data-admin-form>
    @csrf
    @isset($method)
        @method($method)
    @endisset

    <section class="ys-admin-panel" data-admin-panel>
        <div class="ys-admin-grid-fields">
            <label class="ys-admin-field">
                <span class="ys-admin-label">Name</span>
                <input type="text" name="name" value="{{ old('name', $category->name) }}" class="ys-admin-input">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Slug</span>
                <input type="text" name="slug" value="{{ old('slug', $category->slug) }}" class="ys-admin-input">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Sort Order</span>
                <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" class="ys-admin-input">
            </label>
        </div>

        <label class="ys-admin-field mt-4">
            <span class="ys-admin-label">Description</span>
            <textarea name="description" class="ys-admin-textarea">{{ old('description', $category->description) }}</textarea>
        </label>

        <label class="mt-4 inline-flex items-center gap-3 text-sm text-ys-ivory/68">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))>
            Active category
        </label>
    </section>

    <div class="ys-admin-inline-actions">
        <button type="submit" class="ys-admin-button-primary" data-loading-label="Saving category...">{{ $submitLabel }}</button>
        <a href="{{ route('admin.catalog.categories.index') }}" class="ys-admin-button-secondary">Back to categories</a>
    </div>
</form>
