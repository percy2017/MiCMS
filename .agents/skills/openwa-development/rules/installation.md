# OpenWA Installation & Deployment

OpenWA supports Docker (recommended), local dev, and production with optional profiles (PostgreSQL, Redis, S3, dashboard, Traefik proxy).

**Current version:** `v0.1.6` (May 17, 2026)

---

## Quick Start — Docker (Recommended)

### Development

```bash
git clone https://github.com/rmyndharis/OpenWA.git
cd OpenWA
docker compose -f docker-compose.dev.yml up -d
```

Access:
- **API:** http://localhost:2785/api
- **Swagger UI:** http://localhost:2785/api/docs
- **Dashboard:** http://localhost:2886

**Dev compose includes:** SQLite, local storage, both API + Dashboard in one container.

### Production

Basic (SQLite, local storage):
```bash
docker compose up -d
```

With PostgreSQL:
```bash
docker compose --profile postgres up -d
```

Full stack (PostgreSQL + Redis + Dashboard + Traefik):
```bash
docker compose --profile full up -d
```

### Available Profiles

| Profile          | Services              | Use case                |
|------------------|-----------------------|-------------------------|
| `postgres`       | PostgreSQL DB         | Production DB           |
| `redis`          | Redis cache/queue     | Async webhook delivery  |
| `minio`          | S3-compatible storage | Scalable media storage  |
| `with-dashboard` | Web dashboard         | Visual management       |
| `with-proxy`     | Traefik reverse proxy | SSL termination, ACME  |
| `full`           | All of the above      | Complete production stack |

---

## Local Development (without Docker)

```bash
git clone https://github.com/rmyndharis/OpenWA.git
cd OpenWA
npm install            # installs both API and dashboard deps
npm run dev            # starts API + Dashboard with auto-reload
```

- Auto-generates `.env` on first run (no manual setup needed)
- Default API key created and logged to console
- Uses SQLite (default) and local file storage

**Available scripts:**
```bash
npm run dev            # API + Dashboard, watch mode
npm run dev:api        # API only
npm run dev:dashboard  # Dashboard only
npm run build          # Build both for production
npm run start          # Run production build
npm run test           # Run unit tests (Jest)
npm run test:cov       # Tests with coverage
npm run lint           # ESLint
npm run migration:generate  # Generate new TypeORM migration
npm run migration:run       # Apply pending migrations
npm run migration:revert    # Revert last migration
```

---

## Environment Configuration

The `.env` file (auto-generated on first run, fully customizable):

### Core

```env
NODE_ENV=production
API_PORT=2785
LOG_LEVEL=info                # error | warn | info | debug
DOMAIN=localhost
DASHBOARD_PORT=2886
CORS_ORIGINS=*                # comma-separated origins
ENABLE_SWAGGER=true
```

### Engine

```env
ENGINE_TYPE=whatsapp-web.js   # plugin-based
SESSION_DATA_PATH=./data/sessions
PUPPETEER_HEADLESS=true
PUPPETEER_ARGS=--no-sandbox,--disable-setuid-sandbox,--disable-dev-shm-usage,--disable-gpu
```

`PUPPETEER_ARGS` is critical for Docker — without `--no-sandbox` Chromium fails to start in containers.

### Database

```env
DATABASE_TYPE=sqlite          # or postgres
DATABASE_SYNCHRONIZE=false    # FALSE in production!
DATABASE_LOGGING=false

# PostgreSQL (ignored if sqlite)
DATABASE_HOST=localhost
DATABASE_PORT=5432
DATABASE_NAME=openwa
DATABASE_USERNAME=openwa
DATABASE_PASSWORD=your-secure-password
```

> **Important:** `DATABASE_SYNCHRONIZE` MUST be `false` in production. It auto-creates/alters schema from entity definitions, which can lose data. Use migrations instead.

### Redis / Queue

```env
REDIS_ENABLED=false
REDIS_HOST=localhost
REDIS_PORT=6379
# REDIS_PASSWORD=
```

When `REDIS_ENABLED=true`, webhook delivery uses BullMQ with retry/backoff. Otherwise it falls back to direct HTTP delivery with exponential backoff.

### Storage

