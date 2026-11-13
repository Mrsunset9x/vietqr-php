# Supported protocol profile

Reference: [NAPAS VietQR format v1.0, September 2021, supplied by upstream](https://github.com/Ngochiendev/vietqr-core/blob/1b924505c630bebf6324609b036d8260f9e5cf99/docs/QR_Format_T%26C_v1.0_VN_092021.pdf).
This package implements a bounded ASCII NAPAS247 transfer profile, not every EMVCo
merchant-payment field and not a certified or exhaustive implementation of the document.

## Wire format

- Root `00=01` first; `01=11|12`; `38` carries NAPAS recipient information;
  `53=704`; optional `54` whole VND; `58=VN`; optional `62`; `63` last.
- `38.00=A000000727`; `38.01.00` bank BIN (6 ASCII digits);
  `38.01.01` consumer ID (1-19 ASCII letters/digits, digits only for card);
  `38.02=QRIBFTTA|QRIBFTTC`.
- `62.01` bill, `62.05` reference, `62.08` purpose, each up to 25 characters.
- CRC-16/IBM-3740, also called CCITT-FALSE: polynomial `0x1021`, initial `0xFFFF`,
  non-reflected, xor-out `0`. It covers the entire payload including `6304`, before
  the final four hexadecimal digits. Known check: `123456789 -> 29B1`.
- Every emitted character is ASCII, so character and byte lengths are identical.
  Arbitrary Unicode TLV is deliberately rejected rather than guessed.
- Initiation type is independent of whether an amount is present. Defaults are
  ergonomic; explicit type is preserved. A dynamic flag does not enforce one use.
- Generation accepts whole-number money only. Parsing additionally accepts the
  document's trailing-dot notation with total wire length no greater than 13.

## Validation boundaries

Duplicate fields, zero-length values, broken lengths and truncated TLV are rejected
at the root and within `38`, `38.01`, and `62`. No recursive interpretation of unknown
templates occurs. Unknown root/sub-fields are retained through the original payload;
they are not a claim of complete validation. This parser extracts the supported
transfer data; it must not be used as a generic payment authorization mechanism.

Only interpreted additional fields `01`, `05`, `08` receive their application-level
25-character check. Other fields still undergo TLV structural checks in `62` and
remain opaque. Country, currency, service, version and initiation are exact matches.
No silent fallback from unknown service to account transfer is allowed.

Vietnamese normalization uses a deterministic local mapping for precomposed letters
plus Vietnamese combining marks. ASCII/control validation follows normalization;
controls are rejected before normalization too. Unknown scripts/emoji fail. IDs
are never transliterated, trimmed, converted to numbers or truncated.

## Differences from the C# source

- The supplied NAPAS document section 5.2.3.2.2 specifies a 19-character consumer ID;
  upstream permits 25. This first version uses 19 and a narrower alphanumeric ID
  profile. Any longer virtual-account support needs a verified bank-specific contract.
- Purpose is capped at 25 per table 8; upstream README advertises 95 but its builder
  cuts at 25. This package throws instead of cutting a payment reference.
- Floats/bools, unsupported service/currency, duplicate tags and malformed additional
  data fail. CRC has no permissive switch.
- The builder and parser use the same ASCII profile. The upstream builder counts C#
  characters while its parser reads UTF-8 byte lengths; this inconsistency is not ported.
- No embedded branded assets, network discovery, deeplink gateway client or native
  .NET image dependency is bundled. QR encoding is delegated to an optional adapter.

## Golden fixtures and source errata

Fixtures use bank `970403`, account `0011012345678`, card `9704031101234567`, and
(for dynamic samples) amount `180000`, bill `NPS6869`, purpose `thanh toan don hang`.

| Fixture | Expected CRC | Evidence |
|---|---|---|
| Static account | `9E6F` | Corrected field length as described below; independently calculated |
| Static card | `4F52` | NAPAS section 6.1.2 |
| Dynamic account | `2E2E` | NAPAS section 6.1.3 |
| Dynamic card | `A203` | Full worked payload in section 6.1.4 |

The printed static-account sample on page 29 says `0112` before a **13-character**
account. Its table says `0113`. The printed `F4E5` checksums the malformed `0112`
payload, so valid CRC alone is insufficient. The corrected `0113` payload has CRC
`9E6F`. Tests explicitly reject the malformed, CRC-valid version.

The short line below the dynamic-card heading on page 32 repeats a static payload;
the full worked payload on page 33 contains `010212`, amount and additional data.
Fixtures follow the full worked example, which yields `A203`.

All four fixture CRCs were cross-checked with Python standard-library
`binascii.crc_hqx(payload_before_checksum, 0xFFFF)`. Hostile test payloads use a
separate bitwise CRC implementation so malformed-structure tests reach the parser
instead of stopping at a CRC mismatch.

Real banking-app scanning and bank-specific acceptance remain an integration check.
