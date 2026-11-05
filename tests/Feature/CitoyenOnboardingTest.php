<?php

declare(strict_types=1);

use App\Filament\Citoyen\Pages\ChangePassword;
use App\Filament\Citoyen\Pages\Onboarding;
use App\Models\Citoyen;
use App\Support\DovecotPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('citoyen'));
});

it('logs a citizen in with their personal link', function (): void {
    $citoyen = Citoyen::factory()->create();

    $this->get(route('citoyen.auto-login', $citoyen->auth_token))
        ->assertRedirect('/citoyen');

    $this->assertAuthenticatedAs($citoyen, 'citoyen');
});

it('refuses an unknown personal link', function (): void {
    $this->get(route('citoyen.auto-login', 'unknown-token'))->assertForbidden();
});

it('redirects a citizen to the onboarding until it is completed', function (): void {
    $citoyen = Citoyen::factory()->create();

    $this->actingAs($citoyen, 'citoyen')
        ->get('/citoyen')
        ->assertRedirect(route('filament.citoyen.pages.onboarding'));
});

it('opens the dashboard once the onboarding is completed', function (): void {
    $citoyen = Citoyen::factory()->create([
        'charter_accepted_at' => now(),
        'recovery_email' => 'perso@example.com',
    ]);

    $this->actingAs($citoyen, 'citoyen')->get('/citoyen')->assertSuccessful();
});

it('completes the onboarding and stores the password in virtual_users', function (): void {
    $citoyen = Citoyen::factory()->create();

    $this->actingAs($citoyen, 'citoyen');

    livewire(Onboarding::class)
        ->fillForm([
            'accept_charter' => true,
            'new_password' => 'BrandNewPass123',
            'new_password_confirmation' => 'BrandNewPass123',
            'recovery_email' => 'perso@example.com',
        ])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect($citoyen->refresh()->hasCompletedOnboarding())->toBeTrue()
        ->and(DovecotPassword::check('BrandNewPass123', $citoyen->virtualUser->userPassword))->toBeTrue()
        ->and($citoyen->virtualUser->legacy_password)->toBeNull();
});

it('changes the password from the personal page', function (): void {
    $citoyen = Citoyen::factory()->create([
        'charter_accepted_at' => now(),
        'recovery_email' => 'perso@example.com',
    ]);

    $this->actingAs($citoyen, 'citoyen');

    livewire(ChangePassword::class)
        ->fillForm([
            'new_password' => 'BrandNewPass123',
            'new_password_confirmation' => 'BrandNewPass123',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DovecotPassword::check('BrandNewPass123', $citoyen->virtualUser->refresh()->userPassword))->toBeTrue();
});

it('rejects a password shorter than 12 characters', function (): void {
    $citoyen = Citoyen::factory()->create([
        'charter_accepted_at' => now(),
        'recovery_email' => 'perso@example.com',
    ]);

    $this->actingAs($citoyen, 'citoyen');

    livewire(ChangePassword::class)
        ->fillForm([
            'new_password' => 'Short1pass',
            'new_password_confirmation' => 'Short1pass',
        ])
        ->call('save')
        ->assertHasFormErrors(['new_password']);

    expect($citoyen->virtualUser->refresh()->userPassword)->toBeNull();
});

it('no longer exposes an admin panel', function (): void {
    expect(Route::has('filament.admin.pages.dashboard'))->toBeFalse();

    $this->get('/admin')->assertNotFound();
});
