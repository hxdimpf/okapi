#!/bin/bash
# Force-delete all Nut-XX circle test caches from the database.
# Works regardless of OAuth state or cache ownership.
# Usage: bash oc-cleanup-circle.sh

set -e

DBPASS=$(docker inspect mariadb-db-1 --format '{{.Config.Env}}' 2>/dev/null | tr ' ' '\n' | grep MARIADB_ROOT_PASSWORD | cut -d= -f2)

if [ -z "$DBPASS" ]; then
  echo "ERROR: Cannot find MariaDB container. Is the stack running?"
  exit 1
fi

run_sql() {
  docker exec mariadb-db-1 mysql -uroot -p"$DBPASS" -N -e "$1" oc 2>/dev/null
}

COUNT_BEFORE=$(run_sql "SELECT COUNT(*) FROM caches WHERE name LIKE 'Nut-%';")
echo "Found $COUNT_BEFORE Nut caches to delete."

if [ "$COUNT_BEFORE" -eq 0 ]; then
  echo "Nothing to do."
  exit 0
fi

# Drop cache-related triggers so we can delete without stored proc dependencies
echo "Dropping triggers..."
TRIGGERS=$(run_sql "SELECT CONCAT('DROP TRIGGER IF EXISTS ', TRIGGER_NAME, ';') FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='oc' AND (EVENT_OBJECT_TABLE LIKE '%cache%' OR EVENT_OBJECT_TABLE LIKE '%caches%');")
if [ -n "$TRIGGERS" ]; then
  echo "$TRIGGERS" | while read -r drop; do
    run_sql "$drop"
  done
fi

# Delete with FK checks disabled
echo "Deleting..."
run_sql "
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM cache_desc WHERE cache_id IN (SELECT c.cache_id FROM (SELECT cache_id FROM caches WHERE name LIKE 'Nut-%') AS c);
DELETE FROM cache_coordinates WHERE cache_id IN (SELECT c.cache_id FROM (SELECT cache_id FROM caches WHERE name LIKE 'Nut-%') AS c);
DELETE FROM caches WHERE name LIKE 'Nut-%';
SET FOREIGN_KEY_CHECKS = 1;
"

COUNT_AFTER=$(run_sql "SELECT COUNT(*) FROM caches WHERE name LIKE 'Nut-%';")
echo "Remaining: $COUNT_AFTER"

# Recreate triggers via maintain.php
echo "Recreating triggers..."
docker exec oc3-oc3-1 php /var/www/html/sql/stored-proc/maintain.php 2>/dev/null || true

TRIGGER_COUNT=$(run_sql "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='oc';")
echo "Triggers: $TRIGGER_COUNT"
echo "Done."
