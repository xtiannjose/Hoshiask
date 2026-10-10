@php
    $themeInitializerPath = public_path('js/theme-initializer.js');
    $themeInitializerVersion = file_exists($themeInitializerPath)
        ? (string) filemtime($themeInitializerPath)
        : '1';
@endphp
<script src="{{ asset('js/theme-initializer.js') }}?v={{ $themeInitializerVersion }}"></script>
