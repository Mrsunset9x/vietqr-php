<?php

declare(strict_types=1);

namespace VietQr;

use VietQr\Internal\Crc16;
use VietQr\Internal\Tlv;

final class VietQr
{
    public static function generate(Payment $payment): string
    {
        $beneficiary = Tlv::encode('00', $payment->bankBin) . Tlv::encode('01', $payment->accountNo);
        $merchant = Tlv::encode('00', 'A000000727')
            . Tlv::encode('01', $beneficiary)
            . Tlv::encode('02', $payment->service->value);
        $payload = '000201' . Tlv::encode('01', $payment->type->value)
            . Tlv::encode('38', $merchant) . '5303704';
        if ($payment->amount !== null) {
            $payload .= Tlv::encode('54', $payment->amount);
        }
        $payload .= '5802VN';

        $additional = '';
        foreach (['01' => $payment->billNumber, '05' => $payment->reference, '08' => $payment->description] as $tag => $value) {
            if ($value !== null) {
                $additional .= Tlv::encode($tag, $value);
            }
        }
        if ($additional !== '') {
            $payload .= Tlv::encode('62', $additional);
        }
        $payload .= '6304';

        return $payload . Crc16::hex($payload);
    }

    /** Parse the supported transfer profile; CRC validity alone never means payment received. */
    public static function parse(string $payload): ParsedQr
    {
        if (strlen($payload) > 1024 || !preg_match('/\A[\x20-\x7E]+\z/', $payload)) {
            throw new InvalidVietQr('Payload must be printable ASCII and at most 1024 bytes.');
        }
        if (!str_starts_with($payload, '000201') || !preg_match('/6304[0-9A-Fa-f]{4}\z/', $payload)) {
            throw new InvalidVietQr('Payload must start with format 01 and end with a four-digit CRC.');
        }
        if (Crc16::hex(substr($payload, 0, -4)) !== strtoupper(substr($payload, -4))) {
            throw new InvalidVietQr('CRC mismatch.');
        }

        $tags = Tlv::decode($payload);
        if (($tags['53'] ?? null) !== '704' || ($tags['58'] ?? null) !== 'VN') {
            throw new InvalidVietQr('Only currency 704 (VND) and country VN are supported.');
        }
        $type = QrType::tryFrom($tags['01'] ?? '');
        if ($type === null) {
            throw new InvalidVietQr('Initiation method must be 11 or 12.');
        }
        $merchant = Tlv::decode($tags['38'] ?? '');
        if (($merchant['00'] ?? null) !== 'A000000727') {
            throw new InvalidVietQr('Missing or invalid NAPAS identifier.');
        }
        $service = Service::tryFrom($merchant['02'] ?? '');
        if ($service === null) {
            throw new InvalidVietQr('Service must be QRIBFTTA or QRIBFTTC.');
        }
        $beneficiary = Tlv::decode($merchant['01'] ?? '');
        $additional = Tlv::decode($tags['62'] ?? '');

        // NAPAS permits a trailing decimal point for whole VND (e.g. "50000.").
        $amount = $tags['54'] ?? null;
        if ($amount !== null) {
            if (!preg_match('/\A[0-9]+\.?\z/', $amount) || strlen($amount) > 13) {
                throw new InvalidVietQr('Invalid whole-VND amount field.');
            }
            $amount = rtrim($amount, '.');
        }
        foreach (['01', '05', '08'] as $tag) {
            if (isset($additional[$tag]) && strlen($additional[$tag]) > 25) {
                throw new InvalidVietQr("Additional field {$tag} exceeds 25 characters.");
            }
        }

        $payment = new Payment(
            bankBin: $beneficiary['00'] ?? '',
            accountNo: $beneficiary['01'] ?? '',
            amount: $amount,
            description: $additional['08'] ?? null,
            service: $service,
            billNumber: $additional['01'] ?? null,
            reference: $additional['05'] ?? null,
            type: $type,
        );

        return new ParsedQr($payment, $payload, $tags);
    }
}
