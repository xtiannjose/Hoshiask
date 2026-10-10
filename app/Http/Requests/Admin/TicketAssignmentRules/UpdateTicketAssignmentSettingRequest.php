<?php

namespace App\Http\Requests\Admin\TicketAssignmentRules;

use App\Models\TicketAssignmentSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketAssignmentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'distribution_method' => ['required', 'string', Rule::in(TicketAssignmentSetting::METHODS)],
        ];
    }
}
