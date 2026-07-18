#!/bin/bash
#
# سكربت تحديث الباك إند على الإنتاج — يُشغَّل من داخل /var/www/pcu/backend
#   الاستخدام:  ./deploy.sh
#
set -euo pipefail

APP_DIR="/var/www/pcu/backend"
BRANCH="development"

cd "$APP_DIR"

echo "==> سحب آخر التعديلات ($BRANCH)"
git pull origin "$BRANCH"

echo "==> تثبيت حزم Composer (بدون dev)"
composer install --no-dev --optimize-autoloader

echo "==> تشغيل الترحيلات (migrations)"
php artisan migrate --force

echo "==> إعادة بناء الكاش"
php artisan config:cache
php artisan route:cache
php artisan event:cache

echo "==> ضبط الصلاحيات"
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 storage bootstrap/cache

echo "==> إعادة تحميل php-fpm"
systemctl reload php8.3-fpm || true

echo "✔ تم تحديث الباك إند بنجاح."
