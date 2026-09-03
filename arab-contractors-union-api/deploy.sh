#!/bin/bash
#
# سكربت تحديث الباك إند على إنتاج InMotion (cPanel) — يُشغَّل من داخل ~/acu-api
#   الاستخدام:  ./deploy.sh
#
set -euo pipefail

APP_DIR="/home/pcuorg/acu-api"
PHP82="/opt/cpanel/ea-php82/root/usr/bin/php"
BRANCH="development"

cd "$APP_DIR"

echo "==> سحب آخر التعديلات ($BRANCH)"
git pull origin "$BRANCH"

echo "==> تثبيت حزم Composer (بدون dev)"
"$PHP82" composer.phar install --no-dev --optimize-autoloader --no-interaction

echo "==> تشغيل الترحيلات (migrations)"
"$PHP82" artisan migrate --force

echo "==> إعادة بناء الكاش"
"$PHP82" artisan config:cache
"$PHP82" artisan route:cache
"$PHP82" artisan view:cache

echo "==> ربط storage (لو لسا غير مربوط)"
"$PHP82" artisan storage:link || true

echo "✔ تم تحديث الباك إند بنجاح."
