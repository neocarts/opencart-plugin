#!/bin/sh
# First start: copy the release into the volume, run the CLI installer, seed UAH.
# Every start: link the plugin source into the tree (2.3/3.0); 4.x mounts it as extension/neocarts.
set -e
ROOT=/var/www/html

until mysqladmin ping -h"$DB_HOST" -uroot -p"$DB_ROOT_PASSWORD" --silent 2>/dev/null; do sleep 1; done

if [ ! -s "$ROOT/config.php" ]; then
  echo "[oc] installing OpenCart into $ROOT"
  cp -a /opt/opencart/. "$ROOT/"
  cp "$ROOT/config-dist.php" "$ROOT/config.php"
  cp "$ROOT/admin/config-dist.php" "$ROOT/admin/config.php"
  touch "$ROOT/.htaccess"
  php "$ROOT/install/cli_install.php" install \
    --db_hostname "$DB_HOST" --db_username root --db_password "$DB_ROOT_PASSWORD" \
    --db_database "$DB_NAME" --db_driver mysqli --db_port 3306 --db_prefix oc_ \
    --username admin --password admin --email admin@example.com \
    --http_server "$OC_URL"
  rm -rf "$ROOT/install"
  # --force: tables differ between branches (4.1 has no oc_product_special)
  mysql --force --default-character-set=utf8mb4 -h"$DB_HOST" -uroot -p"$DB_ROOT_PASSWORD" "$DB_NAME" < /seed/uah.sql || true
  echo "[oc] installed: $OC_URL (admin/admin)"
fi

if [ -d /plugin/upload ]; then
  # ponytail: symlink every file; a new plugin file needs a container restart (make restart-ocN)
  (cd /plugin/upload && find . -type f) | while read -r f; do
    mkdir -p "$ROOT/$(dirname "$f")"
    ln -sfn "/plugin/upload/$f" "$ROOT/$f"
  done
fi

if [ -d /plugin/shared ]; then
  mkdir -p "$ROOT/system/library" "$ROOT/catalog/view/javascript/neocarts"
  ln -sfn /plugin/shared "$ROOT/system/library/neocarts"
  ln -sfn /plugin/shared/js/neocarts.js "$ROOT/catalog/view/javascript/neocarts/neocarts.js"
fi

chown -R www-data:www-data "$ROOT" 2>/dev/null || true
exec docker-php-entrypoint "$@"
