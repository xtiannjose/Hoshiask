<?php

namespace App\Http\Requests\Concerns;

use App\Support\PhilippineLocations;
use Closure;

trait ValidatesPhilippineLocation
{
    /**
     * @return array<int, mixed>
     */
    protected function provinceRule(): array
    {
        return [
            'required',
            'string',
            'max:120',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! PhilippineLocations::isValidProvince((string) $value)) {
                    $fail('"'.trim((string) $value).'" is not a recognized province. Start typing and pick one from the list (use NCR for Metro Manila).');
                }
            },
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function municipalityRule(): array
    {
        return [
            'required',
            'string',
            'max:120',
            function (string $attribute, mixed $value, Closure $fail): void {
                $province = $this->string('province')->toString();
                if (! PhilippineLocations::isValidProvince($province)) {
                    return; // the province rule already reports its own error
                }

                if (! PhilippineLocations::isValidPair($province, (string) $value)) {
                    $fail('"'.trim((string) $value).'" is not a recognized city or municipality in '.trim($province).'. Start typing and pick one from the list.');
                }
            },
        ];
    }
}
