# Cloudflare Mini DNS Panel

A lightweight, **framework-free** PHP admin panel for managing DNS records across **multiple Cloudflare accounts** — including reverse DNS (`in-addr.arpa` / PTR) workflows.

Built for simple hosting environments: **no Composer**, **no database server**. Data lives in JSON files with file locking (`flock`).

---

## Highlights

- Multi-account Cloudflare support via **API Token**
- Domains listed **per account**, with record counts and reverse-zone IP ranges
- DNS create / edit / delete against the Cloudflare API (**single** and **bulk**)
- Local **JSON cache** refreshed by cron (pages stay fast — no blocking sync on UI)
- Separate **sync cron** and **PTR audit cron** with independent locks
- Operators with **customizable access levels**, rank hierarchy, and per-domain ACL
- **IP / subnet allowlist** for login and panel access
- Reverse DNS tools: IP-range detection, IP → PTR lookup + create-when-missing, PTR consistency audit / auto-delete
- Separated log channels: **Audit**, **DNS changes**, **PTR**, and **Cron**
- Paginated logs with **20 / 50 / 100** per-page selector
- Change your own password from the panel
- Compatible with **PHP 7.4+**

---

## Requirements

| Requirement | Details |
|-------------|---------|
| PHP | **7.4 or newer** |
| Extensions | `curl`, `json`, `session` |
| Web server | Apache, Nginx, LiteSpeed, or PHP built-in server |
| Permissions | Write access to the `data/` directory |
| Cloudflare | API Token with Zone + DNS permissions |

No MySQL/PostgreSQL and no Composer dependencies.

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/MrAriaNet/CloudFlare-Mini.git
cd CloudFlare-Mini
```

### 2. Set the web root

Point your web server **document root** to the `public/` folder.

| Correct | Incorrect |
|---------|-----------|
| `.../CloudFlare-Mini/public` | `.../CloudFlare-Mini` (project root) |

If the document root must stay on the project root, the included root `.htaccess` rewrites requests into `public/` (Apache). Prefer pointing directly at `public/` when possible.

### 3. Make `data/` writable

```bash
chmod -R 755 data
# If needed for your host:
chmod -R 775 data
```

The application auto-creates JSON files on first run, including:

| File | Purpose |
|------|---------|
| `accounts.json` | Cloudflare accounts / API tokens |
| `zones.json` | Cached zones per account |
| `records.json` | Cached DNS records per zone |
| `operators.json` | Panel users |
| `roles.json` | Access levels / permissions |
| `ip_allowlist.json` | Allowed IPs / CIDRs |
| `ptr_settings.json` | PTR audit settings |
| `ptr_issues.json` | Open PTR mismatch list |
| `logs.json` | Audit logs |
| `dns_logs.json` | Forward DNS create / edit / delete |
| `ptr_logs.json` | PTR audit / create / delete |
| `cron_logs.json` | Sync cron jobs |
| `meta.json` | Sync metadata |

### 4. Open the panel and sign in

Default administrator (created automatically when no operators exist):

| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `admin123` |

**Change this password immediately** via **Change Password** (or Operators → Edit).

### 5. Secure cron and allowlist

1. Set a strong `cron_key` in [`app/config.php`](app/config.php).
2. Optionally configure **IP Allowlist** so only trusted networks can reach the panel.

### 6. Add a Cloudflare account

1. Go to **Accounts → Add account**
2. Enter a display name
3. Paste a Cloudflare **API Token**
4. Save — the panel verifies the token and syncs zones / DNS

#### Recommended Cloudflare token permissions

Create a token at: [Cloudflare API Tokens](https://dash.cloudflare.com/profile/api-tokens)

Suggested permissions:

- **Zone → Zone → Read**
- **Zone → DNS → Edit**

Include the zones (or account) you want this panel to manage.

---

## Quick start (local)

```bash
cd public
php -S 0.0.0.0:8080
```

Visit: `http://localhost:8080`

Self-check (optional):

```bash
php scripts/selfcheck.php
```

---

## Features in detail

### Multiple Cloudflare accounts

Add as many accounts as you need. Each account keeps its own API token. Domains are always grouped under their parent account. The Accounts and Dashboard views show status, domain counts, and last sync time.

### Domains

- Forward zones and reverse (`in-addr.arpa`) zones are labeled clearly
- Reverse zones show the derived **IP range**
- Each domain shows a **record count** from the local cache
- Filter by account or search by domain / IP range

