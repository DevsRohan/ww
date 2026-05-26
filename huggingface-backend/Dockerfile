# =========================================================
# Hugging Face Spaces - Docker SDK
# WhatsApp Engine (Node.js + whatsapp-web.js + Puppeteer)
# =========================================================
FROM node:20-slim

# Install Chromium + dependencies for Puppeteer
RUN apt-get update && apt-get install -y --no-install-recommends \
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
    && rm -rf /var/lib/apt/lists/*

# Tell Puppeteer to skip downloading Chromium and use system one
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

# HF Spaces requires user 1000
RUN useradd -m -u 1000 user
USER user
ENV HOME=/home/user \
    PATH=/home/user/.local/bin:$PATH

WORKDIR /home/user/app

# Persistent session dir (HF /data is writable for Pro; fallback to local)
RUN mkdir -p /home/user/app/data/wa_session

# Install deps
COPY --chown=user:user package.json ./
RUN npm install --omit=dev --no-audit --no-fund

# Copy app
COPY --chown=user:user . .

ENV PORT=7860
ENV NODE_ENV=production
ENV SESSION_PATH=/home/user/app/data/wa_session

EXPOSE 7860

CMD ["node", "server.js"]
