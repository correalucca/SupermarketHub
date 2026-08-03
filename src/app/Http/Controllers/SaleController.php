<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleRequest;
use App\Services\SaleService;
use OpenApi\Attributes as OA;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
    ) {}

    #[OA\Post(
        path: '/api/sales',
        summary: 'Registrar uma nova venda',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'product_id', type: 'integer', example: 1),
                                new OA\Property(property: 'quantity', type: 'number', example: 2),
                            ]
                        )
                    ),
                ]
            )
        ),
        tags: ['Vendas'],
        responses: [
            new OA\Response(response: 201, description: 'Venda criada com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
            new OA\Response(response: 422, description: 'Erro de validação ou estoque insuficiente'),
        ]
    )]
    public function store(SaleRequest $request)
    {
        $sale = $this->saleService->createSale(
            $request->validated()['items']
        );

        return response()->json([
            'success' => true,
            'message' => 'Venda finalizada com sucesso.',
            'data' => $sale,
        ], 201);
    }
}
