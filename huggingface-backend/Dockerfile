# =========================================================
# Hugging Face Spaces - Docker SDK
# WhatsApp Engine (Node.js + whatsapp-web.js + Puppeteer)
# =========================================================
FROM node:20-slim

# ---- System deps for Chromium / Puppeteer ----
# node:20-slim is Debian Bookworm (12); package names below are correct for Bookworm.
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends --no-install-suggests \
    chromium \
    fonts-liberation \
    libasound2 \
    libatk-bridge2.0-0 \
    libatk1.0-0 \
    libc6 \
    libcairo2 \
    libcups2 \
    libdbus-1-3 \
    libexpat1 \
    libfontconfig1 \
    libgbm1 \
    libgcc-s1 \
    libglib2.0-0 \
    libgtk-3-0 \
    libnspr4 \
    libnss3 \
    libpango-1.0-0 \
    libpangocairo-1.0-0 \
    libstdc++6 \
    libx11-6 \
    libx11-xcb1 \
    libxcb1 \
    libxcomposite1 \
    libxcursor1 \
    libxdamage1 \
    libxext6 \
    libxfixes3 \
    libxi6 \
    libxrandr2 \
    libxrender1 \
    libxss1 \
    libxtst6 \
    ca-certificates \
    wget \
    curl \
    xdg-utils \
    procps \
    dumb-init \
 && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/*

# Tell Puppeteer to use system Chromium (skip its bundled download)
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

# ---- HF Spaces requires UID 1000 ----
# node:20-slim already ships with `node` user at UID 1000 — remove it,
# then create our `user` at UID 1000 (the UID HF expects).
RUN userdel -r node 2>/dev/null || true \
 && groupadd -g 1000 user \
 && useradd -m -u 1000 -g 1000 -s /bin/bash user

USER user
ENV HOME=/home/user \
    PATH=/home/user/.local/bin:$PATH \
    NODE_ENV=production \
    PORT=7860

WORKDIR /home/user/app

# Persistent session dir (writable by `user`)
RUN mkdir -p /home/user/app/data/wa_session

# ---- Install dependencies first (better cache) ----
COPY --chown=user:user package.json ./
RUN npm install --omit=dev --no-audit --no-fund --loglevel=warn \
 && npm cache clean --force

# ---- Copy app source ----
COPY --chown=user:user . .

ENV SESSION_PATH=/home/user/app/data/wa_session

EXPOSE 7860

# Use dumb-init as PID 1 so SIGTERM/SIGINT propagate to Node correctly
ENTRYPOINT ["/usr/bin/dumb-init", "--"]
CMD ["node", "server.js"]
