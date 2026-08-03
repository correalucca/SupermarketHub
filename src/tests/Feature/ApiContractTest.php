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
}
