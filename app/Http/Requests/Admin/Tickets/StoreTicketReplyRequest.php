<?php

namespace App\Http\Requests\Admin\Tickets;

use App\Http\Requests\Concerns\HasReadableAttachmentMessages;
use App\Rules\NoMalware;
use App\Support\UploadLimits;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketReplyRequest extends FormRequest
{
    use HasReadableAttachmentMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:10000', 'required_without:attachments'],
            'is_internal' => ['boolean'],
            'reply_to_id' => ['nullable', 'integer', 'exists:ticket_replies,id'],
            'attachments' => ['nullable', 'array', 'min:1', 'max:'.UploadLimits::MAX_FILES, 'required_without:message'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,txt,xls,xlsx', 'mimetypes:image/jpeg,image/png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', new NoMalware],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->attachmentMessages(),
            'message.required_without' => 'Type a message or attach a file first.',
            'attachments.required_without' => 'Type a message or attach a file first.',
        ];
    }

    public function attributes(): array
    {
        return $this->attachmentAttributes();
    }
}
