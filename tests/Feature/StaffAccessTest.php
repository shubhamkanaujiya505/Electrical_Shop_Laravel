<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->for(Tenant::create(['name' => 'Shop']), 'tenant')->create(['role' => 'owner']);
    }

    private function staff(User $owner, array $changes = []): User
    {
        return User::factory()->for($owner->tenant, 'tenant')->create(array_replace(['role' => 'staff', 'permissions' => []], $changes));
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['name' => 'Staff', 'email' => 'STAFF@EXAMPLE.TEST', 'password' => 'StaffPass123', 'password_confirmation' => 'StaffPass123', 'permissions' => []], $changes);
    }

    public function test_owner_creates_hashed_staff_in_own_shop_without_switching_session(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->postJson('/api/staff', $this->payload())
            ->assertCreated()->assertJsonPath('staff.role', 'staff')->assertJsonPath('staff.email', 'staff@example.test')
            ->assertJsonPath('staff.permissions', [])->assertJsonMissingPath('staff.password');
        $staff = User::where('role', 'staff')->firstOrFail();
        $this->assertSame($owner->tenant_id, $staff->tenant_id);
        $this->assertTrue(Hash::check('StaffPass123', $staff->password));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_staff_creation_rejects_duplicate_email_bad_password_and_privilege_injection(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->postJson('/api/staff', $this->payload(['password_confirmation' => 'wrong']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/staff', $this->payload(['role' => 'owner', 'tenant_id' => 999, 'permissions' => ['staff.manage']]))
            ->assertUnprocessable()->assertJsonValidationErrors(['role', 'tenant_id', 'permissions.0']);
        $this->postJson('/api/staff', $this->payload())->assertCreated();
        $this->postJson('/api/staff', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_staff_can_login_and_read_own_shop_but_cannot_manage_staff_or_edit_without_permission(): void
    {
        $owner = $this->owner();
        $staff = $this->staff($owner);
        $this->postJson('/api/auth/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.role', 'staff')->assertJsonPath('permissions', []);
        $this->getJson('/api/shop?tenant_id=999')->assertOk()->assertJsonPath('shop.id', $owner->tenant_id);
        $this->getJson('/api/staff')->assertForbidden();
        $this->postJson('/api/staff', $this->payload())->assertForbidden();
        $this->patchJson('/api/staff/'.$staff->id, ['is_active' => true, 'permissions' => ['shop.update']])->assertForbidden();
        $this->patchJson('/api/shop', ['name' => 'Changed'])->assertForbidden();
        $this->postJson('/api/auth/logout')->assertOk();
    }

    public function test_permission_grant_and_revocation_apply_to_existing_staff_access(): void
    {
        $owner = $this->owner();
        $staff = $this->staff($owner);
        $this->actingAs($owner)->patchJson('/api/staff/'.$staff->id, ['is_active' => true, 'permissions' => ['shop.update']])->assertOk();
        $this->actingAs($staff->fresh())->getJson('/api/auth/me')->assertOk()->assertJsonPath('permissions', ['shop.update']);
        $this->patchJson('/api/shop', ['name' => 'Staff update'])->assertOk();
        $this->actingAs($owner)->patchJson('/api/staff/'.$staff->id, ['is_active' => true, 'permissions' => []])->assertOk();
        $this->actingAs($staff->fresh())->patchJson('/api/shop', ['name' => 'Denied'])->assertForbidden();
        $this->assertDatabaseHas('tenants', ['id' => $owner->tenant_id, 'name' => 'Staff update']);
    }

    public function test_owner_cannot_list_or_update_staff_from_another_shop_or_target_an_owner(): void
    {
        $ownerA = $this->owner();
        $ownStaff = $this->staff($ownerA);
        $ownerB = $this->owner();
        $otherStaff = $this->staff($ownerB);
        $this->actingAs($ownerA)->getJson('/api/staff?tenant_id='.$ownerB->tenant_id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownStaff->id)->assertJsonMissingPath('data.0.password');
        foreach ([$otherStaff->id, $ownerA->id, $ownerB->id, 999999] as $id) {
            $this->patchJson('/api/staff/'.$id, ['is_active' => false, 'permissions' => []])->assertNotFound();
        }
        $this->assertTrue($otherStaff->fresh()->is_active);
        $this->assertTrue($ownerA->fresh()->is_active);
    }

    public function test_disabled_staff_cannot_login_or_continue_an_existing_session_but_can_logout(): void
    {
        $owner = $this->owner();
        $staff = $this->staff($owner);
        $this->actingAs($owner)->patchJson('/api/staff/'.$staff->id, ['is_active' => false, 'permissions' => []])->assertOk();
        $this->actingAs($staff->fresh())->getJson('/api/auth/me')->assertForbidden();
        $this->getJson('/api/shop')->assertForbidden();
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/login', ['email' => $staff->email, 'password' => 'password'])->assertUnprocessable();
        $this->assertGuest();
        $this->actingAs($owner)->patchJson('/api/staff/'.$staff->id, ['is_active' => true, 'permissions' => []])->assertOk();
        $this->postJson('/api/auth/logout')->assertOk();
        $this->postJson('/api/auth/login', ['email' => $staff->email, 'password' => 'password'])->assertOk();
    }

    public function test_access_updates_cannot_escalate_roles_or_change_membership_or_credentials(): void
    {
        $owner = $this->owner();
        $staff = $this->staff($owner);
        $this->actingAs($owner)->patchJson('/api/staff/'.$staff->id, ['is_active' => true, 'permissions' => ['staff.manage'], 'role' => 'owner', 'tenant_id' => 999, 'password' => 'NewPass123'])
            ->assertUnprocessable()->assertJsonValidationErrors(['permissions.0', 'role', 'tenant_id', 'password']);
        $this->assertSame('staff', $staff->fresh()->role);
        $this->assertSame([], $staff->fresh()->permissions);
    }

    public function test_guests_and_inactive_shops_cannot_manage_staff(): void
    {
        $this->getJson('/api/staff')->assertUnauthorized();
        $this->postJson('/api/staff', $this->payload())->assertUnauthorized();
        $owner = $this->owner();
        $owner->tenant->forceFill(['is_active' => false])->save();
        $this->actingAs($owner)->getJson('/api/staff')->assertForbidden();
    }
}
