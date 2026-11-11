# Integration register (G-02, 10 Nov 2026)

Every outside system the new Xeasy app (Laravel) sends data to or reads from. Keep this file current when an
integration is added or changed; the audit asked for one register with owner, data, contract and transport.

| System | What goes there | Direction | Transport | Where it is set | Owner | Contract / DPA |
|---|---|---|---|---|---|---|
| Amazon S3 bucket (ap-south-1) | Bill, odometer and payment-proof images | write / read | HTTPS, IAM key | `AWS_*` in .env, `config/filesystems.php` disk `s3` | IT | AWS customer agreement (IT to file) |
| Odometer reading service (Google Cloud Run) | Odometer photo URL; returns the reading | send / receive | HTTPS, TLS verified | `ODOMETER_OCR_URL`, `config/services.php` `odometer_ocr` | IT | **DPA drafted 10 Nov, to be signed** (OCR provider) |
| Bill reading (extraction) service | Bill images from the mobile app; returns the fields | send / receive | HTTPS | called by the mobile app; results come back through `tbl_extraction_queue` | IT / app developers | same DPA as above |
| HRIMS database | Employee, grade, department, eligibility, reporting chain | read only | MySQL on the same server | `DB_HRIMS_*` in .env | HR / IT | internal |
| Focus ERP | Claim and BTC journal exports (Excel); Focus ledger import for reconciliation | file export / file import | file download and upload by Finance | Focus export screens, Focus Ledger vs Xeasy screen | Finance | internal |
| Matrix (bank payment) | Payment instructions and status | send / receive | HTTPS | `MATRIX_BASE_URL`, `config/matrix.php` | Finance / IT | bank agreement |
| File backup server (vnragro.com) | Read old bill files by URL | read | HTTPS | hard-coded fallback in the mobile API file URL helpers | IT | internal (company domain) |
| Mail server | Claim, approval and reminder mail | send | SMTP with TLS | `MAIL_*` in .env; `MAIL_ALLOWED_DOMAINS` limits recipients (G-08) | IT | mail provider terms |
| Central notification service | App push notifications | send | HTTPS | `CENTRAL_NOTIFICATION_BASE_URL` | IT | internal |
| Old Xeasy API (VVNR) | VVNR approvals until that API is ported | send / receive | HTTPS | `API_VVNR_BASE_URL`, `XEASY_LEGACY_API_BASE_URL` | IT | internal |

Rules kept in code:

- TLS verification is on for every outgoing call (`App\Support\TlsVerify`); it cannot be turned off in production (`services.http_tls`).
- No test hosts in production settings: the old code's `test-rodro.xyz` and plain-HTTP Focus / file-server addresses are not used by the new app.
- Secrets live only in .env (never in git); see `GO_LIVE_ENV.md`.

Still open (not code): sign the OCR DPA; confirm the contracting entity for the rodro-branded hosts used by the old system (related-party check); IT to file the AWS and mail-provider terms here.
