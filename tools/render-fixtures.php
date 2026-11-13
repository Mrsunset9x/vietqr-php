<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use VietQr\Payment;
use VietQr\Rendering\BaconQrRenderer;
use VietQr\Service;
use VietQr\VietQr;

$directory = dirname(__DIR__) . '/build/qr';
if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
    throw new RuntimeException('Cannot create fixture output directory.');
}
$payments = [
    'static-account' => new Payment('970403', '0011012345678'),
    'static-card' => new Payment('970403', '9704031101234567', service: Service::Card),
    'dynamic-account' => new Payment('970403', '0011012345678', 180000, 'thanh toán đơn hàng', billNumber: 'NPS6869'),
    'dynamic-card' => new Payment('970403', '9704031101234567', 180000, 'ORDER001', Service::Card),
    'max-fields' => new Payment('970403', str_repeat('1', 19), '9999999999999', str_repeat('a', 25),
        billNumber: str_repeat('B', 25), reference: str_repeat('R', 25)),
];
$renderer = new BaconQrRenderer();
$manifest = [];
foreach ($payments as $name => $payment) {
    foreach ([256, 320, 511, 2048] as $size) {
        foreach (['svg', 'png'] as $format) {
            $filename = "{$name}-{$size}.{$format}";
            file_put_contents($directory . '/' . $filename, $renderer->$format($payment, $size));
            $manifest[$filename] = VietQr::generate($payment);
        }
    }
}
file_put_contents($directory . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);
printf("Wrote %d image fixtures to build/qr.\n", count($manifest));
