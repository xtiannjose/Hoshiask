// Fills the city/municipality suggestion list with the cities of whichever province is typed
// in the province box. Typing still works without it: the server checks the pair either way.

export const buildMunicipalitiesUrl = (baseUrl, province) => {
    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set('province', province);

    return url.toString();
};

export const registerLocationSuggestions = () => {
    const municipalityInput = document.querySelector('input[data-location-municipalities-url]');
    const provinceInput = document.querySelector('input[data-location-province]');
    const datalist = document.getElementById('municipality-options');
    if (!municipalityInput || !provinceInput || !datalist) return;

    const baseUrl = municipalityInput.dataset.locationMunicipalitiesUrl;
    const cache = new Map();
    let latestRequest = 0;

    const render = (municipalities) => {
        datalist.replaceChildren(...municipalities.map((name) => {
            const option = document.createElement('option');
            option.value = name;

            return option;
        }));
    };

    const load = async () => {
        const province = provinceInput.value.trim();
        if (province === '') {
            render([]);
            return;
        }

        const key = province.toLowerCase();
        if (cache.has(key)) {
            render(cache.get(key));
            return;
        }

        const requestId = ++latestRequest;

        try {
            const response = await fetch(buildMunicipalitiesUrl(baseUrl, province), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) return;

            const data = await response.json();
            const municipalities = Array.isArray(data.municipalities) ? data.municipalities : [];
            cache.set(key, municipalities);

            // Ignore an answer for a province the user has already typed past.
            if (requestId === latestRequest) {
                render(municipalities);
            }
        } catch {
            // No suggestions is fine; the field is still a normal text box.
        }
    };

    provinceInput.addEventListener('change', load);
    provinceInput.addEventListener('blur', load);
    void load();
};
