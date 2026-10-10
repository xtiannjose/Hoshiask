<?php

namespace App\Http\Requests\Client\Tickets;

use App\Http\Requests\Concerns\HasReadableAttachmentMessages;
use App\Http\Requests\Concerns\ValidatesPhilippineLocation;
use App\Rules\NoMalware;
use App\Rules\PhoneNumber;
use App\Support\UploadLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    use HasReadableAttachmentMessages;
    use ValidatesPhilippineLocation;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:30', new PhoneNumber],
            'email' => ['required', 'email', 'max:255'],
            'province' => $this->provinceRule(),
            'municipality' => $this->municipalityRule(),
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:50000'],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'ticket_type' => ['prohibited'],
            'ticket_consent' => ['accepted'],
            'attachments' => ['nullable', 'array', 'max:'.UploadLimits::MAX_FILES],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,txt,xls,xlsx', 'mimetypes:image/jpeg,image/png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', new NoMalware],
        ];
    }

    public function messages(): array
    {
        return $this->attachmentMessages();
    }

    public function attributes(): array
    {
        return $this->attachmentAttributes();
    }
}
