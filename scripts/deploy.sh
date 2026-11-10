#!/usr/bin/env bash
# ==============================================================================
# Script de Despliegue y Actualización en Producción
# Proyecto: Sistema de Gestión de Ventas e Inventario
# ==============================================================================
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${PROJECT_ROOT}"

echo "=================================================================="
echo " Invocando Despliegue en Producción - $(date '+%Y-%m-%d %H:%M:%S')"
echo "=================================================================="

# 1. Verificar existencia de .env
if [ ! -f ".env" ]; then
    echo "ERROR: El archivo .env no existe. Copie .env.example y configúrelo antes de desplegar." >&2
    exit 1
fi

# 2. Respaldo preventivo antes del despliegue
if [ -f "scripts/backup-db.sh" ]; then
    echo ">> Creando respaldo preventivo de base de datos..."
    bash scripts/backup-db.sh || echo "Aviso: No se pudo generar respaldo previo (¿primera instalación?). Continuando..."
fi

# 3. Poner en modo mantenimiento si el contenedor está corriendo
if docker ps --format '{{.Names}}' | grep -q "^sistema_app_prod$"; then
    echo ">> Activando modo mantenimiento temporal..."
    BYPASS_SECRET="${DEPLOY_BYPASS_SECRET:-$(head -c 16 /dev/urandom | xxd -p 2>/dev/null || openssl rand -hex 16)}"
    docker compose -f docker-compose.prod.yml exec app php artisan down --render="errors.500" --secret="$BYPASS_SECRET" || true
fi

# 4. Construir y levantar contenedores de producción
echo ">> Levantando servicios de producción con Docker Compose..."
docker compose -f docker-compose.prod.yml up -d --build

# 5. Ejecutar migraciones de base de datos (con flag --force)
echo ">> Ejecutando migraciones de base de datos..."
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force

# 6. Optimizar caché de Laravel (configuración, rutas, vistas)
echo ">> Limpiando y optimizando configuraciones, rutas y vistas en caché..."
docker compose -f docker-compose.prod.yml exec app php artisan config:clear
docker compose -f docker-compose.prod.yml exec app php artisan route:clear
docker compose -f docker-compose.prod.yml exec app php artisan view:clear
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache

# 7. Asegurar permisos en storage y bootstrap/cache
echo ">> Asegurando permisos de directorios..."
docker compose -f docker-compose.prod.yml exec app chown -R www-data:www-data storage bootstrap/cache
docker compose -f docker-compose.prod.yml exec app chmod -R 775 storage bootstrap/cache

# 8. Desactivar modo mantenimiento
if docker ps --format '{{.Names}}' | grep -q "^sistema_app_prod$"; then
    echo ">> Desactivando modo mantenimiento..."
    docker compose -f docker-compose.prod.yml exec app php artisan up || true
fi

# 9. Diagnóstico de salud del Módulo de IA (No bloqueante)
echo ">> Verificando salud del Módulo de IA..."
docker compose -f docker-compose.prod.yml exec app php artisan ai:check || echo "Aviso: Revisa la configuración de GEMINI_API_KEY si deseas habilitar el asistente de IA."

echo "=================================================================="
echo " Despliegue completado exitosamente a las $(date '+%Y-%m-%d %H:%M:%S')"
echo "=================================================================="
