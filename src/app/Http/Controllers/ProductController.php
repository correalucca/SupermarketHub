<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {}

    #[OA\Get(path: '/api/products', summary: 'Listar produtos', tags: ['Produtos'])]
    #[OA\Response(response: 200, description: 'Lista de produtos')]
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->productRepository->all(),
        ]);
    }

    #[OA\Post(path: '/api/products', summary: 'Criar produto', tags: ['Produtos'])]
    #[OA\Response(response: 201, description: 'Produto criado')]
    public function store(ProductRequest $request): JsonResponse
    {
        $product = $this->productRepository->create(
            $request->validated()
        );

        Log::info('Novo produto criado', [
            'sku' => $product->sku,
            'name' => $product->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produto criado com sucesso.',
            'data' => $product,
        ], 201);
    }

    #[OA\Get(path: '/api/products/{id}', summary: 'Exibir produto', tags: ['Produtos'])]
    #[OA\Response(response: 200, description: 'Dados do produto')]
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->productRepository->find($id),
        ]);
    }

    #[OA\Put(path: '/api/products/{id}', summary: 'Atualizar produto', tags: ['Produtos'])]
    #[OA\Response(response: 200, description: 'Produto atualizado')]
    public function update(ProductRequest $request, int $id): JsonResponse
    {
        $product = $this->productRepository->update(
            $id,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Produto atualizado com sucesso.',
            'data' => $product,
        ]);
    }

    #[OA\Delete(path: '/api/products/{id}', summary: 'Remover produto', tags: ['Produtos'])]
    #[OA\Response(response: 200, description: 'Produto removido')]
    public function destroy(int $id): JsonResponse
    {
        $this->productRepository->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Produto removido com sucesso.',
        ]);
    }
}
