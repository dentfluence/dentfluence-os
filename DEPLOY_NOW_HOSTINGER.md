# Deploy Dentfluence — Hostinger VPS (testing, no brand exposure)

Your live testing target: **https://srv1791841.hstgr.cloud**
Server IP: **187.127.152.68**  ·  OS: Ubuntu 24.04  ·  App runs on host port 8080, Caddy adds HTTPS.

We are **NOT** using dentfluence.in yet. The hstgr.cloud hostname keeps your brand out of
public certificate logs. Everyone (you = admin, 7–8 staff) logs in at the URL above with
their own account.

Run each `code box` on the server unless it says "on your PC". Fill in `<...>` values.

---

## Step 1 — SSH into the server (from your PC terminal)

```
ssh root@187.127.152.68
```
Enter the root password you saved during setup.

---

## Step 2 — Confirm the hostname resolves (important for HTTPS)

```
getent hosts srv1791841.hstgr.cloud
```
It must return **187.127.152.68**. If it returns nothing, tell me before doing Step 8 —
Caddy can't get an SSL cert until this resolves.

---

## Step 3 — Basic hardening + firewall

```
adduser dentfluence
usermod -aG sudo dentfluence
ufw allow OpenSSH
ufw allow 80
ufw allow 443
ufw --force enable
```
Then reconnect as that user: `ssh dentfluence@187.127.152.68`

---

## Step 4 — Check Docker (you enabled Docker manager, so it's likely already installed)

```
docker --version && docker compose version
```
If BOTH print versions → skip to Step 5.
If "command not found", install it:
```
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
```
Then **log out and back in**, and re-check the versions.

---

## Step 5 — Get the code onto the server → /opt/dentfluence

```
sudo mkdir -p /opt/dentfluence && sudo chown $USER /opt/dentfluence
```

**Option A — git (cleanest, if your code is in a private repo):**
```
git clone <your-repo-url> /opt/dentfluence
cd /opt/dentfluence
```

**Option B — copy from your Windows PC** (run on your PC, not the server):
```
scp -r "E:\Dentfluence\Dentfluence_OS\Dentfluence Web" dentfluence@187.127.152.68:/opt/dentfluence
```
(Slow because of node_modules/vendor. Git is faster if you have a repo.)

---

## Step 6 — Create the production secrets

```
cd /opt/dentfluence
cp .env.production .env.production.bak   # keep a copy
nano .env.production
```

Generate two strong passwords (run twice, copy each):
```
openssl rand -base64 24
```

Change these lines to match THIS server (use the hstgr hostname, not dentfluence):
```
APP_URL=https://srv1791841.hstgr.cloud
DB_PASSWORD=<first generated password>
DB_ROOT_PASSWORD=<second generated password>
SESSION_DOMAIN=null
```
Brevo email (optional for first boot — login works without it, but "forgot password" won't):
```
MAIL_USERNAME=<your Brevo SMTP login>
MAIL_PASSWORD=<your Brevo SMTP key>
MAIL_FROM_ADDRESS=<a verified Brevo sender, e.g. no-reply@yourbrevodomain>
```
Leave `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `DB_HOST=mysql` as they are.
Save: `Ctrl+O`, `Enter`, `Ctrl+X`.

> Why SESSION_DOMAIN=null: it's a single hostname, so the cookie scopes to the exact host.
> (When we move to app.dentfluence.in later, this becomes .dentfluence.in.)

---

## Step 7 — Launch the app

```
chmod +x deploy.sh backup.sh
./deploy.sh
```
First build takes a few minutes. When done, check all 5 services are up:
```
docker compose --env-file .env.production ps
```
At this point the app is live INSIDE the server on port 8080 (no HTTPS yet).
Quick local test:
```
curl -I http://127.0.0.1:8080
```
Should return an HTTP response (200 or a redirect), not "connection refused".

---

## Step 8 — Add HTTPS with Caddy

```
sudo apt install -y debian-keyring debian-archive-keyring apt-transport-https curl
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | sudo tee /etc/apt/sources.list.d/caddy-stable.list
sudo apt update && sudo apt install -y caddy
sudo nano /etc/caddy/Caddyfile
```

Replace the whole file with exactly this:
```
srv1791841.hstgr.cloud {
    reverse_proxy 127.0.0.1:8080
}
```
Reload:
```
sudo systemctl reload caddy
```
Caddy auto-fetches a Let's Encrypt cert. Wait ~30 seconds, then open:

**https://srv1791841.hstgr.cloud** → you should see the login page with a padlock.

If the cert fails, check Caddy's log: `sudo journalctl -u caddy --no-pager | tail -30`
(usual cause: the hostname didn't resolve in Step 2, or port 80 is blocked.)

---

## Step 9 — Create your team's accounts

Log in as admin at the URL above, then add your 7–8 users (front desk, doctors,
assistants) with their roles. They'll log in at the same URL / in the mobile app.

---

## Step 10 — Daily backups

```
crontab -e
```
Add:
```
0 2 * * * cd /opt/dentfluence && ./backup.sh >> backups/backup.log 2>&1
```

---

## Done = live for testing

- ✅ HTTPS padlock at https://srv1791841.hstgr.cloud
- ✅ Encrypted (safe for real logins + patient data)
- ✅ Zero "dentfluence" anywhere public
- ✅ Staff use mobile app (server URL set to the same address); you use web admin

### When you've registered the brand
Add DNS A record `app` → 187.127.152.68 on dentfluence.in, change the Caddyfile
hostname to `app.dentfluence.in`, set `APP_URL` + `SESSION_DOMAIN` accordingly,
reload Caddy, re-run `./deploy.sh`. ~5 minutes.

### Mobile app (separate task)
Build the app with its server URL = https://srv1791841.hstgr.cloud, then sideload
the APK or push to a Play Store internal-testing track for your staff's phones.
