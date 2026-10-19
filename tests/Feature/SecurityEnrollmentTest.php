<?php

use App\Models\User;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

test('two factor setup provides QR and confirms a real authenticator code', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $this->postJson(route('two-factor.enable'))->assertOk();
    $this->getJson(route('two-factor.qr-code'))->assertOk()->assertJsonStructure(['svg', 'url']);
    $secret = $this->getJson(route('two-factor.secret-key'))->assertOk()->json('secretKey');
    expect($secret)->toBeString()->not->toBeEmpty();
    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();

    $code = (new Google2FA)->getCurrentOtp($secret);
    $this->postJson(route('two-factor.confirm'), ['code' => $code])->assertOk();
    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
    $codes = $this->getJson(route('two-factor.recovery-codes'))->assertOk()->json();
    expect($codes)->toHaveCount(8);
});

test('invalid authenticator code cannot activate two factor authentication', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    $this->postJson(route('two-factor.enable'))->assertOk();
    $this->postJson(route('two-factor.confirm'), ['code' => 'invalid'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

test('recovery code permits login once and is replaced after use', function () {
    $user = User::factory()->withTwoFactor()->create();
    $recoveryCode = $user->recoveryCodes()[0];
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
        ->assertRedirect();
    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->recoveryCodes())->not->toContain($recoveryCode);
});

test('two factor login rejects an invalid code and accepts the real current code', function () {
    $user = User::factory()->withTwoFactor()->create([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt((new Google2FA)->generateSecretKey()),
    ]);
    $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));
    $this->postJson(route('two-factor.login.store'), ['code' => 'invalid'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->assertGuest();

    $this->post(route('two-factor.login.store'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('passkey registration options are authenticated and tied to the configured domain', function () {
    $this->getJson(route('passkey.registration-options'))->assertUnauthorized();
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    $response = $this->getJson(route('passkey.registration-options'))->assertOk();
    $response->assertJsonStructure(['options' => ['challenge', 'rp' => ['id', 'name'], 'user' => ['id', 'name'], 'pubKeyCredParams']]);
    expect($response->json('options.rp.id'))->toBe(parse_url(config('app.url'), PHP_URL_HOST));
    expect($response->json('options.challenge'))->toBeString()->not->toBeEmpty();
    $this->postJson(route('passkey.store'), ['name' => 'Llave de prueba', 'credential' => []])
        ->assertUnprocessable();
    expect($user->passkeys()->count())->toBe(0);
});
