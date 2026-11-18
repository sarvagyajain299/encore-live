# Your TODO(sarvagya) tasks

16 small tasks, two per phase, each marked in the code with `TODO(sarvagya):` and a hint. Do them in order: each one exercises a concept from that phase's interview notes. Rough total: 5–7 hours.

After each task, run `.\scripts\encore.ps1 phpcs` and `.\scripts\encore.ps1 test`. Commit on a branch (`chore/todos-phase-N`) with a Conventional Commit message.

| # | Phase | File | Task | Concept practised | Time |
|---|---|---|---|---|---|
| 1 | 0 | `scripts/install.sh` | Fail fast on empty required `.env` variables | shell scripting, fail-fast | 15 min |
| 2 | 0 | `scripts/encore.sh` (+ `.ps1`, `Makefile`) | `logs [service]` command | Docker Compose | 15 min |
| 3 | 1 | `tests/Unit/Support/SanitizerTest.php` | `test_longitude_range()` | PHPUnit, edge cases | 10 min |
| 4 | 1 | `src/Admin/ShowListColumns.php` | "Doors" admin column | `manage_*_columns` hooks, escaping | 20 min |
| 5 | 2 | `assets/js/main.js` | Close the mobile menu above 900 px | `matchMedia`, ARIA state | 20 min |
| 6 | 2 | `footer.php` | "Back to top" link to `#content` | landmarks, focus management | 10 min |
| 7 | 3 | `src/Tickets/TicketPanel.php` | `encore_low_stock_threshold` filter | writing your own filters | 15 min |
| 8 | 3 | `src/Support/Settings.php` | `add_settings_error()` on invalid max | Settings API | 20 min |
| 9 | 4 | `src/Rest/ShowsController.php` | `?artist=<id>` REST param + smoke check | REST args schema | 25 min |
| 10 | 4 | `src/Integrations/Elementor/UpcomingShowsWidget.php` | "Show past shows" switcher + test | Elementor controls, DRY filters | 30 min |
| 11 | 5 | `src/Waitlist/EmailMask.php` | `mask_phone()` + tests + editor column | privacy by design, `WP_List_Table` | 40 min |
| 12 | 5 | `src/Waitlist/WaitlistModule.php` | `encore_waitlist_rate_limit` filter, documented | filters, integration contract | 15 min |
| 13 | 6 | `docs/SEO.md` | Run Google's Rich Results Test (Code tab) and record the result | structured-data validation | 15 min |
| 14 | 6 | `src/Seo/EventSchemaBuilder.php` | `doorTime` property + unit test | schema.org, dates with offsets | 25 min |
| 15 | 7 | `inc/performance.php` | Measure dequeuing `global-styles`; keep only if it helps | measure-before-you-optimise | 30 min |
| 16 | 7 | `inc/setup.php` | Skip unused `1536x1536` / `2048x2048` sizes | image pipeline | 20 min |

Why these are left to you: you need to be able to say "I wrote this" about real code in the repo, and each task is a likely live-coding or "walk me through it" interview prompt.
