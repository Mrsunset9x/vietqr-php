<?php

declare(strict_types=1);

namespace VietQr\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VietQr\InvalidVietQr;
use VietQr\Payment;
use VietQr\QrType;
use VietQr\Service;

final class PaymentTest extends TestCase
{
    public function testNormalizesVietnameseWithoutChangingBeneficiaryOrRoundingMoney(): void
    {
        $payment = new Payment('970403', '0000012345678', '0000180000', '  Đóng tiền đơn hàng  ', billNumber: 'HĐ001');
        self::assertSame('0000012345678', $payment->accountNo);
        self::assertSame('180000', $payment->amount);
        self::assertSame('Dong tien don hang', $payment->description);
        self::assertSame('HD001', $payment->billNumber);
        self::assertSame(QrType::Dynamic, $payment->type);
    }

    public function testComposedAndDecomposedVietnameseHaveIdenticalOutput(): void
    {
        $composed = new Payment('970403', '001', description: 'Đặng Thị Mỹ');
        $decomposed = new Payment('970403', '001', description: "Đa\u{0306}\u{0323}ng Thi\u{0323} My\u{0303}");
        self::assertSame('Dang Thi My', $composed->description);
        self::assertEquals($composed, $decomposed);
    }

    public function testBoundariesAndTypesAreExplicit(): void
    {
        $payment = new Payment('000001', str_repeat('0', 19), '9999999999999', str_repeat('a', 25),
            billNumber: str_repeat('B', 25), reference: str_repeat('R', 25), type: QrType::Static);
        self::assertSame('9999999999999', $payment->amount);
        self::assertSame(QrType::Static, $payment->type);
        self::assertNull((new Payment('970403', 'A01', description: '   '))->description);
        self::assertSame(QrType::Static, (new Payment('970403', 'A01'))->type);
        self::assertSame(QrType::Dynamic, (new Payment('970403', 'A01', type: QrType::Dynamic))->type);
    }

    public function testPaymentIsImmutable(): void
    {
        $payment = new Payment('970403', '001');
        $this->expectException(\Error::class);
        $payment->accountNo = '002';
    }

    #[DataProvider('invalidFields')]
    public function testInvalidInputFailsInsteadOfBeingSilentlyModified(array $overrides): void
    {
        $this->expectException(InvalidVietQr::class);
        new Payment(...array_replace(['bankBin' => '970403', 'accountNo' => '001'], $overrides));
    }

    public static function invalidFields(): iterable
    {
        foreach (['', '12345', '1234567', '１２３４５６', '97040A', ' 970403', "970403\n"] as $i => $value) {
            yield "bank-{$i}" => [['bankBin' => $value]];
        }
        foreach (['', 'a b', str_repeat('1', 20), '001-02', '１２３', "123\0", "123\n", ' 001'] as $i => $value) {
            yield "account-{$i}" => [['accountNo' => $value]];
        }
        yield 'card-alphabetic' => [['accountNo' => 'A001', 'service' => Service::Card]];
        foreach ([0, '0', '000', -1, '-1', 1.5, 1.0, true, false, [], INF, NAN, '', '1e3', '+1',
            '1,000', '1.00', '100.', ' 100', "100\n", '99999999999999', '１２３'] as $i => $value) {
            yield "amount-{$i}" => [['amount' => $value]];
        }
        foreach (['description', 'billNumber', 'reference'] as $field) {
            foreach ([str_repeat('a', 26), str_repeat(' ', 1025), 'DH😀', "DH\n01", "DH\t01", "DH\x7f01", "\xff", '订单'] as $i => $value) {
                yield "{$field}-{$i}" => [[$field => $value]];
            }
        }
    }

    #[DataProvider('unsafeWeakAmounts')]
    public function testWeakCallersCannotCoerceFloatsOrBooleansIntoMoney(mixed $amount): void
    {
        $create = require __DIR__ . '/fixtures/weak-caller.php';
        $this->expectException(InvalidVietQr::class);
        $create($amount);
    }

    public static function unsafeWeakAmounts(): iterable
    {
        yield 'fractional' => [100.75];
        yield 'integer-float' => [100.0];
        yield 'true' => [true];
        yield 'false' => [false];
    }
}
