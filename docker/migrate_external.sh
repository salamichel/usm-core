#!/bin/sh
set -e

HOST=$1
USER=$2
DB=$3

run() {
    echo "→ $1"
    mariadb -h "$HOST" -u "$USER" "$DB" < "$1"
}

for f in /sql/external_schema.sql ; do
    [ -f "$f" ] && run "$f"
done

echo "✓ $DB : migrations terminées"
