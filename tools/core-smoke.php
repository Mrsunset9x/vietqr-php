<?php

declare(strict_types=1);

// Also run with php -n: the core requires no optional PHP extensions or QR package.
require dirname(__DIR__) . '/vendor/autoload.php';

use VietQr\BankCatalog;
use VietQr\Payment;
use VietQr\Rendering\BaconQrRenderer;
use VietQr\VietQr;

$payment = new Payment('970403', '0011012345678', 180000, 'thanh toán đơn hàng', billNumber: 'NPS6869');
$payload = VietQr::generate($payment);
if ($payload !== '00020101021238570010A00000072701270006970403011300110123456780208QRIBFTTA530370454061800005802VN62340107NPS68690819thanh toan don hang63042E2E') {
    throw new RuntimeException('Core golden vector mismatch.');
}
if (VietQr::parse($payload)->payment != $payment || count(BankCatalog::all()) !== 76) {
    throw new RuntimeException('Core parse/catalog smoke failed.');
}
if (!class_exists(\BaconQrCode\Writer::class)) {
    try {
        (new BaconQrRenderer())->svg($payment);
        throw new RuntimeException('Missing renderer dependency was not detected.');
    } catch (LogicException $error) {
        if (!str_contains($error->getMessage(), 'bacon/bacon-qr-code')) {
            throw $error;
        }
    }
} else {
    foreach (['svg' => 'xmlwriter', 'png' => 'gd'] as $format => $extension) {
        if (extension_loaded($extension)) {
            continue;
        }
        try {
            (new BaconQrRenderer())->$format($payment);
            throw new RuntimeException('Missing renderer extension was not detected.');
        } catch (LogicException $error) {
            if (!str_contains($error->getMessage(), "PHP {$extension} extension")) {
                throw $error;
            }
        }
    }
}
echo 'PASS core smoke on PHP ', PHP_VERSION, PHP_EOL;
