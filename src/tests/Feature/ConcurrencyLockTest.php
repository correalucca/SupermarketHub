<?php

namespace Tests\Feature;

use App\Contracts\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class ConcurrencyLockTest extends TestCase
{
    use DatabaseMigrations;

    public function test_find_with_lock_blocks_second_transaction_while_row_is_locked(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requer MySQL para testar row-level locking (FOR UPDATE).');
        }

        $product = Product::factory()->create([
            'sku' => 'LOCK-001',
            'name' => 'Produto Lock',
            'price' => 10.00,
            'category' => 'Teste',
            'stock_quantity' => 10,
        ]);

        $config = DB::connection()->getConfig();
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database'],
        );

        // Conexão "concorrente" que segura o lock da linha, simulando outra
        // venda em andamento sobre o mesmo produto.
        $concurrentPdo = new PDO($dsn, $config['username'], $config['password']);
        $concurrentPdo->beginTransaction();
        $stmt = $concurrentPdo->query("SELECT * FROM products WHERE id = {$product->id} FOR UPDATE");
        $stmt->fetchAll(); // Consome o resultado (query bufferizada), mantendo o lock ativo até o commit.

        // Limita o tempo de espera do lock para não pendurar a suíte.
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        $repository = app(ProductRepositoryInterface::class);

        try {
            $repository->findWithLock($product->id);
            $this->fail('A consulta com lock deveria ter aguardado a transação concorrente.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('1205', $e->getMessage());
        }

        // Após liberar o lock, a mesma consulta passa a funcionar.
        $concurrentPdo->commit();
        $concurrentPdo = null;

        $locked = $repository->findWithLock($product->id);
        $this->assertEquals($product->id, $locked->id);
        $this->assertEquals(10, $locked->stock_quantity);
    }
}
