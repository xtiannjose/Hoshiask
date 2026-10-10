<?php

namespace App\Http\Requests\Admin\Departments;

use App\Models\Department;
use App\Rules\UniqueDepartmentName;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Department $department */
        $department = $this->route('department');

        return auth()->user()?->can('update', $department) ?? false;
    }

    public function rules(): array
    {
        /** @var Department $department */
        $department = $this->route('department');

        return [
            'name' => ['required', 'string', 'max:255', new UniqueDepartmentName($department->id)],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,bmp,gif', 'mimetypes:image/png,image/jpeg,image/webp,image/bmp,image/gif', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);
    }
}
