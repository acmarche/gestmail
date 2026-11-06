<?php

declare(strict_types=1);

use App\Models\Citoyen;
use App\Models\VirtualUser;
use App\Repository\MailboxRepository;
use App\Support\DovecotPassword;

it('takes an account over and keeps the hash Dovecot was using', function (): void {
    $citoyen = Citoyen::factory()->create();
    $legacyHash = $citoyen->virtualUser->legacy_password;

    $citoyen->takeOver('TemporaryPass123');

    $virtualUser = $citoyen->virtualUser->refresh();

    expect($citoyen->refresh()->hand_password)->toBe($legacyHash)
        ->and($citoyen->isTakenOver())->toBeTrue()
        ->and($virtualUser->legacy_password)->toBeNull()
        ->and(DovecotPassword::check('TemporaryPass123', $virtualUser->userPassword))->toBeTrue();
});

it('does not overwrite the kept hash on a second take over', function (): void {
    $citoyen = Citoyen::factory()->create();
    $legacyHash = $citoyen->virtualUser->legacy_password;

    $citoyen->takeOver('TemporaryPass123');
    $citoyen->refresh()->takeOver('OtherTemporary123');

    expect($citoyen->refresh()->hand_password)->toBe($legacyHash)
        ->and(DovecotPassword::check('OtherTemporary123', $citoyen->virtualUser->userPassword))->toBeTrue();
});

it('restores the kept hash', function (): void {
    $citoyen = Citoyen::factory()->create();
    $legacyHash = $citoyen->virtualUser->legacy_password;

    $citoyen->takeOver('TemporaryPass123');
    $citoyen->refresh()->restorePassword();

    $virtualUser = $citoyen->virtualUser->refresh();

    expect($citoyen->refresh()->isTakenOver())->toBeFalse()
        ->and($virtualUser->userPassword)->toBe($legacyHash)
        ->and($virtualUser->legacy_password)->toBeNull();
});

it('deletes the citoyen with its login', function (): void {
    $citoyen = Citoyen::factory()->create();
    $virtualUserId = $citoyen->virtualUser->id;

    $citoyen->deleteWithLogin();

    expect(Citoyen::find($citoyen->id))->toBeNull()
        ->and(VirtualUser::find($virtualUserId))->toBeNull();
});

it('builds the home directory under the mail root of the mailbox repository', function (): void {
    expect(Citoyen::homeDirectoryFor('Jean.dupont@marche.be'))
        ->toBe('/home/vmail/marche.be/j/Jean.dupont@marche.be');
});

it('follows the mail root configured on the mailbox repository', function (): void {
    app()->instance(MailboxRepository::class, tap(new MailboxRepository, function (MailboxRepository $repository): void {
        $repository->mailRoot = '/tmp/mail/';
    }));

    expect(Citoyen::homeDirectoryFor('jean@marche.be'))->toBe('/tmp/mail/j/jean@marche.be');
});
