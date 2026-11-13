<?php

declare(strict_types=1);

namespace VietQr\Tests;

use PHPUnit\Framework\TestCase;
use VietQr\BankCatalog;
use VietQr\Payment;
use VietQr\VietQr;

final class BankCatalogTest extends TestCase
{
    public function testSnapshotIsCompleteAndAliasesResolveWithoutAmbiguity(): void
    {
        $banks = BankCatalog::all();
        self::assertCount(76, $banks);
        self::assertCount(76, array_unique(array_map(static fn ($bank) => $bank->bin, $banks)));
        foreach ($banks as $bank) {
            self::assertMatchesRegularExpression('/\A[0-9]{6}\z/', $bank->bin);
            self::assertNotSame('', $bank->name);
            foreach ([$bank->bin, $bank->code, $bank->shortName] as $alias) {
                self::assertSame($bank, BankCatalog::find(strtolower($alias)));
            }
        }
        self::assertSame('970436', BankCatalog::find(' vcb ')?->bin);
        self::assertNull(BankCatalog::find('not-a-bank'));
    }

    public function testUnknownBinIsNotBlockedBySnapshotAndCopiesCannotMutateCache(): void
    {
        $payment = new Payment('000001', '001');
        self::assertEquals($payment, VietQr::parse(VietQr::generate($payment))->payment);
        $copy = BankCatalog::all();
        array_pop($copy);
        self::assertCount(76, BankCatalog::all());
    }
}
