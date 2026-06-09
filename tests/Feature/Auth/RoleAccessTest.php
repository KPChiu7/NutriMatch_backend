<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests that role-based access control is correctly enforced.
 * Each role may only access its own routes.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    // --------------------------------------------------------
    // Admin-only routes
    // --------------------------------------------------------

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertStatus(200);
    }

    public function test_rnd_cannot_access_admin_routes(): void
    {
        $rnd = User::factory()->create(['role' => 'rnd']);

        $this->actingAs($rnd)
            ->getJson('/api/admin/users')
            ->assertStatus(403);
    }

    public function test_client_cannot_access_admin_routes(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)
            ->getJson('/api/admin/users')
            ->assertStatus(403);
    }

    // --------------------------------------------------------
    // RND-only routes
    // --------------------------------------------------------

    public function test_client_cannot_access_rnd_routes(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)
            ->getJson('/api/rnd/appointments')
            ->assertStatus(403);
    }

    public function test_admin_cannot_access_rnd_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/rnd/appointments')
            ->assertStatus(403);
    }

    // --------------------------------------------------------
    // Client-only routes
    // --------------------------------------------------------

    public function test_rnd_cannot_access_client_routes(): void
    {
        $rnd = User::factory()->create(['role' => 'rnd']);

        $this->actingAs($rnd)
            ->getJson('/api/client/appointments')
            ->assertStatus(403);
    }

    // --------------------------------------------------------
    // Unauthenticated
    // --------------------------------------------------------

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/admin/users')->assertStatus(401);
        $this->getJson('/api/rnd/appointments')->assertStatus(401);
        $this->getJson('/api/client/appointments')->assertStatus(401);
    }
}
