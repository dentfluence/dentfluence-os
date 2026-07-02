# Dentfluence — VPS Go-Live Runbook (clinic test, target 1 July 2026)

**Stack detected:** Laravel 13 · PHP 8.3 · MySQL DB `dentfluence` (286 migrations) · Sanctum API (mobile) · Vite assets · queue/cache/session = **database** driver · local files in `storage` (real patient PHI).
**Target:** public HTTPS URL, India-region VPS, real patient data, web + mobile.

> ⚠️ **You run all server/artisan commands yourself** (per your workflow). This doc is copy-paste ready. Commands assume **Ubuntu 24.04** on the VPS. Run them as a sudo user over SSH.

---

## 0. Read this first (decisions baked in)

1. **AI features will be OFF on the VPS.** Whisper (CUDA) + Ollama need a GPU; a normal VPS has none. Voice notes, Tulip copilot, receipt/x-ray scan won't function. Everything else works. *(Fix later by pointing `OLLAMA_URL`/Whisper at a GPU box; out of scope for this test.)*
2. **India region for the VPS** — real PHI + DPDP data-residency. Use DigitalOcean **Bangalore (BLR1)** or AWS Lightsail **Mumbai**. Don't pick a EU/US region.
3. **Reuse your existing `APP_KEY`.** If any data is encrypted with it (and Laravel encrypts sessions/some fields), a *new* key makes that data unreadable. Copy the `APP_KEY=` line from your local `.env` exactly.
4. **Import the database — never `migrate:fresh`.** You have real data + 286 applied migrations. You'll import a full dump (schema + data). Running `migrate:fresh` would **wipe everything**.
5. **HTTPS is mandatory** before the clinic touches it. That needs a **domain name** pointed at the VPS IP (Let's Encrypt won't issue for a bare IP).

---

## 1. Prerequisites (buy these today)

- [ ] **VPS:** 2 vCPU / 4 GB RAM / 80 GB SSD, Ubuntu 24.04, **India region**. (~₹1,000–1,500/mo. 4 GB because MySQL + PHP-FPM + queue worker.)
- [ ] **Domain or subdomain** you control (e.g. `app.yourclinic.in`). Add an **A record** → VPS public IP. DNS can take 30–60 min to propagate.
- [ ] **SSH key** added to the VPS (password login disabled — step 3).
- [ ] Your local **Laragon** running so you can export the DB + files.

---

## 2. Point DNS

In your registrar's DNS panel:
```
Type: A   Host: app   Value: <VPS_PUBLIC_IP>   TTL: 5 min
```
Verify before continuing (wait until it returns the VPS IP):
```bash
dig +short app.yourclinic.in
```

---

## 3. Server: base install + lockdown

SSH in, then:

```bash
# --- update ---
sudo apt update && sudo apt -y upgrade

# --- firewall: only SSH + web ---
sudo apt -y install ufw fail2ban
sudo ufw allow OpenSSH
sudo ufw allow 80,443/tcp
sudo ufw --force enable

# --- harden SSH (key-only) ---
sudo sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
sudo systemctl restart ssh
```

> Make sure your SSH **key login works** before you log out, or you'll lock yourself out.

---

## 4. Install LEMP + PHP 8.3 + tooling

```bash
# Nginx
sudo apt -y install nginx

# PHP 8.3 + extensions Laravel + phpspreadsheet need
sudo apt -y install php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl

# MySQL 8
sudo apt -y install mysql-server
sudo mysql_secure_installation     # set a strong root password, answer Y to the rest

# Composer
cd /tmp && curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 20 (for building front-end assets)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt -y install nodejs

# Git + unzip
sudo apt -y install git unzip
```

---

## 5. Create the database + app DB user

