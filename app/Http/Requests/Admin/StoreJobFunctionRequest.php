<?php

namespace App\Http\Requests\Admin;

use App\Enums\FunctionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJobFunctionRequest extends FormRequest
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
        $this->validateTheRubric($validator);
    }
}
