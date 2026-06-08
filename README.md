# Mi CMS 

## 🚀 Instalación

### Requisitos
- PHP 8.5+
- Composer
- Node.js 20+
- npm 10+
- SQLite (o MySQL/PostgreSQL)

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

# 8. (Opcional) Iniciar Reverb para WebSockets
php artisan reverb:start
```

Accede a:
- **Frontend público**: http://localhost:8000
- **Admin**: http://localhost:8000/admin
- **Reverb (WebSocket)**: ws://localhost:8080

## 📜 Licencia

MIT