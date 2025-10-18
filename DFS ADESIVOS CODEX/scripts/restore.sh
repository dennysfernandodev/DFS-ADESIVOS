#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Uso: $0 <arquivo-backup.tar.gz>" >&2
  exit 1
fi

BACKUP_FILE="$1"

if [[ ! -f "${BACKUP_FILE}" ]]; then
  echo "[ERRO] Arquivo de backup não encontrado: ${BACKUP_FILE}" >&2
  exit 1
fi

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

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT

echo "[INFO] Extraindo backup..."
tar -xzf "${BACKUP_FILE}" -C "${TMP_DIR}"

if [[ -d "${TMP_DIR}/wp-content" ]]; then
  echo "[INFO] Restaurando wp-content..."
  mkdir -p "${PROJECT_ROOT}/wp-content"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete "${TMP_DIR}/wp-content/" "${PROJECT_ROOT}/wp-content/"
  else
    rm -rf "${PROJECT_ROOT}/wp-content"
    cp -a "${TMP_DIR}/wp-content" "${PROJECT_ROOT}/"
  fi
else
  echo "[WARN] Diretório wp-content não encontrado no backup."
fi

if [[ -f "${TMP_DIR}/db.sql" ]]; then
  echo "[INFO] Restaurando banco de dados..."
  run_dc exec -T db mysql -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "${TMP_DIR}/db.sql"
else
  echo "[WARN] Arquivo db.sql não encontrado no backup."
fi

echo "[OK] Restauração concluída."
