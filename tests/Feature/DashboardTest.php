<?php

namespace Tests\Feature;

use App\Models\Expansion;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get('/my/uploads');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/my/uploads');
        $response->assertStatus(200);
    }

    public function test_public_pages_load(): void
    {
        Expansion::syncFromConfig();
        Zone::factory()->create(['short_name' => 'arena', 'name' => 'The Arena']);

        foreach (['/', '/zones', '/zones?exp[]=classic&doors=1&sort=size&dir=desc', '/zones/arena', '/about', '/api-docs'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
