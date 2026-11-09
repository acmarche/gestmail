<?php

declare(strict_types=1);

namespace App\Sms;

use App\Sms\Dto\SmsMessageStatus;
use App\Sms\Dto\SmsResponse;
use App\Sms\Exception\SmsException;
use SimpleXMLElement;

final class InforiusXmlParser
{
    /**
     * @return array{token: string, expiration: int}
     */
    public function parseTokenResponse(string $xmlString): array
    {
        $xml = $this->load($xmlString);

        $error = $this->value($xml->Error);
        if ($error !== null) {
            $code = $this->value($xml->ErrorCode) ?? '';

            throw new SmsException(sprintf('Token request failed [%s]: %s', $code, $error));
        }

        $token = $this->value($xml->Token);
        if ($token === null || $token === '') {
            throw new SmsException('Token request returned no token.');
        }

        return [
            'token' => $token,
            'expiration' => (int) ($this->value($xml->Expiration) ?? 0),
        ];
    }

    public function parseSendResponse(string $xmlString): SmsResponse
    {
        $xml = $this->load($xmlString);

        $messages = [];
        if (isset($xml->Messages->MessageStatus)) {
            foreach ($xml->Messages->MessageStatus as $node) {
                $messages[] = new SmsMessageStatus(
                    number: $this->value($node->Number),
                    type: $this->value($node->Type),
                    errorCode: $this->value($node->ErrorCode),
                    errorMessage: $this->value($node->ErrorMessage),
                    customerReference: $this->value($node->CustomerReference),
                );
            }
        }

        return new SmsResponse(
            error: $this->value($xml->Error),
            balance: (float) ($this->value($xml->Balance) ?? 0),
            messages: $messages,
        );
    }

    private function load(string $xmlString): SimpleXMLElement
    {
        $xml = @simplexml_load_string($xmlString, SimpleXMLElement::class, LIBXML_NOCDATA);

        if ($xml === false) {
            throw new SmsException('Failed to parse XML response: '.$xmlString);
        }

        return $xml;
    }

    private function value(?SimpleXMLElement $element): ?string
    {
        if ($element === null) {
            return null;
        }

        $attributes = $element->attributes('i', true);
        if (isset($attributes['nil']) && (string) $attributes['nil'] === 'true') {
            return null;
        }

        $value = mb_trim((string) $element);

        return $value === '' ? null : $value;
    }
}
