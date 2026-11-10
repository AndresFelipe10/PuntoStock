#!/usr/bin/env bash
# ==============================================================================
# Script de Respaldo Automatizado de Base de Datos MySQL (Docker)
# Proyecto: Sistema de Gestión de Ventas e Inventario
# ==============================================================================
set -euo pipefail

# Directorio base del proyecto
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${PROJECT_ROOT}/backups"
TIMESTAMP="$(date +"%Y%m%d_%H%M%S")"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"

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
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: La contraseña de base de datos (DB_ROOT_PASSWORD) no está configurada en .env." >&2
    exit 1
fi

# Detectar contenedor de base de datos activo (producción o desarrollo)
if docker ps --format '{{.Names}}' | grep -q "^sistema_db_prod$"; then
    CONTAINER_NAME="sistema_db_prod"
elif docker ps --format '{{.Names}}' | grep -q "^sistema_db$"; then
    CONTAINER_NAME="sistema_db"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: No se encontró un contenedor MySQL activo (sistema_db_prod o sistema_db)." >&2
    exit 1
fi

mkdir -p "${BACKUP_DIR}"

BACKUP_FILE="${BACKUP_DIR}/dump_${DB_NAME}_${TIMESTAMP}.sql.gz"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Iniciando respaldo de '${DB_NAME}' desde '${CONTAINER_NAME}'..."

# Generar dump y comprimir con gzip protegiendo credenciales en MYSQL_PWD
docker exec -e MYSQL_PWD="${DB_PASS}" "${CONTAINER_NAME}" mysqldump \
    -u"${DB_USER}" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    "${DB_NAME}" | gzip > "${BACKUP_FILE}"

# Verificar integridad mínima
if [ -s "${BACKUP_FILE}" ]; then
    FILE_SIZE="$(du -h "${BACKUP_FILE}" | cut -f1)"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Respaldo completado exitosamente: ${BACKUP_FILE} (${FILE_SIZE})"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: El archivo de respaldo está vacío." >&2
    rm -f "${BACKUP_FILE}"
    exit 1
fi

# Política de retención: eliminar respaldos mayores a RETENTION_DAYS
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Aplicando política de retención (${RETENTION_DAYS} días)..."
find "${BACKUP_DIR}" -type f -name "dump_${DB_NAME}_*.sql.gz" -mtime +"${RETENTION_DAYS}" -print -delete || true

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Proceso de respaldo finalizado con éxito."
