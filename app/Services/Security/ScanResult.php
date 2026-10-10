<?php

namespace App\Services\Security;

/**
 * What the virus scanner said about one file: clean, infected (with the name of the virus), or
 * "could not be checked" (with the reason).
 */
final readonly class ScanResult
{
    private function __construct(
        public ?string $signature = null,
        public ?string $errorMessage = null,
    ) {}

    public static function clean(): self
    {
        return new self;
    }

    public static function infected(string $signature): self
    {
        return new self(signature: $signature);
    }

    public static function error(string $message): self
    {
        return new self(errorMessage: $message);
    }

    public function isClean(): bool
    {
        return $this->signature === null && $this->errorMessage === null;
    }

    public function isInfected(): bool
    {
        return $this->signature !== null;
    }

    public function isError(): bool
    {
        return $this->errorMessage !== null;
    }
}
