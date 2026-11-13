# Attribution

This independent PHP implementation is based on the protocol implementation and
bank directory in [Ngochiendev/vietqr-core](https://github.com/Ngochiendev/vietqr-core),
MIT, copyright 2026 Ngochiendev. Reviewed upstream commit:
`1b924505c630bebf6324609b036d8260f9e5cf99` (2026-11-04).

`resources/banks.json` is a reduced snapshot of the upstream directory, retrieved
2026-11-13. It contains identifiers and names only. The snapshot is informational;
it is not a live bank availability, beneficiary verification, or account lookup API.

No upstream logos, fonts, PDF documents, or .NET binaries are redistributed.
VietQR and NAPAS are trademarks of their respective owners. This project is not
an official NAPAS product and has not been certified by NAPAS.

Optional QR encoding is supplied by `bacon/bacon-qr-code` (BSD-2-Clause), installed
separately through Composer with its own license and dependencies.
