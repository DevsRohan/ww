# WhatsApp Engine (Hugging Face Spaces)

Node.js + Express + Socket.io + whatsapp-web.js backend for the WhatsApp CRM.

## Deploy

1. Create a new Space (Docker SDK).
2. Push these files to the Space repo.
3. Set Space **Secrets**:
   - `API_KEY`
   - `WEBHOOK_SECRET`
   - `HOSTINGER_WEBHOOK_URL`
   - `ALLOWED_ORIGINS`
4. Wait for build → open Space URL → scan QR (visible in `/qr` page).

## Endpoints

- `GET  /health`       — engine status
- `GET  /qr`           — QR code (HTML page)
- `POST /send-message` — `{ phone, message }`  (auth: `x-api-key`)
- `POST /check-number` — `{ phone }`           (auth: `x-api-key`)
- `GET  /status`       — connection state JSON (auth: `x-api-key`)
- `POST /restart`      — restart WhatsApp client (auth: `x-api-key`)

## Sockets (public, browser connects directly)

Events emitted: `engine:qr`, `engine:ready`, `engine:disconnected`,
`message:inbound`, `message:outbound`, `message:status`,
`campaign:progress`, `lead:replied`, `system:heartbeat`.

## Persistence

WhatsApp session stored in `SESSION_PATH` (default: `/home/user/app/data/wa_session`).
On HF free tier, filesystem is ephemeral — user must rescan QR after restart.
For HF Pro, mount `/data` as persistent storage.
