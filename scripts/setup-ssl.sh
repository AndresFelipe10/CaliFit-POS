#!/usr/bin/env bash
# ==============================================================================
# Script de Aprovisionamiento y Renovación SSL con Let's Encrypt para CaliFit POS
# Dominio: saborcalifit.systems (y pos.saborcalifit.systems)
# ==============================================================================
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${PROJECT_ROOT}"

DOMAIN="saborcalifit.systems"
EMAIL="saborcalifit@gmail.com"

echo "=================================================================="
echo " CaliFit POS — Configurando Certificado SSL para ${DOMAIN}"
echo "=================================================================="

# 1. Asegurar directorios de certbot
mkdir -p docker/certbot/conf docker/certbot/www

# 2. Solicitar certificado con Certbot
echo ">> Emitiendo certificado con Let's Encrypt..."
docker run --rm --name certbot \
  -v "${PROJECT_ROOT}/docker/certbot/conf:/etc/letsencrypt" \
  -v "${PROJECT_ROOT}/docker/certbot/www:/var/www/certbot" \
  certbot/certbot certonly --webroot -w /var/www/certbot \
  -d "${DOMAIN}" -d "pos.${DOMAIN}" -d "www.${DOMAIN}" \
  --email "${EMAIL}" --agree-tos --no-eff-email --keep-until-expiring

# 3. Activar configuración SSL en Nginx
if [ -f "docker/certbot/conf/live/${DOMAIN}/fullchain.pem" ]; then
    echo ">> Certificado emitido exitosamente. Activando HTTPS en Nginx..."
    cp docker/nginx/nginx.ssl.conf docker/nginx/nginx.prod.conf
    docker compose -f docker-compose.prod.yml exec nginx nginx -s reload || docker compose -f docker-compose.prod.yml restart nginx
    echo ">> HTTPS activado y Nginx recargado con éxito."
else
    echo "ERROR: No se encontró el certificado generado en docker/certbot/conf/live/${DOMAIN}/" >&2
    exit 1
fi

echo "=================================================================="
echo " CaliFit POS — ¡SSL configurado exitosamente en https://${DOMAIN}!"
echo "=================================================================="
