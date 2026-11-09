<?php

declare(strict_types=1);

use App\Filament\Citoyen\Pages\Auth\RecoveryLogin;
use App\Filament\Citoyen\Pages\Auth\RequestRecoveryLink;
use App\Models\Citoyen;
use App\Models\VirtualUser;
use App\Notifications\CitoyenRecoveryLink;
use App\Sms\InforiusClient;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

/**
 * InforiusClient is final, so the gateway is faked at the HTTP layer, as in the intranet.
 */
function fakeSmsGateway(mixed $sendResponse): HttpFactory
{
    $http = new HttpFactory;
    $http->fake([
        'sms.example.test/RequestToken' => HttpFactory::response(
            <<<'XML'
<RequestTokenResponse xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
    <Error i:nil="true"/>
    <Expiration>1200000</Expiration>
    <Token>token-xyz</Token>
</RequestTokenResponse>
XML
        ),
        'sms.example.test/Send' => $sendResponse,
    ]);

    app()->instance(InforiusClient::class, new InforiusClient(
        host: 'https://sms.example.test/',
        user: 'test_user',
        password: 'secret',
        http: $http,
    ));

    return $http;
}

function successfulSendResponse(): mixed
{
    return HttpFactory::response(
        <<<'XML'
<SendMessageResponse xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
    <Error i:nil="true"/>
    <Balance>500</Balance>
    <Messages>
        <MessageStatus>
            <ErrorCode i:nil="true"/>
            <ErrorMessage i:nil="true"/>
            <Number>+32470123456</Number>
            <Type>S</Type>
        </MessageStatus>
    </Messages>
</SendMessageResponse>
XML
    );
}

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

it('gives the same answer and sends nothing without recovery data', function (string $mail): void {
    $http = fakeSmsGateway(successfulSendResponse());
    VirtualUser::factory()->create(['username' => 'no.recovery@marche.be']);
    Citoyen::factory()->create(['mail' => 'no.recovery@marche.be']);

    livewire(RequestRecoveryLink::class)
        ->fillForm(['mail' => $mail])
        ->call('request')
        ->assertNotified('Demande enregistrée');

    Notification::assertNothingSent();
    $http->assertNothingSent();
})->with(['unknown mailbox' => 'nobody@marche.be', 'no recovery data' => 'no.recovery@marche.be']);

it('sends the recovery link by sms to the recovery phone', function (): void {
    $http = fakeSmsGateway(successfulSendResponse());
    $citoyen = Citoyen::factory()->create(['recovery_phone' => '0470 12 34 56']);

    livewire(RequestRecoveryLink::class)
        ->fillForm(['mail' => $citoyen->mail])
        ->call('request')
        ->assertNotified('Demande enregistrée');

    $http->assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/Send')
        && str_contains($request->body(), urlencode('+32470123456'))
        && str_contains($request->body(), urlencode('/citoyen/recuperation/')));
    Notification::assertNothingSent();
});

it('sends the same single link by mail and by sms', function (): void {
    $http = fakeSmsGateway(successfulSendResponse());
    $citoyen = Citoyen::factory()->create(['recovery_email' => 'perso@example.com', 'recovery_phone' => '0470123456']);

    livewire(RequestRecoveryLink::class)
        ->fillForm(['mail' => $citoyen->mail])
        ->call('request');

    $mailedUrl = null;
    Notification::assertSentOnDemand(CitoyenRecoveryLink::class, function (CitoyenRecoveryLink $notification) use (&$mailedUrl): bool {
        $mailedUrl = $notification->url;

        return true;
    });

    $http->assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/Send')
        && str_contains($request->body(), urlencode($mailedUrl)));
});

it('still answers when the sms gateway fails', function (): void {
    fakeSmsGateway(HttpFactory::response('', 500));
    $citoyen = Citoyen::factory()->create(['recovery_phone' => '0470123456']);

    livewire(RequestRecoveryLink::class)
        ->fillForm(['mail' => $citoyen->mail])
        ->call('request')
        ->assertNotified('Demande enregistrée');
});

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
