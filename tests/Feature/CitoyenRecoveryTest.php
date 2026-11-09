<?php

declare(strict_types=1);

use App\Filament\Citoyen\Pages\Auth\RecoveryLogin;
use App\Filament\Citoyen\Pages\Auth\RequestRecoveryLink;
use App\Models\Citoyen;
use App\Models\VirtualUser;
use App\Notifications\CitoyenRecoveryLink;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('citoyen'));
    Notification::fake();
});

it('stores the personal data encrypted and reads it back', function (): void {
    $citoyen = Citoyen::factory()->create([
        'recovery_email' => 'perso@example.com',
        'recovery_phone' => '0470123456',
        'l' => 'Marche-en-Famenne',
    ]);

    $stored = DB::table('citoyens')->where('id', $citoyen->id)->sole();

    expect($stored)
        ->recovery_email->not->toContain('perso@example.com')
        ->recovery_phone->not->toContain('0470123456')
        ->l->not->toContain('Marche')
        ->and($citoyen->refresh())
        ->recovery_email->toBe('perso@example.com')
        ->recovery_phone->toBe('0470123456')
        ->l->toBe('Marche-en-Famenne');
});

it('links to the recovery page from the login page', function (): void {
    $this->get(route('filament.citoyen.auth.login'))
        ->assertSee(route('filament.citoyen.auth.recovery'));
});

it('sends a recovery link to the recovery email of the mailbox', function (): void {
    $citoyen = Citoyen::factory()->create(['recovery_email' => 'perso@example.com']);

    livewire(RequestRecoveryLink::class)
        ->fillForm(['mail' => mb_strtoupper($citoyen->mail)])
        ->call('request')
        ->assertNotified('Demande enregistrée');

    Notification::assertSentOnDemand(
        CitoyenRecoveryLink::class,
        fn (CitoyenRecoveryLink $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'perso@example.com'
            && $notification->mail === $citoyen->mail,
    );
});

it('gives the same answer and sends nothing without a recovery email', function (string $mail): void {
    VirtualUser::factory()->create(['username' => 'phone.only@marche.be']);
    Citoyen::factory()->create(['mail' => 'phone.only@marche.be', 'recovery_phone' => '0470123456']);

    livewire(RequestRecoveryLink::class)
        ->fillForm(['mail' => $mail])
        ->call('request')
        ->assertNotified('Demande enregistrée');

    Notification::assertNothingSent();
})->with(['unknown mailbox' => 'nobody@marche.be', 'no recovery email' => 'phone.only@marche.be']);

it('logs the citoyen in on the password page only when they continue', function (): void {
    $citoyen = Citoyen::factory()->create(['charter_accepted_at' => now(), 'recovery_email' => 'perso@example.com']);
    $token = RecoveryLogin::issueToken($citoyen);

    $this->get(route('filament.citoyen.auth.recovery-login', ['token' => $token]))->assertSuccessful();
    $this->assertGuest('virtual_user');

    livewire(RecoveryLogin::class, ['token' => $token])
        ->callAction(TestAction::make('continue')->schemaComponent('recovery-actions'))
        ->assertRedirect(route('filament.citoyen.pages.change-password'));

    $this->assertAuthenticatedAs($citoyen->virtualUser, 'virtual_user');
});

it('refuses a link that was already used', function (): void {
    $token = RecoveryLogin::issueToken(Citoyen::factory()->create());

    livewire(RecoveryLogin::class, ['token' => $token])
        ->callAction(TestAction::make('continue')->schemaComponent('recovery-actions'));
    Auth::guard('virtual_user')->logout();

    livewire(RecoveryLogin::class, ['token' => $token])
        ->assertSet('isValid', false)
        ->assertSee("Ce lien n'est plus valable");
});

it('refuses a link replaced by a newer one', function (): void {
    $citoyen = Citoyen::factory()->create();
    $first = RecoveryLogin::issueToken($citoyen);
    $second = RecoveryLogin::issueToken($citoyen);

    livewire(RecoveryLogin::class, ['token' => $first])->assertSet('isValid', false);
    livewire(RecoveryLogin::class, ['token' => $second])->assertSet('isValid', true);
});

it('refuses an expired or unknown link', function (): void {
    $token = RecoveryLogin::issueToken(Citoyen::factory()->create());

    $this->travel(RecoveryLogin::LIFETIME_MINUTES + 1)->minutes();

    livewire(RecoveryLogin::class, ['token' => $token])->assertSet('isValid', false);
    livewire(RecoveryLogin::class, ['token' => 'unknown'])->assertSet('isValid', false);
    $this->assertGuest('virtual_user');
});
