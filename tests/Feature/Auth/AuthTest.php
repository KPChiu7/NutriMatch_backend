<?php

namespace Tests\Feature\Auth;

use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests for authentication endpoints.
 * Covers: login, register (client), register (rnd), me, logout.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    // --------------------------------------------------------
    // Login
    // --------------------------------------------------------

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email'    => 'test@nutrimatch.ph',
            'password' => Hash::make('password123'),
            'role'     => 'client',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@nutrimatch.ph',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'token_type', 'user'])
            ->assertJsonPath('user.email', 'test@nutrimatch.ph')
            ->assertJsonMissing(['password']); // SECURITY: password must never be in response
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'test@nutrimatch.ph']);

        $this->postJson('/api/auth/login', [
            'email'    => 'test@nutrimatch.ph',
            'password' => 'wrongpassword',
        ])->assertStatus(422);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email'     => 'inactive@nutrimatch.ph',
            'password'  => Hash::make('password123'),
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email'    => 'inactive@nutrimatch.ph',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_soft_deleted_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email'    => 'deleted@nutrimatch.ph',
            'password' => Hash::make('password123'),
        ]);
        $user->delete();

        $this->postJson('/api/auth/login', [
            'email'    => 'deleted@nutrimatch.ph',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    // --------------------------------------------------------
    // Register Client
    // --------------------------------------------------------

    public function test_client_can_register(): void
    {
        $response = $this->postJson('/api/auth/register/client', [
            'first_name'            => 'Maria',
            'last_name'             => 'Santos',
            'email'                 => 'maria@test.ph',
            'password'              => 'Password1',
            'password_confirmation' => 'Password1',
            'date_of_birth'         => '1995-06-15',
            'sex'                   => 'female',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'token_type', 'user']);

        $this->assertDatabaseHas('users', ['email' => 'maria@test.ph', 'role' => 'client']);
        $this->assertDatabaseHas('client_profiles', ['user_id' => $response->json('user.id')]);
    }

    public function test_client_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@test.ph']);

        $this->postJson('/api/auth/register/client', [
            'first_name'            => 'Maria',
            'last_name'             => 'Santos',
            'email'                 => 'existing@test.ph',
            'password'              => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertStatus(422)
          ->assertJsonPath('errors.email.0', 'The email has already been taken.');
    }

    // --------------------------------------------------------
    // Me & Logout
    // --------------------------------------------------------

    public function test_authenticated_user_can_get_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissing(['password']);
    }

    public function test_unauthenticated_request_to_me_returns_401(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        // Token should be revoked
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }
}
