# Cloudflare Mini DNS Panel

A lightweight, **framework-free** PHP admin panel for managing DNS records across **multiple Cloudflare accounts**.

Built for simple hosting environments: no Composer, no database server — data is stored in JSON files with file locking.

---

## Highlights

- Multi-account Cloudflare support via **API Token**
- Domains listed **per account** (not mixed into one global list)
- DNS records shown **only under each domain**
- Instant create / edit / delete against the Cloudflare API
- Local **JSON cache** with automatic refresh every hour
- Operators with roles and **per-domain access control**
- Full **audit logging** of logins and DNS/admin actions
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

The application auto-creates JSON files on first run:

- `accounts.json`
- `zones.json`
- `records.json`
- `operators.json`
- `logs.json`
- `meta.json`

### 4. Open the panel and sign in

Default administrator (created automatically on first run):

| Field | Value |
|-------|-------|
| Username | `admin` |
| Password | `admin123` |

**Change this password immediately** via **Change Password** (or Operators → Edit).

### 5. Add a Cloudflare account

1. Go to **Accounts → Add account**
2. Enter a display name
3. Paste a Cloudflare **API Token**
4. Save — the panel verifies the token and syncs zones/DNS

#### Recommended Cloudflare token permissions

Create a token at: [Cloudflare API Tokens](https://dash.cloudflare.com/profile/api-tokens)

Suggested permissions:

- **Zone → Zone → Read**
- **Zone → DNS → Edit**

Include the zones (or account) you want this panel to manage.

---

## Quick start (local)

Using PHP’s built-in server:

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

Add as many accounts as you need. Each account keeps its own API token. Domains are always grouped under their parent account.

### DNS management

Supported record types:

`A`, `AAAA`, `CNAME`, `TXT`, `MX`, `NS`, `SRV`, `CAA`, `PTR`

Per domain you can:

- List records
- Create records
- Edit records
- Delete records
- Refresh that domain’s records from Cloudflare

Mutations call Cloudflare **immediately**. On success, the local JSON cache is updated in place.

### Caching & sync

| Mode | Behavior |
|------|----------|
| Lazy sync | On page load, if an account cache is older than **1 hour**, it refreshes automatically |
| Manual sync | **Accounts → Sync** or **Dashboard → Force sync all accounts** |
| CLI sync | `php scripts/sync.php` (ideal for cron) |

Failed syncs are logged and do not wipe previously cached good data.

#### Cron example (hourly)

```cron
0 * * * * /usr/bin/php /path/to/CloudFlare-Mini/scripts/sync.php >/dev/null 2>&1
```

---

## Operators & access control

### Roles

| Role | Permissions |
|------|-------------|
| **admin** | Manage Cloudflare accounts, operators, all domains/DNS, view all audit logs |
| **editor** | Create / edit / delete DNS on allowed domains |
| **viewer** | Read-only access to allowed domains |

### Domain scope

For each non-admin operator you can choose:

- **All domains** — access every synced zone
- **Selected domains** — access only chosen zone IDs

Admins always have full domain access.

### Password management

- Any logged-in user can change their own password under **Change Password**
- Admins can set or reset another operator’s password under **Operators → Edit**

---

## Audit logs

Important actions are appended to `data/logs.json`, including:

- Login / logout / failed login
- Account create / update / delete / sync
- Operator create / update / delete
- DNS create / update / delete / refresh
- Password changes

- **Admins** see all logs  
- **Editors / viewers** see their own actions  

---

## Configuration

Edit [`app/config.php`](app/config.php):

| Key | Default | Description |
|-----|---------|-------------|
| `app_name` | Cloudflare Mini DNS Panel | Application title |
| `sync_interval` | `3600` | Cache lifetime in seconds |
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
│   ├── bootstrap.php          # App bootstrap
│   ├── config.php             # Configuration
│   ├── Auth.php               # Login / sessions
│   ├── Access.php             # Roles & domain ACL
│   ├── JsonStore.php          # Atomic JSON storage
│   ├── CloudflareClient.php   # Cloudflare API v4 client
│   ├── SyncService.php        # Sync + cache helpers
│   ├── Logger.php             # Audit logger
│   ├── helpers.php            # Shared helpers
│   ├── controllers/           # Route controllers
│   └── views/                 # English HTML templates
├── public/
│   ├── index.php              # Front controller (web root)
│   └── assets/                # CSS / JS
├── data/                      # JSON database (not for public access)
├── scripts/
│   ├── sync.php               # CLI hourly sync
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
- Use least-privilege Cloudflare API tokens
- CSRF tokens are required on POST forms
- Login attempts are rate-limited in-session
- Prefer HTTPS in production
- Keep PHP and the host patched

---

## Troubleshooting

| Problem | What to check |
|---------|----------------|
| Redirect loop | Make sure you open `.../public/index.php` (or a vhost rooted at `public/`). Update to the latest helpers that redirect to `index.php?r=...`. |
| Blank page / 500 | Enable PHP error display temporarily; confirm `curl` + `json` extensions; ensure `data/` is writable |
| Cannot add account | Token invalid or missing Zone/DNS permissions |
| Domains missing for an operator | Operator domain access may be set to **Selected** with no zones chosen |
| Cache looks stale | Use **Sync** on the account, **Force sync**, or run `php scripts/sync.php` |
| Permission denied writing JSON | Fix ownership/permissions on `data/` for the PHP/web user |

---

## Tech stack

- Plain PHP 7.4+ (no framework)
- Session-based authentication
- JSON file storage with `flock`
- Cloudflare API v4 over cURL
- Vanilla HTML / CSS / JS front end

---

## Roadmap ideas

- Export / import DNS records
- Per-account sync schedule overrides
- 2FA for operators
- Webhook notifications on DNS changes

Contributions and issue reports are welcome.

---

## License

This project is released under the [MIT License](LICENSE).

---

## Disclaimer

This project is **not** affiliated with or endorsed by Cloudflare, Inc.  
“Cloudflare” is a trademark of Cloudflare, Inc.
