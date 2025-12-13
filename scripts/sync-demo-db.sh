#!/usr/bin/env bash
set -euo pipefail

LOCAL_DB_NAME="${LOCAL_DB_NAME:-technova}"
LOCAL_DB_USER="${LOCAL_DB_USER:-baptiste}"
LOCAL_DB_HOST="${LOCAL_DB_HOST:-127.0.0.1}"

REMOTE_SSH="${REMOTE_SSH:-technova@ssh-technova.alwaysdata.net}"
REMOTE_DB_NAME="${REMOTE_DB_NAME:-technova_api}"
REMOTE_DB_USER="${REMOTE_DB_USER:-technova}"
REMOTE_DB_HOST="${REMOTE_DB_HOST:-postgresql-technova.alwaysdata.net}"
REMOTE_HOME=$(ssh "$REMOTE_SSH" 'echo $HOME')
REMOTE_SQL_PATH="${REMOTE_HOME}/technova-demo.sql"

DUMP_FILE="${DUMP_FILE:-$(mktemp /tmp/technova-demo-XXXX.sql)}"

echo "[1/4] Dump local (${LOCAL_DB_NAME}) -> ${DUMP_FILE}" >&2
pg_dump --data-only --column-inserts --no-owner --no-acl \
  -h "$LOCAL_DB_HOST" -U "$LOCAL_DB_USER" "$LOCAL_DB_NAME" > "$DUMP_FILE"

echo "[2/4] Transfert du dump vers ${REMOTE_SSH}:${REMOTE_SQL_PATH}" >&2
scp "$DUMP_FILE" "${REMOTE_SSH}:${REMOTE_SQL_PATH}"

if [[ -z "${REMOTE_DB_PASSWORD:-}" ]]; then
  echo "Erreur: REMOTE_DB_PASSWORD n'est pas défini." >&2
  exit 1
fi

echo "[3/4] Restauration sur Alwaysdata (${REMOTE_DB_NAME})" >&2

ssh "$REMOTE_SSH" bash <<EOF
export PGPASSWORD="${REMOTE_DB_PASSWORD}"

# --- TRUNCATE COMPLET ---
psql -h "$REMOTE_DB_HOST" -U "$REMOTE_DB_USER" -d "$REMOTE_DB_NAME" <<'SQL'
DO \$\$
DECLARE
    r RECORD;
BEGIN
    FOR r IN (SELECT tablename FROM pg_tables WHERE schemaname = 'public') LOOP
        EXECUTE 'TRUNCATE TABLE ' || quote_ident(r.tablename) || ' RESTART IDENTITY CASCADE';
    END LOOP;
END
\$\$;
SQL

# --- IMPORT DU DUMP ---
psql -h "$REMOTE_DB_HOST" -U "$REMOTE_DB_USER" -d "$REMOTE_DB_NAME" < "$REMOTE_SQL_PATH"
EOF

echo "[4/4] Nettoyage" >&2
rm -f "$DUMP_FILE"
ssh "$REMOTE_SSH" "rm -f ${REMOTE_SQL_PATH}"

echo "Synchronisation terminée." >&2
