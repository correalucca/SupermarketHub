<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'A venda deve conter ao menos um item.',
            'items.*.product_id.required' => 'O ID do produto é obrigatório.',
            'items.*.product_id.exists' => 'O produto informado não existe.',
            'items.*.quantity.min' => 'A quantidade deve ser maior que zero.',
        ];
    }
}