```env
STORAGE_TYPE=local            # or s3
STORAGE_LOCAL_PATH=./data/media

# S3/MinIO
S3_ENDPOINT=http://localhost:9000
S3_BUCKET=openwa
S3_REGION=us-east-1
S3_ACCESS_KEY=minioadmin
S3_SECRET_KEY=minioadmin
```

### Webhook

```env
WEBHOOK_TIMEOUT=10000         # ms
WEBHOOK_MAX_RETRIES=3
WEBHOOK_RETRY_DELAY=5000      # ms (exponential backoff base)
```

### Rate Limiting

```env
RATE_LIMIT_TTL=60             # window in seconds
RATE_LIMIT_MAX=100            # max requests per window
```

### Security

```env
API_MASTER_KEY=               # emergency admin key, optional but recommended
```

### Plugins

```env
PLUGINS_ENABLED=true
PLUGINS_DIR=./data/plugins
```

---

## First-Run Flow

1. **Container starts** → reads `.env` (or generates one with defaults)
2. **Database initialized** → runs pending migrations
3. **Master API key check** → if `API_MASTER_KEY` is empty, generates a random one and logs it
4. **Default API key created** → first key is created automatically (used by bundled dashboard)
5. **Webhook URL printed** → if a webhook was set in `POST /sessions`, it's shown
6. **Server ready** → binds to `0.0.0.0:2785`

**To get the auto-generated API key on first run:**

```bash
docker logs openwa-api 2>&1 | grep "API key"
```

Or in local dev:
```bash
npm run dev 2>&1 | grep -i "key"
```

---

## Session Creation & QR Flow

```bash
# 1. Create session record
curl -X POST http://localhost:2785/api/sessions \
  -H "X-API-Key: $KEY" \
  -d '{"name": "tigo1"}'

# Returns: {"data": {"id": "sess_abc", "status": "INITIALIZING", ...}}

# 2. Start the session
curl -X POST http://localhost:2785/api/sessions/sess_abc/start \
  -H "X-API-Key: $KEY"

# 3. Get QR code
curl http://localhost:2785/api/sessions/sess_abc/qr \
  -H "X-API-Key: $KEY"
# Returns: {"data": {"code": "2@...", "image": "data:image/png;base64,..."}}

# 4. User scans with WhatsApp → status becomes CONNECTED
# 5. Receive `session.qr` (if subscribed) and `session.status: CONNECTED` webhook
```

**Programmatic QR for headless servers:**

You can pipe the `image` (base64 PNG) to a QR decoder, or use the `code` field with a QR generator library.

---

## Production Deployment Tips

### 1. Use External Database

Don't rely on SQLite for production. Use `--profile postgres` and an external PostgreSQL server (RDS, Cloud SQL, etc.).

### 2. Enable Redis for Reliable Webhooks

```env
REDIS_ENABLED=true
```

Direct mode (no Redis) blocks the request thread on webhook delivery. Queue mode is async, more reliable, and supports retry.

### 3. Use S3/MinIO for Media

Local disk fills up fast with media. Use S3-compatible storage (`--profile minio` for built-in, or AWS S3 / CloudFlare R2 / Backblaze B2).

### 4. Run Behind a Reverse Proxy (Traefik)

```env
DASHBOARD_ENABLED=true
PROXY_ENABLED=true
TRAEFIK_ACME_EMAIL=admin@yourdomain.com
```

