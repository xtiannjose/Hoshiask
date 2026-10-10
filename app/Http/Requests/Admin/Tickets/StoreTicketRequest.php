<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Http\Requests\Concerns\HasReadableAttachmentMessages;
use App\Http\Requests\Concerns\NormalizesAssignedToInput;
use App\Http\Requests\Concerns\ResolvesAssignableAgentRule;
use App\Http\Requests\Concerns\ValidatesPhilippineLocation;
use App\Http\Requests\Concerns\ValidatesTicketLifecycleDates;
use App\Models\Ticket;
use App\Models\User;
use App\Rules\NoMalware;
use App\Rules\PhoneNumber;
use App\Support\UploadLimits;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    use HasReadableAttachmentMessages;
    use NormalizesAssignedToInput;
    use ResolvesAssignableAgentRule;
    use ValidatesPhilippineLocation;
    use ValidatesTicketLifecycleDates;

    public function authorize(): bool
    {
        return (bool) $this->user()?->canCreateClientTickets();
    }

    public function rules(): array
    {
        $ticketType = Ticket::normalizeTicketTypeValue($this->input('ticket_type'));
        $requesterRoles = $ticketType === Ticket::TYPE_INTERNAL
            ? User::TICKET_ASSIGNABLE_ROLES
            : [User::ROLE_CLIENT];
        $canSetManualDates = (bool) $this->user()?->canSetManualTicketDates();
        $manualDateRule = $canSetManualDates
            ? ['nullable', 'date', 'before_or_equal:now']
            : ['prohibited'];

        return [
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereIn('role', $requesterRoles)
                    ->where('is_active', true)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30', new PhoneNumber],
            'email' => ['nullable', 'email', 'max:255'],
            'province' => $this->provinceRule(),
            'municipality' => $this->municipalityRule(),
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:50000'],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'ticket_type' => ['required', Rule::in(Ticket::TYPES)],
            'assigned_to' => $ticketType === Ticket::TYPE_INTERNAL
                ? ['required', 'array', 'min:1']
                : ['prohibited'],
            'assigned_to.*' => $ticketType === Ticket::TYPE_INTERNAL
                ? [$this->assignableAgentRule()]
                : ['prohibited'],
            'attachments' => ['nullable', 'array', 'max:'.UploadLimits::MAX_FILES],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,txt,xls,xlsx', 'mimetypes:image/jpeg,image/png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', new NoMalware],
            'created_at' => $manualDateRule,
            'resolved_at' => $manualDateRule,
            'closed_at' => $manualDateRule,
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateTicketLifecycleDateOrder($validator));
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAssignedToInput();

        $contactNumber = trim((string) $this->input('contact_number', ''));
        $email = trim((string) $this->input('email', ''));

        if ($this->has('ticket_type')) {
            $this->merge([
                'ticket_type' => Ticket::normalizeTicketTypeValue($this->input('ticket_type')),
                'contact_number' => $contactNumber !== '' ? $contactNumber : null,
                'email' => $email !== '' ? $email : null,
            ]);

            return;
        }

        $this->merge([
            'contact_number' => $contactNumber !== '' ? $contactNumber : null,
            'email' => $email !== '' ? $email : null,
        ]);
    }
}
