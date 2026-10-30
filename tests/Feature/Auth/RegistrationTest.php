<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mqmesh.signup.questions' => [['question' => 'Which game?', 'answers' => ['everquest']]]]);
    }

    private function filledForm()
    {
        return Volt::test('auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('email_confirmation', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('answer', ' EverQuest ');
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $form = $this->filledForm();
        $this->travel(5)->seconds();

        $response = $form->call('register');

        $response
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_the_two_email_addresses_must_match(): void
    {
        $form = $this->filledForm()->set('email_confirmation', 'test@exmaple.com');
        $this->travel(5)->seconds();

        $form->call('register')->assertHasErrors(['email' => 'confirmed']);

        $this->assertGuest();
    }

    public function test_bots_that_submit_instantly_are_turned_away(): void
    {
        $this->filledForm()->call('register')->assertHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_bots_that_fill_the_hidden_field_are_turned_away(): void
    {
        $form = $this->filledForm()->set('website', 'https://spam.example');
        $this->travel(5)->seconds();

        $form->call('register')->assertHasErrors('email');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_question_must_be_answered(): void
    {
        $form = $this->filledForm()->set('answer', 'world of warcraft');
        $this->travel(5)->seconds();

        $form->call('register')->assertHasErrors('answer');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_browser_cannot_change_the_start_time(): void
    {
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        $this->filledForm()->set('startedAt', 1);
    }
}
