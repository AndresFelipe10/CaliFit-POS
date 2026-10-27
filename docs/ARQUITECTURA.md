# Arquitectura — CaliFit Sistema POS

## Vista general
```
NÚCLEO PRINCIPAL (Laravel 12 + MySQL 8.4)
        +
MÓDULO OPCIONAL DE IA (Gemini)
```
El núcleo es autosuficiente; el módulo IA es un añadido desactivable. Ver reglas de aislamiento en `.agents/rules/04-ai.md`.

## Stack
- Backend: PHP 8.3+, Laravel 12 (MVC, Eloquent, Migrations, Seeders, Factories, Form Requests, Middleware, Policies).
- Frontend: Blade + Bootstrap 5 + JS vanilla.
- BD: MySQL 8.4.
- Infraestructura: Docker Compose (`califit_app`, `califit_nginx`, `califit_db`).

## Modelo Mono-Negocio
Este sistema opera como software a la medida para un único negocio (SaborCaliFit). No existe multi-tenancy, creación de múltiples empresas ni planes de suscripción. La instancia completa de base de datos y aplicación pertenece exclusivamente a SaborCaliFit.

## Identidad Visual (Paleta Corporativa CaliFit)
- **Primario (Verde Fitness / Esmeralda):** `#10B981` (Hover: `#059669`, Acentos: `#34D399`).
- **Neutro Oscuro (Carbón / Dark Slate):** `#111827` / `#1F2937` (Sidebar, Navbar, títulos).
- **Fondos y Superficies:** `#F9FAFB` y `#FFFFFF`.
- **Estados Operativos:**
  - Verde (`#10B981`): Libre / Pagado / Stock Normal.
  - Amarillo (`#F59E0B`): En Cobro / Bajo Stock / Pendiente.
  - Rojo (`#EF4444`): Ocupado / Agotado / Cancelado / Vencida.

---

## Despliegue y Operaciones

### Arquitectura de Despliegue

```
DESARROLLO (docker-compose.yml):
Host / Dev Machine
└── Docker Compose (puerto 8081 host → 80 nginx, 3309 host → 3306 db)
    ├── califit_app (PHP-FPM 8.3 + Laravel, bind mount de código fuente para recarga en vivo)
    ├── califit_nginx (Proxy web + servidor de estáticos, bind mount)
    └── califit_db (MySQL 8.4, volumen persistente califit_mysql_data, expuesto en puerto 3309)

PRODUCCIÓN (docker-compose.prod.yml):
Internet → DNS / Dominio → Router / Firewall (Puertos 80, 443)
    ↓
Nginx Container (califit_nginx_prod)
    ├── Terminación SSL / TLS (Certbot Let's Encrypt o Cloudflare Origin)
    ├── Compresión Gzip + Caché perimetral de estáticos (30 días)
    ├── Cabeceras de seguridad HTTP (X-Frame-Options, X-Content-Type-Options, etc.)
    └── FastCGI Pass (app:9000)
    ↓
PHP-FPM Container (califit_app_prod)
    ├── Imagen inmutable con código y dependencias empacadas (Dockerfile.prod)
    ├── Composer optimizado (--no-dev --optimize-autoloader)
    ├── Zend OPcache activo y pre-compilado en memoria (validate_timestamps=0)
    ├── Caché de Laravel compilada (config, routes, views)
    └── Conexión de red interna a MySQL (sin exposición de puertos a Internet)
    ↓
MySQL Container (califit_db_prod)
    ├── MySQL 8.4 Server
    ├── Volumen local persistente: califit_mysql_prod_data
    └── Backups automatizados diarios vía scripts/backup-db.sh
```

---

### Guía de Despliegue Reproducible en Producción

#### 1. Requisitos Mínimos del Servidor / VPS
- **Sistema Operativo:** Ubuntu 22.04 LTS o 24.04 LTS.
- **Recursos de Hardware:**
  - CPU: 1 vCPU mínimo (2 vCPU recomendado).
  - RAM: 1.5 GB mínimo (2 GB recomendado; con 1 GB habilitar 2 GB de SWAP).
  - Disco: 20 GB SSD.
- **Software Base:** Docker Engine (v24.0+), Docker Compose Plugin (v2.20+), Git, Curl, Bash.

#### 2. Preparación de Secretos y Variables de Entorno
```bash
cd /var/www/califit-pos
cp .env.example .env
```

Configurar en `.env`:
```ini
APP_NAME="CaliFit Sistema POS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio-califit.com
APP_TIMEZONE=America/Bogota
APP_LOCALE=es
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=califit_pos_prod
DB_USERNAME=califit_prod_user
DB_PASSWORD=GENERAR_PASSWORD_MUY_SEGURO_AQUI
DB_ROOT_PASSWORD=GENERAR_ROOT_PASSWORD_MUY_SEGURO_AQUI

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

AI_MODULE_ENABLED=false
AI_PROVIDER=gemini
GEMINI_API_KEY=tu_api_key_de_produccion_aqui
AI_TIMEOUT_SECONDS=10

SUPPORT_WHATSAPP=573127704455

PROD_PORT=80
PROD_SSL_PORT=443
BACKUP_RETENTION_DAYS=7
```

#### 3. Despliegue Inicial Paso a Paso

