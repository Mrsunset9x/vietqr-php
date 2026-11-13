<?php

declare(strict_types=1);

namespace VietQr;

enum QrType: string
{
    case Static = '11';
    case Dynamic = '12';
}
