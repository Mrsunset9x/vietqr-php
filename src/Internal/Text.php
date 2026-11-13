<?php

declare(strict_types=1);

namespace VietQr\Internal;

use VietQr\InvalidVietQr;

/** @internal */
final class Text
{
    /** @var array<string, string>|null */
    private static ?array $vietnamese = null;

    public static function optional(?string $value, string $field, int $limit = 25): ?string
    {
        if ($value === null) {
            return null;
        }

        if (strlen($value) > 1024) {
            throw new InvalidVietQr("{$field} input must be at most 1024 bytes.");
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidVietQr("{$field} cannot contain control characters.");
        }

        if (preg_match('/[^\x20-\x7E]/', $value)) {
            $value = strtr($value, self::vietnameseMap());
            // Handle decomposed Vietnamese without requiring intl or mbstring.
            $value = preg_replace('/[\x{0300}\x{0301}\x{0303}\x{0309}\x{0323}\x{0302}\x{0306}\x{031B}]/u', '', $value);
            if ($value === null || preg_match('/[^\x20-\x7E]/', $value)) {
                throw new InvalidVietQr("{$field} must contain ASCII or Vietnamese text.");
            }
        }

        $value = trim($value, ' ');
        if (strlen($value) > $limit) {
            throw new InvalidVietQr("{$field} must be at most {$limit} characters after normalization.");
        }

        return $value === '' ? null : $value;
    }

    /** @return array<string, string> */
    private static function vietnameseMap(): array
    {
        if (self::$vietnamese !== null) {
            return self::$vietnamese;
        }

        $groups = [
            'a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'A' => 'ÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴ',
            'e' => 'èéẹẻẽêềếệểễ', 'E' => 'ÈÉẸẺẼÊỀẾỆỂỄ',
            'i' => 'ìíịỉĩ', 'I' => 'ÌÍỊỈĨ',
            'o' => 'òóọỏõôồốộổỗơờớợởỡ', 'O' => 'ÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠ',
            'u' => 'ùúụủũưừứựửữ', 'U' => 'ÙÚỤỦŨƯỪỨỰỬỮ',
            'y' => 'ỳýỵỷỹ', 'Y' => 'ỲÝỴỶỸ', 'd' => 'đ', 'D' => 'Đ',
        ];
        $map = [];
        foreach ($groups as $ascii => $characters) {
            foreach (preg_split('//u', $characters, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
                $map[$character] = $ascii;
            }
        }

        return self::$vietnamese = $map;
    }
}
