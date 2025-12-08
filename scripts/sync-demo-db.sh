#!/usr/bin/env bash
set -euo pipefail

# Ce script exporte la base locale, l'envoie sur Alwaysdata et la restaure.
# Ajuste les variables ci-dessous (ou exporte-les dans l'environnement)
# avant de lancer :
#   LOCAL_DB_NAME, LOCAL_DB_USER, LOCAL_DB_HOST
#   REMOTE_SSH, REMOTE_DB_NAME, REMOTE_DB_USER, REMOTE_DB_HOST
#   REMOTE_DB_PASSWORD (utilisé via PGPASSWORD)

LOCAL_DB_NAME="${LOCAL_DB_NAME:-technova}"
LOCAL_DB_USER="${LOCAL_DB_USER:-baptiste}"
LOCAL_DB_HOST="${LOCAL_DB_HOST:-127.0.0.1}"

REMOTE_SSH="${REMOTE_SSH:-technova@ssh-technova.alwaysdata.net}"
REMOTE_DB_NAME="${REMOTE_DB_NAME:-technova_api}"
REMOTE_DB_USER="${REMOTE_DB_USER:-technova}"
REMOTE_DB_HOST="${REMOTE_DB_HOST:-postgresql-technova.alwaysdata.net}"
REMOTE_SQL_PATH="${REMOTE_SQL_PATH:-~/technova-demo.sql}"

DUMP_FILE="${DUMP_FILE:-$(mktemp /tmp/technova-demo-XXXX.sql)}"

echo "[1/4] Dump local (${LOCAL_DB_NAME}) -> ${DUMP_FILE}" >&2
pg_dump --data-only --column-inserts --no-owner --no-acl -h "$LOCAL_DB_HOST" -U "$LOCAL_DB_USER" "$LOCAL_DB_NAME" > "$DUMP_FILE"

echo "[2/4] Transfert du dump vers ${REMOTE_SSH}:${REMOTE_SQL_PATH}" >&2
scp "$DUMP_FILE" "${REMOTE_SSH}:${REMOTE_SQL_PATH}"

if [[ -z "${REMOTE_DB_PASSWORD:-}" ]]; then
  echo "Erreur: REMOTE_DB_PASSWORD n'est pas défini. Exporte-le avant de lancer le script." >&2
  exit 1
fi

echo "[3/4] Restauration sur Alwaysdata (${REMOTE_DB_NAME})" >&2
read -r -d '' truncate_sql <<'SQL'
DO $$
DECLARE
    r RECORD;
BEGIN
    FOR r IN (SELECT tablename FROM pg_tables WHERE schemaname = 'public') LOOP
        EXECUTE 'TRUNCATE TABLE ' || quote_ident(r.tablename) || ' RESTART IDENTITY CASCADE';
    END LOOP;
END
$$;
SQL
ssh "$REMOTE_SSH" "PGPASSWORD=\"${REMOTE_DB_PASSWORD}\" psql -h $REMOTE_DB_HOST -U $REMOTE_DB_USER -d $REMOTE_DB_NAME -c \"$truncate_sql\" >/dev/null"
ssh "$REMOTE_SSH" "PGPASSWORD=\"${REMOTE_DB_PASSWORD}\" psql -h $REMOTE_DB_HOST -U $REMOTE_DB_USER -d $REMOTE_DB_NAME < $REMOTE_SQL_PATH"

echo "[4/4] Nettoyage" >&2
rm -f "$DUMP_FILE"
ssh "$REMOTE_SSH" "rm -f ${REMOTE_SQL_PATH}"

echo "Synchronisation terminée." >&2
