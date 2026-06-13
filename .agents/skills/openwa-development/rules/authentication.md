# OpenWA Authentication & Authorization

OpenWA uses **API key-based authentication** with role-based access control. All authentication is stateless (no JWT, no sessions) — the `X-API-Key` header is the only credential.

---

## Quick Reference

```bash
# Recommended: header
curl -H "X-API-Key: openwa_sk_abc123..." https://openwa.hostbol.lat/api/sessions

# Less secure: query param (for browser/WS fallback)
curl "https://openwa.hostbol.lat/api/sessions?apiKey=openwa_sk_abc123..."

# WebSocket: query param only (browsers can't set headers on WS)
wss://openwa.hostbol.lat/ws?apiKey=openwa_sk_abc123...
```

---

## Header Format

```http
GET /api/sessions HTTP/1.1
Host: openwa.hostbol.lat
X-API-Key: openwa_sk_abc123...
X-Request-ID: req_1706868000000
Content-Type: application/json
Accept: application/json
```

| Header         | Required | Description                                  |
|----------------|----------|----------------------------------------------|
| `X-API-Key`    | Yes      | The full API key (NOT a hashed version)      |
| `X-Request-ID` | No       | Custom request ID; returned in `meta.requestId` for tracing |
| `Content-Type` | Yes (POST/PUT) | `application/json`                     |
| `Accept`       | Recommended | `application/json`                         |

---

## API Key Format

OpenWA keys follow the format `openwa_sk_{random}`. Example:
```
openwa_sk_a3f9b2e8d4c1f7e5a9b3d6f2e8c4a1b7
```

**Storage:**
- The key is stored in the DB as a **SHA-256 hash** (`keyHash` column, 64 chars). The plaintext is shown **only once** at creation.
- `keyPrefix` (8 chars) is stored separately for display ("openwa_sk" or first chars).
- If you lose the key, you must **revoke** it and create a new one.

---

## Roles

```typescript
export enum ApiKeyRole {
  ADMIN = 'admin',        // full access
  OPERATOR = 'operator',  // read+write, no API key management
  VIEWER = 'viewer',      // read-only
}
```

| Operation                | admin | operator | viewer |
|--------------------------|:-----:|:--------:|:------:|
| Read sessions/messages   | ✅    | ✅       | ✅     |
| Send messages            | ✅    | ✅       | ❌     |
| Manage contacts/groups   | ✅    | ✅       | ❌     |
| Configure webhooks       | ✅    | ✅       | ❌     |
| Read audit logs          | ✅    | ❌       | ❌     |
| Create/revoke API keys   | ✅    | ❌       | ❌     |
| Update settings          | ✅    | ❌       | ❌     |
| Update infra             | ✅    | ❌       | ❌     |
| Restart server           | ✅    | ❌       | ❌     |

The default role on key creation is `operator` (most permissive non-admin role).

---

## API Key Lifecycle

### Create

```http
POST /api/auth/api-keys
{
  "name": "Laravel Production",
  "role": "operator",
  "allowedIps": ["190.180.10.0/24", "200.50.100.5"],     // CIDR whitelist, optional
  "allowedSessions": ["sess_abc", "sess_xyz"],            // session restrict, optional
  "expiresAt": "2027-01-01T00:00:00.000Z"                // optional
}
```

Response `201` (plaintext key shown **only here**):
```json
{
  "id": "key_abc",
  "name": "Laravel Production",
  "key": "openwa_sk_a3f9b2e8d4c1f7e5a9b3d6f2e8c4a1b7",
  "role": "operator",
  "keyPrefix": "openwa_s",
  "createdAt": "2026-06-12T10:00:00.000Z"
}
```

⚠️ **Store `data.key` immediately. It cannot be retrieved later.**

### List / Read

```http
GET /api/auth/api-keys
```

