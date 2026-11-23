<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $limits = config('codesentinel.limits');
        $kb = (int) ceil($limits['max_file_bytes'] / 1024);

        return [
            'title' => ['nullable', 'string', 'max:120'],
            'mode' => ['required', Rule::in(['paste', 'upload'])],
            // Pasted code
            'code' => ['required_if:mode,paste', 'nullable', 'string', 'max:'.$limits['max_file_bytes']],
            'language' => ['required_if:mode,paste', 'nullable', Rule::in(array_keys(config('codesentinel.languages')))],
            // Uploaded files. Type is decided by our own intake check on the content, not the browser's claim.
            'files' => ['required_if:mode,upload', 'nullable', 'array', 'max:'.$limits['max_files']],
            'files.*' => ['file', 'max:'.$kb],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required_if' => 'Paste some code to analyse.',
            'code.max' => 'That is larger than the limit for pasted code.',
            'language.required_if' => 'Choose the language of the code.',
            'files.required_if' => 'Choose at least one file.',
            'files.max' => 'At most :max files per analysis.',
            'files.*.max' => 'Each file can be at most :max KB.',
        ];
    }
}
