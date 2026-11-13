# Verification record

Date: **2026-11-13**. This is the local verification snapshot taken before the first
GitHub publication. For checks on subsequent commits, see
[GitHub Actions](https://github.com/Mrsunset9x/vietqr-php/actions).

## Executed checks

| Check | Result |
|---|---|
| Composer metadata | `composer validate --strict`: PASS |
| Syntax | `composer lint`: PASS |
| Static analysis | PHPStan 2.3.5, level `max`, PHP target 8.2: PASS, no baseline/ignores |
| Windows PHP 8.2.35 | PHPUnit: **143 tests, 869 assertions**, PASS |
| Windows PHP 8.3.36 | Same suite: PASS |
| Windows PHP 8.4.27 | Same suite: PASS |
| Windows PHP 8.5.12 | Same suite: PASS |
| Linux PHP 8.5.12 | Same suite: PASS in an ephemeral container, network disabled, source mounted read-only |
| Core without optional PHP extensions | `php -n tools/core-smoke.php`: PASS on all four Windows versions and Linux 8.5 |
| Independent image decoding | **40 images, 80 decodes** including rotation: PASS |
| Production core install | Fresh consumer from clean Composer ZIP: package only, no external runtime dependency, `php -n` smoke PASS |
| Production renderer install | Fresh consumer from clean ZIP plus Bacon and enum: SVG/PNG PASS |
| Minimum allowed dependencies | PHP 8.2.35, Bacon 3.0.0, enum 1.0.3, PHPUnit 10.5.62: **143 tests, 869 assertions**, PASS |
| Minimum static analyzer | PHPStan 2.1.0, level `max`: PASS |
| Dependency advisories | Latest and minimum tested dependency sets: `composer audit` PASS, no advisories at verification time |
| Distribution contents | Checked ZIP excludes vendor/build/git/cache; includes source, bank data, README, protocol notes, license and attribution |

Development installation: PHPUnit 10.5.66, PHPStan 2.3.5, BaconQrCode 3.1.1,
dasprid/enum 1.0.7. Dev dependency chains are not production requirements.
The PHPUnit minimum is 10.5.62, excluding versions affected by advisory
`GHSA-vvj3-c3rp-c85p` (PHPT coverage deserialization).

Image verification uses Python 3.13, ZXing-C++ 3.1.1, Pillow 12.3.0 and PyMuPDF
1.28.0 in a separate virtual environment. It reads PNG directly and rasterizes SVG
before decoding. Cases: static account/card, dynamic account/card, maximum field
lengths; sizes 256, 320, 511 and 2.048 px; original and 90-degree rotation.
The PNG maximum-field preview was also visually inspected.

## What the tests protect

- Four canonical payload fixtures, checked against an independent CRC implementation.
- Reject the printed CRC-valid but structurally malformed static-account sample.
- Exact beneficiary IDs including leading zeroes; exact large integer money.
- Reject floats/bools even in weakly typed callers; reject overflow, zero, negatives,
  decimal/exponential notation, separators and invalid digit alphabets.
- Composed/decomposed Vietnamese normalization; unsupported Unicode, control
  characters and oversized inputs fail without truncation.
- Required fields, currency/country/service, nested TLV bounds and duplicates.
- Reject every truncated prefix and every tested single-bit corruption of a sample.
- Explicit static/dynamic choices, extreme values and supported-field round trips.
- Unknown fields and original payload preserved; catalog aliases and immutability.
- SVG/PNG dimensions, no external image assets, bounded sizes, safe renderer reuse.
- Missing optional dependency/extension returns actionable errors.

## Local benchmark

PHP 8.4.27 CLI on Windows, OPcache CLI disabled. Seven batches of 5.000 operations
after warm-up; times are median batch averages, not HTTP latency percentiles.

| Operation | Median per operation |
|---|---:|
| Generate from validated Payment | 9.54 microseconds |
| Construct Payment and generate | 11.30 microseconds |
| Parse and validate | 16.28 microseconds |

Peak PHP allocated memory reported by the benchmark: **2.097.152 bytes**. The measured
path does not load the bank directory or render images. Host/application/FPM overhead,
parallel load and end-to-end payment flows are not represented. Re-run
`composer benchmark` in the deployment environment before relying on throughput.

## Reproduce

```sh
composer install
composer validate --strict
composer check
composer audit
php -n tools/core-smoke.php
php tools/verify-install.php
php tools/verify-install.php --with-renderer
php tools/render-fixtures.php
python tools/decode-fixtures.py
composer benchmark
```

For minimum dependencies, use a separate checkout and run
`composer update --prefer-lowest --prefer-stable`, then `composer check` and
`composer audit`. The image checker needs the pinned Python tools listed in README.

## Not verified or outside this delivery

- GitHub-hosted CI had not run at the time of this local snapshot. Its results are
  recorded separately in GitHub Actions, linked above.
- Linux PHP 8.2/8.3/8.4 and macOS were not run locally. Linux 8.2-8.5 is in the CI matrix.
- No banking app, physical print/camera, bank-specific virtual account or live
  transfer acceptance test. No payment was initiated.
- No NAPAS certification, beneficiary-name lookup or transaction confirmation.
- No promise of zero bugs; the tested ASCII transfer profile and remaining limits
  are explicit in `PROTOCOL.md`.
