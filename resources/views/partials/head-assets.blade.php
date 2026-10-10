{{-- Shared <head> assets for every layout: favicons, fonts, the theme initializer and the app bundle. --}}
<link rel="icon" type="image/png" href="{{ asset('images/Hoshishi Icon.png') }}">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
<link rel="apple-touch-icon" href="{{ asset('images/Hoshishi Icon.png') }}">
@include('partials.theme-initializer')
@foreach(\App\Support\FontPreloads::urls() as $fontUrl)
    <link rel="preload" as="font" type="font/woff2" href="{{ $fontUrl }}" crossorigin>
@endforeach
@vite(['resources/css/app.css', 'resources/js/app.js'])
