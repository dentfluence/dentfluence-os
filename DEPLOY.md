# Dentfluence — Deployment Runbook

Step-by-step guide to put Dentfluence on a public server (VPS) with Docker,
HTTPS, automated backups, and the AI features safely turned off for v1.

Follow the steps **in order**. Anything you run on the server is in a `code box`.
You can copy/paste them. Where you must fill in your own value, it looks like
`<your-value>`.

---

## What you already have (built locally)

| File | What it does |
|------|--------------|
| `Dockerfile` | Builds the app image (PHP 8.3 + your assets) |
| `docker-compose.yml` | Runs app + nginx + MySQL + queue + scheduler |
| `docker/` | nginx, PHP, and startup configs |
| `.env.production` | Production settings (you fill in secrets on the server) |
| `deploy.sh` | One command to build, migrate, and go live |
| `backup.sh` | One command to back up the database + uploads |

---

## Step 1 — Buy a VPS

Recommended starting size for the early clinics:

- **2 vCPU / 4 GB RAM / 80 GB SSD** — comfortable for the first dozens of clinics.
- Region: **India (Mumbai/Bangalore)** for low latency to your users.
- OS image: **Ubuntu 24.04 LTS**.

Good providers (any of these work):
- **DigitalOcean** — "Basic Droplet", 4 GB ($24/mo), simple UI.
- **Hetzner Cloud** — CPX21 (~€8/mo), best price; has a Singapore region.
- **AWS Lightsail** — 4 GB plan, if you prefer AWS.

> You can resize up later without rebuilding — that's the point of Docker.

When it's created, note the server's **public IP address**.

---

## Step 2 — Point your domain at the server

In your domain registrar's DNS settings, add an **A record**:

```
Type: A    Name: app    Value: <your-server-ip>    TTL: 300
```

This makes `app.dentfluence.com` point to your server. DNS can take a few
minutes to an hour. (Use whatever subdomain you set in `APP_URL`.)

---

## Step 3 — First login + basic server hardening

SSH into the server (replace with your IP):

```
ssh root@<your-server-ip>
```

Create a non-root user and a firewall:

```
adduser dentfluence
usermod -aG sudo dentfluence
ufw allow OpenSSH
ufw allow 80
ufw allow 443
ufw --force enable
```

From here, log in as that user: `ssh dentfluence@<your-server-ip>`.

---

## Step 4 — Install Docker

```
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
```

**Log out and back in** (so the docker group applies), then verify:

```
docker --version
docker compose version
```

---

## Step 5 — Get the code onto the server

Put the project in `/opt/dentfluence`. Two options:

**Option A — git (recommended if your repo is on GitHub/GitLab):**
```
sudo mkdir -p /opt/dentfluence && sudo chown $USER /opt/dentfluence
git clone <your-repo-url> /opt/dentfluence
cd /opt/dentfluence
```

**Option B — copy from your PC** (run this on your Windows machine, not the server):
```
scp -r "E:\Dentfluence\Dentfluence_OS\Dentfluence Web" dentfluence@<your-server-ip>:/opt/dentfluence
```

> `.env.production` is git-ignored, so with Option A it will NOT be on the
> server yet — you create it in the next step. With Option B it copies up, but
> you still edit the secrets in the next step.

---

## Step 6 — Fill in the production secrets

On the server, in `/opt/dentfluence`:

```
cp .env.example .env.production   # only if .env.production isn't there yet
nano .env.production
```

Generate two strong passwords (run twice, copy each result):
```
openssl rand -base64 24
```

Fill in every `>>> CHANGE ME <<<` line:
- `APP_URL` → `https://app.dentfluence.com`
- `DB_PASSWORD` → first generated password
- `DB_ROOT_PASSWORD` → second generated password
- `SESSION_DOMAIN` → `.dentfluence.com`
- `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` → from your Brevo
  dashboard (Brevo → SMTP & API → SMTP). Username is the login email shown
  there; password is the **SMTP key**, not your account password.

