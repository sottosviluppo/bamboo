#!/bin/bash
set -e

APP_DIR=/var/www/bamboo

# Crea le cartelle se non esistono (il volume potrebbe non averle)
mkdir -p "$APP_DIR/app/cache"
mkdir -p "$APP_DIR/app/logs"

# Assegna la proprietà a www-data (utente Apache, UID 33)
# || true evita che un eventuale Permission denied blocchi il container
chown -R www-data:www-data "$APP_DIR/app/cache" 2>/dev/null || true
chown -R www-data:www-data "$APP_DIR/app/logs"  2>/dev/null || true

chmod -R 775 "$APP_DIR/app/cache" 2>/dev/null || true
chmod -R 775 "$APP_DIR/app/logs"  2>/dev/null || true

echo "[entrypoint] Permessi app/cache e app/logs corretti."

# Esegui il comando passato (di default: apache2-foreground)
exec "$@"