<?php

namespace App\Http\Requests\Lab;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-lab');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // TODO(client): confirm the size limit (PROJECT.md §9).
            'report' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'report.required' => 'Choose the report PDF.',
            'report.mimes' => 'Upload the report as a PDF.',
            'report.max' => 'The report must be 20 MB or smaller.',
        ];
    }
}
