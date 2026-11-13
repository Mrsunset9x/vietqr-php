<?php

declare(strict_types=1);

namespace VietQr;

use VietQr\Internal\Text;

final readonly class Payment
{
    public ?string $amount;
    public ?string $description;
    public ?string $billNumber;
    public ?string $reference;
    public QrType $type;

    /**
     * Amount deliberately uses mixed to reject floats/bools even in weak PHP callers.
     *
     * @param mixed $amount Accepts only int|string|null; positive whole VND, at most 13 digits.
     */
    public function __construct(
        public string $bankBin,
        public string $accountNo,
        mixed $amount = null,
        ?string $description = null,
        public Service $service = Service::Account,
        ?string $billNumber = null,
        ?string $reference = null,
        ?QrType $type = null,
    ) {
        if (!preg_match('/\A[0-9]{6}\z/', $bankBin)) {
            throw new InvalidVietQr('bankBin must be exactly 6 ASCII digits.');
        }
        $pattern = $service === Service::Card ? '/\A[0-9]{1,19}\z/' : '/\A[A-Za-z0-9]{1,19}\z/';
        if (!preg_match($pattern, $accountNo)) {
            throw new InvalidVietQr('accountNo must be 1-19 ASCII letters/digits (digits only for cards).');
        }

        if ($amount !== null && (!is_int($amount) && !is_string($amount))) {
            throw new InvalidVietQr('amount must be an integer or digit string, never a float or boolean.');
        }
        $digits = $amount === null ? null : (string) $amount;
        if ($digits !== null && (!preg_match('/\A[0-9]{1,13}\z/', $digits) || trim($digits, '0') === '')) {
            throw new InvalidVietQr('amount must be positive whole VND with at most 13 digits.');
        }

        $this->amount = $digits === null ? null : ltrim($digits, '0');
        $this->description = Text::optional($description, 'description');
        $this->billNumber = Text::optional($billNumber, 'billNumber');
        $this->reference = Text::optional($reference, 'reference');
        $this->type = $type ?? ($amount === null ? QrType::Static : QrType::Dynamic);
    }
}
