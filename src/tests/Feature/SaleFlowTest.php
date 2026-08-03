<?php

namespace Tests\Feature;

use App\Contracts\FiscalProviderInterface;
use App\Contracts\SaleRepositoryInterface;
use App\Jobs\IssueFiscalDocumentJob;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
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

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => 'out',
            'quantity' => 3,
            'unit_price' => 25.00,
            'reference_type' => 'sale',
            'reference_id' => $responseData['id'],
        ]);

        $this->product->refresh();
        $this->assertEquals(97, $this->product->stock_quantity);

        Queue::assertPushed(IssueFiscalDocumentJob::class, function ($job) use ($responseData) {
            return $job->saleId === $responseData['id'];
        });
    }

    public function test_can_create_sale_with_multiple_items(): void
    {
        $secondProduct = Product::factory()->create([
            'sku' => 'TEST-002',
            'name' => 'Segundo Produto',
            'price' => 10.00,
            'category' => 'Teste',
            'stock_quantity' => 50,
        ]);

        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
                ['product_id' => $secondProduct->id, 'quantity' => 5],
            ],
        ];

        $response = $this->postJson('/api/sales', $payload, $this->authHeaders);

        $response->assertStatus(201);
        $responseData = $response->json('data');
        $this->assertEquals(100.00, $responseData['total']);
        $this->assertCount(2, $responseData['items']);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $responseData['id'],
            'product_id' => $this->product->id,
            'quantity' => 2,
            'subtotal' => 50.00,
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $responseData['id'],
            'product_id' => $secondProduct->id,
            'quantity' => 5,
            'subtotal' => 50.00,
        ]);

        $this->product->refresh();
        $secondProduct->refresh();
        $this->assertEquals(98, $this->product->stock_quantity);
        $this->assertEquals(45, $secondProduct->stock_quantity);
    }

    public function test_returns_422_when_stock_is_insufficient_and_rolls_back(): void
    {
        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 999],
            ],
        ];

        $response = $this->postJson('/api/sales', $payload, $this->authHeaders);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code' => 422,
        ]);

        $message = $response->json('message');
        $this->assertStringContainsString('Produto Teste', $message);
        $this->assertStringContainsString('TEST-001', $message);

        // A transação deve ter sido revertida por completo.
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);

        $this->product->refresh();
        $this->assertEquals(100, $this->product->stock_quantity);

        Queue::assertNotPushed(IssueFiscalDocumentJob::class);
    }

    public function test_returns_422_when_any_item_has_insufficient_stock(): void
    {
        $secondProduct = Product::factory()->create([
            'sku' => 'TEST-002',
            'name' => 'Segundo Produto',
            'price' => 10.00,
            'category' => 'Teste',
            'stock_quantity' => 50,
        ]);

        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
                ['product_id' => $secondProduct->id, 'quantity' => 500],
            ],
        ];

        $response = $this->postJson('/api/sales', $payload, $this->authHeaders);

        $response->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);

        $this->product->refresh();
        $secondProduct->refresh();
        $this->assertEquals(100, $this->product->stock_quantity);
        $this->assertEquals(50, $secondProduct->stock_quantity);
    }

    public function test_returns_validation_error_for_empty_items(): void
    {
        $response = $this->postJson('/api/sales', ['items' => []], $this->authHeaders);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code' => 422,
        ]);
        $this->assertArrayHasKey('items', $response->json('errors'));
    }

    public function test_returns_validation_error_when_items_are_missing(): void
    {
        $response = $this->postJson('/api/sales', [], $this->authHeaders);

        $response->assertStatus(422);
        $this->assertArrayHasKey('items', $response->json('errors'));
    }

    public function test_returns_validation_error_when_product_id_is_missing(): void
    {
        $response = $this->postJson('/api/sales', [
            'items' => [
                ['quantity' => 1],
            ],
        ], $this->authHeaders);

        $response->assertStatus(422);
        $this->assertArrayHasKey('items.0.product_id', $response->json('errors'));
    }

    public function test_returns_validation_error_for_nonexistent_product(): void
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
        $this->assertArrayHasKey('items.0.product_id', $response->json('errors'));
    }

    public function test_returns_validation_error_for_zero_quantity(): void
    {
        $response = $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 0],
            ],
        ], $this->authHeaders);

        $response->assertStatus(422);
        $this->assertArrayHasKey('items.0.quantity', $response->json('errors'));
    }

    public function test_requires_authentication(): void
    {
        $response = $this->postJson('/api/sales', [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'code' => 401,
        ]);
    }

    public function test_fiscal_job_executed_synchronously_persists_protocol(): void
    {
        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2],
            ],
        ];

        $response = $this->postJson('/api/sales', $payload, $this->authHeaders);

        $response->assertStatus(201);
        $saleId = $response->json('data.id');

        // A venda é criada sem protocolo fiscal; ele só é gravado pelo job.
        $this->assertNull(Sale::find($saleId)->fiscal_protocol);

        // Executa o job exatamente como foi despachado, mas com as
        // dependências reais do container (provider fiscal + repository).
        $job = Queue::pushed(IssueFiscalDocumentJob::class)->first();
        $job->handle(
            app(FiscalProviderInterface::class),
            app(SaleRepositoryInterface::class),
        );

        $sale = Sale::find($saleId);
        $this->assertNotNull($sale->fiscal_protocol);
        $this->assertStringStartsWith('NF-', $sale->fiscal_protocol);
    }
}
