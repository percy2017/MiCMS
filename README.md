# Mi CMS

## 🚀 Instalación

### Requisitos
- PHP 8.5+
- Composer
- Node.js 20+
- npm 10+
- SQLite (o MySQL/PostgreSQL)
- Nginx (recomendado para producción con WebSockets)
- PM2 o supervisor (para mantener Reverb + queue corriendo)

### Pasos

```bash
# 1. Clonar el repositorio
git clone <repo-url> cms
cd cms

# 2. Instalar dependencias PHP
composer install

# 3. Instalar dependencias Node
npm install

# 4. Copiar y configurar .env
cp .env.example .env
php artisan key:generate

# 5. Crear base de datos y sembrar
touch database/database.sqlite
php artisan migrate --seed

# 6. Compilar assets
npm run build

# 7. Iniciar el servidor
php artisan serve

# 8. Iniciar Reverb para WebSockets (en otra terminal)
php artisan reverb:start

# 9. Iniciar queue worker (en otra terminal)
php artisan queue:work --tries=3 --timeout=60
```

Accede a:
- **Frontend público**: http://localhost:8000
- **Admin**: http://localhost:8000/admin
- **Reverb (WebSocket)**: ws://localhost:8080

## 💬 Módulo ChatBot — URLs y comportamiento

El módulo de ChatBot expone las siguientes URLs (vía Wayfinder desde React):

| URL | Método | Acción |
|-----|--------|--------|
| `/admin/chats` | GET | Lista de conversaciones. Soporta query params: `?channel_id=X`, `?search=texto`, `?active=Y` |
| `/admin/chats/{conversation}` | GET | Deep link: lista + chat abierto. Mismo handler que el index (acepta `{conversation}` como param de ruta) |
| `/admin/chats/{conversation}/reply` | POST | Enviar mensaje de admin (texto + opcional file) |
| `/admin/chats/{conversation}/close` | POST | Cerrar conversación |
| `/admin/chats/{conversation}/reopen` | POST | Reabrir conversación |
| `/admin/chats/{conversation}/read` | POST | Marcar como leído |
| `/admin/chats/{conversation}` | DELETE | Eliminar conversación + mensajes + media (cascade) |
| `/api/webhooks/evolution/{channel}` | POST | Webhook entrante de Evolution API |
| `/admin/reverb` | GET | Monitor de conexiones y eventos Reverb (polling HTTP cada 2s) |

### URLs de chat en el frontend

El componente `Modules/ChatBot/resources/js/Pages/Chats/Index.tsx` maneja la navegación así:

- **Listado**: estado local + `router.reload({ only: ['conversations', 'stats'] })` para refrescar al recibir eventos Echo
- **Click en una conversación** del sidebar: `router.visit('/admin/chats/' + id)` → cambia la URL a `/admin/chats/{id}` y carga el chat abierto via SSR
- **Realtime**: `useEcho('chatbot.admin', 'ChatBotMessageReceived', handler)` suscribe al channel y dispara `router.reload({ only: ['conversations', 'stats'] })` cuando llega un mensaje nuevo
- **Filtros**: `router.get('/admin/chats', { channel_id, search }, ...)` con query params

### Mantener procesos en producción con PM2

```bash
# Instalar PM2 globalmente
npm install -g pm2

# Iniciar Reverb
pm2 start ecosystem.config.cjs

# Ver procesos
pm2 list

# Ver logs en vivo
pm2 logs reverb
pm2 logs queue

# Reiniciar
pm2 restart all

# Detener
pm2 stop all
```

## 🔌 Configuración de WebSockets (Reverb) en producción

Para que el realtime (mensajes en vivo, monitor de sockets) funcione desde un dominio público (HTTPS), el navegador debe poder conectarse a Reverb vía `wss://tudominio/app/...`. Reverb escucha en `localhost:2003` (server-side), pero el cliente JS necesita llegar al WebSocket.

### Variables de entorno requeridas

```env
# .env (producción)

# === Reverb: configuración para el CLIENTE (browser) ===
# El bundle del frontend usa esto para construir la URL wss://tudominio.com/app/...
# Debe apuntar al dominio público por el que nginx hace proxy
REVERB_HOST="tudominio.com"
REVERB_PORT=443
REVERB_SCHEME=https

# === Reverb: puerto server-side (donde escucha el proceso reverb) ===
# Reverb binds aquí, NO en el puerto público
REVERB_SERVER_HOST="0.0.0.0"
REVERB_SERVER_PORT=2003

# === Reverb: configuración para el BROADCASTER (backend HTTP) ===
# Laravel emite eventos via HTTP POST al server Reverb. DEBE apuntar
# directamente a 127.0.0.1:2003 (no al proxy público), porque nginx
# no tiene endpoint para /apps/.../events
REVERB_BROADCASTER_HOST="127.0.0.1"
REVERB_BROADCASTER_PORT=2003
REVERB_BROADCASTER_SCHEME=http

# === Variables compiladas al bundle del frontend (Vite) ===
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# Driver de broadcast (DEBE ser "reverb", no "log" ni "null")
BROADCAST_CONNECTION=reverb
```

Después de cambiar `.env`:
```bash
# 1. Recompilar el bundle del frontend para que VITE_REVERB_* se incluyan
npm run build

# 2. Reiniciar Reverb y queue para que recarguen las env vars
pm2 restart all --update-env
```

### Configuración de Nginx (proxy WSS para Reverb)

