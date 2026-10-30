<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        return tap(User::factory()->create()->forceFill(['role' => $role]))->save();
    }

    public function test_admins_delete_a_user_and_their_tokens_and_sessions(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $user = User::factory()->create();
        $user->createToken('updater', ['upload']);
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        Volt::actingAs($admin)->test('admin.users')->call('deleteUser', $user->id);

        $this->assertModelMissing($user);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('sessions', 0);
    }

    public function test_admins_cannot_delete_themselves_or_another_admin(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $other = $this->userWithRole(User::ROLE_ADMIN);

        $page = Volt::actingAs($admin)->test('admin.users');
        $page->call('deleteUser', $admin->id);
        $page->call('deleteUser', $other->id);

        $this->assertModelExists($admin);
        $this->assertModelExists($other);
    }

    public function test_only_admins_can_delete_users(): void
    {
        $moderator = $this->userWithRole(User::ROLE_MODERATOR);

        Volt::actingAs($moderator)->test('admin.users')->assertForbidden();
    }

    public function test_prune_removes_only_old_unconfirmed_accounts(): void
    {
        $spam = User::factory()->unverified()->create(['created_at' => now()->subDays(8)]);
        $recent = User::factory()->unverified()->create();
        $confirmed = User::factory()->create(['created_at' => now()->subDays(8)]);

        $this->artisan('mqmesh:prune-unverified --dry-run')->assertSuccessful();
        $this->assertModelExists($spam);

        $this->artisan('mqmesh:prune-unverified')->assertSuccessful();

        $this->assertModelMissing($spam);
        $this->assertModelExists($recent);
        $this->assertModelExists($confirmed);
    }
}
