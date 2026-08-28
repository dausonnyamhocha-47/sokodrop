<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * StoreProductRequest
 *
 * Validates a merchant's new-product submission for Merchant\InventoryController::store().
 * Previously this validation lived inline in the controller via
 * Validator::make() — extracted here so it matches the FormRequest pattern
 * already used for StoreOrderRequest and RegisterShopperRequest, and so the
 * "shop must exist and be approved" business check stays in the controller
 * (it needs $request->user()->shop, which isn't cleanly expressible as a
 * pure validation rule) while pure field-shape validation lives here.
 */
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role restriction ('merchant') and shop-approval check are handled
        // by route middleware + the controller itself; this only guards
        // that *some* authenticated user is making the request.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'unit' => ['required', 'string', 'max:20'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'image_path' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Jina la bidhaa linahitajika.',
            'price.required' => 'Bei ya bidhaa inahitajika.',
            'price.min' => 'Bei haiwezi kuwa hasi.',
            'stock_quantity.required' => 'Idadi ya bidhaa dukani inahitajika.',
            'unit.required' => 'Kipimo cha bidhaa kinahitajika (mfano: kg, kipande).',
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