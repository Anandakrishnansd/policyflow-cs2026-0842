<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_request_id' => ['required', 'string', 'max:64'],
            'agent_id' => ['required', 'string', 'max:64'],
            'customer_name' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'destination' => ['required', 'string', 'in:DOMESTIC,INTERNATIONAL,domestic,international'],
            'trip_start' => ['required', 'date_format:Y-m-d'],
            'trip_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:trip_start'],
            'plan_code' => ['required', 'string', 'in:BASIC,PLUS,PREMIUM,basic,plus,premium'],
            'sum_insured' => ['required', 'integer', 'in:100000,300000,500000'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_request_id.required' => 'The client_request_id field is required.',
            'agent_id.required' => 'The agent_id field is required.',
            'customer_name.required' => 'The customer_name field is required.',
            'date_of_birth.before' => 'Date of birth must be a past date.',
            'destination.in' => 'Supported destinations are: DOMESTIC, INTERNATIONAL.',
            'trip_end.after_or_equal' => 'The trip_end date must be greater than or equal to trip_start.',
            'plan_code.in' => 'Supported plans are: BASIC, PLUS, PREMIUM.',
            'sum_insured.in' => 'Supported coverage sums are: 100000, 300000, 500000.',
        ];
    }

    /**
     * Return structured JSON 422 validation response for API callers.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => 'validation_error',
            'message' => 'The submitted policy request failed validation checks.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
