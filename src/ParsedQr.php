<?php

declare(strict_types=1);

namespace VietQr;

final readonly class ParsedQr
{
    /** @param array<int|string, string> $tags Original root fields; unknown fields are retained. */
    public function __construct(
        public Payment $payment,
        public string $payload,
        public array $tags,
    ) {
    }
}
