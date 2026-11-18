# CV bullets

Each bullet uses only what this repo actually contains, with numbers that come from saved reports or test output. Pick 3–4. Adjust the verbs to your own role in the pairing.

- **Built a WordPress/WooCommerce event-ticketing portal from scratch.** A custom OOP plugin (PHP 8.2, PSR-4, 83 classes/enums/interfaces, ~8,600 lines) plus a custom accessible theme, covering tiered tickets with capacity-based stock, server-side purchase rules, a validated block-checkout field, and REST/shortcode/Elementor integrations. WordPress Coding Standards clean and 138 PHPUnit tests passing.

- **Designed a sold-out waitlist on versioned custom MySQL tables.** It uses double opt-in with hashed single-use tokens, transactional row-locked state changes with an audit log, FIFO batch notifications via Action Scheduler, signed 24-hour claim links, conversion tracking, and GDPR export/erase. Verified by a 32-step end-to-end test.

- **Improved mobile Lighthouse performance to 100 on all audited pages (from 98–99).** Server response time fell from ~1 s to ~10 ms, LCP dropped 0.3–0.8 s and page weight fell 52–59%. The work: configuring and fixing page caching with stock-aware purging, inlining critical CSS, removing unused WooCommerce/jQuery assets, and serving WebP. Measured as the median of 3 Lighthouse runs, before and after.

- **Implemented technical SEO and analytics.** Event structured data inside the SEO plugin's schema graph; automated 301 archiving of past events with sitemap exclusion; noindex rules for filtered pages; and GA4 e-commerce tracking through Google Tag Manager with once-per-order purchase events. Covered by 21 SEO checks and an 11-step browser test.

> Before using a bullet, be ready to explain it line by line. The interview notes (`docs/INTERVIEW_NOTES.md`) have the reasoning and practice questions for each.
