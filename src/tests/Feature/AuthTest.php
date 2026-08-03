<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Usuário registrado com sucesso.',
        ]);
        $response->assertJsonPath('data.user.email', 'joao@example.com');
        $this->assertNotEmpty($response->json('data.token'));

        $this->assertDatabaseHas('users', ['email' => 'joao@example.com']);

        $user = User::where('email', 'joao@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'joao@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code' => 422,
        ]);
        $this->assertArrayHasKey('email', $response->json('errors'));
    }

    public function test_register_validates_required_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    public function test_register_validates_password_min_length(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => '123',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('password', $response->json('errors'));
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'joao@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'joao@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Login realizado com sucesso.',
        ]);
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'joao@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'joao@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'code' => 422,
        ]);
        $this->assertSame('Credenciais inválidas.', $response->json('errors.email.0'));
    }

    public function test_login_fails_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nao-existe@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $this->assertSame('Credenciais inválidas.', $response->json('errors.email.0'));
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria Souza',
            'email' => 'maria@example.com',
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/me', ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonPath('data.email', 'maria@example.com');
        $response->assertJsonPath('data.name', 'Maria Souza');
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Não autenticado.',
            'code' => 401,
        ]);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/logout', [], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Logout realizado com sucesso.',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Em HTTP real cada request é uma nova aplicação; aqui resetamos os guards
        // para o singleton não reutilizar o usuário autenticado no request anterior.
        $this->app['auth']->forgetGuards();

        // O mesmo token não deve mais funcionar após o logout.
        $this->getJson('/api/me', ['Authorization' => 'Bearer ' . $token])
            ->assertStatus(401);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }
}
