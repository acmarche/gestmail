<?php

declare(strict_types=1);

namespace App\Sms\Dto;

final class SmsResponse
{
    /**
     * @param  array<int, SmsMessageStatus>  $messages
     */
    public function __construct(
        public ?string $error = null,
        public float $balance = 0.0,
        public array $messages = [],
    ) {}

    public function isSuccessful(): bool
    {
        if ($this->error !== null) {
            return false;
        }

        foreach ($this->messages as $message) {
            if ($message->hasError()) {
                return false;
            }
        }

        return true;
    }
}