```bash
sudo mysql
```
In the MySQL prompt (replace the password with a strong one and **save it**):
```sql
CREATE DATABASE dentfluence CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dentfluence'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON dentfluence.* TO 'dentfluence'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 6. Get the code onto the server

**Option A — via Git (cleanest, if you have a private remote):**
```bash
sudo mkdir -p /var/www && sudo chown $USER:$USER /var/www
cd /var/www
git clone <YOUR_PRIVATE_REPO_URL> dentfluence
cd dentfluence
```

**Option B — upload a zip (no GitHub needed).** On your **Windows machine**, zip the project **excluding** `vendor`, `node_modules`, `.git`, then from the VPS:
```bash
# from your PC (PowerShell), scp the zip up:
#   scp dentfluence.zip user@VPS_IP:/var/www/
cd /var/www && unzip dentfluence.zip -d dentfluence && cd dentfluence
```

---

## 7. Export local data from Laragon → import to VPS

On your **Windows / Laragon** machine (Laragon's MySQL must be running):

```bash
# DB dump (schema + data). Run in Laragon terminal / cmd:
mysqldump -u root -p --single-transaction --routines --triggers dentfluence > dentfluence.sql

# Patient files (PHI) — zip the storage app dir:
#   it holds clinical-files, patients, consultations, etc.
#   from the project root:
#   (PowerShell) Compress-Archive -Path storage\app\* -DestinationPath storage_app.zip
```

Copy both up to the VPS:
```bash
# from your PC:
#   scp dentfluence.sql storage_app.zip user@VPS_IP:/var/www/dentfluence/
```

On the **VPS**, import the DB and restore files:
```bash
cd /var/www/dentfluence
mysql -u dentfluence -p dentfluence < dentfluence.sql      # imports data + marks migrations done

unzip storage_app.zip -d storage/app/                       # restore PHI files
```

---

## 8. Production `.env`

```bash
cp .env.example .env
nano .env
```
Set these (leave the rest as sensible defaults):
```ini
APP_NAME="Dentfluence"
APP_ENV=production
APP_KEY=                      # ← PASTE the exact APP_KEY from your LOCAL .env (do not regenerate)
APP_DEBUG=false               # ← MUST be false in production
APP_URL=https://app.yourclinic.in

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dentfluence
DB_USERNAME=dentfluence
DB_PASSWORD=CHANGE_ME_STRONG_PASSWORD   # the one from step 5

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true     # cookies only over HTTPS
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

# Sanctum / mobile API — list your domain so the app can call the API
SANCTUM_STATEFUL_DOMAINS=app.yourclinic.in
SESSION_DOMAIN=.yourclinic.in

# AI off for VPS (no GPU) — features that use these will simply not run
OLLAMA_URL=http://127.0.0.1:11434
WHISPER_DEVICE=cpu
```

> If `APP_KEY` is genuinely empty in your local `.env`, only then run `php artisan key:generate`. If it has a value, **copy it** — don't regenerate.

---

## 9. Install deps, build, cache

```bash
cd /var/www/dentfluence

composer install --no-dev --optimize-autoloader

npm install --ignore-scripts
npm run build                 # builds Vite assets into public/build

php artisan storage:link      # public/storage → storage/app/public

# verify DB connection works (should list migrations, all "Ran")
php artisan migrate:status

# production caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 10. Permissions

```bash
sudo chown -R www-data:www-data /var/www/dentfluence
sudo find /var/www/dentfluence -type d -exec chmod 755 {} \;
sudo find /var/www/dentfluence -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/dentfluence/storage /var/www/dentfluence/bootstrap/cache
```

---

## 11. Nginx vhost

```bash
sudo nano /etc/nginx/sites-available/dentfluence
```
Paste (swap the domain):
```nginx
server {
    listen 80;
    server_name app.yourclinic.in;
    root /var/www/dentfluence/public;

    index index.php;
    charset utf-8;
    client_max_body_size 50M;          # allow x-ray/doc uploads

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```
Enable + reload:
```bash
sudo ln -s /etc/nginx/sites-available/dentfluence /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

---

## 12. HTTPS (Let's Encrypt)

```bash
sudo apt -y install certbot python3-certbot-nginx
sudo certbot --nginx -d app.yourclinic.in --redirect --agree-tos -m you@email.com --no-eff-email
```
Certbot auto-edits the vhost for 443 + auto-renews. Test renewal:
```bash
sudo certbot renew --dry-run
```

---

## 13. Queue worker + scheduler (background jobs)

Your app uses the **database queue** — messages, exports, etc. won't process without a worker.

**Queue worker (systemd):**
```bash
sudo nano /etc/systemd/system/dentfluence-queue.service
```
```ini
[Unit]
Description=Dentfluence queue worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/dentfluence/artisan queue:work --sleep=3 --tries=3 --timeout=90

