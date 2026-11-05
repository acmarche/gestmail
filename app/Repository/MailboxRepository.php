<?php

declare(strict_types=1);

namespace App\Repository;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use SplFileInfo;

final class MailboxRepository
{
    public string $mailRoot = '/home/vmail/marche.be/';

    /**
     * Scripts Sieve présents dans le répertoire d'un citoyen.
     *
     * @return array<int, string>
     */
    public function findSieveFiles(?string $homeDirectory): array
    {
        if (! $homeDirectory) {
            return [];
        }

        $path = mb_rtrim($homeDirectory, '/').'/sieve';

        if (! File::isDirectory($path)) {
            return [];
        }

        return File::glob($path.'/*.sieve');
    }

    /**
     * Adresses vers lesquelles les scripts Sieve du citoyen renvoient le courrier.
     *
     * Une redirection signifie que le compte transfère son courrier ailleurs :
     * les messages non lus ne prouvent alors pas que le compte est abandonné.
     *
     * @return array<int, string>
     */
    public function sieveRedirects(?string $homeDirectory): array
    {
        $addresses = [];

        foreach ($this->findSieveFiles($homeDirectory) as $file) {
            $script = $this->stripSieveComments(File::get($file));

            if (preg_match_all('/\bredirect\b[^;"]*"([^"]+)"/i', $script, $matches) > 0) {
                $addresses = array_merge($addresses, $matches[1]);
            }
        }

        return array_values(array_unique($addresses));
    }

    /**
     * Chemin du dossier Maildir contenant les messages non lus d'un citoyen.
     */
    public function maildirNewPath(?string $homeDirectory): ?string
    {
        $maildir = $this->maildirPath($homeDirectory);

        return $maildir ? $maildir.'/new' : null;
    }

    /**
     * Nombre de messages non lus dans le Maildir d'un citoyen.
     * Retourne null si le dossier Maildir/new n'existe pas.
     */
    public function countNewMails(?string $homeDirectory): ?int
    {
        $path = $this->maildirNewPath($homeDirectory);

        if (! $path || ! File::isDirectory($path)) {
            return null;
        }

        return count(File::files($path));
    }

    /**
     * Date de dépôt du plus ancien message encore présent dans Maildir/new.
     *
     * Dovecot déplace les messages de new/ vers cur/ dès qu'un client IMAP
     * ouvre la INBOX : l'ancienneté de ce message indique donc depuis combien
     * de temps le citoyen n'a plus relevé son courrier.
     *
     * Retourne null si le dossier n'existe pas ou ne contient aucun message.
     */
    public function oldestNewMailAt(?string $homeDirectory): ?CarbonImmutable
    {
        $path = $this->maildirNewPath($homeDirectory);

        if (! $path || ! File::isDirectory($path)) {
            return null;
        }

        $timestamps = array_map(
            fn (SplFileInfo $file): int => $this->deliveryTimestamp($file),
            File::files($path)
        );

        if ($timestamps === []) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp(min($timestamps));
    }

    /**
     * Tous les répertoires IMAP présents sous la racine Dovecot.
     *
     * Les boîtes sont rangées par initiale : `<racine>/x/xavier.collart@marche.be`. Seuls
     * les répertoires contenant un sous-dossier `Maildir` sont retenus, ce qui
     * écarte les entrées parasites de la racine sans avoir à les énumérer.
     *
     * @return array<string, string> nom du répertoire => chemin du répertoire
     */
    public function allMailboxDirectories(): array
    {
        if (! File::isDirectory($this->mailRoot)) {
            return [];
        }

        $mailboxes = [];

        foreach (File::directories($this->mailRoot) as $initial) {
            foreach (File::directories($initial) as $path) {
                if (File::isDirectory($path.'/Maildir')) {
                    $mailboxes[basename($path)] = $path;
                }
            }
        }

        ksort($mailboxes);

        return $mailboxes;
    }

    /**
     * Espace occupé, en octets, d'après le fichier de quota Maildir++.
     *
     * Dovecot maintient `Maildir/maildirsize` : une première ligne décrivant
     * la limite (`10485760S,1000C`), puis une ligne par modification au format
     * `octets messages`, les suppressions étant négatives. Le total courant est
     * donc la somme de la première colonne de ces lignes.
     *
     * Le chiffre est approximatif : Dovecot n'y recalcule l'occupation réelle
     * qu'au moment du dépassement du quota ou à l'expiration du fichier.
     *
     * Retourne null si le fichier n'existe pas ou n'est pas lisible.
     */
    public function mailboxSize(?string $homeDirectory): ?int
    {
        $maildir = $this->maildirPath($homeDirectory);

        if (! $maildir || ! File::isReadable($path = $maildir.'/maildirsize')) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false || $lines === []) {
            return null;
        }

        $bytes = 0;

        foreach (array_slice($lines, 1) as $line) {
            $columns = preg_split('/\s+/', mb_trim($line)) ?: [];

            if (isset($columns[0]) && preg_match('/^-?\d+$/', $columns[0]) === 1) {
                $bytes += (int) $columns[0];
            }
        }

        return max($bytes, 0);
    }

    /**
     * Chemin du dossier Maildir d'un citoyen.
     */
    public function maildirPath(?string $homeDirectory): ?string
    {
        if (! $homeDirectory) {
            return null;
        }

        return mb_rtrim($homeDirectory, '/').'/Maildir';
    }

    /**
     * Date de la dernière relève du courrier par le citoyen.
     *
     * Dovecot réécrit son journal d'index à chaque session IMAP ou POP3 : la
     * date de modification de `dovecot.index.log` est donc la trace la plus
     * fiable de la dernière connexion. À défaut, le dossier `cur` est daté du
     * dernier déplacement d'un message lu depuis `new`, ce qui approche la
     * même information.
     *
     * Retourne null si aucune de ces traces n'existe.
     */
    public function lastLoginAt(?string $homeDirectory): ?CarbonImmutable
    {
        $maildir = $this->maildirPath($homeDirectory);

        if (! $maildir) {
            return null;
        }

        foreach ([$maildir.'/dovecot.index.log', $maildir.'/cur'] as $path) {
            if (File::exists($path)) {
                return CarbonImmutable::createFromTimestamp(File::lastModified($path));
            }
        }

        return null;
    }

    /**
     * Retire les commentaires d'un script Sieve (# ligne et bloc slash-étoile).
     */
    private function stripSieveComments(string $script): string
    {
        $script = preg_replace('#/\*.*?\*/#s', '', $script) ?? $script;

        return preg_replace('/^\s*#.*$/m', '', $script) ?? $script;
    }

    /**
     * Date de dépôt d'un message Maildir.
     *
     * Le nom de fichier Maildir commence par l'horodatage unix de la remise
     * (ex: 1712345678.M123P456.host). On retombe sur la date de modification
     * du fichier si le nom ne suit pas cette convention.
     */
    private function deliveryTimestamp(SplFileInfo $file): int
    {
        if (preg_match('/^(\d{9,12})\./', $file->getFilename(), $matches) === 1) {
            return (int) $matches[1];
        }

        return $file->getMTime();
    }
}
