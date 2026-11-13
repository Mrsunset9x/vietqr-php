<?php

declare(strict_types=1);

namespace VietQr;

final readonly class Bank
{
    public function __construct(
        public string $bin,
        public string $code,
        public string $name,
        public string $shortName,
    ) {
    }
}
