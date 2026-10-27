# CaliFit Sistema POS — Software a la Medida para SABORCALIFIT | BOCHALEMA

[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Docker Compose](https://img.shields.io/badge/Docker%20Compose-3%20Servicios-2496ED?style=flat-square&logo=docker&logoColor=white)](https://www.docker.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)

**CaliFit Sistema POS** es un software de punto de venta a la medida, diseñado exclusivamente para **SABORCALIFIT | BOCHALEMA**, un negocio con modelo mixto de suplementación deportiva y restaurante de alimentación saludable ubicado en **Arbolatta Mall, Cl. 48 #106-86, Cali, Valle del Cauca, Colombia**.

El sistema gestiona punto de venta, inventario con motor de recetas por porciones en gramos, adiciones reutilizables con precio, salón de mesas, comandas de cocina, despachos a domicilio/para llevar, cuadre de caja adaptativo e impresión térmica de 58 mm.

**Línea de Soporte:** +57 312 770 4455 · **Zona Horaria:** America/Bogota (UTC-5)

---

## Principio Fundamental del Sistema

```
┌─────────────────────────────────────────────────────────┐
│        NÚCLEO PRINCIPAL AUTOSUFICIENTE                  │
│        (Laravel 12 + MySQL 8.4 + Docker)                │
│  Operación comercial, transaccional y de reportes 100%  │
└───────────────────────────┬─────────────────────────────┘
                            │
                            ▼ (Opcional y Desacoplado)
┌─────────────────────────────────────────────────────────┐
│       MÓDULO DE IA EN LENGUAJE NATURAL (Gemini)         │
│  Consultas en lenguaje natural, solo lectura, whitelist │
│  y fallback automático ante indisponibilidad            │
└─────────────────────────────────────────────────────────┘
```

El sistema opera al 100% de su capacidad sin depender de servicios externos de inteligencia artificial.

---

## Características Principales

### 1. Software Mono-Negocio Exclusivo
- Sistema diseñado a la medida para un único negocio (SaborCaliFit). No es un SaaS multi-tenant.
- Panel SuperAdmin (`/superadmin`) como consola centralizada de Gestión de Usuarios, Colaboradores y Matriz RBAC.

### 2. Modelo de Negocio Mixto y Catálogo Unificado
- **Suplementos y Vitaminas:** Venta directa por unidad con descuento de stock unitario.
- **Restaurante / Cafetería Saludable:** Platos y preparaciones compuestas con descuento de insumos via motor de recetas y adiciones.
- Un solo catálogo con tipos de producto: `INSUMO`, `PREPARACION`, `PLATO`, `SUPLEMENTO`.

### 3. Motor de Recetas por Porciones en Gramos
- Unidades base internas: Gramos (`g`) para sólidos, Mililitros (`ml`) para líquidos.
- Factor de conversión: 1 libra = 500 g (regla del negocio).
- Porciones base configurables por plato (carne 200 g, arroz 62.5 g, frijoles 23.81 g, etc.).
- Exclusión mutua frijoles/lentejas en recetas base.

### 4. Adiciones Reutilizables con Precio
- Extras vinculados a platos con su propia receta de insumos (ej. "Carne adicional 120 g").
- Fórmula de Precio: `Total = (Precio Plato × Cant.) + Σ(Precio Adición × Cant. Adición)`
- Fórmula de Consumo: `Insumo = (Cant. × Porción Base) + Σ(Cant. Adición × Consumo Adición)`

### 5. Deducción Atómica de Inventario
- **Cero Stock Negativo:** Toda venta valida disponibilidad bajo `lockForUpdate` en `DB::transaction`.
- Si falta un solo gramo de insumo, la venta completa se rechaza en bloque.
- Anulación simétrica restaura exactamente los insumos consumidos (base + adiciones).
- Precios cobrados congelados en `sale_details` y `venta_adicion` (snapshot inmutable).

### 6. Punto de Venta (POS)
- Selector reactivo de platos con adiciones permitidas (checkbox/contador + precio incremental).
- Carrito con desglose de plato y adiciones seleccionadas.
- Pagos mixtos (hasta 5 líneas: efectivo, tarjeta, Nequi/Daviplata, otro).
- Numeración consecutiva de comprobantes (`FAC-000001`).

### 7. Salón, Despachos e Impresión Térmica
- **Salón:** Mapa táctil de mesas (`LIBRE`, `OCUPADA`, `EN COBRO`) con comandas por tandas.
- **Despacho:** Tablero Kanban para Domicilios y Para Llevar con flete y contraentrega.
- **Cuadre de Caja:** Conciliación por canales (Salón, Domicilios, Para Llevar).
- **Impresión 58 mm:** Comanda cocina (sin precios), tirilla despacho, pre-cuenta y comprobante con propina e INC.

### 8. Control de Acceso (RBAC)
- **SuperAdmin/Dueño:** Control total, gestión de usuarios, configuración.
- **Administrador:** Catálogo, recetas, costos, gastos, reportes financieros.
- **Empleado/Cajero/Mesero:** POS, salón, despachos, clientes, cuadre de caja. Sin acceso a costos ni reportes gerenciales (403).

### 9. Dashboard y Reportes
- KPIs operativos y financieros en tiempo real.
- Métricas financieras protegidas (exclusivas Administrador).
- Reportes de ventas, inventario, ranking de productos con exportación CSV.

### 10. Asistente de IA (Opcional)
- Consultas en lenguaje natural adaptadas a SaborCaliFit.
- Solo lectura, whitelist de intents, fallback controlado.
- Desactivable sin afectar el núcleo.

---

## Stack Tecnológico

| Capa | Tecnología | Justificación |
|---|---|---|
| **Backend** | PHP 8.3 FPM + Laravel 12 | Eloquent ORM, Policies, testing, migraciones |
| **Frontend** | Blade + Bootstrap 5.3 + Vanilla JS | Rápido, responsivo, sin SPA |
| **Base de Datos** | MySQL 8.4 LTS | Transacciones ACID, bloqueo pesimista |
| **Servidor Web** | Nginx Alpine | Proxy inverso con compresión y SSL/TLS |
| **Contenedores** | Docker Compose | Paridad dev/prod, despliegue en 1 comando |
| **IA (Opcional)** | Google Gemini API | Desacoplado mediante `AiProviderInterface` |

---

## Identidad Visual (Paleta Corporativa CaliFit)

| Función | Color | Hex |
|---|---|---|
| **Primario (Verde Fitness)** | Esmeralda | `#10B981` |
| Hover | Verde oscuro | `#059669` |
| Acentos | Verde claro | `#34D399` |
| **Neutro Oscuro** | Carbón / Dark Slate | `#111827` / `#1F2937` |
| **Fondos** | Gris claro / Blanco | `#F9FAFB` / `#FFFFFF` |
| **Estado: Libre/Pagado** | Verde | `#10B981` |
| **Estado: En Cobro/Pendiente** | Amarillo | `#F59E0B` |
| **Estado: Ocupado/Agotado** | Rojo | `#EF4444` |

---

## Puesta en Marcha en Desarrollo

### Prerrequisitos
- [Git](https://git-scm.com/) instalado.
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows/macOS) o Docker Engine + Compose (Linux).

### 1. Clonar el repositorio
```bash
git clone <URL_DEL_REPOSITORIO>
cd "CaliFit Sistema POS"
```

### 2. Configurar el archivo de entorno
```bash
cp .env.example .env
```

### 3. Levantar los contenedores
```bash
docker compose up -d --build
```
Este comando construirá y levantará los 3 servicios:
- `califit_app` (PHP 8.3 FPM)
- `califit_nginx` (Servidor web en http://localhost:8081)
- `califit_db` (MySQL 8.4 en puerto host 3309)

### 4. Inicializar la base de datos
```bash
# Generar clave de cifrado
docker compose exec app php artisan key:generate

# Ejecutar migraciones y poblar datos de demostración
docker compose exec app php artisan migrate --seed
```

### 5. Acceder a la Aplicación
👉 **[http://localhost:8081](http://localhost:8081)**

---

## Credenciales de Demostración

El seeder crea automáticamente datos de demostración para SaborCaliFit con productos (suplementos e insumos), recetas, adiciones, mesas y una venta de ejemplo:

| Rol | Correo Electrónico | Contraseña | Permisos |
|---|---|---|---|
| **SuperAdmin/Dueño** | `admin@saborcalifit.com` | `password123` | Control total (Usuarios, Configuración, Reportes, IA) |
| **Administrador** | `admin@demo.com` | `password123` | Catálogo, recetas, costos, gastos, reportes |
| **Empleado** | `empleado@demo.com` | `password123` | POS, salón, despachos, clientes, cuadre de caja |

---

## Configuración del Módulo de IA (Opcional)

Por defecto, el sistema funciona con el módulo de IA desactivado (`AI_MODULE_ENABLED=false`). Para activarlo:
1. Obtén una clave de API en [Google AI Studio](https://aistudio.google.com/).
2. En `.env`:
   ```ini
   AI_MODULE_ENABLED=true
   AI_PROVIDER=gemini
   GEMINI_API_KEY=tu_clave_real_de_gemini_aqui
   AI_TIMEOUT_SECONDS=10
   ```
3. Reinicia la caché: `docker compose exec app php artisan config:clear`
4. Ingresa al menú **"Asistente IA"** para formular consultas.

---

## Ejecución de Pruebas Automatizadas

```bash
docker compose exec app php artisan test
```

---

## Contenedores Docker

| Servicio | Contenedor Dev | Contenedor Prod | Puerto Host | Función |
|---|---|---|---|---|
| App (PHP-FPM) | `califit_app` | `califit_app_prod` | — | Laravel + PHP 8.3 |
| Nginx | `califit_nginx` | `califit_nginx_prod` | 8081 (dev), 80/443 (prod) | Servidor web |
| MySQL | `califit_db` | `califit_db_prod` | 3309 (dev) | Base de datos |

| Recurso | Dev | Prod |
|---|---|---|
| **Base de datos** | `califit_pos_dev` | `califit_pos_prod` |
| **Usuario MySQL** | `califit_user` | `califit_prod_user` |
| **Volumen MySQL** | `califit_mysql_data` | `califit_mysql_prod_data` |
| **Volumen Storage** | bind mount | `califit_app_storage` |
| **Red** | `califit_network` | `califit_prod_network` |

---

## Despliegue en Producción

1. **Configuración:** [`docker-compose.prod.yml`](docker-compose.prod.yml)
2. **Script de Despliegue:** `bash scripts/deploy.sh`
3. **Respaldos:**
   - Consolidado (BD + adjuntos): `bash scripts/backup.sh`
   - Solo BD: `bash scripts/backup-db.sh`
   - Restauración: `bash scripts/restore-db.sh backups/dump_archivo.sql.gz`

Guía completa → **[docs/ARQUITECTURA.md](docs/ARQUITECTURA.md)**

---

## Índice de Documentación

- **[Plan del Proyecto](docs/PLAN_PROYECTO.md)** — Alcance, modelo de negocio, motor de recetas, módulos y criterios de aceptación.
- **[Arquitectura](docs/ARQUITECTURA.md)** — Infraestructura Docker CaliFit, flujo de venta con recetas y adiciones, estructura de carpetas.
- **[Base de Datos](docs/BASE_DATOS.md)** — Diccionario relacional completo con tablas de recetas, adiciones y reglas de inventario.
- **[Seguridad](docs/SEGURIDAD.md)** — Controles OWASP, integridad de recetas, validación de adiciones, cumplimiento normativo.
- **[Módulo de IA](docs/MODULO_IA.md)** — Intents de SaborCaliFit, arquitectura desacoplada, fallback.
- **[Decisiones Técnicas](docs/DECISIONES_TECNICAS.md)** — ADRs de transición a mono-negocio, motor de recetas en gramos e identidad CaliFit.
- **[Roadmap](docs/ROADMAP.md)** — Fases de desarrollo organizadas por funcionalidad (0-20).
- **[Instrucciones para Agentes de IA](AGENTS.md)** — Punto de entrada, reglas y workflows en `.agents/`.

---

## Licencia

Este proyecto fue desarrollado como software a la medida para SABORCALIFIT | BOCHALEMA. Todos los derechos reservados.
