<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait NormalizesAssignedToInput
{
    protected function prepareForValidation(): void
    {
        $this->normalizeAssignedToInput();
    }

    protected function normalizeAssignedToInput(): void
    {
        if (! $this->exists('assigned_to')) {
            return;
        }

        $rawAssignedTo = $this->input('assigned_to');
        $normalizedAssignedTo = collect(is_array($rawAssignedTo) ? $rawAssignedTo : [$rawAssignedTo])
            ->map(function ($value) {
                if ($value === null) {
                    return null;
                }

                $normalized = trim((string) $value);

                return $normalized === '' ? null : $normalized;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'assigned_to' => $normalizedAssignedTo,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('assigned_to')) {
                return;
            }

            foreach (array_keys($validator->errors()->messages()) as $key) {
                if (! str_starts_with($key, 'assigned_to.')) {
                    continue;
                }

                $validator->errors()->add('assigned_to', $validator->errors()->first($key));

                return;
            }
        });
    }
}
