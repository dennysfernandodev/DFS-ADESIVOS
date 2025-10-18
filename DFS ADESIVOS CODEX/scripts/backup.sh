#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${PROJECT_ROOT}"

if [[ ! -f .env ]]; then
  echo "[ERRO] Arquivo .env não encontrado." >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

if command -v docker >/dev/null 2>&1; then
  if docker compose version >/dev/null 2>&1; then
    DOCKER_COMPOSE=(docker compose)
  else
    DOCKER_COMPOSE=(docker-compose)
  fi
else
  echo "[ERRO] Docker não está instalado." >&2
  exit 1
fi

run_dc() {
  "${DOCKER_COMPOSE[@]}" "$@"
}

BACKUP_DIR="${PROJECT_ROOT}/backups"
mkdir -p "${BACKUP_DIR}"

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT

SQL_FILE="${TMP_DIR}/db.sql"
ARCHIVE="${BACKUP_DIR}/dfs-adesivos-${TIMESTAMP}.tar.gz"

echo "[INFO] Exportando banco de dados para ${SQL_FILE}"
run_dc exec -T db mysqldump -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" --single-transaction --quick --lock-tables=false > "${SQL_FILE}"

echo "[INFO] Gerando pacote ${ARCHIVE}"
tar -czf "${ARCHIVE}" -C "${PROJECT_ROOT}" wp-content -C "${TMP_DIR}" db.sql

echo "[OK] Backup finalizado em ${ARCHIVE}"
