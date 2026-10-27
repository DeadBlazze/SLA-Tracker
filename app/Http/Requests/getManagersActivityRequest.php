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
            'datetimeFrom' => ['required', 'date'],
            'datetimeTo'   => ['required', 'date', 'after_or_equal:datetimeFrom'],
            'groupBy' => ['required', 'in:status,leadId']
        ];
    }
}
