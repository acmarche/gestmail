<?php

declare(strict_types=1);

namespace App\Sms;

use App\Sms\Dto\SmsResponse;
use App\Sms\Exception\SmsException;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheStoreRepository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;

/**
 * Client of the Inforius SMS gateway, ported from the intranet (AcMarche\App\Sms) without
 * the history endpoint.
 */
final class InforiusClient
{
    private const CONTENT_NS = 'http://schemas.datacontract.org/2004/07/Inforius.eCommunication.Contract.Message';

    private const INSTANCE_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    private const TOKEN_CACHE_KEY = 'sms.inforius.token';

    /**
     * Safety margin (seconds) before the token expires.
     */
    private const TOKEN_TTL_MARGIN = 60;

    private readonly HttpFactory $http;

    private readonly InforiusXmlParser $parser;

    private readonly CacheRepository $cache;

    public function __construct(
        private readonly string $host,
        private readonly string $user,
        private readonly string $password,
        private readonly ?string $sender = null,
        ?HttpFactory $http = null,
        ?InforiusXmlParser $parser = null,
        ?CacheRepository $cache = null,
    ) {
        $this->http = $http ?? new HttpFactory;
        $this->parser = $parser ?? new InforiusXmlParser;
        // Without a store the token is cached for this instance only, which keeps
        // the client usable outside a booted application.
        $this->cache = $cache ?? new CacheStoreRepository(new ArrayStore);
    }

    public static function fromConfig(): self
    {
        $host = (string) config('app.sms.host');
        $user = (string) config('app.sms.user');
        $password = (string) config('app.sms.password');

        if ($host === '' || $user === '' || $password === '') {
            throw new SmsException('Missing SMS_HOST, SMS_USER or SMS_PASSWORD configuration.');
        }

        return new self(
            host: mb_rtrim($host, '/').'/',
            user: $user,
            password: $password,
            sender: config('app.sms.sender'),
            cache: Cache::store(),
        );
    }

    /**
     * Send a single SMS to the given international number (e.g. 32476123456).
     */
    public function sendSms(string $number, string $message, ?string $customerReference = null): SmsResponse
    {
        return $this->sendSmsBatch([
            ['number' => $number, 'text' => $message, 'customerReference' => $customerReference],
        ]);
    }

    /**
     * Send one or more SMS messages in a single request.
     *
     * @param  array<int, array{number: string, text: string, customerReference?: ?string}>  $recipients
     */
    public function sendSmsBatch(array $recipients): SmsResponse
    {
        if ($recipients === []) {
            throw new SmsException('At least one recipient is required.');
        }

        $token = $this->getAccessToken();
        $body = $this->buildSendXml($token, $recipients);

        $xml = $this->post('Send', $body);

        return $this->parser->parseSendResponse($xml);
    }

    public function releaseToken(): void
    {
        $token = $this->readCachedToken();
        if ($token === null) {
            return;
        }

        $this->post('ReleaseToken?access_token='.urlencode($token), '');
        $this->forgetCachedToken();
    }

    public function getAccessToken(bool $forceRefresh = false): string
    {
        if ($forceRefresh) {
            $this->forgetCachedToken();
        }

        $cached = $this->readCachedToken();
        if ($cached !== null) {
            return $cached;
        }

        $body = $this->buildTokenXml();
        $xml = $this->post('RequestToken', $body);

        ['token' => $token, 'expiration' => $expirationMs] = $this->parser->parseTokenResponse($xml);

        $ttl = max(30, (int) floor($expirationMs / 1000) - self::TOKEN_TTL_MARGIN);
        $this->storeCachedToken($token, $ttl);

        return $token;
    }

    private function post(string $action, string $body): string
    {
        try {
            $response = $this->http
                ->withBody($body, 'application/x-www-form-urlencoded')
                ->post($this->host.$action);
        } catch (ConnectionException $exception) {
            throw new SmsException('SMS gateway unreachable: '.$exception->getMessage(), 0, $exception);
        }

        if ($response->failed()) {
            throw new SmsException(sprintf(
                'SMS gateway returned HTTP %d for action "%s".',
                $response->status(),
                $action,
            ));
        }

        return $response->body();
    }

    private function readCachedToken(): ?string
    {
        $value = $this->cache->get(self::TOKEN_CACHE_KEY);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function storeCachedToken(string $token, int $ttl): void
    {
        $this->cache->put(self::TOKEN_CACHE_KEY, $token, $ttl);
    }

    private function forgetCachedToken(): void
    {
        $this->cache->forget(self::TOKEN_CACHE_KEY);
    }

    private function buildTokenXml(): string
    {
        return sprintf(
            '<RequestTokenRequest xmlns="%s" xmlns:i="%s"><Password>%s</Password><UserName>%s</UserName></RequestTokenRequest>',
            self::CONTENT_NS,
            self::INSTANCE_NS,
            htmlspecialchars($this->password, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->user, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
        );
    }

    /**
     * @param  array<int, array{number: string, text: string, customerReference?: ?string}>  $recipients
     */
    private function buildSendXml(string $token, array $recipients): string
    {
        $xml = '<script xmlns="'.self::CONTENT_NS.'">';
        $xml .= '<context>';
        $xml .= '<user>'.htmlspecialchars($this->user, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</user>';
        if ($this->sender !== null && $this->sender !== '') {
            $xml .= '<sender>'.htmlspecialchars($this->sender, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</sender>';
        }
        $xml .= '<token>'.htmlspecialchars($token, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</token>';
        $xml .= '</context>';

        $xml .= '<recipients>';
        foreach ($recipients as $recipient) {
            $number = SmsUtils::cleanPhoneNumber($recipient['number']);
            $text = urlencode($recipient['text']);

            $xml .= '<recipient>';
            $xml .= '<type>S</type>';
            $xml .= '<number>'.urlencode('+'.$number).'</number>';
            $xml .= '<text><![CDATA['.$text.']]></text>';

            $reference = $recipient['customerReference'] ?? null;
            if ($reference !== null && $reference !== '') {
                $xml .= '<customerReference>'.htmlspecialchars(
                    mb_substr($reference, 0, 50),
                    ENT_XML1 | ENT_QUOTES,
                    'UTF-8'
                ).'</customerReference>';
            }
            $xml .= '</recipient>';
        }
        $xml .= '</recipients>';
        $xml .= '</script>';

        return $xml;
    }
}