[Install]
WantedBy=multi-user.target
```
```bash
sudo systemctl daemon-reload
sudo systemctl enable --now dentfluence-queue
sudo systemctl status dentfluence-queue
```

**Scheduler (cron)** — for reminders/recall if used:
```bash
sudo crontab -u www-data -e
```
Add:
```
* * * * * cd /var/www/dentfluence && php artisan schedule:run >> /dev/null 2>&1
```

---

## 14. Daily DB backup (minimum safety for real PHI)

```bash
sudo mkdir -p /var/backups/dentfluence
sudo crontab -e
```
Add (keeps 7 days):
```
30 2 * * * mysqldump -u dentfluence -p'CHANGE_ME_STRONG_PASSWORD' dentfluence | gzip > /var/backups/dentfluence/db-$(date +\%F).sql.gz
0 3 * * * find /var/backups/dentfluence -name 'db-*.sql.gz' -mtime +7 -delete
```

---

## 15. Smoke test (before sharing with clinic)

- [ ] `https://app.yourclinic.in` loads with a **padlock** (valid cert).
- [ ] Login works; you land on the dashboard.
- [ ] Open a known patient → their data + uploaded files/images render (confirms DB **and** storage came across).
- [ ] Create a test consultation / prescription → saves.
- [ ] Page source / network: no Laravel debug error pages (confirms `APP_DEBUG=false`).
- [ ] `sudo systemctl status dentfluence-queue` → **active (running)**.

---

## 16. Mobile app

Point the Flutter app's API base URL at the VPS, then rebuild:
- Change the base URL constant (wherever `http://10.0.2.2:8000` / `localhost` is configured) to **`https://app.yourclinic.in`**.
- Confirm `SANCTUM_STATEFUL_DOMAINS` / token auth still resolve over HTTPS.
- Rebuild the APK and install on the test phones.

*(The mobile project isn't in this workspace, so make this change in the `dentfluence_mobile` repo.)*

---

## 17. Security must-dos (real PHI — do NOT skip)

- [ ] `APP_DEBUG=false` ✔ (step 8) — stops leaking stack traces + env.
- [ ] HTTPS + redirect ✔ (step 12).
- [ ] Firewall: only 22/80/443; **MySQL not exposed** (bound to localhost by default — keep it).
- [ ] Strong, unique DB + MySQL-root + server passwords; SSH key-only ✔ (step 3).
- [ ] Daily backup ✔ (step 14).
- [ ] Force strong staff passwords; if you have MFA built, turn it on for the test accounts.
- [ ] Tell test staff this is a live system with real data — no sharing logins.

---

## 18. If something breaks (quick triage)

| Symptom | Likely cause | Fix |
|---|---|---|
| 500 error, blank page | perms / cache | `php artisan optimize:clear`, then re-cache (step 9); check `storage/logs/laravel.log` |
| 419 / page expired on login | session/cookie domain | recheck `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_SECURE_COOKIE` |
| Patient files 404 | storage link or files not copied | re-run `php artisan storage:link`; confirm step 7 unzip |
| Assets/styles missing | Vite not built | `npm run build`; confirm `public/build` exists |
| Jobs not processing | queue worker down | `sudo systemctl restart dentfluence-queue` |
| Cert fails to issue | DNS not propagated | wait for `dig` to return VPS IP, re-run certbot |

---

## What this gets you on July 1

A real, HTTPS-secured, India-hosted Dentfluence your clinic can log into from anywhere and test with live data — **minus the GPU-AI features**. It is *not yet* the full cloud production setup (no load balancing, no managed DB, no DPDP consent-flow verification sign-off, no automated off-site backups). For a supervised clinic test that's fine; before a wider rollout, come back to the Phase A/C hardening in `docs/plan-build-timeline.md`.
