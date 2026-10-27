# Workflow — Implementar una feature

Proceso a ejecutar cada vez que se implementa una funcionalidad nueva o se modifica una existente en CaliFit Sistema POS.

1. Consultar `.agents/rules/` relevantes a la feature (mínimo `00-project.md` y `01-architecture.md`; agregar `02-database.md`, `03-security.md` o `04-ai.md` según corresponda).
2. Consultar la documentación relevante en `docs/` (`PLAN_PROYECTO.md` para alcance, `ARQUITECTURA.md` para estructura, `BASE_DATOS.md` si toca datos o recetas/adiciones, `MODULO_IA.md` si toca el asistente).
3. Inspeccionar el código existente relacionado antes de escribir nada nuevo (no asumir que no existe).
4. Comprender las dependencias: modelos, Policies, Services, rutas involucradas.
5. Si la feature involucra recetas o adiciones, verificar coherencia con las tablas de `docs/BASE_DATOS.md` y las fórmulas de consumo/precio en `docs/PLAN_PROYECTO.md`.
6. Implementar la funcionalidad siguiendo las reglas de arquitectura y seguridad aplicables.
7. Ejecutar la suite de pruebas relevante (ver `.agents/workflows/ejecutar-tests.md`).
8. Revisar seguridad: autorización, validación, deducción atómica de inventario si aplica (ver `.agents/rules/03-security.md`).
9. Actualizar la documentación afectada en `docs/` si la feature cambia comportamiento documentado.
10. Verificar contra el criterio de aceptación de la fase correspondiente en `docs/ROADMAP.md`.
11. Preparar un resumen breve de los cambios antes de hacer commit.
