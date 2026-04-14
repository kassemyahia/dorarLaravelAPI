<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        $message = implode('. ', $validator->errors()->all());

        throw new HttpResponseException(response()->json([
            'status' => 'fail',
            'message' => $message,
        ], 400));
    }
}
