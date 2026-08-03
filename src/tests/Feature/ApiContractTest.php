<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_id_header_is_generated_when_absent(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'x@example.com',
            'password' => 'password',
        ]);

        $requestId = $response->headers->get('X-Request-Id');
        $this->assertNotNull($requestId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $requestId
        );
    }

    public function test_request_id_header_is_echoed_when_provided(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'x@example.com',
            'password' => 'password',
        ], ['X-Request-Id' => 'my-custom-request-id']);

        $response->assertHeader('X-Request-Id', 'my-custom-request-id');
    }

    public function test_unknown_api_route_returns_404_json_contract(): void
    {
        $response = $this->getJson('/api/rota-inexistente');

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Recurso não encontrado.',
            'code' => 404,
        ]);
    }

    public function test_unauthenticated_request_returns_401_json_contract(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Não autenticado.',
            'code' => 401,
        ]);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }

    public function test_unhandled_exception_returns_500_json_contract(): void
    {
        // Rota temporária que lança uma exceção não mapeada para validar o contrato 500.
        $this->app['router']->get('/api/boom', fn () => throw new \RuntimeException('Falha inesperada'));

        $response = $this->getJson('/api/boom');

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'code' => 500,
        ]);
        $this->assertStringContainsString('Falha inesperada', $response->json('message'));
    }

    public function test_non_api_requests_keep_default_error_handling(): void
    {
        // Rotas fora do /api/* devem manter o tratamento padrão do Laravel
        // (o handler retorna null e não aplica o contrato JSON).
        $response = $this->get('/pagina-web-inexistente');

        $response->assertStatus(404);
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('success', $response->getContent());
    }

    public function test_production_hides_internal_error_message(): void
    {
        // Simula ambiente de produção: a mensagem real da exceção não deve vazar.
        $this->app['env'] = 'production';

        $this->app['router']->get('/api/boom', fn () => throw new \RuntimeException('Segredo interno da aplicacao'));

        $response = $this->getJson('/api/boom');

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Erro interno do servidor.',
            'code' => 500,
        ]);
        $this->assertStringNotContainsString('Segredo interno da aplicacao', $response->json('message'));
    }
}
