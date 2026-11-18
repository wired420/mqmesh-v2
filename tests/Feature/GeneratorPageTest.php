<?php

namespace Tests\Feature;

use Tests\TestCase;

class GeneratorPageTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_the_generator_page_embeds_the_app_inside_the_site_layout_with_isolation_headers(): void
    {
        $this->get('/generator')
            ->assertOk()
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Embedder-Policy', 'credentialless')
            ->assertSee('generator-app/index.html', false)
            ->assertSee('allow="cross-origin-isolated', false)
            ->assertSee('Mesh generator · MQMesh', false)
            ->assertDontSee('in no way affiliated');
    }

    public function test_other_pages_are_not_isolated_and_link_to_the_generator(): void
    {
        $response = $this->get('/about')->assertOk()->assertSee(route('generator'), false);

        $this->assertFalse($response->headers->has('Cross-Origin-Embedder-Policy'));
    }

    public function test_a_mesh_to_open_is_passed_through_to_the_app(): void
    {
        $this->get('/generator?zone=poknowledge&mesh='.urlencode('/files/7/download?view=1').'&label=v2&other=x')
            ->assertOk()
            ->assertSee('generator-app/index.html?zone=poknowledge&amp;mesh=%2Ffiles%2F7%2Fdownload%3Fview%3D1&amp;label=v2"', false);
    }
}
