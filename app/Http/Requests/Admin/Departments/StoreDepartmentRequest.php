<?php

namespace App\Http\Requests\Admin\Departments;

use App\Models\Department;
use App\Rules\UniqueDepartmentName;
use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('create', Department::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new UniqueDepartmentName],
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,bmp,gif', 'mimetypes:image/png,image/jpeg,image/webp,image/bmp,image/gif', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);
    }
}
