# Implementation plan

## Scope and decisions

- Standalone Composer library, PHP 8.2+, PSR-4 namespace `VietQr`.
- Zero mandatory Composer dependencies; no framework, HTTP client, network,
  account lookup, filesystem writes, hidden configuration, or service container.
- NAPAS247 account/card payloads, static/dynamic initiation, whole VND amounts,
  bill/reference/description, strict parsing with CRC verification.
- Immutable `Payment` value object; `VietQr` facade; internal TLV/CRC/text helpers.
  One renderer interface and one optional Bacon adapter. No repository/factory
  hierarchy or generic EMV framework.
- ASCII transfer profile: Vietnamese text is transliterated deterministically;
  unsupported characters and oversized fields fail without truncation. Consumer
  ID follows the 19-character bound in the supplied NAPAS v1.0 document.
- Bank directory is local, lazy, and optional for payload generation. Unknown BINs
  of the correct shape remain usable; the snapshot must not become an allowlist.
- SVG/PNG rendering delegates QR matrix generation to BaconQrCode. Reimplementing
  QR error correction would add code and risk. GD is needed only for PNG.
- PHPUnit and PHPStan are development-only. CI checks PHP 8.2-8.5, Linux/Windows,
  normal/minimal dependencies, and the no-extension core path.

## Delivery sequence

1. [x] Protocol review, provenance, canonical fixtures and malformed examples.
2. [x] Core, strict validation/parser, bank catalog and optional renderer.
3. [x] Boundary/negative/round-trip tests, independent image decoding, PHP matrix.
4. [x] README, runnable examples, benchmarks, distribution/install checks, CI.

Implemented and locally verified on 2026-11-13. See `VERIFICATION.md` for actual
runtime coverage, commands, dependency versions and remaining integration limits.

## Acceptance

- Golden payload bytes and independent CRCs match; official sample transcription
  errors are documented rather than reproduced in generated output.
- Reject tampering, duplicate tags, missing mandatory fields, malformed nesting,
  unsupported service/currency/country, controls, non-ASCII payloads and overflow.
- Preserve leading zeroes in beneficiary identifiers and exact integer amounts;
  explicitly reject float/bool input even from a non-strict PHP caller.
- Read back SVG/PNG with an independent decoder and compare with the source payload.
- `composer validate --strict`, lint, PHPStan maximum level and PHPUnit pass.
- A fresh production install has no runtime dependency; optional renderer install
  works independently. Documentation must distinguish executed checks from CI-only
  checks and actual banking-app acceptance (not available in this task).

## Deferred features

Custom logos/fonts and print-card templates, image-to-payload decoding, bank-app
deeplinks, Laravel providers, virtual-account IDs beyond 19 characters, payment
notifications/reconciliation, Packagist publication. These are separate extensions,
not requirements of the lightweight first version agreed for this task.
