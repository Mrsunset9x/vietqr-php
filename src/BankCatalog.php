<?php

declare(strict_types=1);

namespace VietQr;

/** Local snapshot, not a validation allowlist or a beneficiary lookup service. */
final class BankCatalog
{
    /** @var list<Bank>|null */
    private static ?array $banks = null;

    /** @var array<string, Bank> */
    private static array $index = [];

    /** @return list<Bank> */
    public static function all(): array
    {
        if (self::$banks !== null) {
            return self::$banks;
        }

        $path = __DIR__ . '/../resources/banks.json';
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('Cannot read the bundled bank catalog.');
        }
        /** @var list<array{bin: string, code: string, name: string, shortName: string}> $rows */
        $rows = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        $banks = [];
        $index = [];
        foreach ($rows as $row) {
            $bank = new Bank($row['bin'], $row['code'], $row['name'], $row['shortName']);
            $banks[] = $bank;
            foreach ([$bank->bin, $bank->code, $bank->shortName] as $alias) {
                $key = strtolower($alias);
                if (isset($index[$key]) && $index[$key]->bin !== $bank->bin) {
                    throw new \RuntimeException('Ambiguous alias in bundled bank catalog.');
                }
                $index[$key] = $bank;
            }
        }
        self::$index = $index;

        return self::$banks = $banks;
    }

    public static function find(string $binOrCode): ?Bank
    {
        self::all();

        return self::$index[strtolower(trim($binOrCode))] ?? null;
    }
}
