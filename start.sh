#!/bin/bash
set -e

echo "==> AutoSecForge Railway Startup"

# Wait for MySQL to be ready (Railway MySQL addon)
if [ -n "$MYSQLHOST" ]; then
  export DB_HOST="${MYSQLHOST}"
  export DB_PORT="${MYSQLPORT:-3306}"
  export DB_NAME="${MYSQLDATABASE:-security_dashboard}"
  export DB_USER="${MYSQLUSER:-dashboard}"
  export DB_PASSWORD="${MYSQLPASSWORD}"
  echo "==> Using Railway MySQL: $DB_HOST:$DB_PORT/$DB_NAME"
fi

# Also support DATABASE_URL style (Railway sometimes provides this)
if [ -n "$DATABASE_URL" ]; then
  # Parse mysql://user:pass@host:port/dbname
  DB_USER=$(echo "$DATABASE_URL" | sed -E 's|mysql://([^:]+):.*|\1|')
  DB_PASSWORD=$(echo "$DATABASE_URL" | sed -E 's|mysql://[^:]+:([^@]+)@.*|\1|')
  DB_HOST=$(echo "$DATABASE_URL" | sed -E 's|mysql://[^@]+@([^:/]+).*|\1|')
  DB_PORT=$(echo "$DATABASE_URL" | sed -E 's|mysql://[^@]+@[^:]+:([^/]+)/.*|\1|')
  DB_NAME=$(echo "$DATABASE_URL" | sed -E 's|.*/([^?]+).*|\1|')
  export DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD
  echo "==> Parsed DATABASE_URL: $DB_HOST:$DB_PORT/$DB_NAME"
fi

# Wait for DB to be ready (max 60s)
echo "==> Waiting for database..."
for i in $(seq 1 30); do
  if php -r "
    \$pdo = new PDO(
      'mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_NAME};charset=utf8mb4',
      '${DB_USER}', '${DB_PASSWORD}'
    );
    echo 'ok';
  " 2>/dev/null | grep -q ok; then
    echo "==> Database is ready!"
    break
  fi
  echo "   Attempt $i/30 - waiting 2s..."
  sleep 2
done

# Seed the schema
echo "==> Running schema migrations..."
php -r "
  try {
    \$pdo = new PDO(
      'mysql:host=${DB_HOST};port=${DB_PORT};charset=utf8mb4',
      '${DB_USER}', '${DB_PASSWORD}',
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    \$sql = file_get_contents('/var/www/html/database/schema.sql');
    // Split by semicolons and run each statement
    \$stmts = array_filter(array_map('trim', explode(';', \$sql)));
    foreach (\$stmts as \$stmt) {
      if (!empty(\$stmt)) {
        try { \$pdo->exec(\$stmt); } catch (Exception \$e) { /* ignore alter if already done */ }
      }
    }
    echo 'Schema OK';
  } catch (Exception \$e) {
    echo 'Schema error: ' . \$e->getMessage();
  }
"

echo "==> Starting Apache..."
apache2-foreground
