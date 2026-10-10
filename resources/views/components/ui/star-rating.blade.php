@props(['idPrefix' => 'rating', 'value' => null, 'invalid' => false])

{{-- Tappable 1-5 star rating. Real radio buttons underneath, so it works with the keyboard (arrow keys),
     screen readers and a form post, and it needs no script. The stars are listed 5 to 1 in the markup and
     drawn in reverse so "this star and every star before it" can be filled with plain CSS. --}}
@php
    $labels = [5 => 'Excellent', 4 => 'Good', 3 => 'Average', 2 => 'Poor', 1 => 'Very poor'];
    $selected = $value === null || $value === '' ? null : (int) $value;
@endphp
<fieldset class="star-rating" data-star-rating aria-invalid="{{ $invalid ? 'true' : 'false' }}">
    <legend class="form-label">Your rating <span class="text-red-500" aria-hidden="true">*</span><span class="sr-only">(required)</span></legend>
    <div class="star-rating__stars">
        @foreach($labels as $number => $text)
            <input
                type="radio"
                name="rating"
                id="{{ $idPrefix }}-{{ $number }}"
                value="{{ $number }}"
                class="sr-only"
                {{ $selected === $number ? 'checked' : '' }}
                @if($loop->first) required @endif
            >
            <label for="{{ $idPrefix }}-{{ $number }}" title="{{ $number }} {{ $number === 1 ? 'star' : 'stars' }}: {{ $text }}">
                <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                <span class="sr-only">{{ $number }} {{ $number === 1 ? 'star' : 'stars' }}, {{ $text }}</span>
            </label>
        @endforeach
    </div>
    <p class="mt-1 text-xs text-slate-500">Tap a star: 1 is very poor, 5 is excellent.</p>
</fieldset>
