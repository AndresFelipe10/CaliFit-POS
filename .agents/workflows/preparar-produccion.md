# Workflow — Preparar producción

Ejecutar antes de considerar el sistema CaliFit POS listo para un despliegue real.

1. **`.env`**: confirmar que no hay secretos en el repositorio; `.env.example` actualizado con todas las variables CaliFit y placeholders.
2. **Secretos**: API keys de Gemini y credenciales de MySQL solo en `.env` de producción, nunca versionadas.
3. **Docker**: revisar que `docker-compose.prod.yml` use los nombres CaliFit (`califit_app_prod`, `califit_nginx_prod`, `califit_db_prod`) y configuraciones apropiadas para producción.
4. **Migraciones**: confirmar que todas las migraciones corren limpio sobre una base vacía, incluyendo las tablas de recetas y adiciones.
5. **Persistencia**: volumen `califit_mysql_prod_data` configurado correctamente; estrategia de backup documentada en `docs/ARQUITECTURA.md`.
6. **HTTPS / Nginx**: configuración de dominio y certificado documentada.
7. **Logs**: confirmar que no se registran datos sensibles; nivel de log apropiado.
8. **Backups**: scripts `scripts/backup.sh` y `scripts/backup-db.sh` verificados con nombres CaliFit.
9. **Seguridad**: `APP_DEBUG=false`, rate limiting activo, checklist de `docs/SEGURIDAD.md` verificado.
10. **Configuración de producción**: `APP_ENV=production`, caché de configuración/rutas de Laravel optimizada.
11. **Identidad visual**: paleta verde fitness (#10B981) y marca CaliFit aplicadas en toda la interfaz y tickets térmicos.
