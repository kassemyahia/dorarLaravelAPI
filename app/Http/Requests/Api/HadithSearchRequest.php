<?php

namespace App\Http\Requests\Api;

class HadithSearchRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'value' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('page')) {
            $this->merge(['page' => 1]);
        }
    }
}
