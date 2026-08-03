<?php

namespace Tests\Feature;

use Tests\TestCase;

class SwaggerDocumentationTest extends TestCase
{
    public function test_documentation_ui_and_json_are_generated_and_served(): void
    {
        $this->artisan('l5-swagger:generate')->assertSuccessful();

        $this->get('/api/documentation')->assertOk();

        $response = $this->get('/docs')->assertOk();
        $this->assertArrayHasKey('paths', $response->json());
        $this->assertArrayHasKey('/api/sales', $response->json('paths'));
    }
}
