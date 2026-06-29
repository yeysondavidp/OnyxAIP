<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('pm can log in with valid credentials and reaches dashboard', function () {
    $user = User::factory()->create([
        'email'    => 'pm@onyx.test',
        'password' => bcrypt('correct-password'),
        'role'     => UserRole::Pm,
    ]);

    $response = $this->post(route('login.post'), [
        'email'    => 'pm@onyx.test',
        'password' => 'correct-password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('logout ends the session and redirects to login', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('invalid credentials show a plain non-blaming error', function () {
    User::factory()->create(['email' => 'pm@onyx.test']);

    $response = $this->post(route('login.post'), [
        'email'    => 'pm@onyx.test',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    // Must not say "user not found" or reveal whether the email exists.
    $error = session('errors')->first('email');
    expect($error)->not->toContain('not found')
        ->and($error)->not->toContain('no account');
    $this->assertGuest();
});

it('invalid credentials for non-existent email show the same error (no enumeration)', function () {
    $response = $this->post(route('login.post'), [
        'email'    => 'ghost@nowhere.test',
        'password' => 'any-password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('unauthenticated visitors are redirected to login from PM routes', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('clients.index'))->assertRedirect(route('login'));
});

it('technician role cannot access the PM portal', function () {
    $tech = User::factory()->technician()->create();

    $this->actingAs($tech)
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('client_user role cannot access the PM portal', function () {
    $clientUser = User::factory()->clientUser()->create();

    $this->actingAs($clientUser)
        ->get(route('dashboard'))
        ->assertForbidden();
});

it('security headers are present on responses', function () {
    $response = $this->get(route('login'));

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});