### DNS management

Supported record types:

`A`, `AAAA`, `CNAME`, `TXT`, `MX`, `NS`, `SRV`, `CAA`, `PTR`

Per domain you can:

- List records (with type / proxy / TTL badges)
- Create a single record
- Edit / delete a single record
- **Bulk add** up to 200 records per submit
- **Bulk edit** selected records in a table form
- Refresh that domain’s records from Cloudflare

Mutations call Cloudflare **immediately**. On success, the local JSON cache is updated in place. Successful DNS create / edit / delete events are written to **DNS Change Logs** (not the general audit log).

#### Bulk add format

One record per line. Columns:

```text
type,name,content,ttl,proxied,priority
```

- Separators: CSV (`,`), pipe (`|`), or tab
- `ttl`, `proxied`, and `priority` are optional
- Lines starting with `#` are ignored
- Header row `type,name,...` is skipped

Examples:

```text
# examples
A,www,203.0.113.10,1,0,
A,api,203.0.113.11,1,1,
CNAME,blog,www.example.com,1,0,
MX,@,mail.example.com,1,0,10
TXT,@,"v=spf1 include:_spf.google.com ~all",1,0,
PTR,10,host.example.com,1,0,
```

`proxied` accepts `1` / `true` / `yes` / `proxied` / `on`.

#### Bulk edit

1. Open a domain’s DNS records
2. Select one or more rows
3. Click **Bulk edit selected**
4. Update fields in the grid and save

Limited to **200** records per submit. Partial failures are reported without discarding successful updates.

### Caching & sync

Page loads **never** call the Cloudflare sync API in the background. The panel always reads from the local JSON cache so login and navigation stay fast.

| Mode | Behavior |
|------|----------|
| Cron (recommended) | Refresh cache every hour in the background |
| Manual sync | **Accounts → Sync** or **Dashboard → Force sync all accounts** |
| Per-domain refresh | **DNS Records → Refresh from Cloudflare** |
| On account add/edit | One-time sync for that account only |

DNS create / edit / delete still hit Cloudflare **immediately**, then update the cache.

Failed syncs are logged and do not wipe previously cached good data.

#### Option A — CLI cron (best)

Schedule **two separate jobs** (do not combine PTR into sync):

```cron
0 * * * * /usr/bin/php /path/to/CloudFlare-Mini/scripts/cron.php >/dev/null 2>&1
15 * * * * /usr/bin/php /path/to/CloudFlare-Mini/scripts/cron_ptr.php >/dev/null 2>&1
```

| Script | Purpose |
|--------|---------|
| `scripts/cron.php` | Cloudflare account / zone / DNS cache sync |
| `scripts/cron_ptr.php` | PTR consistency audit (+ optional auto-delete) |

Each job has its own lock file so they cannot duplicate or overlap themselves.

Force anytime:

```bash
php scripts/cron.php --force
php scripts/cron_ptr.php --force
```

#### Option B — HTTP cron (cPanel / shared hosting)

1. Set a strong secret in `app/config.php`:

```php
'cron_key' => 'put-a-long-random-secret-here',
```

2. Schedule these URLs every hour (separate jobs):

```text
https://your-domain.example/cron.php?key=put-a-long-random-secret-here
https://your-domain.example/cron_ptr.php?key=put-a-long-random-secret-here
```

Optional force:

```text
https://your-domain.example/cron.php?key=YOUR_SECRET&force=1
https://your-domain.example/cron_ptr.php?key=YOUR_SECRET&force=1
```

---

## Reverse DNS (PTR)

### What you get

- Automatic detection of `in-addr.arpa` reverse zones and their **IP ranges**
- **Find PTR** by IPv4 on the Domains page
- **Create PTR** when no record exists (pick covering reverse zone + hostname)
- **PTR Audit**: forward-check each PTR hostname (FCrDNS via DNS; optional ICMP ping on manual runs)
- Open mismatches listed for review, dismiss, or delete
- Optional **auto-check** / **auto-delete** via `cron_ptr` only

### PTR audit settings

Available under **PTR Audit** (requires permissions):

| Setting | Meaning |
|---------|---------|
| Enable auto PTR audit | Honored by `cron_ptr` only |
| Auto-check interval | Minimum seconds between auto runs (default `21600` = 6 hours) |
| Auto-delete mismatched PTRs | Delete broken PTR records during audit / PTR cron |
| Use ICMP ping | Manual audit only — PTR cron always uses fast DNS-only checks |

