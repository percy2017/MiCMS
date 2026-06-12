# Mi CMS

## 🚀 Instalación

### Requisitos
- PHP 8.4+
- Composer
- Node.js 20+
- npm 10+
- SQLite (o MySQL/PostgreSQL)
- Nginx (recomendado para producción con WebSockets)
- PM2 o supervisor (para mantener Reverb + queue corriendo)

### Pasos

```bash
# 1. Clonar el repositorio
git clone https://github.com/percy2017/MiCMS.git
cd MiCMS

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
```

### PM2

```bash
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
### Configuración de Nginx
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
## 🔑 Credenciales por defecto

Al ejecutar `php artisan migrate --seed` se crea un usuario administrador con acceso al panel `/admin`:

| Campo    | Valor             |
| -------- | ----------------- |
| Email    | `admin@admin.com` |
| Password | `Admin2026$`      |
| Rol      | `admin`           |

> ⚠️ **Importante:** Cambia estas credenciales inmediatamente en producción desde el panel de administración o ejecutando un nuevo seeder. No las uses en entornos públicos.

## 📜 Licencia

MIT