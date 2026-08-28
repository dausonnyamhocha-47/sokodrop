<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * StoreOrderRequest
 *
 * Validates a VIP client's checkout submission: a delivery address plus
 * an array of cart items (product_id + quantity). Stock sufficiency and
 * price snapshotting are NOT validated here (that requires DB lookups
 * against live Product rows) — that logic belongs in OrderController,
 * which uses this request purely for shape/presence validation.
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only authenticated VIP clients may place orders — enforced by the
        // 'role:vip' route middleware, so we simply require an authenticated user here.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'delivery_address' => ['required', 'string', 'max:1000'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
            'delivery_fee' => ['required', 'numeric', 'min:0', 'max:9999999.99'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Kikapu chako hakina bidhaa yoyote. (Your cart is empty.)',
            'items.*.product_id.exists' => 'Bidhaa moja au zaidi hazipatikani. (One or more products could not be found.)',
            'items.*.quantity.min' => 'Idadi ya bidhaa lazima iwe angalau 1.',
            'delivery_address.required' => 'Anwani ya kufikisha bidhaa inahitajika.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Uthibitishaji umeshindikana.',
            'errors' => $validator->errors(),
        ], 422));
    }
}