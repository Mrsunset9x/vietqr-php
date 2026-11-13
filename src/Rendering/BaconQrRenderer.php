<?php

declare(strict_types=1);

namespace VietQr\Rendering;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use VietQr\Payment;
use VietQr\VietQr;

/** Optional adapter. No network, external assets, configuration or implicit file writes. */
final class BaconQrRenderer implements QrRenderer
{
    public function svg(Payment $payment, int $size = 320): string
    {
        $this->checkRequirements($size, 'xmlwriter');
        $renderer = new ImageRenderer(new RendererStyle($size, 4), new SvgImageBackEnd());

        return (new Writer($renderer))->writeString(VietQr::generate($payment), 'ISO-8859-1', ErrorCorrectionLevel::M());
    }

    public function png(Payment $payment, int $size = 320): string
    {
        $this->checkRequirements($size, 'gd');
        // Four-module quiet zone, square modules; moderate compression avoids excessive CPU.
        $renderer = new GDLibRenderer($size, 4, 'png', 6);

        return (new Writer($renderer))->writeString(VietQr::generate($payment), 'ISO-8859-1', ErrorCorrectionLevel::M());
    }

    private function checkRequirements(int $size, string $extension): void
    {
        if ($size < 256 || $size > 2048) {
            throw new \InvalidArgumentException('QR size must be between 256 and 2048 pixels.');
        }
        if (!class_exists(Writer::class)) {
            throw new \LogicException('Rendering requires bacon/bacon-qr-code:^3.0. Install it with Composer or provide your own QrRenderer.');
        }
        if (!extension_loaded($extension)) {
            throw new \LogicException("This renderer requires the PHP {$extension} extension.");
        }
    }
}
