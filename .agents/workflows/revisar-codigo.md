# Workflow — Revisar código

Revisión a aplicar antes de cerrar cualquier fase o feature significativa en CaliFit Sistema POS.

1. **Arquitectura**: ¿respeta la estructura de capas de `docs/ARQUITECTURA.md`? ¿el módulo IA sigue aislado en `app/AI/`? ¿el motor de recetas vive en `app/Services/Recipe/`?
2. **Clean Code**: nombres descriptivos, métodos con responsabilidad única, sin duplicación evidente, sin magic numbers, bajo acoplamiento.
3. **Seguridad**: checklist de `.agents/rules/03-security.md` — validación de adiciones, autorización RBAC, deducción atómica, mass assignment, XSS, CSRF.
4. **Motor de Recetas**: ¿las cantidades por porción son positivas y coherentes con la unidad base? ¿los precios de adiciones se congelan en snapshots? ¿la anulación es simétrica?
5. **Testing**: ¿existen tests para la lógica nueva? ¿cubren el caso feliz y al menos un caso de rechazo/autorización?
6. **Consistencia**: ¿el código sigue las convenciones ya establecidas en el resto del proyecto (nombres de rutas, estructura de Services, formato de respuestas)?
7. **Documentación**: ¿algún documento de `docs/` quedó desactualizado por este cambio?

Si cualquiera de estos puntos falla, corregir antes de hacer commit — no dejarlo para "después".
