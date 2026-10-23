<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EmailConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconfirmed_users_can_download_but_not_upload(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/zones')->assertOk();
        $this->actingAs($user)->get('/upload')->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get('/settings/tokens')->assertRedirect(route('verification.notice'));

        \App\Models\Expansion::syncFromConfig();
        \App\Models\Zone::factory()->create(['short_name' => 'arena']);
        Sanctum::actingAs($user, ['read', 'upload']);
        $this->postJson('/api/v1/zones/arena/versions')->assertForbidden();
    }

    public function test_verification_links_are_limited_to_one_per_fifteen_minutes(): void
    {
        $user = User::factory()->unverified()->create();

        $this->assertSame(0, $user->sendVerificationEmailThrottled());
        $this->assertGreaterThan(0, $user->sendVerificationEmailThrottled());

        $this->travel(16)->minutes();
        $this->assertSame(0, $user->sendVerificationEmailThrottled());
    }

    public function test_verification_links_expire_after_an_hour(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(config('auth.verification.expire')), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->travel(61)->minutes();
        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_only_the_verification_email_reaches_unconfirmed_addresses(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $this->assertNull($user->routeNotificationForMail(new ResetPassword('token')));
        $this->assertSame($user->email, $user->routeNotificationForMail(new VerifyEmail));

        $user->markEmailAsVerified();
        $this->assertSame($user->email, $user->routeNotificationForMail(new ResetPassword('token')));
    }
}
