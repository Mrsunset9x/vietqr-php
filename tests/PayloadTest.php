<?php

declare(strict_types=1);

namespace VietQr\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VietQr\Internal\Crc16;
use VietQr\InvalidVietQr;
use VietQr\Payment;
use VietQr\QrType;
use VietQr\Service;
use VietQr\VietQr;

final class PayloadTest extends TestCase
{
    #[DataProvider('goldenVectors')]
    public function testGoldenPayloads(array $vector): void
    {
        $payment = new Payment(
            $vector['bankBin'], $vector['accountNo'], $vector['amount'], $vector['description'],
            Service::from($vector['service']), $vector['billNumber'], type: QrType::from($vector['type']),
        );
        self::assertSame($vector['payload'], VietQr::generate($payment));
        $parsed = VietQr::parse($vector['payload']);
        self::assertEquals($payment, $parsed->payment);
        self::assertSame($vector['payload'], $parsed->payload);
        self::assertSame('704', $parsed->tags[53]);
    }

    public static function goldenVectors(): iterable
    {
        $vectors = json_decode(file_get_contents(__DIR__ . '/fixtures/golden.json'), true, 32, JSON_THROW_ON_ERROR);
        foreach ($vectors as $name => $vector) {
            yield $name => [$vector];
        }
    }

    public function testCrcKnownCheckValueAndIndependentByteCalculation(): void
    {
        self::assertSame('FFFF', Crc16::hex(''));
        self::assertSame('29B1', Crc16::hex('123456789'));
        for ($i = 0; $i < 32; ++$i) {
            $input = hash('sha256', (string) $i, true);
            self::assertSame(substr(self::sign($input), -4), Crc16::hex($input . '6304'));
        }
    }

    public function testCombinationsRoundTripIncludingLargestSupportedPayload(): void
    {
        foreach (Service::cases() as $service) {
            foreach (QrType::cases() as $type) {
                foreach ([null, 1, '9999999999999'] as $amount) {
                    foreach ([null, 'Đơn hàng 0001', str_repeat('X', 25)] as $description) {
                        $payment = new Payment('000001', '0000123456789012345', $amount, $description, $service,
                            str_repeat('B', 25), str_repeat('R', 25), $type);
                        self::assertEquals($payment, VietQr::parse(VietQr::generate($payment))->payment);
                    }
                }
            }
        }
    }

    public function testPreservesUnrecognizedFieldsAndOriginalBytes(): void
    {
        $root = self::root();
        $root['80'] = self::tlv(['00' => 'example.test', '01' => 'opaque']);
        $root['59'] = 'SHOP';
        $root['54'] = '001000.';
        $root['62'] = self::tlv(['03' => 'STORE1', '05' => 'ORDER1']);
        $payload = self::sign(self::tlv($root));
        $parsed = VietQr::parse($payload);
        self::assertSame('1000', $parsed->payment->amount);
        self::assertSame('ORDER1', $parsed->payment->reference);
        self::assertSame($root['80'], $parsed->tags[80]);
        self::assertSame($root['62'], $parsed->tags[62]);
        self::assertSame($payload, $parsed->payload);
    }

    public function testAcceptsLowercaseCrcAndNonCanonicalFieldOrder(): void
    {
        $root = self::root();
        $payload = self::sign(self::tlv(['00' => '01', '58' => 'VN', '38' => $root[38], '53' => '704', '01' => '11']));
        $lowercase = substr($payload, 0, -4) . strtolower(substr($payload, -4));
        self::assertSame('0011012345678', VietQr::parse($lowercase)->payment->accountNo);
    }

    public function testEveryTruncationAndSingleBitCorruptionIsRejected(): void
    {
        $payload = VietQr::generate(new Payment('970403', '0011012345678', 180000, 'ORDER001'));
        for ($i = 0, $size = strlen($payload); $i < $size; ++$i) {
            $this->assertRejected(substr($payload, 0, $i));
            $changed = $payload;
            $changed[$i] = chr(ord($changed[$i]) ^ 1);
            $this->assertRejected($changed);
        }
    }

    #[DataProvider('malformedPayloads')]
    public function testMalformedPayloadsWithRecomputedCrcStillFail(string $payload): void
    {
        $this->expectException(InvalidVietQr::class);
        VietQr::parse($payload);
    }

