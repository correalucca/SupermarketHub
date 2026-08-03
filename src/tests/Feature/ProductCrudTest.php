<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    private array $authHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        $this->authHeaders = ['Authorization' => 'Bearer ' . $token];
    }

    private function productPayload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'SKU-100',
            'name' => 'Arroz',
            'price' => 12.50,
            'category' => 'Alimentos',
            'stock_quantity' => 20,
        ], $overrides);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/products')->assertStatus(401);
    }

    public function test_index_returns_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/products', $this->authHeaders);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_product(): void
    {
        $response = $this->postJson('/api/products', $this->productPayload(), $this->authHeaders);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Produto criado com sucesso.',
        ]);
        $response->assertJsonPath('data.sku', 'SKU-100');

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU-100',
            'name' => 'Arroz',
            'price' => 12.50,
        ]);
    }

    public function test_create_requires_authentication(): void
    {
        $this->postJson('/api/products', $this->productPayload())->assertStatus(401);
    }

    public function test_create_rejects_duplicate_sku(): void
    {
        Product::factory()->create(['sku' => 'SKU-100']);

        $response = $this->postJson('/api/products', $this->productPayload(), $this->authHeaders);

        $response->assertStatus(422);
        $this->assertArrayHasKey('sku', $response->json('errors'));
        $this->assertDatabaseCount('products', 1);
    }

    public function test_create_validates_required_fields(): void
    {
        $response = $this->postJson('/api/products', [], $this->authHeaders);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertArrayHasKey('sku', $errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('price', $errors);
        $this->assertArrayHasKey('category', $errors);
    }

    public function test_can_show_product(): void
    {
        $product = Product::factory()->create(['sku' => 'SKU-100']);

        $response = $this->getJson("/api/products/{$product->id}", $this->authHeaders);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.id', $product->id);
        $response->assertJsonPath('data.sku', 'SKU-100');
    }

    public function test_show_missing_product_returns_404(): void
    {
        $response = $this->getJson('/api/products/999', $this->authHeaders);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Recurso não encontrado.',
            'code' => 404,
        ]);
    }

    public function test_can_update_product(): void
    {
        $product = Product::factory()->create(['sku' => 'SKU-100']);

        $response = $this->putJson(
            "/api/products/{$product->id}",
            $this->productPayload(['name' => 'Arroz Integral']),
            $this->authHeaders,
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Produto atualizado com sucesso.',
        ]);
        $response->assertJsonPath('data.name', 'Arroz Integral');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Arroz Integral',
        ]);
    }

    public function test_update_can_keep_same_sku(): void
    {
        $product = Product::factory()->create(['sku' => 'SKU-100']);

        $response = $this->putJson(
            "/api/products/{$product->id}",
            $this->productPayload(),
            $this->authHeaders,
        );

        $response->assertStatus(200);
    }

    public function test_update_rejects_sku_of_another_product(): void
    {
        $productA = Product::factory()->create(['sku' => 'SKU-A']);
        Product::factory()->create(['sku' => 'SKU-B']);

        $response = $this->putJson(
            "/api/products/{$productA->id}",
            $this->productPayload(['sku' => 'SKU-B']),
            $this->authHeaders,
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('sku', $response->json('errors'));
    }

    public function test_can_delete_product(): void
    {
        $product = Product::factory()->create(['sku' => 'SKU-100']);

        $response = $this->deleteJson("/api/products/{$product->id}", [], $this->authHeaders);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Produto removido com sucesso.',
        ]);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertStatus(401);
    }
}
