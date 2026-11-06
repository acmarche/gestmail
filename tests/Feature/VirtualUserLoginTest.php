<?php

declare(strict_types=1);

use App\Filament\Citoyen\Pages\Auth\CitoyenLogin;
use App\Models\Citoyen;
use App\Models\VirtualUser;
use App\Support\DovecotPassword;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('citoyen'));
});

function saltedShaHash(string $password): string
{
    $salt = 'salt';

    return '{SSHA}'.base64_encode(sha1($password.$salt, true).$salt);
}

function citoyenWithPasswords(?string $userPassword, ?string $legacyPassword): Citoyen
{
    $citoyen = Citoyen::factory()->create();

    $citoyen->virtualUser->forceFill([
        'userPassword' => $userPassword,
        'legacy_password' => $legacyPassword,
    ])->save();

    return $citoyen;
}

function logInWithPassword(string $username, string $password): Testable
{
    return livewire(CitoyenLogin::class)
        ->fillForm(['method' => 'password', 'username' => $username, 'password' => $password])
        ->call('authenticate');
}

it('logs in with the username and the userPassword', function (): void {
    $citoyen = citoyenWithPasswords(DovecotPassword::hash('BrandNewPass123'), null);

    logInWithPassword($citoyen->mail, 'BrandNewPass123')->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($citoyen->virtualUser, 'virtual_user');
});

it('logs in with the username and the legacy password', function (): void {
    $citoyen = citoyenWithPasswords(null, saltedShaHash('OldPassword'));

    logInWithPassword($citoyen->mail, 'OldPassword')->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($citoyen->virtualUser, 'virtual_user');
});

it('logs in with the personal token', function (): void {
    $citoyen = Citoyen::factory()->create();

    livewire(CitoyenLogin::class)
        ->fillForm(['method' => 'token', 'token' => $citoyen->auth_token])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($citoyen->virtualUser, 'virtual_user');
});

it('refuses an unknown personal token', function (): void {
    Citoyen::factory()->create();

    livewire(CitoyenLogin::class)
        ->fillForm(['method' => 'token', 'token' => 'unknown-token'])
        ->call('authenticate')
        ->assertHasFormErrors(['token']);

    $this->assertGuest('virtual_user');
});

it('refuses a wrong password', function (): void {
    $citoyen = citoyenWithPasswords(null, saltedShaHash('OldPassword'));

    logInWithPassword($citoyen->mail, 'WrongPassword')->assertHasFormErrors(['username']);

    $this->assertGuest('virtual_user');
});

it('refuses an unknown username', function (): void {
    logInWithPassword('unknown@marche.be', 'OldPassword')->assertHasFormErrors(['username']);

    $this->assertGuest('virtual_user');
});

it('ignores the legacy password once a userPassword is set, like Dovecot', function (): void {
    $citoyen = citoyenWithPasswords(DovecotPassword::hash('BrandNewPass123'), saltedShaHash('OldPassword'));

    logInWithPassword($citoyen->mail, 'OldPassword')->assertHasFormErrors(['username']);

    $this->assertGuest('virtual_user');
});

it('refuses an inactive account with a password', function (): void {
    $citoyen = citoyenWithPasswords(null, saltedShaHash('OldPassword'));
    $citoyen->virtualUser->update(['active' => false]);

    logInWithPassword($citoyen->mail, 'OldPassword')->assertHasFormErrors(['username']);

    $this->assertGuest('virtual_user');
});

it('refuses an inactive account with the personal link', function (): void {
    $citoyen = Citoyen::factory()->create();
    $citoyen->virtualUser->update(['active' => false]);

    $this->get(route('citoyen.auto-login', $citoyen->auth_token))->assertForbidden();

    $this->assertGuest('virtual_user');
});

it('refuses a login without a citoyen', function (): void {
    $virtualUser = VirtualUser::factory()->create(['legacy_password' => saltedShaHash('OldPassword')]);

    logInWithPassword($virtualUser->username, 'OldPassword')->assertHasFormErrors(['username']);

    $this->assertGuest('virtual_user');
});

it('does not reuse a session opened with a citoyen id under the former guard', function (): void {
    $citoyen = Citoyen::factory()->create();
    $formerSessionKey = 'login_citoyen_'.sha1(SessionGuard::class);

    $this->withSession([$formerSessionKey => $citoyen->virtualUser->id])
        ->get('/citoyen')
        ->assertRedirect(route('filament.citoyen.auth.login'));

    $this->assertGuest('virtual_user');
});

it('logs out without a remember token column', function (): void {
    $citoyen = Citoyen::factory()->create();

    $this->actingAs($citoyen->virtualUser, 'virtual_user')
        ->post(route('filament.citoyen.auth.logout'))
        ->assertRedirect();

    $this->assertGuest('virtual_user');
});

it('names the panel user after the citoyen', function (): void {
    $citoyen = Citoyen::factory()->create(['givenName' => 'Ada', 'sn' => 'Martin']);

    expect($citoyen->virtualUser->getFilamentName())->toBe('Ada Martin');
});
