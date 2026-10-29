# CaliFit Sistema POS — Software a la Medida para SABORCALIFIT | BOCHALEMA

[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Docker Compose](https://img.shields.io/badge/Docker%20Compose-3%20Servicios-2496ED?style=flat-square&logo=docker&logoColor=white)](https://www.docker.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)

**CaliFit Sistema POS** es un software de punto de venta a la medida, diseñado exclusivamente para **SABORCALIFIT | BOCHALEMA**, un negocio con modelo mixto de suplementación deportiva y restaurante de alimentación saludable ubicado en **Arbolatta Mall, Cl. 48 #106-86, Cali, Valle del Cauca, Colombia**.

El sistema gestiona punto de venta, inventario con motor de recetas por porciones en gramos, adiciones reutilizables con precio, salón de mesas, comandas de cocina y barra, despachos a domicilio y para llevar, cartera de fiados, cuadre de caja diario adaptativo e impresión térmica calibrada a 80 mm.

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
- Sistema diseñado a la medida para un único local físico (SaborCaliFit Bochalema). No es un SaaS multi-tenant.
- Arquitectura RBAC bipartita nativa (`admin`, `employee`). Gestión integral de colaboradores en `/users` protegida por rol Administrador.

### 2. Modelo de Negocio Mixto y Catálogo Unificado
- **Suplementos y Vitaminas:** Venta directa por unidad con descuento de stock unitario.
- **Restaurante / Cafetería Saludable:** Platos y preparaciones compuestas con descuento de insumos vía motor de recetas y adiciones.
- Un solo catálogo con tipos de producto: `INSUMO`, `PREPARACION`, `PLATO`, `SUPLEMENTO`.

### 3. Motor de Recetas por Porciones en Gramos
- Unidades base internas: Gramos (`g`) para sólidos, Mililitros (`ml`) para líquidos.
- Factor de conversión: 1 libra = 500 g (regla del negocio).
- Porciones base configurables por plato (carne 200 g, arroz 62.5 g, frijoles 23.81 g, etc.).
- Exclusión mutua frijoles/lentejas en recetas base.

### 4. Adiciones Reutilizables con Precio
- Extras vinculados a platos con su propia receta de insumos (ej. "Carne adicional 120 g").
- Fórmula de Precio: `Total = (Precio Plato × Cant.) + Σ(Precio Adición × Cant. Adición)`.
- Fórmula de Consumo: `Insumo = (Cant. × Porción Base) + Σ(Cant. Adición × Consumo Adición)`.

### 5. Deducción Atómica de Inventario
- **Cero Stock Negativo:** Toda venta valida disponibilidad bajo `lockForUpdate` en `DB::transaction`.
- Si falta un solo gramo de insumo, la venta completa se rechaza en bloque.
- Anulación simétrica restaura exactamente los insumos consumidos (base + adiciones) con tipo `DEVOLUCION_ANULACION`.
- Precios cobrados congelados en `sale_details` y `venta_adicion` (snapshot inmutable).

### 6. Punto de Venta (POS) y Despachos
- Selector reactivo de platos con adiciones permitidas, escalado matemático de porciones y modal rápido de clientes AJAX.
- Carrito con desglose de plato y adiciones seleccionadas.
- Pagos mixtos (hasta 5 líneas: efectivo, tarjeta, Nequi/Daviplata, crédito/cartera).
- Módulo de Despachos (Para Llevar / Domicilios) con tablero Kanban, asignación de flete y comprobantes de contraentrega.
- Numeración consecutiva de comprobantes (`FAC-000001`).

### 7. Salón, Comandas e Impresión Térmica de 80 mm
- **Salón:** Mapa táctil e interactivo de mesas (`LIBRE`, `OCUPADA`, `EN COBRO`) con apertura inmediata y división de cuentas (`split-bill`).
- **Comandas:** Centro de comandas (`/restaurant/orders`) por pestañas y estados con auditoría de cancelaciones y trazabilidad total.
- **Impresión Térmica 80 mm:** Comanda cocina con tipografía de 18.5px bold (sin precios), tirilla de despacho, pre-cuenta con propina sugerida (10% Ley 1935/2018), factura con INC (8% Art. 512-1 E.T.), arqueo de caja y contraste térmico puro `#000000` sobre `#FFFFFF`.

### 8. Control de Acceso (RBAC)
- **Administrador 1 (Dueño):** Control total del negocio, gestión del equipo (`/users`), catálogo, costos, compras/gastos, arqueos, reportes gerenciales y asistente IA.
- **Administrador 2 (Gerente):** Control administrativo completo de la operación, catálogo y finanzas.
- **Mesero / Cajero (`employee`):** Operación de POS Mostrador, Salón de Mesas, Comandas, Despacho, Clientes y Arqueo de turno. Sin acceso a costos, gastos ni métricas financieras (403).

### 9. Cartera / Fiados y Gastos Operativos
- **Cartera:** Créditos autorizados a clientes de confianza, control de abonos parciales o totales y exclusión autoritativa de deudas canceladas.
- **Gastos:** Registro clasificado de compras/gastos (Efectivo Caja Chica, Transferencia/Banco, Crédito Proveedor), deducible de efectivo en cuadre de caja con adjuntos seguros (`nosniff`).

### 10. Cuadre de Caja Diario Adaptativo
- Arqueo de caja por turnos de cajero con consolidación multicanal (Salón, Domicilios, Mostrador).
- Conciliación de efectivo físico vs pagos digitales (Nequi, Daviplata, Tarjetas, Crédito), deducción de gastos de caja chica, cálculo de sobrante/faltante, justificación de descuadres y reporte térmico de 80 mm.

### 11. Dashboard Gerencial y Reportes
- KPIs operativos y financieros en tiempo real con segregación de privilegios (enmascaramiento de dinero a empleados).
- Reportes exportables de ventas, inventario, balance contable y ranking de productos fit.

### 12. Asistente de IA (Opcional)
- Consultas en lenguaje natural optimizadas para SaborCaliFit mediante Gemini 2.5 Flash / Flash Lite con memoria persistente en BD.
- Solo lectura, whitelist estricta de intents, respuestas 100% en español colombiano y fallback controlado offline.

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

## Credenciales Maestras del Sistema

El seeder oficial (`DatabaseSeeder.php`) aprovisiona automáticamente la matriz de usuarios para **SaborCaliFit Bochalema**:

| Rol | Correo Electrónico | Contraseña | Permisos y Alcance |
|---|---|---|---|
| **Administrador 1 (Dueño)** | `admin@saborcalifit.com` | `Admin1234#` | Control total del negocio, gestión del equipo (`/users`), configuración, compras/gastos, arqueos, reportes gerenciales e IA |
| **Administrador 2 (Gerente)** | `gerente@saborcalifit.com` | `Admin1234#` | Control administrativo completo de la operación diaria, catálogo, recetas, compras y reportes |
| **Mesero (Operación)** | `mesero@saborcalifit.com` | `Admin1234#` | Operación de mostrador POS, salón de mesas, comandas, despachos a domicilio, clientes y arqueo de turno |

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
4. Ingresa al menú **"Asistente IA"** para formular consultas financieras u operativas en lenguaje natural.

---

## Ejecución de Pruebas Automatizadas

La totalidad del sistema cuenta con certificación continua mediante PHPUnit y Laravel Test Runner:

```bash
docker compose exec app php artisan test
```

> **Certificación:** **169 tests passing (1.340 assertions)** con 100% de éxito, 0 fallos y 0 regresiones.

---

## Contenedores Docker

| Servicio | Contenedor Dev | Contenedor Prod | Puerto Host | Función |
|---|---|---|---|---|
| App (PHP-FPM) | `califit_app` | `califit_app_prod` | — | Laravel 12 + PHP 8.3 FPM |
| Nginx | `califit_nginx` | `califit_nginx_prod` | 8081 (dev), 80/443 (prod) | Servidor web y reverse proxy |
| MySQL | `califit_db` | `califit_db_prod` | 3309 (dev) | Base de datos MySQL 8.4 LTS |

| Recurso | Dev | Prod |
|---|---|---|
| **Base de datos** | `califit_pos_dev` | `califit_pos_prod` |
| **Usuario MySQL** | `califit_user` | `califit_prod_user` |
| **Volumen MySQL** | `califit_mysql_data` | `califit_mysql_prod_data` |
| **Volumen Storage** | bind mount | `califit_app_storage` |
| **Red** | `califit_network` | `califit_prod_network` |

---

## Despliegue en Producción y Operaciones

El proyecto incluye scripts en Bash totalmente automatizados, auditados y con permisos de ejecución (`chmod +x`):

1. **Configuración de Producción:** [`docker-compose.prod.yml`](docker-compose.prod.yml)
2. **Despliegue Inmutable en 1 Comando:**
   ```bash
   bash scripts/deploy.sh
   ```
   *(Valida el entorno, levanta contenedores de producción, corre migraciones pendientes, optimiza cachés de rutas/vistas/config y reinicia workers).*
3. **Copias de Seguridad (Backups):**
   - **Respaldo Completo (Base de Datos + Archivos de Storage):**
     ```bash
     bash scripts/backup.sh
     ```
   - **Respaldo Rápido de Base de Datos MySQL:**
     ```bash
     bash scripts/backup-db.sh
     ```
   - **Restauración Atómica de Base de Datos:**
     ```bash
     bash scripts/restore-db.sh backups/db_califit_pos_prod_YYYYMMDD_HHMMSS.sql.gz
     ```

Guía completa de arquitectura → **[docs/ARQUITECTURA.md](docs/ARQUITECTURA.md)**

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
