{{-- Type-ahead lists for the province and city/municipality boxes (see resources/js/lib/location-suggestions.js).
     The city list starts empty and is filled once a province has been chosen. --}}
<datalist id="province-options">
    @foreach(\App\Support\PhilippineLocations::provinces() as $provinceOption)
        <option value="{{ $provinceOption }}"></option>
    @endforeach
</datalist>
<datalist id="municipality-options"></datalist>
