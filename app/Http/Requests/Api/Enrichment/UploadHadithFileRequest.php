<?php

namespace App\Http\Requests\Api\Enrichment;

use App\Http\Requests\Api\BaseApiRequest;

class UploadHadithFileRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:json', 'max:104857600'], // Max 100MB
            'delay_ms' => ['nullable', 'integer', 'min:1000', 'max:30000'],
            'confidence_threshold_low' => ['nullable', 'numeric', 'between:0,1'],
            'confidence_threshold_medium' => ['nullable', 'numeric', 'between:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A JSON file is required',
            'file.mimes' => 'Only JSON files are allowed',
            'file.max' => 'File size must not exceed 100MB',
            'delay_ms.min' => 'Delay must be at least 1000ms',
            'delay_ms.max' => 'Delay must not exceed 30000ms',
        ];
    }
}
