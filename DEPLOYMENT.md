# Deployment Guide

Two environments — set them up in this order.

---

## 1) Hugging Face Spaces (Node.js engine)

### Create the Space

1. Go to https://huggingface.co/new-space → **Docker → Blank**
2. Name it (e.g. `wcrm-engine`)
3. Visibility: **Public** (so your dashboard browser can connect Socket.io directly) or Private (will require login token).
4. Push the files from `huggingface-backend/` to the Space repo:

```bash
cd huggingface-backend
git init
git remote add origin https://huggingface.co/spaces/<your-user>/<space-name>
git add .
git commit -m "init engine"
git push -u origin main
```

### Set Space Secrets (Settings → Variables and secrets → New secret)

| Key | Value |
|-----|-------|
| `API_KEY` | strong random (≥ 40 chars) — copy this for Hostinger |
| `WEBHOOK_SECRET` | strong random (≥ 40 chars) — copy this for Hostinger |
| `HOSTINGER_WEBHOOK_URL` | `https://yourdomain.com/webhook.php` |
| `ALLOWED_ORIGINS` | `https://yourdomain.com,https://www.yourdomain.com` |

### After the Space builds

- Visit `https://<your-space>.hf.space/` — should show JSON status.
- Visit `https://<your-space>.hf.space/qr` — scan QR with your WhatsApp.
- Visit `https://<your-space>.hf.space/health` — should show `state: ready` after scan.

> **Note about HF persistence:** the WhatsApp session is stored under `/home/user/app/data/wa_session`. On the **free tier**, this filesystem is wiped on every restart — you'll need to rescan QR after a Space sleep/restart. For production, upgrade to **HF Pro** and mount `/data` as persistent storage (set `SESSION_PATH=/data/wa_session`).

---

## 2) Hostinger (PHP dashboard)

### Database

1. Hostinger → **Databases → MySQL** → create database `whatsapp_crm` and a user with full privileges
2. Open phpMyAdmin → **Import** → upload `sql/schema.sql` first, then `sql/seed.sql`

### Files

1. Upload everything inside `hostinger/` to your `public_html/` directory.
2. Edit `config/app.php`:

```php
'db' => [
    'host'     => 'localhost',
    'database' => 'u123_whatsapp_crm',
    'username' => 'u123_admin',
    'password' => '<your db password>',
],
```

3. Make sure these directories are writable by the PHP user:
```
public_html/uploads/
public_html/logs/
```
Hostinger File Manager → right-click → **Permissions → 0755** for folders, **0644** for files. `uploads/` and `logs/` should be **0775** (or 0777 if your host requires).

### First login

1. Visit `https://yourdomain.com/`
2. Log in with `admin@example.com / admin@12345`
3. Open **Settings** and fill in:
   - **Hugging Face**: `node_api_url` (`https://<space>.hf.space`), `node_api_key`, `socket_url` (same as `node_api_url`), `webhook_secret`
   - **Groq**: `groq_api_key`
4. Save — settings persist in DB and override `config/app.php`.

### Cron jobs

Hostinger → **Advanced → Cron Jobs**

| Schedule | Command |
|----------|---------|
| `*/2 * * * *`  | `/usr/bin/php /home/USER/public_html/scripts/campaign.php >> /home/USER/public_html/logs/campaign.log 2>&1` |
| `*/5 * * * *`  | `/usr/bin/php /home/USER/public_html/scripts/retry_failed.php >> /home/USER/public_html/logs/retry.log 2>&1` |
| `*/10 * * * *` | `/usr/bin/php /home/USER/public_html/scripts/validate_pending.php >> /home/USER/public_html/logs/validate.log 2>&1` |
| `0 3 * * *`    | `/usr/bin/php /home/USER/public_html/scripts/cleanup_logs.php` |

Replace `USER` with your Hostinger username. Some hosts use `php` instead of `/usr/bin/php` — check **Hosting → Advanced → PHP version**.

---

## 3) First run checklist

- [ ] HF Space green/ready (QR scanned)
- [ ] Hostinger DB connected (login works)
- [ ] Settings saved (engine URL, API key, webhook secret, Groq key)
- [ ] CSV uploaded
- [ ] Validation cron has run (Pending leads → Valid/Not on WhatsApp)
- [ ] Test "Send first outreach" on **one lead** before starting a campaign
- [ ] Start campaign

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| Sidebar shows "Disconnected" | Check `socket_url` setting matches HF Space URL exactly. |
| Webhook events not received | Check `webhook_secret` matches both sides. Verify `webhook.php` reachable publicly. |
| QR never appears | HF Space may be sleeping (free tier). Refresh `/qr` page. |
| All sends fail with `engine_not_ready` | WhatsApp not authenticated — rescan QR. |
| Numbers stay `pending` | Validate cron not running, or HF engine offline. |
| Messages duplicated | Webhook signature missing — check `WEBHOOK_SECRET` envs match. |
