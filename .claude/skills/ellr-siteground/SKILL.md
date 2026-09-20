---
name: ellr-siteground
description: Inspect or debug the live ellr-qbo-timesheet SiteGround Shared deployment over SSH. Use when Luc asks about production, api.timesheet.ellr.ca, admin.timesheet.ellr.ca, timesheet.ellr.ca, or a live deploy/rollback.
---

# SiteGround production (Timesheet)

Canonical: `.cursor/rules/siteground-ssh.mdc`, `docs/siteground-shared-hosting.md`. Live host, user, and filesystem paths: gitignored `.cursor/rules/siteground-ssh.local.mdc`.

## Rules

- Use SSH. Do not assume FTP.
- Connect with the alias only: `ssh ellr-timesheet-sg`
- If the local rule is missing, stop and ask Luc. Do not invent hostnames or usernames from git history.
- Do not paste `.env`, tokens, or private keys into chat.
- Do not deploy from this skill. Deploy is manual GitHub Actions (`.github/workflows/deploy-siteground.yml`, `workflow_dispatch`, `production` environment) from `main`.

## PHP on Shared

Default `php` / `composer` may be 8.2. Always prefer:

```bash
PHP=/usr/local/php83/bin/php-cli
"$PHP" -d memory_limit=512M /usr/local/bin/composer.phar …
"$PHP" artisan …
```

Read paths from `.cursor/rules/siteground-ssh.local.mdc` after connecting. Public hostnames are fine to mention: `api.timesheet.ellr.ca`, `admin.timesheet.ellr.ca`, `timesheet.ellr.ca`.