1. **Clonar el repositorio:**
   ```bash
   git clone <URL_DEL_REPOSITORIO> /var/www/califit-pos
   cd /var/www/califit-pos
   ```

2. **Crear y configurar `.env`:**
   ```bash
   cp .env.example .env
   nano .env
   ```

3. **Construir y levantar contenedores:**
   ```bash
   docker compose -f docker-compose.prod.yml up -d --build
   ```

4. **Generar clave de cifrado:**
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan key:generate --force
   ```

5. **Ejecutar migraciones:**
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
   ```

6. **Sembrar datos maestros y credenciales iniciales del Administrador / Dueño:**
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
   ```

7. **Optimizar Laravel para producción:**
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan config:cache
   docker compose -f docker-compose.prod.yml exec app php artisan route:cache
   docker compose -f docker-compose.prod.yml exec app php artisan view:cache
   ```

8. **Ajustar permisos:**
   ```bash
   docker compose -f docker-compose.prod.yml exec app chown -R www-data:www-data storage bootstrap/cache
   docker compose -f docker-compose.prod.yml exec app chmod -R 775 storage bootstrap/cache
   ```

#### 4. Certificados SSL/HTTPS
Misma estrategia que la arquitectura anterior (Let's Encrypt / Certbot o Cloudflare SSL Full/Strict), con rutas actualizadas a `/var/www/califit-pos`.

#### 5. Copias de Seguridad y Recuperación
- **Respaldo consolidado:** `bash scripts/backup.sh`
- **Respaldo de BD:** `bash scripts/backup-db.sh`
- **Restauración:** `bash scripts/restore-db.sh backups/dump_califit_pos_prod_YYYYMMDD.sql.gz`
- **Cron diario (2:00 AM):** `0 2 * * * /bin/bash /var/www/califit-pos/scripts/backup-db.sh >> /var/log/califit_backup.log 2>&1`

#### 6. Actualización y Despliegue Continuo
```bash
bash scripts/deploy.sh
```

#### 7. Monitoreo y Logs
```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f app
docker compose -f docker-compose.prod.yml logs -f nginx
```

---

## Flujo de Venta con Motor de Recetas y Adiciones

```
Request POS (Controller)
  → Form Request (validación de plato, adiciones, cantidades)
  → Policy (autorización por rol)
  → DB::transaction con lockForUpdate
     → Resolver precio unitario del plato desde BD
     → Resolver precios unitarios de adiciones desde BD
     → Consultar receta del plato → obtener ingredientes y cantidades por porción
     → Consultar recetas de cada adición → obtener ingredientes y cantidades
     → Verificar stock suficiente para TODOS los ingredientes (base + adiciones)
     → Si stock insuficiente → RECHAZAR venta en bloque
     → Descontar insumos del plato base (cantidad_vendida × cantidad_por_porcion)
     → Descontar insumos de cada adición (cantidad_adicion × consumo_adicion)
     → Registrar inventory_movements con tipo SALIDA_VENTA
     → Crear sale, sale_details, venta_adicion (snapshots inmutables de precio)
     → Crear sale_payments (pagos mixtos)
  → Respuesta al usuario
```

### Anulación Simétrica
```
Anulación (Controller)
  → Policy (solo Administrador)
  → DB::transaction
     → Leer sale_details → restaurar stock de platos (receta base)
     → Leer venta_adicion → restaurar stock de insumos de adiciones
     → Registrar inventory_movements con tipo DEVOLUCION_ANULACION
     → Marcar venta como anulada
```

---

## Estructura de Carpetas (Objetivo)

```
app/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Middleware/
├── Models/
├── Policies/
├── Services/
│   ├── Sales/           (SaleService con deducción atómica de recetas + adiciones)
│   ├── Inventory/
│   ├── Recipe/          (Motor de recetas y conversión de unidades)
│   └── AI/
└── AI/
    ├── Contracts/        (AiProviderInterface)
    ├── Providers/        (GeminiProvider)
    ├── DTOs/
    ├── Tools/            (un servicio por intent permitido)
    └── Exceptions/
database/
resources/
├── views/
│   ├── layouts/          (app.blade.php, sidebar.blade.php, auth.blade.php)
│   ├── auth/
│   ├── categories/
│   ├── customers/
│   ├── dashboard.blade.php
│   ├── errors/
│   ├── inventory/
│   ├── products/         (catálogo unificado: suplementos + insumos + platos)
│   ├── recipes/          (gestión de recetas y adiciones)
│   ├── reports/
│   ├── restaurant/       (salón, mesas, comandas, despachos)
│   ├── sales/
│   ├── suppliers/
│   └── users/
routes/
tests/
config/
public/
storage/
docs/
.agents/
```

## Responsabilidades por capa
- **Controllers**: delgados, orquestan Form Request + Service + respuesta.
- **Form Requests**: validación de entrada incluyendo adiciones con sus cantidades y precios.
- **Services**: lógica de negocio no trivial (ventas con recetas, inventario, conversión de unidades, IA).
- **Middleware**: seguridad HTTP (`SecurityHeadersMiddleware`), control de roles (`CheckRole`), verificación de tipo de negocio (`EnsureBusinessIsRestaurant`).
- **Policies**: autorización fina por recurso y rol.
- **Models**: relaciones Eloquent, casts de precisión decimal.

## Flujo de información — módulo IA
Ver `docs/MODULO_IA.md` para el flujo completo con validación de intents.
