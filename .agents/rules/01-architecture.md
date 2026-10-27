# Regla 01 — Arquitectura y stack

**Nivel de aplicación:** siempre que se cree o modifique código.

## Stack fijo (no sustituir sin justificación en `docs/DECISIONES_TECNICAS.md`)
- Backend: PHP 8.3+, Laravel 12, MVC, Eloquent, Migrations, Seeders, Factories, Form Requests, Middleware, Policies, Auth de sesión nativo.
- Frontend: Blade, HTML5, CSS3, JS vanilla, Bootstrap 5. **Prohibido** React/Vue/Angular en esta etapa.
- BD: MySQL 8.4.
- Infraestructura: Docker Compose con exactamente 3 servicios (`califit_app`, `califit_nginx`, `califit_db`). No agregar Redis, colas o phpMyAdmin sin necesidad real documentada.

Arquitectura detallada, capas y estructura de carpetas → `docs/ARQUITECTURA.md`

## Reglas accionables
- Controladores delgados; lógica de negocio en Services solo cuando aporte valor real.
- El motor de recetas y deducción de inventario vive en `app/Services/Recipe/` y `app/Services/Sales/`.
- El módulo IA vive físicamente aislado en `app/AI/` y `app/Services/AI/`, nunca mezclado con controladores del núcleo.
- Prohibida la lógica de negocio en archivos Blade.
- Cada dependencia nueva (Composer/NPM) requiere justificación en `docs/DECISIONES_TECNICAS.md`.
- No agregar microservicios, CQRS, event sourcing, Kubernetes, Kafka, múltiples bases de datos o múltiples proveedores de IA simultáneos. Sobreingeniería prohibida.

## Identidad visual
- **Paleta CaliFit:** Verde Fitness `#10B981`, Neutro Oscuro `#111827`, Fondos `#F9FAFB`.
- Bootstrap 5 con variables CSS customizadas para la paleta corporativa.
- Tipografía moderna (Inter o Plus Jakarta Sans via Google Fonts).
