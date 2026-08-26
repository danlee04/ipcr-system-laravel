<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateJobFunctionRequest extends FormRequest
{
    use LinksAFunction;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->functionRules();
    }

    public function messages(): array
    {
        return $this->functionMessages();
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateTheLink($validator);
    }
}
