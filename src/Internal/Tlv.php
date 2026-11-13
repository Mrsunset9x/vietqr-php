<?php

declare(strict_types=1);

namespace VietQr\Internal;

use VietQr\InvalidVietQr;

/** @internal Tag-length-value codec for the printable ASCII transfer profile. */
final class Tlv
{
    public static function encode(string $tag, string $value): string
    {
        $length = strlen($value);
        if (!preg_match('/\A[0-9]{2}\z/', $tag) || $length < 1 || $length > 99) {
            throw new InvalidVietQr('TLV requires a two-digit tag and a value of 1-99 characters.');
        }

        return $tag . str_pad((string) $length, 2, '0', STR_PAD_LEFT) . $value;
    }

    /** @return array<int|string, string> PHP converts numeric-string keys such as "38" to integers. */
    public static function decode(string $payload): array
    {
        $tags = [];
        $size = strlen($payload);
        for ($offset = 0; $offset < $size;) {
            $header = substr($payload, $offset, 4);
            if (!preg_match('/\A[0-9]{4}\z/', $header)) {
                throw new InvalidVietQr('Malformed TLV header.');
            }
            $tag = substr($header, 0, 2);
            $length = (int) substr($header, 2, 2);
            if ($length === 0 || $offset + 4 + $length > $size) {
                throw new InvalidVietQr('Invalid or truncated TLV value.');
            }
            if (array_key_exists($tag, $tags)) {
                throw new InvalidVietQr("Duplicate TLV tag {$tag}.");
            }
            $tags[$tag] = substr($payload, $offset + 4, $length);
            $offset += 4 + $length;
        }

        return $tags;
    }
}
