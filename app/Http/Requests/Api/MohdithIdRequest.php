<?php

namespace App\Http\Requests\Api;

class MohdithIdRequest extends BaseApiRequest
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
            'id' => ['required', 'string', 'regex:/^[0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'Mohdith ID is required',
            'id.regex' => 'Invalid mohdith ID format',
        ];
    }
}
