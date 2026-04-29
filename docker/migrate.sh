#!/bin/sh
set -e

HOST=$1
USER=$2
DB=$3

run() {
    echo "→ $1"
    mariadb -h "$HOST" -u "$USER" "$DB" < "$1"
}

# Fichiers de base dans l'ordre fixe
for f in /sql/schema.sql /sql/add_photos.sql; do
    [ -f "$f" ] && run "$f"
done

# Toutes les migrations dans l'ordre numérique
for f in $(ls /sql/migrations/*.sql 2>/dev/null | sort); do
    run "$f"
done

echo "✓ $DB : migrations terminées"