Leave `APP_KEY` as-is (already generated). Save with `Ctrl+O`, `Enter`, `Ctrl+X`.

---

## Step 7 — Launch

```
chmod +x deploy.sh backup.sh
./deploy.sh
```

This builds the images, starts all five services, runs your migrations, and
caches config. First build takes a few minutes. When it finishes:

```
docker compose --env-file .env.production ps
```

All services should show `running`. The app is now live **inside** the server
on port 8080 — next we add HTTPS so the public can reach it securely.

---

## Step 8 — Add HTTPS (Caddy reverse proxy)

Caddy sits in front of the app and gets a free SSL certificate automatically.

Install Caddy on the server:
```
sudo apt install -y debian-keyring debian-archive-keyring apt-transport-https curl
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
sudo apt update && sudo apt install -y caddy
```

Configure it:
```
sudo nano /etc/caddy/Caddyfile
```

Replace the contents with (use your real domain):
```
app.dentfluence.com {
    reverse_proxy 127.0.0.1:8080
}
```

Reload Caddy:
```
sudo systemctl reload caddy
```

Caddy fetches a Let's Encrypt certificate automatically. Open
`https://app.dentfluence.com` in a browser — you should see the login page
with a padlock. **Done — you're live.**

---

## Step 9 — Turn on automated daily backups

Schedule the backup to run every night at 2 AM:

```
crontab -e
```

Add this line (adjust the path if different):
```
0 2 * * * cd /opt/dentfluence && ./backup.sh >> backups/backup.log 2>&1
```

Backups land in `/opt/dentfluence/backups/` and auto-prune after 14 days.

> **Also copy backups off the server** (e.g. to S3 or another machine). A backup
> on the same disk won't survive a server failure.

---

## Step 10 — Test a restore ONCE (do not skip)

A backup you've never restored is not a backup. Test it now, while it's safe:

```
# 1. Run a backup manually
./backup.sh

# 2. Restore the database from the newest dump into the running DB
LATEST=$(ls -t backups/db_*.sql.gz | head -1)
gunzip < "$LATEST" | docker compose --env-file .env.production exec -T mysql \
  mysql -u root -p"$(grep DB_ROOT_PASSWORD .env.production | cut -d= -f2)" dentfluence

# 3. Restore uploaded files
LATESTF=$(ls -t backups/files_*.tar.gz | head -1)
docker compose --env-file .env.production exec -T app \
  tar xzf - -C /var/www/html/storage < "$LATESTF"
```

Log into the app and confirm your data is intact. Now you trust your backups.

---

## Go-live checklist

- [ ] `APP_ENV=production` and `APP_DEBUG=false` in `.env.production`
- [ ] DB passwords set (not the placeholder text)
- [ ] HTTPS padlock shows on your domain
- [ ] A test email sends (try "forgot password" on the login screen)
- [ ] `docker compose ps` shows queue + scheduler running
- [ ] Daily backup cron added
- [ ] Restore tested once successfully
- [ ] The six failing finance/inventory tests re-run and green (do this locally
      before launch — payment → ledger → invoice-paid is the critical one)

---

## Day-to-day commands

```
# See running services
docker compose --env-file .env.production ps

# Watch app logs
docker compose --env-file .env.production logs -f app

# Deploy a new version (after pushing/copying new code)
./deploy.sh

# Restart everything
docker compose --env-file .env.production restart

# Stop everything (data is preserved in volumes)
docker compose --env-file .env.production down
```

---

## Turning AI features back on later (post-v1)

Tulip, voice notes, and vision/scan are **off** in v1 because a normal VPS has
no GPU. When you want them:

1. Stand up a GPU server (or a hosted Ollama endpoint) running the models.
2. In `.env.production`, set `OLLAMA_URL` to that endpoint and flip
   `ASSISTANT_ENABLED=true` (and `ASSISTANT_VISION_ENABLED` if you want scanning).
3. Re-run `./deploy.sh`.

Nothing else changes — the code is already there, just gated off.
