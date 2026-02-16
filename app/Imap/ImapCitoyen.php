<?php

namespace App\Imap;

use DirectoryTree\ImapEngine\Exceptions\ImapCapabilityException;
use DirectoryTree\ImapEngine\Exceptions\ImapConnectionFailedException;
use DirectoryTree\ImapEngine\Mailbox;
use Exception;

class ImapCitoyen
{
    private ?Mailbox $mailbox = null;

    public function __construct(
        private  string $host,
        private  string $user,
        private  string $password,
    ) {
        $this->host = $host ?? config('imap.citoyen.host');
        $this->user = $this->user ?? config('imap.citoyen.user');
        $this->password = $this->password ?? config('imap.citoyen.password');
    }

    /**
     * Connect as a user using admin proxy authentication.
     *
     * @throws ImapConnectionFailedException
     * @throws Exception
     */
    public function connect(string $user): Mailbox
    {
        // Use proxy authentication: user*admin format
        $username = $user.'*'.$this->user;

        $this->mailbox = new Mailbox([
            'host' => $this->host,
            'port' => 993,
            'encryption' => 'ssl',
            'username' => $username,
            'password' => $this->password,
            'validate_cert' => false,
        ]);

        try {
            $this->mailbox->connect();
        } catch (ImapConnectionFailedException $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        return $this->mailbox;
    }

    /**
     * Get quota for a user mailbox.
     * Returns array:
     * [
     *   "usage" => 88909
     *   "limit" => 1048576
     *   "pourcentage" => "8.48"
     * ]
     *
     * @throws Exception
     */
    public function getQuota(string $user): array
    {
        $quota = [];

        if (!$this->mailbox?->connected()) {
            $this->connect($user);
        }

        try {
            // Get the inbox folder to retrieve quota
            $folder = $this->mailbox->inbox();
            $quotaData = $folder->quota();

            // The quota() method returns an array like: ['INBOX' => ['STORAGE' => ['usage' => X, 'limit' => Y]]]
            foreach ($quotaData as $quotaValues) {
                if (isset($quotaValues['STORAGE'])) {
                    $quota['usage'] = $quotaValues['STORAGE']['usage'];
                    $quota['limit'] = $quotaValues['STORAGE']['limit'];
                    $pourcentage = $quota['limit'] == 0 ? 100 : ($quota['usage'] * 100) / $quota['limit'];
                    $quota['pourcentage'] = number_format($pourcentage, 2);
                    break;
                }
            }
        } catch (ImapCapabilityException $e) {
            throw new Exception('Le serveur IMAP ne supporte pas les quotas: '.$e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        } finally {
            $this->close();
        }

        return $quota;
    }

    public function close(): void
    {
        $this->mailbox?->disconnect();
        $this->mailbox = null;
    }
}