Returns metadata only (no plaintext):
```json
{
  "success": true,
  "data": [
    {
      "id": "key_abc",
      "name": "Laravel Production",
      "keyPrefix": "openwa_s",
      "role": "operator",
      "allowedIps": ["190.180.10.0/24"],
      "isActive": true,
      "lastUsedAt": "2026-06-12T10:30:00.000Z",
      "usageCount": 1234,
      "createdAt": "2026-06-12T10:00:00.000Z",
      "expiresAt": null
    }
  ]
}
```

### Update

```http
PUT /api/auth/api-keys/{id}
{
  "name": "Laravel Prod (renamed)",
  "role": "viewer",                    // can downgrade
  "allowedIps": ["190.180.10.0/24"],
  "isActive": true
}
```

The plaintext `key` is **not updatable**. To rotate, revoke + create new.

### Revoke (deactivate)

```http
POST /api/auth/api-keys/{id}/revoke
```

Sets `isActive: false` but keeps the record (for audit). Use this when:
- Key is compromised but you want to keep usage stats
- Temporarily disabling a key without losing history
- Rotating a key (revoke old, create new, then optionally delete old)

### Delete (permanent)

```http
DELETE /api/auth/api-keys/{id}
```

Removes the record. **Audit logs referencing this key will keep a reference** (the `apiKeyId` is preserved, even if the key is gone).

---

## Security Features

### 1. CIDR Whitelisting (`allowedIps`)

```json
{ "allowedIps": ["190.180.10.0/24", "2001:db8::/32"] }
```

- Supports IPv4 and IPv6 CIDR
- Empty array = no restriction
- The check is against the **client IP** of the incoming request (after any proxy headers like `X-Forwarded-For`)
- Returns `403 FORBIDDEN` if client's IP is not in any allowed range

**For Laravel behind a load balancer:** Make sure the `X-Forwarded-For` header is trusted by OpenWA (or use direct IP). Otherwise, the IP check is against the LB, not the original client.

### 2. Session Restriction (`allowedSessions`)

```json
{ "allowedSessions": ["sess_abc", "sess_xyz"] }
```

- Empty array = access all sessions
- Restrict a key to specific sessions (good for shared hosting)

### 3. Expiry (`expiresAt`)

```json
{ "expiresAt": "2027-01-01T00:00:00.000Z" }
```

- After this date, all requests with this key return `401 UNAUTHORIZED`
- Set `null` (or omit) for no expiry
- Expired keys are NOT auto-deleted; they remain in the DB for audit

### 4. Active Flag (`isActive`)

- Set to `false` to immediately revoke (no expiry wait)
- Equivalent to `POST /revoke` but inline in update

### 5. Usage Tracking

- `lastUsedAt`: timestamp of most recent use
- `usageCount`: total successful requests with this key
- Useful for anomaly detection (sudden spikes, unusual times)

---

## Master API Key (`API_MASTER_KEY` env)

The `.env` file has a special `API_MASTER_KEY` setting:

```bash
API_MASTER_KEY=     # leave empty to disable
```

When set, this key:
- Has **admin role** regardless of DB
- Is **not** stored in the database
- Bypasses IP whitelist and session restriction
- Cannot be listed via `/auth/api-keys`
- Use case: emergency access, infrastructure automation

⚠️ **If you set this, treat it as a root password.** It's the only key that can recover from a complete lockout.

---

## The Bootstrap Endpoint

```http
GET /api/dashboard/bootstrap
```

Returns the **default** API key (the first one created). This endpoint is **public** on purpose:
- OpenWA is self-hosted
- The bundled dashboard runs on the same origin
- External clients should never call this
- Always use the user-provided key from the login page for non-bundled clients

```json
{
  "id": "key_abc",
  "key": "openwa_sk_...",
  "name": "default"
}
```

---

## Validating a Key

```http
POST /api/auth/validate
Header: X-API-Key: <key>
```

Response `200` if valid:
```json
{ "success": true, "data": { "valid": true, "role": "operator" } }
```

