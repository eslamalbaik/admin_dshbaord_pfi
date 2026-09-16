#!/bin/bash
#
# نشر الباك إند على سيرفر الإنتاج (Ubuntu VPS — srv1962001 / api.pcuorg.cloud)
#   الاستخدام:  ./deploy-vps.sh
#
# ملاحظة: deploy.sh المجاور خاص بسيرفر InMotion cPanel القديم — لا تخلط بينهما.
#
set -euo pipefail

APP_DIR="/var/www/pcuorg/api"
BRANCH="deploy-new"
BACKUP_DIR="$HOME/pcu-backups"
KEEP_DAYS=30
STAMP="$(date +%F-%H%M)"

cd "$APP_DIR"
mkdir -p "$BACKUP_DIR"

# ---------------------------------------------------------------
#  نسخ احتياطية — قبل أي تعديل
# ---------------------------------------------------------------
DB_NAME="$(grep -E '^DB_DATABASE=' .env | cut -d= -f2-)"
DB_USER="$(grep -E '^DB_USERNAME=' .env | cut -d= -f2-)"
DB_PASS="$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2-)"

echo "==> نسخة احتياطية لقاعدة البيانات"
# ملف مؤقت بصلاحيات مقيدة حتى لا تظهر كلمة المرور في قائمة العمليات
MYCNF="$(mktemp)"
chmod 600 "$MYCNF"
printf '[client]\nuser=%s\npassword=%s\n' "$DB_USER" "$DB_PASS" > "$MYCNF"
mysqldump --defaults-extra-file="$MYCNF" "$DB_NAME" > "$BACKUP_DIR/db-$STAMP.sql"
rm -f "$MYCNF"

# مجرد وجود الملف لا يكفي — الإعادة التوجيه تنشئه حتى لو فشل mysqldump
if ! tail -1 "$BACKUP_DIR/db-$STAMP.sql" | grep -q 'Dump completed'; then
  echo "✘ فشلت النسخة الاحتياطية لقاعدة البيانات — توقف قبل أي تعديل."
  exit 1
fi
echo "    $BACKUP_DIR/db-$STAMP.sql"

echo "==> نسخة احتياطية لملفات الرفع"
tar -czf "$BACKUP_DIR/storage-$STAMP.tar.gz" storage/app/public
echo "    $BACKUP_DIR/storage-$STAMP.tar.gz"

# ---------------------------------------------------------------
#  النشر
# ---------------------------------------------------------------
echo "==> وضع الصيانة"
php artisan down || true

# أعِد الموقع للعمل مهما حدث — نجاحاً أو فشلاً
trap 'php artisan up || true' EXIT

echo "==> سحب آخر التعديلات ($BRANCH)"
git pull origin "$BRANCH"

echo "==> تثبيت حزم Composer (بدون dev)"
composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

echo "==> تشغيل الترحيلات"
php artisan migrate --force

echo "==> إعادة بناء الكاش"
php artisan optimize

echo "==> ربط storage"
php artisan storage:link || true

echo "==> إعادة تشغيل خدمات الإشعارات الفورية (Reverb + queue worker)"
# لازم تعريف الوحدتين systemd أول مرة يدوياً — انظر ملاحظات REALTIME_NOTIFICATIONS.md
sudo systemctl restart pcu-api-queue pcu-api-reverb || echo "⚠ تخطّي: خدمات pcu-api-queue/pcu-api-reverb غير مُعرَّفة بعد"

# ---------------------------------------------------------------
#  تنظيف النسخ القديمة
# ---------------------------------------------------------------
find "$BACKUP_DIR" -name 'db-*.sql'          -mtime "+$KEEP_DAYS" -delete
find "$BACKUP_DIR" -name 'storage-*.tar.gz'  -mtime "+$KEEP_DAYS" -delete

echo "✔ تم النشر بنجاح — $STAMP"
