<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // O apiResource gera o parâmetro como {product}; mantemos fallback para {id}.
        $productId = $this->route('product') ?? $this->route('id');

        return [
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'name' => 'required|string|max:200',
            'price' => 'required|numeric|min:0',
            'category' => 'required|string|max:100',
            'stock_quantity' => 'sometimes|numeric|min:0',
        ];
    }
}
