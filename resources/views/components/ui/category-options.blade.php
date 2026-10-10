@props([
    'categories' => [],
    'selected' => null,
])

@foreach($categories as $category)
    <option value="{{ $category->id }}" {{ (string) $selected === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
@endforeach
