<?php

declare(strict_types=1);

namespace VietQr\Rendering;

use VietQr\Payment;

/** Implement this interface to use an existing QR encoder in the host application. */
interface QrRenderer
{
    public function svg(Payment $payment, int $size = 320): string;

    public function png(Payment $payment, int $size = 320): string;
}
