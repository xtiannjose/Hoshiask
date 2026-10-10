<?php

namespace App\Http\Requests\Legal;

use Illuminate\Foundation\Http\FormRequest;

class AcceptLegalConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
            'accept_platform_consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'accept_terms.accepted' => 'Please review and accept the Terms of Service to continue.',
            'accept_privacy.accepted' => 'Please review and accept the Privacy Notice and Consent to continue.',
            'accept_platform_consent.accepted' => 'Please confirm the platform consent statement to continue.',
        ];
    }
}