    public static function malformedPayloads(): iterable
    {
        $root = self::root();
        foreach (['00', '01', '38', '53', '58'] as $tag) {
            $changed = $root;
            unset($changed[$tag]);
            yield "missing-root-{$tag}" => [self::sign(self::tlv($changed))];
        }
        foreach (['00' => '02', '01' => '13', '53' => '840', '58' => 'US', '54' => '0'] as $tag => $value) {
            yield "wrong-root-{$tag}" => [self::sign(self::tlv(array_replace($root, [$tag => $value])))];
        }
        foreach (['-1', '1e2', '1.5', '1.0', '1..', '1,000', ' 100', '99999999999999', '9999999999999.'] as $i => $amount) {
            yield "bad-wire-amount-{$i}" => [self::sign(self::tlv($root + ['54' => $amount]))];
        }
        foreach (['00' => 'A000000999', '02' => 'UNKNOWN1', '01' => '0006970403'] as $tag => $value) {
            $merchant = self::merchant();
            $merchant[$tag] = $value;
            yield "wrong-merchant-{$tag}" => [self::sign(self::tlv(array_replace($root, ['38' => self::tlv($merchant)])))];
        }
        foreach (['00', '01', '02'] as $tag) {
            $merchant = self::merchant();
            unset($merchant[$tag]);
            yield "missing-merchant-{$tag}" => [self::sign(self::tlv(array_replace($root, ['38' => self::tlv($merchant)])))];
        }
        foreach (['00' => 'ABCDEF', '01' => str_repeat('1', 20)] as $tag => $value) {
            $merchant = self::merchant();
            $merchant['01'] = self::tlv(array_replace(['00' => '970403', '01' => '001'], [$tag => $value]));
            yield "bad-beneficiary-{$tag}" => [self::sign(self::tlv(array_replace($root, ['38' => self::tlv($merchant)])))];
        }
        foreach (['01', '05', '08'] as $tag) {
            yield "overlong-additional-{$tag}" => [self::sign(self::tlv($root + ['62' => self::tlv([$tag => str_repeat('X', 26)])]))];
        }
        foreach (['01', '38', '53', '63'] as $tag) {
            yield "duplicate-root-{$tag}" => [self::sign(self::tlv($root) . self::tlv([$tag => $root[$tag] ?? '1234']))];
        }
        yield 'duplicate-unknown' => [self::sign(self::tlv($root) . '9901a9901b')];
        $merchant = self::tlv(self::merchant()) . '0208QRIBFTTC';
        yield 'duplicate-merchant' => [self::sign(self::tlv(array_replace($root, ['38' => $merchant])))];
        $merchant = self::merchant();
        $merchant['01'] .= '0103BAD';
        yield 'duplicate-beneficiary' => [self::sign(self::tlv(array_replace($root, ['38' => self::tlv($merchant)])))];
        yield 'duplicate-additional' => [self::sign(self::tlv($root + ['62' => '0801a0801b']))];
        foreach (['', '08', '0805X', '0800', 'XX01a', '08a1x', '0801Xz'] as $i => $value) {
            yield "bad-nested-{$i}" => [self::sign(self::tlv($root + ['62' => $value]))];
        }
        yield 'root-length-zero' => [self::sign(self::tlv($root) . '9900')];
        yield 'root-truncated' => [self::sign(self::tlv($root) . '9999x')];
        yield 'root-invalid-header' => [self::sign(self::tlv($root) . 'xx01a')];
        yield 'bad-final-crc-length' => [self::tlv($root) . '6303ABC'];
        yield 'bad-final-crc-hex' => [self::tlv($root) . '6304GGGG'];
        yield 'unicode' => [self::sign(self::tlv($root + ['59' => 'Đặng']))];
        yield 'control' => [self::sign(self::tlv($root + ['59' => "A\nB"]))];
        yield 'oversize' => [str_repeat('0', 1025)];
        yield 'valid-crc-but-invalid-doc-static' => ['00020101021138570010A00000072701270006970403011200110123456780208QRIBFTTA53037045802VN6304F4E5'];
    }

    private function assertRejected(string $payload): void
    {
        try {
            VietQr::parse($payload);
            self::fail('Malformed payload unexpectedly accepted.');
        } catch (InvalidVietQr) {
            self::assertTrue(true);
        }
    }

    private static function root(): array
    {
        return ['00' => '01', '01' => '11', '38' => self::tlv(self::merchant()), '53' => '704', '58' => 'VN'];
    }

    private static function merchant(): array
    {
        return ['00' => 'A000000727', '01' => self::tlv(['00' => '970403', '01' => '0011012345678']), '02' => 'QRIBFTTA'];
    }

    private static function tlv(array $tags): string
    {
        $payload = '';
        foreach ($tags as $tag => $value) {
            $payload .= sprintf('%02d%02d%s', $tag, strlen($value), $value);
        }
        return $payload;
    }

    // Deliberately independent bitwise implementation, only for constructing hostile fixtures.
    private static function sign(string $body): string
    {
        $body .= '6304';
        $crc = 0xFFFF;
        foreach (unpack('C*', $body) as $byte) {
            $crc ^= $byte << 8;
            for ($bit = 0; $bit < 8; ++$bit) {
                $crc = ($crc & 0x8000) ? ($crc << 1) ^ 0x1021 : $crc << 1;
                $crc &= 0xFFFF;
            }
        }
        return $body . sprintf('%04X', $crc);
    }
}
