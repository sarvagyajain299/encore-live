# PR: Phase 8, QA, hardening and release

**Branch:** `feature/phase-8-qa-release` → `main`

## Summary
Hardens the site (server-level security headers that also cover cached pages, XML-RPC and user enumeration blocked, file editing disabled, version leaks removed). Adds a Playwright purchase test and a single QA runner covering every suite. Ships the release docs: README, QA, SECURITY (with a nonce/capability audit), CV bullets and the TODO list.

## Changes
- **Plugin:** `src/Security/Hardening.php`.
- **Docker:**
  - `docker/wordpress/Dockerfile` + `security-headers.conf` (custom Apache image)
  - `DISALLOW_FILE_EDIT` in wp-config
  - `expose_php = Off`
- **Theme:** removed a duplicate "Tickets" landmark label on the show page (found by Playwright).
- **Tests:**
  - `scripts/smoke-security.ps1`
  - `tests/e2e/` (`package.json`, Playwright config, `specs/purchase.spec.js`, analytics check)
  - `scripts/qa.ps1`
- **Docs:** `README.md`, `docs/QA.md`, `docs/SECURITY.md`, `docs/CV_BULLETS.md`, `docs/TODOS.md`, plus updates to `ARCHITECTURE.md`, `ANALYTICS.md` and `CLAUDE.md`.

## Verification
`.\scripts\qa.ps1`: full table in `docs/QA.md` (all suites pass).

## Test steps
1. `.\scripts\encore.ps1 up` (rebuilds the WordPress image), then `.\scripts\encore.ps1 install`.
2. `cd tests\e2e; npm install; cd ..\..`
3. `.\scripts\qa.ps1`.
4. Run `curl.exe -I http://localhost:8080/shows/` twice. Both responses carry the security headers, including the cached one.

## Screenshots to take
- `qa.ps1` summary table
- `curl -I` output showing the headers
- Playwright HTML report (`npx playwright test --reporter=html`)

## Risks
- **No CSP or HSTS yet.** HSTS needs HTTPS (VPS). CSP needs nonces for GTM and inline scripts, and should start in Report-Only. Both are in `docs/SECURITY.md`.
- **Browser tests need Chrome installed locally** (Playwright `channel: 'chrome'`).

## TODOs for Sarvagya
The full list of 16 is in `docs/TODOS.md`.