Response `401` if invalid:
```json
{ "success": false, "error": { "code": "UNAUTHORIZED" } }
```

Use this to:
- Probe a key without performing an actual operation
- Verify a key from a stored credential before rotating
- Quick health check from your client

---

## Error Responses

| Code | HTTP | When                                                  |
|------|------|-------------------------------------------------------|
| `UNAUTHORIZED`        | 401 | Missing or invalid key, expired, or inactive         |
| `FORBIDDEN`           | 403 | Insufficient role, or IP not in `allowedIps`          |
| `RATE_LIMITED`        | 429 | Too many requests (per category, see api-endpoints)   |

---

## Audit Trail

All auth events are logged to `/api/audit` (admin only):

| Action              | Severity | When                              |
|---------------------|----------|-----------------------------------|
| `api_key_created`   | info     | POST /auth/api-keys success       |
| `api_key_used`      | info     | Every API call (sampled in some configs) |
| `api_key_revoked`   | warn     | POST /auth/api-keys/{id}/revoke   |
| `api_key_deleted`   | warn     | DELETE /auth/api-keys/{id}        |
| `api_key_auth_failed` | error  | 401 response                      |

---

## Client-Side Best Practices (Laravel example)

```php
class OpenWaClient
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openwa.api_key'); // from .env
        $this->baseUrl = config('services.openwa.base_url');
    }

    public function request(string $method, string $path, array $body = []): array
    {
        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'X-Request-ID' => 'req_' . now()->getTimestampMs(),
        ])
        ->timeout(15)
        ->retry(3, function (int $attempt, Exception $e) {
            return $e instanceof ConnectionException && $attempt < 3;
        })
        ->$method($this->baseUrl . $path, $body);

        if ($response->status() === 401) {
            throw new UnauthorizedException('OpenWA API key invalid or expired');
        }

        if ($response->status() === 429) {
            throw new RateLimitedException(
                'Rate limited. Retry after: ' . $response->header('Retry-After')
            );
        }

        $response->throw();

        return $response->json('data') ?? [];
    }
}
```

**Store the API key** in Laravel's `.env`:
```env
OPENWA_API_KEY=openwa_sk_...
OPENWA_BASE_URL=https://openwa.hostbol.lat/api
```

```php
// config/services.php
'openwa' => [
    'api_key' => env('OPENWA_API_KEY'),
    'base_url' => env('OPENWA_BASE_URL', 'http://localhost:2785/api'),
],
```

---

## Common Pitfalls

1. **Don't use `Authorization: Bearer`** — the OpenAPI spec says `bearer`, but it expects the API key in `X-API-Key` header, NOT a JWT. Bearer will fail.
2. **Don't put the key in URL path** — only header or `?apiKey=` query param (the latter is logged in access logs).
3. **Don't share the key across services** — create one per consumer (Laravel, n8n, dashboard, etc.) so you can revoke independently.
4. **CIDR whitelisting requires real client IP** — make sure `X-Forwarded-For` is trusted on OpenWA's side, not just your load balancer.
5. **`allowedSessions` is enforced server-side** — if a key is restricted to `["sess_abc"]`, it cannot send to `sess_xyz`. Returns `403 FORBIDDEN`.
6. **`expiresAt` is checked on every request** — there's no grace period.
7. **Revoked keys are not deleted** — they're flagged `isActive: false`. To remove entirely, DELETE.
8. **The bootstrap endpoint is public** — don't accidentally expose it on a public-facing OpenWA instance. Either remove the dashboard container (`DASHBOARD_ENABLED=false`) or restrict the network.
9. **The master key has no audit trail of its own** — its usage doesn't show up as `api_key_used` in the audit log because it's not in the DB.
10. **Keys are 64 chars hashed, 8 char prefix** — if you see the prefix in logs, that's not enough to authenticate, only to identify the key.
