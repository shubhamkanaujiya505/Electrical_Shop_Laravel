<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class OwnerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $changes = []): array
    {
        return array_replace([
            'shop_name' => 'City Electricals',
            'name' => 'Shop Owner',
            'email' => 'owner@example.test',
            'password' => 'ShopPass123',
            'password_confirmation' => 'ShopPass123',
        ], $changes);
    }

    private function owner(string $shopName = 'City Electricals'): User
    {
        $shop = Tenant::create(['name' => $shopName]);

        return User::factory()->for($shop, 'tenant')->create(['role' => 'owner']);
    }

    public function test_registration_creates_a_shop_and_signed_in_owner(): void
    {
        $this->postJson('/api/auth/register', $this->registration(['email' => 'OWNER@EXAMPLE.TEST']))
            ->assertCreated()->assertJsonPath('user.role', 'owner')
            ->assertJsonPath('user.email', 'owner@example.test')
            ->assertJsonPath('shop.name', 'City Electricals')
            ->assertJsonMissingPath('user.password');

        $user = User::firstOrFail();
        $this->assertTrue(Hash::check('ShopPass123', $user->password));
        $this->assertSame(Tenant::firstOrFail()->id, $user->tenant_id);
        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_registration_validates_password_and_duplicate_email(): void
    {
        $this->postJson('/api/auth/register', $this->registration(['password_confirmation' => 'wrong']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('tenants', 0);
        $this->postJson('/api/auth/register', $this->registration())->assertCreated();
        $this->postJson('/api/auth/register', $this->registration())
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_registration_cannot_choose_a_role_or_existing_shop(): void
    {
        $this->postJson('/api/auth/register', $this->registration(['role' => 'platform_admin', 'tenant_id' => 999]))
            ->assertUnprocessable()->assertJsonValidationErrors(['role', 'tenant_id']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('tenants', 0);
    }

    public function test_failed_owner_creation_does_not_leave_an_orphan_shop(): void
    {
        User::creating(fn () => throw new RuntimeException('Simulated write failure'));
        try {
            $this->postJson('/api/auth/register', $this->registration())->assertStatus(500);
            $this->assertDatabaseCount('tenants', 0);
            $this->assertDatabaseCount('users', 0);
        } finally {
            User::flushEventListeners();
        }
    }

    public function test_owner_can_login_and_logout(): void
    {
        $owner = $this->owner();
        $this->postJson('/api/auth/login', ['email' => strtoupper($owner->email), 'password' => 'password'])
            ->assertOk()->assertJsonPath('shop.id', $owner->tenant_id);
        $this->postJson('/api/auth/logout')->assertOk();
        $this->assertGuest();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_invalid_login_is_generic_and_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->postJson('/api/auth/login', ['email' => 'unknown@example.test', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGuest();
    }

    public function test_guests_cannot_access_shop_or_owner_details(): void
    {
        $this->getJson('/api/shop')->assertUnauthorized();
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->patchJson('/api/shop', ['name' => 'Changed'])->assertUnauthorized();
    }

    public function test_owner_access_is_always_scoped_to_authenticated_shop(): void
    {
        $ownerA = $this->owner('Shop A');
        $ownerB = $this->owner('Shop B');
        $this->actingAs($ownerA);
        $this->getJson('/api/shop?tenant_id='.$ownerB->tenant_id)
            ->assertOk()->assertJsonPath('shop.name', 'Shop A');
        $this->getJson('/api/shops/'.$ownerB->tenant_id)->assertNotFound();
        $this->patchJson('/api/shop', ['name' => 'Changed', 'tenant_id' => $ownerB->tenant_id])
            ->assertUnprocessable()->assertJsonValidationErrors('tenant_id');
        $this->patchJson('/api/shop', ['name' => 'Shop A Updated', 'phone' => '9876543210'])
            ->assertOk()->assertJsonPath('shop.id', $ownerA->tenant_id);
        $this->assertDatabaseHas('tenants', ['id' => $ownerB->tenant_id, 'name' => 'Shop B', 'phone' => null]);
        $this->actingAs($ownerB)->getJson('/api/shop')->assertOk()->assertJsonPath('shop.name', 'Shop B');
    }

    public function test_inactive_shop_and_unprivileged_users_cannot_access_owner_routes(): void
    {
        $owner = $this->owner();
        $owner->tenant->forceFill(['is_active' => false])->save();
        $this->actingAs($owner)->getJson('/api/shop')->assertForbidden();
        $owner = $this->owner();
        $owner->forceFill(['role' => 'staff'])->save();
        $this->actingAs($owner)->patchJson('/api/shop', ['name' => 'Changed'])->assertForbidden();
    }

    public function test_inactive_owner_cannot_login(): void
    {
        $owner = $this->owner();
        $owner->forceFill(['is_active' => false])->save();
        $this->postJson('/api/auth/login', ['email' => $owner->email, 'password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_csrf_is_required_for_real_browser_mutations(): void
    {
        // Laravel normally bypasses CSRF during unit tests; exercise the real branch.
        $this->app['env'] = 'local';
        $this->get('/api/auth/csrf')->assertNoContent()->assertCookie('XSRF-TOKEN');
        $this->postJson('/api/auth/register', $this->registration())->assertStatus(419);
        $this->assertDatabaseCount('tenants', 0);
    }
}
