<?php

namespace App\Http\Requests;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => ['required', Rule::in(['property', 'appraisal', 'contact'])],
            'property_id' => ['nullable', 'exists:properties,id'],
            'name'        => ['required', 'string', 'max:120'],
            'email'       => ['required', 'email:rfc', 'max:180'],
            'phone'       => ['nullable', 'string', 'max:40', function ($attribute, $value, $fail) {
                // Backend is the source of truth: a phone must reduce to a
                // plausible E.164 number, not "call me" or 40 random letters.
                $normalized = Phone::normalize($value);

                if ($normalized === null || ! preg_match('/^\+\d{7,15}$/', $normalized)) {
                    $fail('That phone number does not look right.');
                }
            }],
            'message'     => ['nullable', 'string', 'max:4000'],

            // Appraisal-only fields, collected into `details`.
            'address'       => ['nullable', 'string', 'max:200'],
            'suburb'        => ['nullable', 'string', 'max:120'],
            'property_type' => ['nullable', 'string', 'max:40'],
            'bedrooms'      => ['nullable', 'integer', 'min:0', 'max:20'],
            'timeframe'     => ['nullable', 'string', 'max:80'],

            // Honeypot - real people never fill this in.
            'company' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'company.prohibited' => 'Your message could not be sent.',
            'name.required'      => 'Please tell us your name.',
            'email.email'        => 'That email address does not look right.',
            'phone.regex'        => 'That phone number does not look right.',
        ];
    }

    public function appraisalDetails(): ?array
    {
        if ($this->input('type') !== 'appraisal') {
            return null;
        }

        return array_filter([
            'address'       => $this->input('address'),
            'suburb'        => $this->input('suburb'),
            'property_type' => $this->input('property_type'),
            'bedrooms'      => $this->input('bedrooms'),
            'timeframe'     => $this->input('timeframe'),
        ], fn($value) => $value !== null && $value !== '');
    }
}