PTR cron processes records in batches (`ptr_audit_batch` in config) so large reverse zones do not hang the job.

### PTR logs

All PTR activity (audit summaries, creates, deletes, settings changes) is stored **only** in `data/ptr_logs.json` and shown under **PTR Logs**. It never appears in Audit / DNS / Cron logs.

---

## Operators & access control

Access is controlled by **customizable roles** stored in `data/roles.json`.

### Default access levels

| Role | Rank | Can create operators up to | Typical use |
|------|------|----------------------------|-------------|
| **Administrator** | 100 | Any level (including admin) | Full system control |
| **Manager** | 60 | Rank ≤ 40 (Editor / Viewer) | Team lead who creates staff |
| **Editor** | 40 | — | DNS create / edit / delete |
| **Viewer** | 20 | — | Read-only |

### Granular permissions

Permissions are grouped in the Access Levels UI:

**Accounts & security**

- Manage Cloudflare accounts
- Sync a single Cloudflare account
- Force sync all accounts
- Manage IP allowlist

**Domains & DNS**

- View domains / DNS records
- Create / edit / delete DNS
- Refresh DNS from Cloudflare
- View PTR mismatch list
- Run PTR consistency audit
- Configure PTR auto-check / auto-delete
- Delete mismatched PTR records

**Operators**

- Manage operators
- Define access levels (roles)

**Logs**

- View audit / DNS / cron / PTR logs
- View everyone’s logs (not only own)
- Clear each log channel separately

Each role also has a **max assignable rank** — a hard ceiling for roles that user may assign to others.

### Hierarchy rule

A user **cannot** assign an access level that is:

1. Higher than their own rank, or
2. Higher than their role’s **max assignable rank**

Example: Manager (rank 60, max assignable 40) can create Editor and Viewer, but **not** Manager or Administrator.

### Domain scope

For each operator (except full admin-rank accounts) you can choose:

- **All domains** — every synced zone
- **Selected domains** — only chosen zone IDs

### Password management

- Any logged-in user can change their own password under **Change Password**
- Users who manage operators can reset passwords for operators within their allowed rank

### IP allowlist

Under **IP Allowlist**, restrict panel access to specific IPv4 addresses or CIDR subnets (for example `203.0.113.0/24`). When configured, requests from other IPs are denied before login.

---

## Logging

Four separate channels — PTR never mixes with the others:

| Channel | File | Contents |
|---------|------|----------|
| **Audit Logs** | `data/logs.json` | Login / logout / failures, accounts, operators, roles, password, DNS refresh, IP allowlist, clears |
| **DNS Change Logs** | `data/dns_logs.json` | Forward DNS create / edit / delete (including bulk) |
| **PTR Logs** | `data/ptr_logs.json` | PTR audit, create, delete, settings |
| **Cron Logs** | `data/cron_logs.json` | Background sync jobs only |

Notes:

- Users with **view all logs** see everyone’s entries (where applicable)
- Others typically see only their own operator actions
- Each channel can be cleared independently (with permission)
- List pages support search and pagination (**20 / 50 / 100** per page)
- Older PTR-related rows that landed in other channels are migrated into PTR Logs automatically

---

## Configuration

Edit [`app/config.php`](app/config.php):

| Key | Default | Description |
|-----|---------|-------------|
| `app_name` | Cloudflare Mini DNS Panel | Application title |
| `sync_interval` | `3600` | How long cache is considered fresh (seconds) |
| `web_lazy_sync` | `false` | If `true`, pages may sync on load (slow — keep `false`) |
| `cron_key` | *(change me)* | Secret for `/cron.php` and `/cron_ptr.php` HTTP cron |
| `cron_lock_ttl` | `900` | Seconds before a stuck cron lock is considered stale |
| `cron_time_limit` | `600` | PHP time limit for a cron run |
| `ptr_audit_batch` | `300` | Max PTR records checked per auto PTR cron run |
| `default_admin.username` | `admin` | Seeded only when no operators exist |
| `default_admin.password` | `admin123` | Seeded only when no operators exist |
| `dns_types` | A, AAAA, CNAME, … | Allowed DNS types in the UI |
| `login_max_attempts` | `8` | Failed logins before temporary lock |
| `login_lock_seconds` | `300` | Lock duration after too many failures |

After the first successful boot, changing `default_admin` does **not** reset an existing admin user.

---

## Project structure

