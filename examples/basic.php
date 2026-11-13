<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use VietQr\BankCatalog;
use VietQr\Payment;
use VietQr\VietQr;

// Documentation example only. Replace the destination before accepting payments.
$payment = new Payment(
    bankBin: '970403',
    accountNo: '0011012345678',
    amount: 180000,
    description: 'thanh toan don hang',
    billNumber: 'NPS6869',
);

$payload = VietQr::generate($payment);
$parsed = VietQr::parse($payload);

echo $payload, PHP_EOL;
echo 'Account: ', $parsed->payment->accountNo, PHP_EOL;
echo 'Amount: ', $parsed->payment->amount, ' VND', PHP_EOL;
echo 'Bank snapshot: ', BankCatalog::find($payment->bankBin)?->shortName ?? 'Unknown', PHP_EOL;