Traefik handles SSL termination + ACME (Let's Encrypt).

### 5. Set `API_MASTER_KEY`

Treat this like a root password. Set it once and store in a password manager.

### 6. Configure CIDR Whitelisting on API Keys

```bash
curl -X POST http://localhost:2785/api/auth/api-keys \
  -H "X-API-Key: $ADMIN_KEY" \
  -d '{
    "name": "Laravel",
    "role": "operator",
    "allowedIps": ["203.0.113.0/24"]
  }'
```

### 7. Use Health Check Endpoints

OpenWA exposes `/health/live` and `/health/ready` for Kubernetes:

```yaml
livenessProbe:
  httpGet:
    path: /api/health/live
    port: 2785
  initialDelaySeconds: 30
  periodSeconds: 10

readinessProbe:
  httpGet:
    path: /api/health/ready
    port: 2785
  initialDelaySeconds: 5
  periodSeconds: 5
```

### 8. Log Forwarding

OpenWA logs to stdout (in Docker) and to `./logs/` (in local dev). Forward to your log aggregator (Loki, ELK, CloudWatch).

`LOG_LEVEL=info` is recommended for production; `debug` is very verbose.

### 9. Backups

Two data sources to back up:
- **Database** (`./data/openwa.sqlite` or PostgreSQL dump)
- **Session credentials** (`./data/sessions/` — the WhatsApp auth tokens, can be reused across OpenWA restarts)

The `infra/export-data` endpoint exports all DB data as JSON for migration:
```bash
curl -H "X-API-Key: $ADMIN_KEY" http://localhost:2785/api/infra/export-data > backup.json
```

### 10. Container Resource Limits

```yaml
services:
  openwa-api:
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 2G
        reservations:
          cpus: '0.5'
          memory: 512M
```

WhatsApp-web.js + Chromium is memory-hungry. Allocate at least 1GB per session.

---

## Upgrading

```bash
# Pull latest image
docker compose pull

# Apply migrations
docker compose run --rm openwa-api npm run migration:run:prod

# Restart
docker compose up -d
```

**Breaking changes between versions:** Check `CHANGELOG.md`. Major versions (e.g., `v0.2.0`) may have migration scripts that must run.

---

## Database Migrations

OpenWA uses TypeORM migrations. Generated migrations live in `src/migrations/`.

```bash
# Generate a new migration after entity changes
npm run migration:generate -- src/migrations/AddFieldX

# Apply pending migrations
npm run migration:run

# Revert last migration
npm run migration:revert

# Show migration status
npm run migration:show

# Production variants (run from dist/)
npm run migration:run:prod
npm run migration:revert:prod
```

**`DATABASE_SYNCHRONIZE=true` auto-creates tables on first boot (dev only). Set to `false` in production** to require explicit migrations.

---

## Troubleshooting

### Chromium fails to start in Docker

**Error:** `Failed to launch the browser process`

**Fix:** Ensure `PUPPETEER_ARGS` includes `--no-sandbox` (and `--disable-setuid-sandbox`):
```env
PUPPETEER_ARGS=--no-sandbox,--disable-setuid-sandbox,--disable-dev-shm-usage,--disable-gpu
```

### Session disconnects frequently

**Cause:** Memory pressure, network issues, or WhatsApp anti-ban kicking in.

**Fix:**
- Increase memory limits
- Reduce message frequency
- Use session rotation for bulk sends
- Enable `WEBHOOK_MAX_RETRIES` to retry failed sends

### Webhooks not received

**Cause:** Either the webhook wasn't registered, or the consumer is not reachable from OpenWA.

**Fix:**
1. List webhooks: `GET /api/sessions/{id}/webhooks`
2. Test delivery: `POST /api/sessions/{id}/webhooks/{id}/test`
3. Check OpenWA logs for delivery errors
4. If `REDIS_ENABLED=true`, check Redis queue: `redis-cli LLEN bull:webhook:waiting`

### "Data type jsonb in entity is not supported by sqlite"

**Cause:** Migration script targets PostgreSQL syntax but DB is SQLite (or vice versa).

**Fix:** Ensure `DATABASE_TYPE` matches your migration history. If you changed DB types, regenerate migrations or re-import data.

### "Session is not active"

**Cause:** Calling `send-*` before `start` completed, or after `stop`.

**Fix:** Wait for `status: CONNECTED` event/webhook, or check `GET /sessions/{id}` for current status.

### High memory usage / OOM kills

**Cause:** Each session loads Chromium and holds media in memory.

**Fix:**
- Set `PUPPETEER_HEADLESS=true` (default)
- Use `--disable-dev-shm-usage` (default in PUPPETEER_ARGS)
- Limit concurrent sessions to memory budget
- For >5 sessions, scale horizontally (multiple OpenWA instances, separate DB)

---

## Ports Summary

| Service     | Port  | Description                                |
|-------------|-------|--------------------------------------------|
| API         | 2785  | REST API + Swagger UI + WebSocket          |
| Dashboard   | 2886  | Web management interface                   |
| PostgreSQL  | 5432  | (with --profile postgres)                  |
| Redis       | 6379  | (with REDIS_ENABLED=true)                  |
| MinIO       | 9000  | S3-compatible storage (with --profile minio) |
| MinIO UI    | 9001  | Web console for MinIO                      |
| Traefik     | 80/443 | (with --profile with-proxy)              |
