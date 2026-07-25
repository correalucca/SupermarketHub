<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Jobs\IssueFiscalDocumentJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SaleFlowTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private array $authHeaders;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;
        $this->authHeaders = ['Authorization' => 'Bearer ' . $token];

        $this->product = Product::factory()->create([
            'sku' => 'TEST-001',
            'name' => 'Produto Teste',
            'price' => 25.00,
            'category' => 'Teste',
            'stock_quantity' => 100,
        ]);
    }

    public function test_can_create_sale_and_reduces_stock(): void
    {
        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 3],
            ],
        ];

        $response = $this->postJson('/api/sales', $payload, $this->authHeaders);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Venda finalizada com sucesso.',
        ]);

        $responseData = $response->json('data');
        $this->assertEquals(75.00, $responseData['total']);
        $this->assertEquals('completed', $responseData['status']);

        $this->assertDatabaseHas('sales', [
            'id' => $responseData['id'],
            'total' => 75.00,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $responseData['id'],
            'product_id' => $this->product->id,
            'quantity' => 3,
            'unit_price' => 25.00,
            'subtotal' => 75.00,
        ]);

        $this->product->refresh();
        $this->assertEquals(97, $this->product->stock_quantity);

        Queue::assertPushed(IssueFiscalDocumentJob::class, function ($job) use ($responseData) {
            return $job->saleId === $responseData['id'];
        });
    }

    public function test_returns_error_when_insufficient_stock(): void
    {
        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 999],
            ],
        ];

        $response = $this->postJson('/api/sales', $payload, $this->authHeaders);

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
        ]);

        $this->product->refresh();
        $this->assertEquals(100, $this->product->stock_quantity);
    }

    public function test_returns_validation_error_for_invalid_data(): void
    {
        $response = $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => 999, 'quantity' => 1],
            ],
        ], $this->authHeaders);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code' => 422,
        ]);
    }
}
