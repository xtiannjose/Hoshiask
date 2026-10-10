@props(['fileNote' => false])

{{-- Lists every validation problem at the top of a form, each linking to its field. A page reload
     puts the top of the form on screen, so without this an error far down the page looks like nothing happened. --}}
@if ($errors->any())
    <div
        data-error-summary
        tabindex="-1"
        role="alert"
        class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
    >
        <p class="font-semibold">
            {{ $errors->count() === 1 ? 'There is 1 problem to fix before this can be sent.' : 'There are '.$errors->count().' problems to fix before this can be sent.' }}
        </p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach ($errors->messages() as $field => $messages)
                <li>
                    <a href="#{{ \Illuminate\Support\Str::before($field, '.') }}" class="font-medium underline">{{ $messages[0] }}</a>
                </li>
            @endforeach
        </ul>
        @if ($fileNote)
            <p class="mt-3 font-medium">Your file choice was not kept. Please choose your file(s) again before sending.</p>
        @endif
    </div>
@endif
