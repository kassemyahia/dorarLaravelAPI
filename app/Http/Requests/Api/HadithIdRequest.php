<?php

namespace App\Http\Requests\Api;

class HadithIdRequest extends BaseApiRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('id') !== null) {
            $this->merge(['id' => $this->route('id')]);
        }
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'regex:/^[0-9a-zA-Z-_]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'Hadith ID is required',
            'id.regex' => 'Invalid hadith ID format',
        ];
    }
}
