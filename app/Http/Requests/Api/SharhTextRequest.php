<?php

namespace App\Http\Requests\Api;

class SharhTextRequest extends BaseApiRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('text') !== null) {
            $this->merge(['text' => $this->route('text')]);
        }
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'Search text cannot be empty',
        ];
    }
}
