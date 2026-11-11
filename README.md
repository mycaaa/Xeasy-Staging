# Xeasy — VNR expense management (Laravel 12)

Staging build of the new Xeasy web app and mobile API, with the application-audit fixes up to 11 Nov 2026.

- Deploy (test host xeasy.vnrseeds.co.in, then production): **[deploy/README.md](deploy/README.md)**, one script `deploy/deploy.sh`
- Settings for go-live and the switches to turn on later: [deploy/GO_LIVE_ENV.md](deploy/GO_LIVE_ENV.md)
- Settings template: [.env.example](.env.example) (copy to `.env`; never commit `.env`)
- Outside systems the app talks to: [docs/INTEGRATIONS.md](docs/INTEGRATIONS.md)
- API response format: [docs/API_RESPONSE.md](docs/API_RESPONSE.md)
- Backups: [scripts/backup/xeasy_backup.sh](scripts/backup/xeasy_backup.sh)

Quick start on a prepared server:

```bash
git clone <repository url> /var/www/xeasy && cd /var/www/xeasy
deploy/deploy.sh --first-run    # makes .env; fill it
deploy/deploy.sh --first-run    # installs
```
