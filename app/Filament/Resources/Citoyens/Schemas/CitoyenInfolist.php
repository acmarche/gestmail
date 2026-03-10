<?php

declare(strict_types=1);

namespace App\Filament\Resources\Citoyens\Schemas;

use App\Imap\ImapCitoyen;
use App\Models\Citoyen;
use Exception;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CitoyenInfolist
{
    /**
     * @var array<string, array{usage: int, limit: int, pourcentage: string}|null>
     */
    private static array $quotaCache = [];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->columns()
                    ->components([
                        TextEntry::make('givenName')
                            ->label('Prénom'),
                        TextEntry::make('sn')
                            ->label('Nom'),
                        TextEntry::make('cn')
                            ->label('Nom complet'),
                        TextEntry::make('uid')
                            ->label('Identifiant'),
                        TextEntry::make('employeeNumber')
                            ->label('Numéro employé'),
                    ]),
                Section::make('Coordonnées')
                    ->columns()
                    ->components([
                        TextEntry::make('mail')
                            ->label('Email'),
                        TextEntry::make('postalAddress')
                            ->label('Adresse'),
                        TextEntry::make('postalCode')
                            ->label('Code postal'),
                        TextEntry::make('l')
                            ->label('Localité'),
                    ]),
                Section::make('Paramètres')
                    ->columns()
                    ->components([
                        TextEntry::make('gosaMailQuota')
                            ->label('Quota'),
                        TextEntry::make('gosaMailForwardingAddress')
                            ->label('Adresse de transfert'),
                        TextEntry::make('gosaMailAlternateAddress')
                            ->label('Adresse alternative'),
                        TextEntry::make('homeDirectory')
                            ->label('Répertoire home'),
                    ]),
                Section::make('Connexion')
                    ->columns()
                    ->components([
                        TextEntry::make('last_connection')
                            ->label('Dernière connexion')
                            ->date(),
                        TextEntry::make('protocol_connection')
                            ->label('Protocole'),
                        TextEntry::make('port_connection')
                            ->label('Port')
                            ->numeric(),
                        IconEntry::make('secure_connection')
                            ->label('Sécurisé')
                            ->boolean(),
                    ]),
                Section::make('Utilisation boîte mail')
                    ->columns()
                    ->components([
                        TextEntry::make('quota_usage')
                            ->label('Utilisation')
                            ->state(function (Citoyen $record): string {
                                $quotaInfo = self::getQuotaInfo($record->uid);

                                if ($quotaInfo === null) {
                                    return 'Impossible de récupérer le quota';
                                }

                                $usageMo = round($quotaInfo['usage'] / 1024, 2);
                                $limitMo = round($quotaInfo['limit'] / 1024, 2);

                                return "{$usageMo} Mo / {$limitMo} Mo ({$quotaInfo['pourcentage']}%)";
                            }),
                        TextEntry::make('quota_percentage')
                            ->label('Pourcentage utilisé')
                            ->state(function (Citoyen $record): string {
                                $quotaInfo = self::getQuotaInfo($record->uid);

                                return $quotaInfo !== null ? $quotaInfo['pourcentage'].'%' : '-';
                            })
                            ->badge()
                            ->color(function (Citoyen $record): string {
                                $quotaInfo = self::getQuotaInfo($record->uid);

                                if ($quotaInfo === null) {
                                    return 'gray';
                                }

                                $percentage = (float) $quotaInfo['pourcentage'];

                                return match (true) {
                                    $percentage >= 90 => 'danger',
                                    $percentage >= 70 => 'warning',
                                    default => 'success',
                                };
                            }),
                    ]),
                Section::make('Divers')
                    ->components([
                        TextEntry::make('dn')
                            ->label('DN')
                            ->columnSpanFull(),
                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @return array{usage: int, limit: int, pourcentage: string}|null
     */
    private static function getQuotaInfo(string $uid): ?array
    {
        if (array_key_exists($uid, self::$quotaCache)) {
            return self::$quotaCache[$uid];
        }

        try {
            $imapCitoyen = new ImapCitoyen(
                config('imap.citoyen.host'),
                config('imap.citoyen.user'),
                config('imap.citoyen.password'),
            );

            self::$quotaCache[$uid] = $imapCitoyen->getQuota($uid);
        } catch (Exception) {
            self::$quotaCache[$uid] = null;
        }

        return self::$quotaCache[$uid];
    }
}
