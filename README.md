# WhatsApp CRM + Cold Outreach Operating System

Production-grade dual-environment SaaS for WhatsApp cold outreach, segmentation, and conversation management.

```
┌─────────────────────────────────────────┐
│  HOSTINGER (PHP 8 + MySQL)              │
│  Dashboard · Webhooks · AI · DB · Cron  │
└─────────────────┬───────────────────────┘
                  │  HTTPS REST  (api key)
                  │  Webhooks    (HMAC-SHA256)
┌─────────────────▼───────────────────────┐
│  HUGGING FACE SPACES (Node.js + Docker) │
│  whatsapp-web.js · Socket.io · Queue    │
└─────────────────────────────────────────┘
        ▲
        │ Socket.io (browser → HF, direct)
        │
   ┌────┴────┐
   │ Browser │  Tailwind CDN · Vanilla JS
   └─────────┘
```

## What this is

A premium, white + green themed SaaS dashboard that lets you:

- Upload Google-Maps-style business CSVs
- Validate WhatsApp numbers in batches
- Generate **personalized first-outreach** messages with **Groq AI**
- Send only the **first message** automatically (anti-ban: random 120–300s delays, daily caps)
- **Stop automation** the moment a lead replies, then continue manually from the chat panel
- Watch every event in real time via Socket.io

## Repository layout

```
whatsapp-crm/
├── huggingface-backend/   # Node.js engine — deploy on HF Spaces (Docker SDK)
├── hostinger/             # PHP dashboard + APIs — upload to public_html
└── sql/                   # schema + seed
```

See [DEPLOYMENT.md](DEPLOYMENT.md) for a step-by-step guide.

## Anti-ban philosophy

WhatsApp ban algorithms detect *patterns*. This system avoids patterns by:

1. Sending only the **first** message (no spam loops, no follow-ups)
2. Random 120–300 second delays between messages
3. Daily cap (default 60/day)
4. AI personalization (every message is unique)
5. Active-hours window (10 AM – 8 PM by default)
6. Hard-stop on first reply — humans take over
7. Number validation before send (skip invalid numbers)

## Tech stack

- **Backend (engine):** Node.js 20, Express, Socket.io, whatsapp-web.js, Puppeteer
- **Backend (CRM):** PHP 8, PDO/MySQL
- **Frontend:** Tailwind CDN, Vanilla JS, Socket.io client
- **AI:** Groq (Llama 3.3 70B by default)

## Default credentials

`admin@example.com` / `admin@12345` — **change on first login**.
