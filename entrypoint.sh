#!/bin/bash
set -e

echo "Waiting for MySQL to be ready..."
until php -r "new PDO('mysql:host=mysql;dbname=CosplayCouture', 'cosplay_user', 'cosplay_pass');" 2>/dev/null; do
  echo "MySQL not ready yet, retrying in 3s..."
  sleep 3
done
echo "MySQL is ready."

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "Clearing and warming up cache..."
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

echo "Starting Supervisor (nginx + php-fpm)..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf