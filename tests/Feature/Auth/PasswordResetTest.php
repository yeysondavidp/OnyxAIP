<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('forgot password page renders', function () {
    $this->get(route('password.request'))->assertOk();
});

it('sends reset link for a known email', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'pm@onyx.test']);

    $this->post(route('password.email'), ['email' => 'pm@onyx.test'])
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('does not reveal whether email exists (no enumeration)', function () {
    Notification::fake();

    // Unknown email — same success response as a known one.
    $response = $this->post(route('password.email'), ['email' => 'ghost@nowhere.test']);

    $response->assertRedirect();
    // session has 'status' (not an error) — indistinguishable from success
    $response->assertSessionHas('status');
    Notification::assertNothingSent();
});

it('valid reset token sets a new password and redirects to login', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'pm@onyx.test']);

    $this->post(route('password.email'), ['email' => 'pm@onyx.test']);

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->post(route('password.update'), [
        'token'                 => $token,
        'email'                 => 'pm@onyx.test',
        'password'              => 'new-secure-password-123',
        'password_confirmation' => 'new-secure-password-123',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('status');
});

it('expired or invalid token shows an error', function () {
    User::factory()->create(['email' => 'pm@onyx.test']);

    $this->post(route('password.update'), [
        'token'                 => 'invalid-token',
        'email'                 => 'pm@onyx.test',
        'password'              => 'new-secure-password-123',
        'password_confirmation' => 'new-secure-password-123',
    ])->assertSessionHasErrors('email');
});
