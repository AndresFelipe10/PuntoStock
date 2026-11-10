#!/usr/bin/env bash
# ==============================================================================
# Script de Restauración de Base de Datos MySQL (Docker)
# Proyecto: Sistema de Gestión de Ventas e Inventario
# ==============================================================================
set -euo pipefail

if [ "$#" -ne 1 ]; then
    echo "Uso: $0 <ruta_al_archivo_dump.sql.gz | ruta_al_archivo_dump.sql>"
    exit 1
fi

BACKUP_FILE="$1"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [ ! -f "${BACKUP_FILE}" ]; then
    echo "ERROR: El archivo especificado no existe: ${BACKUP_FILE}" >&2
    exit 1
fi

# Cargar variables de base de datos de forma segura desde .env (soporta caracteres especiales en contraseñas)
if [ -f "${PROJECT_ROOT}/.env" ]; then
    while IFS= read -r line || [ -n "$line" ]; do
        line="$(echo "$line" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
        case "$line" in
            \#*|"") continue ;;
            DB_*=*|MYSQL_*=*)
                var_name="${line%%=*}"
                var_val="${line#*=}"
                if [[ "$var_val" =~ ^\"(.*)\"$ ]]; then
                    var_val="${BASH_REMATCH[1]}"
                elif [[ "$var_val" =~ ^\'(.*)\'$ ]]; then
                    var_val="${BASH_REMATCH[1]}"
                fi
                export "$var_name=$var_val"
                ;;
        esac
    done < "${PROJECT_ROOT}/.env"
fi

DB_NAME="${DB_DATABASE:-sistema_web}"
DB_USER="root"
DB_PASS="${DB_ROOT_PASSWORD:-}"

if [ -z "${DB_PASS}" ]; then
    echo "ERROR: La contraseña de base de datos (DB_ROOT_PASSWORD) no está configurada en .env." >&2
    exit 1
fi

# Detectar contenedor activo
if docker ps --format '{{.Names}}' | grep -q "^sistema_db_prod$"; then
    CONTAINER_NAME="sistema_db_prod"
elif docker ps --format '{{.Names}}' | grep -q "^sistema_db$"; then
    CONTAINER_NAME="sistema_db"
else
    echo "ERROR: No se encontró un contenedor MySQL activo (sistema_db_prod o sistema_db)." >&2
    exit 1
fi

echo "ADVERTENCIA: Esta operación sobreescribirá los datos actuales de '${DB_NAME}' en el contenedor '${CONTAINER_NAME}'."
read -p "¿Desea continuar? [s/N]: " -r CONFIRMATION
if [[ ! "${CONFIRMATION}" =~ ^[sS]$ ]]; then
    echo "Operación cancelada."
    exit 0
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Restaurando base de datos desde ${BACKUP_FILE}..."

if [[ "${BACKUP_FILE}" == *.gz ]]; then
    gunzip -c "${BACKUP_FILE}" | docker exec -i -e MYSQL_PWD="${DB_PASS}" "${CONTAINER_NAME}" mysql -u"${DB_USER}" "${DB_NAME}"
else
    docker exec -i -e MYSQL_PWD="${DB_PASS}" "${CONTAINER_NAME}" mysql -u"${DB_USER}" "${DB_NAME}" < "${BACKUP_FILE}"
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Restauración completada exitosamente."
