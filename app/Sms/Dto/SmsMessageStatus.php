<?php

declare(strict_types=1);

namespace App\Sms\Dto;

final class SmsMessageStatus
{
    public function __construct(
        public ?string $number = null,
        public ?string $type = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?string $customerReference = null,
    ) {}

    public function hasError(): bool
    {
        return $this->errorCode !== null || $this->errorMessage !== null;
    }
}