> **⚠️ IMPORTANTE (HTTP/2 + WebSocket bug):** nginx tiene un bug con el upgrade de WebSocket HTTP/2 → HTTP/1.1 al upstream: devuelve `500 Internal Server Error` sin log. La solución más simple y probada es **desactivar HTTP/2 globalmente** (comentar `http2 on;` en `/etc/nginx/conf.d/http2-directive.conf`).

#### Paso 1 — Desactivar HTTP/2 en nginx

Editar `/etc/nginx/conf.d/http2-directive.conf` y comentar la línea:

```nginx
# http2 on;   ← comentado
```

#### Paso 2 — Agregar el `location /app` en el `server { }` de tu dominio

En Hestia: editar `/home/hostbol/conf/web/tudominio.com/nginx.conf_custom` o crear un include como `/home/hostbol/conf/web/tudominio.com/nginx.reverb.conf` y referenciarlo desde `nginx.conf` / `nginx.ssl.conf`. Contenido:

```nginx
# Proxy WebSocket para Laravel Reverb
# (Browser se conecta a wss://tudominio.com/app/<key> y nginx lo pasa a Reverb en localhost:2003)
location /app {
    proxy_pass http://127.0.0.1:2003;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    # WebSocket: no buffering, timeout largo
    proxy_buffering off;
    proxy_read_timeout 86400;
    proxy_send_timeout 86400;
}
```

El include va **dentro del bloque `server { ... }` de tu dominio** (el mismo que sirve el sitio). Ejemplo para Hestia en `nginx.ssl.conf` y `nginx.conf`:

```nginx
include /home/hostbol/conf/web/tudominio.com/nginx.reverb.conf;
```

#### ¿Por qué desactivar HTTP/2?

| Cliente | Sin HTTP/2 (`Connection: close` negociado) | Resultado |
|---------|--------------------------------------------|-----------|
| HTTP/1.1 + TLS + WebSocket | upgrade pasa tal cual al upstream HTTP/1.1 | ✅ 101 Switching Protocols |
| HTTP/2 + WebSocket | nginx re-baja a HTTP/1.1 (header `Connection` prohibido en HTTP/2) | ❌ 500 (bug nginx) |

Desactivar HTTP/2 fuerza al browser a negociar HTTP/1.1, donde el upgrade funciona perfectamente. El **tradeoff** es que las páginas del sitio cargan un poco más lento (sin multiplexing de HTTP/2), pero el WebSocket realtime funciona.

Si más adelante necesitas HTTP/2 para performance del sitio, la solución alternativa es usar el patrón con `map`:

```nginx
# En contexto http {} (no server {})
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

# En location /app dentro de server {}
proxy_set_header Connection $connection_upgrade;  # variable, NO literal
```

#### Después de modificar nginx

```bash
nginx -t                  # validar configuración (NO debe haber errores)
systemctl reload nginx    # aplicar
```

#### Verificar que funciona (test manual)

```bash
curl -i -s --max-time 5 \
  -H "Connection: Upgrade" \
  -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Version: 13" \
  -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
  "https://tudominio.com/app/TU_REVERB_APP_KEY?protocol=7&client=js&version=8.4.0&flash=false" | head -5
```

Debe responder `HTTP/1.1 101 Switching Protocols` con `X-Powered-By: Laravel Reverb`.

### Verificar que funciona

1. **Abrir `/admin/reverb`** en el navegador → debería mostrar "0 Conexiones"
2. **Abrir otra tab en `/admin/chats`** → la tab del monitor debería mostrar **"1 Conexión Activa"** (porque la tab de chats está suscrita al channel)
3. **Mandar un webhook de prueba**:
   ```bash
   curl -X POST https://tudominio.com/api/webhooks/evolution/1 \
     -H "Content-Type: application/json" \
     -d '{
       "event": "messages.upsert",
       "instance": "test",
       "data": {
         "key": {"remoteJid": "59199999999@s.whatsapp.net", "fromMe": false, "id": "TEST-1"},
         "pushName": "Tester",
         "message": {"conversation": "Hola en vivo"},
         "messageType": "conversation"
       }
     }'
   ```
4. **La tab de chats debería mostrar el nuevo chat en tiempo real** (sin recargar)

### Troubleshooting

| Síntoma | Causa probable |
|---------|---------------|
| Monitor muestra "0 Conexiones" | El bundle del frontend usa `localhost:2003` (mismatch con `VITE_REVERB_HOST`). Recompilar con `npm run build` |
| Browser muestra error WebSocket failed | Nginx no tiene el `location /app` configurado, o el firewall bloquea el 2003 al exterior |
| `curl https://...` da 500 con upgrade headers, pero `curl --http1.1 https://...` da 101 | Bug nginx HTTP/2 → HTTP/1.1 upgrade. Usar patrón con `map` para `$connection_upgrade` (ver sección arriba) |
| Los chats nuevos solo aparecen al recargar la página | El WebSocket NO está conectado. Misma causa que el síntoma anterior |
| `Failed to listen on 0.0.0.0:2003` | Otro proceso usa el puerto 2003. Verificar con `ss -tlnp \| grep 2003` |
| Mensajes NO se emiten a Reverb | `BROADCAST_CONNECTION=log` en `.env` (debe ser `reverb`) |
| Eventos llegan al monitor pero no a la tab | El bundle del frontend está cacheado. Ctrl+Shift+R |

## 📜 Licencia

MIT