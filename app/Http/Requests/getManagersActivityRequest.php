<?php

namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class getManagersActivityRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'timestampFrom' => ['required', 'integer', 'min:0'],
            'timestampTo'   => ['required', 'integer', 'min:0', 'gte:timestampFrom'],
            'groupBy' => ['required', 'in:status,leadId']
        ];
    }
}
