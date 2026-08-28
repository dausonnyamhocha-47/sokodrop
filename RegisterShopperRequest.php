<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * RegisterShopperRequest
 *
 * Validates the combined User + ShopperProfile payload submitted when a
 * new delivery agent signs up. Kept as one FormRequest (rather than two)
 * because the registration form is a single atomic submission from the
 * frontend RegisterShopper.jsx page.
 */
class RegisterShopperRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint — anyone can apply to become a shopper.
        return true;
    }

    public function rules(): array
    {
        return [
            // User account fields
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone', 'regex:/^\+?[0-9]{9,15}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            // ShopperProfile / KYC fields
            'nida_number' => ['required', 'string', 'max:30', 'unique:users,nida_number'],
            'id_number' => ['required', 'string', 'max:30'],
            'id_document_path' => ['nullable', 'string'],
            'vehicle_type' => ['required', 'in:bicycle,motorcycle,car,on_foot'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Barua pepe hii tayari imesajiliwa. (This email is already registered.)',
            'phone.unique' => 'Namba ya simu hii tayari imesajiliwa. (This phone number is already registered.)',
            'nida_number.unique' => 'Namba ya NIDA hii tayari imesajiliwa. (This NIDA number is already registered.)',
            'phone.regex' => 'Namba ya simu si sahihi. (Phone number format is invalid.)',
            'vehicle_type.in' => 'Aina ya usafiri iliyochaguliwa si sahihi.',
        ];
    }

    /**
     * Return a consistent JSON error envelope on validation failure instead
     * of Laravel's default redirect-based response (this is an API-only app).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Uthibitishaji umeshindikana.',
            'errors' => $validator->errors(),
        ], 422));
    }
}