# MiCMSv0.1

Un CMS moderno construido con **Laravel 13**, **Inertia v3**, **React 19**, **Puck** y **Tailwind 4**. Permite diseñar páginas visualmente, gestionar medios, organizar menús dinámicos, ejecutar tareas programadas y extender el sistema con paquetes modulares.

## ✨ Características

- **Editor visual Puck** — Diseña páginas arrastrando bloques (heading, texto, imagen, columnas, pricing, features, testimonials, video, etc.). Sin tocar código.
- **Gestión de medios** — Subida, búsqueda, edición y organización de imágenes y archivos.
- **Menús dinámicos** — Crea menús por ubicación (header, footer, sidebar) con items anidados y links a páginas internas o URLs externas.
- **Tareas programadas** — Interfaz visual para gestionar cron jobs de Laravel con historial de ejecuciones.
- **Sistema de paquetes** — Activa/desactiva módulos (Chat, CRM, POS) que extienden el CMS. Cada paquete activo aparece como item en el sidebar.
- **Chat Widget** — Botón flotante en el frontend con panel de chat. Los mensajes se persisten y se transmiten en tiempo real via Reverb.
- **Reverb Monitor** — Dashboard de monitoreo de conexiones WebSocket en vivo.
- **Páginas públicas con header + footer** — Layout completo con menú de navegación y copyright dinámico.
- **Modo oscuro** — Toggle global con persistencia en cookie.
- **Autenticación** — Login, registro, recuperación de contraseña, 2FA (Fortify).

## 🧱 Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.5, Laravel 13 |
| Frontend | Inertia v3, React 19, TypeScript |
| Estilos | Tailwind CSS 4 |
| Componentes UI | shadcn/ui (Radix UI + class-variance-authority) |
| Editor visual | Puck (@puckeditor/core) |
| Auth | Laravel Fortify (con 2FA y passkeys) |
| Real-time | Laravel Reverb (WebSocket) |
| Broadcasting | @laravel/echo-react |
| DB | SQLite (default), soporta MySQL/PostgreSQL |
| Cache | database driver (compartido entre procesos) |
| Tests | Pest 4, PHPUnit 12 |
| Formato | Laravel Pint, ESLint, Prettier |

## 📦 Módulos incluidos

| Módulo | Ruta | Descripción |
|---|---|---|
| **Páginas** | `/admin/paginas` | CRUD + diseño visual con Puck |
| **Medios** | `/admin/media` | Biblioteca de imágenes y archivos |
| **Menús** | `/admin/menus` | Menús dinámicos por ubicación |
| **Paquetes** | `/admin/paquetes` | Activar/desactivar módulos del CMS |
| **Tareas Prog.** | `/admin/schedule` | Tareas cron con historial |
| **Socket** | `/admin/reverb` | Monitor de conexiones WebSocket |
| **Configuración** | `/settings/*` | Perfil, seguridad, apariencia, sitio |

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

Usuario por defecto: creado por `UserSeeder` (ver `database/seeders/UserSeeder.php`).

## 💻 Desarrollo

```bash
# Hot-reload frontend
npm run dev

# Hot-reload backend
php artisan serve

# Watch de cambios y compilar
npm run dev

# Linter PHP
vendor/bin/pint

# Linter JS/TS
npm run lint

# Formatear código
npm run format
```

## 🧪 Tests

```bash
# Todos los tests
php artisan test

# Solo un módulo
php artisan test --compact --filter=Package

# Verbose
php artisan test --filter=ChatWidget
```

Hay **159 tests** que cubren:
- Autenticación (Fortify)
- CRUD de Páginas, Medios, Menús
- Paquetes (activar, desactivar, sidebar dinámico)
- Chat Widget (visibilidad, envío, mensajes admin)
- Schedule (CRUD, toggle, history)
- Reverb (monitoreo)

## 📁 Estructura

```
.
├── app/
│   ├── Http/Controllers/
│   │   ├── ChatWidget/        # API pública del chat
│   │   ├── Media/             # CRUD de medios
│   │   ├── Menu/              # CRUD de menús
│   │   ├── Package/           # CRUD de paquetes + mensajes chat
│   │   ├── Page/              # CRUD de páginas Puck
│   │   └── Settings/          # Perfil, seguridad, apariencia, sitio
│   ├── Models/
│   ├── Policies/
│   ├── Services/              # PackageManager, ReverbMonitorService, ScheduleLoaderService
│   └── Events/                # ChatMessageReceived, etc.
├── database/
│   ├── migrations/
│   ├── seeders/               # User, Menu, Package, DefaultLandingPage
│   └── factories/
├── resources/
│   ├── js/
│   │   ├── components/        # shadcn/ui + custom (chat, public)
│   │   ├── layouts/
│   │   ├── pages/             # Inertia pages
│   │   ├── paginas/           # Puck blocks, components, context
│   │   └── routes/            # Wayfinder-generated routes
│   └── css/
├── routes/
│   ├── web.php
│   ├── admin.php
│   ├── settings.php
│   └── channels.php
└── tests/
    └── Feature/               # Tests Pest
```

## 🎨 Puck Blocks

14 bloques disponibles en el editor visual:

- **Layout**: Columns, Grid, Spacer, Divider
- **Tipografía**: Heading, Text
- **Medios**: Image, Video
- **Interactivo**: Button, HTML
- **Bloques**: Pricing, Feature, Testimonials

Cada bloque soporta padding, margin, color de fondo, border-radius, sombra, max-width, ocultar en móvil/desktop y animaciones de entrada.

## 🧩 Paquetes (extensibilidad)

El sistema de paquetes permite activar/desactivar módulos del CMS:

- **Chat Widget** — Botón flotante con chat en vivo
- **CRM** — Gestión de clientes
- **TVP / POS** — Punto de venta

Cada paquete se almacena en la tabla `packages` con su configuración JSON. Al activarlo:
- Aparece como item en el sidebar (raíz, junto a Paquetes/Medios).
- Si tiene frontend (como el chat), se inyecta en la página pública.

## 🌐 Canales Reverb

- `chat-widget.{sessionId}` (público) — Visitante recibe respuestas del admin.
- `chat-widget.admin` (privado) — Admin recibe todos los mensajes nuevos.

## 📜 Licencia

MIT

## 🤝 Contribución

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea tu feature branch (`git checkout -b feature/amazing-feature`)
3. Commit tus cambios (`git commit -m 'Add some amazing feature'`)
4. Push al branch (`git push origin feature/amazing-feature`)
5. Abre un Pull Request

Antes de hacer PR:
- `vendor/bin/pint --dirty --format agent`
- `npm run build`
- `php artisan test --compact`