```text
CloudFlare-Mini/
├── app/
│   ├── bootstrap.php          # App bootstrap + JSON store seed
│   ├── config.php             # Configuration
│   ├── Auth.php               # Login / sessions
│   ├── Access.php             # Permissions & domain ACL helpers
│   ├── JsonStore.php          # Atomic JSON storage with flock
│   ├── RoleService.php        # Access levels / permissions
│   ├── CloudflareClient.php   # Cloudflare API v4 client
│   ├── SyncService.php        # Account / zone / DNS sync + cache
│   ├── PtrService.php         # Reverse DNS helpers + audit
│   ├── PtrCronRunner.php      # PTR cron runner
│   ├── CronRunner.php         # Sync cron runner
│   ├── CronLock.php           # Non-overlapping cron locks
│   ├── IpAllowlist.php        # IP / CIDR allowlist
│   ├── Logger.php             # Multi-channel logger
│   ├── helpers.php            # Shared helpers (URL, CSRF, pagination, …)
│   ├── controllers/           # Route controllers
│   └── views/                 # English HTML templates
├── public/
│   ├── index.php              # Front controller (web root)
│   ├── cron.php               # HTTP sync cron (?key=...)
│   ├── cron_ptr.php           # HTTP PTR cron (?key=...)
│   └── assets/                # CSS / JS
├── data/                      # JSON database (not for public access)
├── scripts/
│   ├── cron.php               # CLI sync cron (recommended)
│   ├── cron_ptr.php           # CLI PTR cron (recommended)
│   ├── sync.php               # Alias → cron.php
│   └── selfcheck.php          # Basic install check
├── .gitignore
├── .htaccess                  # Optional rewrite to public/
└── README.md
```

---

## Web server examples

### Apache (document root = `public/`)

No special rewrite is required for query-string routing (`index.php?r=...`).

Ensure `AllowOverride` is enabled if you rely on `.htaccess`.

### Nginx

```nginx
server {
    listen 80;
    server_name dns-panel.example.com;
    root /var/www/CloudFlare-Mini/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php7.4-fpm.sock; # adjust version/socket
    }

    # Deny access if someone mispoints root at the project folder
    location ~ ^/(app|data|scripts)/ {
        deny all;
    }
}
```

---

## Security notes

- Treat `data/` as sensitive — it stores API tokens and password hashes
- Never expose `data/`, `app/`, or `scripts/` as a public URL
- Change the default admin password on first login
- Change `cron_key` before enabling HTTP cron endpoints
- Use least-privilege Cloudflare API tokens
- Prefer enabling the **IP allowlist** in production
- CSRF tokens are required on POST forms
- Login attempts are rate-limited in-session
- Prefer HTTPS in production
- Keep PHP and the host patched

---

## Troubleshooting

| Problem | What to check |
|---------|----------------|
| Redirect loop | Make sure you open `.../public/index.php` (or a vhost rooted at `public/`). |
| Blank page / 500 | Enable PHP error display temporarily; confirm `curl` + `json` extensions; ensure `data/` is writable |
| Cannot add account | Token invalid or missing Zone/DNS permissions |
| Domains missing for an operator | Operator domain access may be set to **Selected** with no zones chosen |
| Cache looks stale | Schedule `cron.php`, use **Force sync**, or open `/cron.php?key=...` |
| PTR audit never runs | Schedule `cron_ptr.php` separately; enable auto-check; wait for interval; try `--force` |
| Login / pages are slow | Keep `web_lazy_sync` = `false` and use cron for background sync |
| Access denied (IP) | Your client IP is outside the configured allowlist |
| Permission denied writing JSON | Fix ownership/permissions on `data/` for the PHP/web user |
| Bulk create/edit partial errors | Check Cloudflare API responses in the flash message; fix invalid rows and retry |

---

## Tech stack

- Plain PHP 7.4+ (no framework)
- Session-based authentication
- JSON file storage with `flock`
- Cloudflare API v4 over cURL
- Vanilla HTML / CSS / JS front end

---

## Roadmap ideas

- Export / import zone snapshots
- Per-account sync schedule overrides
- 2FA for operators
- Webhook / Telegram notifications on DNS or PTR changes
- IPv6 reverse DNS (`ip6.arpa`) helpers

Contributions and issue reports are welcome.

---

## License

This project is released under the [MIT License](LICENSE).

---

## Disclaimer

This project is **not** affiliated with or endorsed by Cloudflare, Inc.  
“Cloudflare” is a trademark of Cloudflare, Inc.
