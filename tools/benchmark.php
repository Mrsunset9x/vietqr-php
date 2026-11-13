<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use VietQr\Payment;
use VietQr\VietQr;

$payment = new Payment('970403', '0011012345678', 180000, 'thanh toan don hang', billNumber: 'NPS6869');
$payload = VietQr::generate($payment);
$operations = [
    'generate_prevalidated' => static fn () => VietQr::generate($payment),
    'construct_and_generate' => static fn () => VietQr::generate(new Payment('970403', '0011012345678', 180000, 'thanh toan don hang')),
    'parse_and_validate' => static fn () => VietQr::parse($payload),
];
$report = ['php' => PHP_VERSION, 'os' => PHP_OS_FAMILY, 'opcache_cli' => (bool) ini_get('opcache.enable_cli'), 'iterations_per_batch' => 5000, 'batches' => 7];
foreach ($operations as $name => $operation) {
    for ($i = 0; $i < 1000; ++$i) {
        $operation();
    }
    $samples = [];
    for ($batch = 0; $batch < 7; ++$batch) {
        $start = hrtime(true);
        for ($i = 0; $i < 5000; ++$i) {
            $operation();
        }
        $samples[] = (hrtime(true) - $start) / 5000 / 1000;
    }
    sort($samples);
    $report[$name] = ['median_microseconds' => round($samples[3], 2), 'slowest_batch_microseconds' => round(max($samples), 2)];
}
$report['peak_allocated_bytes'] = memory_get_peak_usage(true);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
