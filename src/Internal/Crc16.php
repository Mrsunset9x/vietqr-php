<?php

declare(strict_types=1);

namespace VietQr\Internal;

/** @internal CRC-16/IBM-3740 (CCITT-FALSE): poly 0x1021, init 0xFFFF, no reflection/xor. */
final class Crc16
{
    /** @var list<int>|null */
    private static ?array $table = null;

    public static function hex(string $bytes): string
    {
        $table = self::$table ??= self::buildTable();
        $crc = 0xFFFF;
        for ($i = 0, $length = strlen($bytes); $i < $length; ++$i) {
            $crc = (($crc << 8) ^ $table[(($crc >> 8) ^ ord($bytes[$i])) & 0xFF]) & 0xFFFF;
        }

        return sprintf('%04X', $crc);
    }

    /** @return list<int> */
    private static function buildTable(): array
    {
        $table = [];
        for ($i = 0; $i < 256; ++$i) {
            $crc = $i << 8;
            for ($bit = 0; $bit < 8; ++$bit) {
                $crc = (($crc << 1) ^ (($crc & 0x8000) ? 0x1021 : 0)) & 0xFFFF;
            }
            $table[] = $crc;
        }

        return $table;
    }
}
