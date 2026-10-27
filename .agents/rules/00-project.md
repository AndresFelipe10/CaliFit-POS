# Regla 00 — Proyecto y principio fundamental

**Nivel de aplicación:** siempre, en toda interacción con el repositorio.

## Qué es este proyecto
**CaliFit Sistema POS** — Software a la medida para SABORCALIFIT | BOCHALEMA (Arbolatta Mall, Cali, Colombia). Sistema de punto de venta con modelo mixto: tienda de suplementación deportiva y restaurante de alimentación saludable. Motor de recetas por porciones en gramos, adiciones reutilizables con precio y deducción atómica de inventario.

Contexto completo → `docs/PLAN_PROYECTO.md`

## Principio no negociable
```
NÚCLEO PRINCIPAL (Laravel 12 + MySQL 8.4)
        +
MÓDULO OPCIONAL DE IA (Gemini)
```
El núcleo debe funcionar al 100% con la IA desactivada, sin API key, o con el proveedor caído. Nunca implementes ni modifiques el núcleo asumiendo que la IA está disponible.

## Reglas generales permanentes
- Este es un sistema **mono-negocio** exclusivo para SaborCaliFit. No existe multi-tenancy ni `business_id`.
- PROHIBIDO desarrollar más de una fase del roadmap a la vez (`docs/ROADMAP.md`).
- PROHIBIDO continuar a la siguiente fase si la fase actual tiene tests fallando.
- PROHIBIDO introducir tecnologías o paquetes no aprobados sin registrar en `docs/DECISIONES_TECNICAS.md`.
- PROHIBIDO modificar una decisión arquitectónica fundamental sin documentarla como nueva entrada ADR.
- PROHIBIDO subir `.env`, API keys, tokens o cualquier secreto al repositorio.
- OBLIGATORIO analizar el workspace antes de escribir código (no asumir que el repo está vacío).
- OBLIGATORIO ejecutar pruebas, documentar y hacer commit al cierre de cada fase.
- Ante ambigüedad, prioridad: **seguridad > integridad de datos > simplicidad > velocidad**.

## Decisiones fundamentales que NO se eliminan sin razón real y documentada
Laravel, PHP, MySQL, Docker, Nginx, Blade, Bootstrap, mono-negocio exclusivo, motor de recetas en gramos/ml, adiciones reutilizables con precio, deducción atómica de inventario, cero stock negativo, snapshot de precios, dashboard, panel inteligente de inventario, alertas de reposición, módulo IA aislado y desactivable, Gemini como proveedor inicial, IA solo lectura, function calling / structured output, ausencia de SQL arbitrario generado por IA, fallback cuando la IA no esté disponible, capacidad futura de reemplazar Gemini, identidad visual verde fitness (#10B981).
