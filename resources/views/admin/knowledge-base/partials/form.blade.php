@php
    $article = $article ?? null;
@endphp
<div>
    <label for="title" class="form-label">Title <span class="text-red-500">*</span></label>
    <input type="text" name="title" id="title" value="{{ old('title', $article?->title) }}" required maxlength="255" class="form-input @error('title') border-red-300 @enderror">
    @error('title')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="category" class="form-label">Category</label>
    <select name="category" id="category" class="form-input">
        @foreach($categories as $category)
            <option value="{{ $category->value }}" {{ old('category', $article?->category) === $category->value ? 'selected' : '' }}>{{ $category->label() }}</option>
        @endforeach
    </select>
</div>

<div>
    <label for="body" class="form-label">Content <span class="text-red-500">*</span></label>
    <textarea name="body" id="body" rows="12" required class="form-input @error('body') border-red-300 @enderror" placeholder="Write the article content here...">{{ old('body', $article?->body) }}</textarea>
    @error('body')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<label class="flex items-center gap-2 text-sm text-slate-700">
    <input type="checkbox" name="is_published" value="1" class="ticket-checkbox" {{ old('is_published', $article?->is_published) ? 'checked' : '' }}>
    Published (visible to field offices)
</label>
