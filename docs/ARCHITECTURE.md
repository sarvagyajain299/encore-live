# Architecture

This page shows the planned target architecture. Parts marked *(Phase N)* do not exist yet.

## 1. Request flow

```mermaid
flowchart LR
    B[Browser] -->|HTTPS| CF[Cloudflare CDN<br/>production only]
    CF --> NX[Nginx / Apache]
    NX -->|cache HIT| PC[(WP Super Cache<br/>static HTML files)]
    NX -->|cache MISS, logged in,<br/>cart / checkout| WP[WordPress core<br/>PHP 8.2]

    subgraph WP_RUNTIME[WordPress runtime]
        WP --> TH[encore-theme<br/>templates and assets]
        WP --> EC[encore-core plugin]
        WP --> WC[WooCommerce]
        WP --> YO[Yoast SEO]
        WP --> EL[Elementor]

        EC --> SVC1[Content model<br/>CPTs, taxonomies, meta]
        EC --> SVC2[Ticketing rules<br/>Phase 3]
        EC --> SVC3[REST API encore/v1<br/>Phase 4]
        EC --> SVC4[Waitlist service<br/>Phase 5]
        EC --> SVC5[Schema + GA4 dataLayer<br/>Phase 6]
        TH -. reads data via .-> EC
    end

    SVC1 --> DB[(MySQL 8.0 / InnoDB)]
    SVC4 --> DB
    WC --> DB
```

**Rule:** the theme renders. The plugin decides. If you switch the theme off, every show, rule and waitlist entry still exists.

## 2. Restock → waitlist notification (Phase 5)

```mermaid
sequenceDiagram
    autonumber
    participant Admin as Admin / Refund
    participant WC as WooCommerce
    participant EC as encore-core<br/>StockListener
    participant AS as Action Scheduler<br/>(wp_actionscheduler_* tables)
    participant N as WaitlistNotifier
    participant DB as MySQL
    participant M as WC_Email to Mailpit / SMTP

    Admin->>WC: Refund or raise capacity (stock 0 to 4)
    WC->>EC: woocommerce_variation_set_stock
    EC->>AS: enqueue encore_waitlist_notify(variation_id)
    Note over EC,AS: Stock update returns immediately.<br/>The slow work runs later.
    AS->>N: run job (WP-Cron / async request)
    N->>DB: START TRANSACTION<br/>SELECT waiting entries FIFO ... FOR UPDATE
    N->>DB: UPDATE status = notified, claim_expires_at = now + 24h<br/>INSERT audit log rows, COMMIT
    N->>M: "Tickets are back" email with signed claim link
    AS-->>N: 24h later: expire unclaimed, notify next batch
```

## 3. Waitlist double opt-in email flow (Phase 5)

```mermaid
flowchart TD
    F[Fan submits waitlist form] --> R[POST /encore/v1/waitlist<br/>nonce, honeypot, rate limit]
    R --> E{Entry exists for<br/>show + tier + email?}
    E -- yes --> P[Return existing position]
    E -- no --> I[INSERT status = pending_confirmation<br/>store hash of token]
    I --> C[Confirmation email<br/>single-use token link]
    C --> K{Clicked within 48h?}
    K -- yes --> W[status = waiting<br/>'You're on the list' email]
    K -- no --> X[Scheduled action: status = expired]
```

## 4. Local development environment (Phase 0)

```mermaid
flowchart LR
    subgraph Host[Windows host]
        REPO[Git repo<br/>C:\dev\encore-live]
        PS[scripts/encore.ps1]
    end
    subgraph Docker[Docker Desktop, WSL2]
        WPC[wordpress<br/>:8080]
        DBC[(db: MySQL 8.0)]
        PMA[phpmyadmin<br/>:8081]
        MP[mailpit<br/>:8025 UI, :1025 SMTP]
        CLI[wpcli<br/>on demand]
        TOOLS[tools: PHP 8.2 + Composer<br/>on demand]
        VOL[(wp_data volume<br/>WP core + 3rd-party plugins)]
    end
    PS --> CLI & TOOLS
    REPO -- bind mount: theme, plugin, mu-plugins --> WPC
    REPO -- bind mount: whole repo --> TOOLS
    WPC --> DBC
    CLI --> DBC
    WPC -- wp_mail via SMTP --> MP
    WPC --- VOL
    CLI --- VOL
```

| Service | Image | Why |
|---|---|---|
| `wordpress` | `wordpress:php8.2-apache` | Official image. Builds `wp-config.php` from environment variables, so there are no secrets in files. |
| `db` | `mysql:8.0` | Same engine as production. InnoDB gives transactions for the waitlist. |
| `phpmyadmin` | `phpmyadmin:5.2` | Look inside custom tables while developing. |
| `mailpit` | `axllent/mailpit` | Catches every email. No real email can escape a dev machine. |
| `wpcli` | `wordpress:cli-php8.2` | Scripted, repeatable install. Runs as uid 33 to match Apache's `www-data`. |
| `tools` | `docker/tools/Dockerfile` | PHP 8.2 + Composer for PHPCS and PHPUnit. You don't need PHP on Windows. |
