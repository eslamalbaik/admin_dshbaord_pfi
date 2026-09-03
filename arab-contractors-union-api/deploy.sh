#!/bin/bash
#
# سكربت تحديث الباك إند على إنتاج InMotion (cPanel) — يُشغَّل من داخل ~/acu-api
#   الاستخدام:  ./deploy.sh
#
set -euo pipefail

APP_DIR="/home/pcuorg/acu-api"
PHP83="/opt/cpanel/ea-php83/root/usr/bin/php"
BRANCH="development"

cd "$APP_DIR"

echo "==> سحب آخر التعديلات ($BRANCH)"
git pull origin "$BRANCH"

echo "==> تثبيت حزم Composer (بدون dev)"
"$PHP83" composer.phar install --no-dev --optimize-autoloader --no-interaction

echo "==> تشغيل الترحيلات (migrations)"
"$PHP83" artisan migrate --force

echo "==> إعادة بناء الكاش"
"$PHP83" artisan config:cache
"$PHP83" artisan route:cache
"$PHP83" artisan view:cache

echo "==> ربط storage (لو لسا غير مربوط)"
"$PHP83" artisan storage:link || true

echo "✔ تم تحديث الباك إند بنجاح."
