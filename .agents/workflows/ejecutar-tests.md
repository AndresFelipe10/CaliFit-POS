# Workflow — Ejecutar pruebas

1. Identificar qué pruebas ejecutar:
   - Cambios en modelos/migraciones → Unit tests relacionados + Feature tests de los módulos que los usan.
   - Cambios en controladores/rutas → Feature tests del módulo afectado + tests de autorización RBAC.
   - Cambios en recetas o adiciones → Tests de deducción atómica de inventario, concurrencia y anulación simétrica.
   - Cambios en `app/AI/` o `app/Services/AI/` → suite completa de tests de IA (ver `.agents/rules/05-testing.md`).
   - Cambios en Policies o Middleware → suite completa de tests de autorización.
2. Ejecutar la suite completa del proyecto, no solo los tests del módulo tocado (para detectar regresiones).
3. Ante un fallo:
   a. Leer el mensaje de error completo antes de modificar nada.
   b. Determinar si el fallo es del código nuevo o de una regresión en código existente.
   c. Corregir la causa raíz, no silenciar o saltar el test.
4. Cuándo detenerse: si tras dos intentos de corrección razonables el test sigue fallando, detener el desarrollo de la fase actual, documentar el bloqueo y reportarlo.
5. No avanzar de fase (`docs/ROADMAP.md`) con tests fallando, sin excepción.
