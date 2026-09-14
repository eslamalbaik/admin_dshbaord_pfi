#!/bin/bash
#
# نشر الفرونت إند على سيرفر الإنتاج (Ubuntu VPS — srv1962001)
#   الاستخدام:  ./deploy-vps.sh
#
# السيرفر يبني من المصدر: يسحب فرع deploy-new ثم ينفّذ npm run build محلياً،
# والناتج في dist/ هو ما يُقدَّم للزوار. لذلك لا نسحب فرع deploy-dist هنا.
#
set -euo pipefail

APP_DIR="/var/www/pcuorg/front"
BRANCH="deploy-new"
BACKUP_DIR="$HOME/pcu-backups"
KEEP_DAYS=30
STAMP="$(date +%F-%H%M)"
BACKUP="$BACKUP_DIR/front-dist-$STAMP.tar.gz"

cd "$APP_DIR"
mkdir -p "$BACKUP_DIR"

# ---------------------------------------------------------------
#  نسخة احتياطية من البناء الحالي — هي خط الرجوع لو فشل البناء
# ---------------------------------------------------------------
HAVE_BACKUP=0
if [ -d dist ]; then
  echo "==> نسخة احتياطية من البناء الحالي"
  tar -czf "$BACKUP" dist
  HAVE_BACKUP=1
  echo "    $BACKUP"
else
  echo "==> لا يوجد بناء سابق — لن تتوفر إمكانية الرجوع"
fi

rollback() {
  if [ "$HAVE_BACKUP" -eq 1 ]; then
    echo "    إرجاع البناء السابق..."
    rm -rf dist
    tar -xzf "$BACKUP" -C .
    echo "    تم الإرجاع — الموقع يعمل بالنسخة السابقة."
  else
    echo "    لا توجد نسخة للرجوع إليها."
  fi
}

# ---------------------------------------------------------------
#  السحب والبناء
# ---------------------------------------------------------------
echo "==> سحب آخر التعديلات ($BRANCH)"
git pull origin "$BRANCH"

echo "==> مزامنة المكتبات"
npm install --no-audit --no-fund

echo "==> بناء الإنتاج (قد يستغرق عدة دقائق)"
if ! npm run build; then
  echo "✘ فشل البناء."
  rollback
  exit 1
fi

# البناء قد ينتهي بنجاح ظاهري دون أن ينتج ملفاً — تحقّق فعلياً
if [ ! -f dist/index.html ]; then
  echo "✘ البناء لم ينتج dist/index.html."
  rollback
  exit 1
fi

# ---------------------------------------------------------------
#  تحقّق أن الدومين الصحيح مخبوز في الناتج
# ---------------------------------------------------------------
API_URL="$(grep -E '^VITE_API_BASE_URL=' .env.production | cut -d= -f2-)"
if ! grep -rq "$API_URL" dist/assets/ 2>/dev/null; then
  echo "⚠  تحذير: لم يُعثر على $API_URL داخل البناء — راجع .env.production"
fi

# ---------------------------------------------------------------
#  تنظيف النسخ القديمة
# ---------------------------------------------------------------
find "$BACKUP_DIR" -name 'front-dist-*.tar.gz' -mtime "+$KEEP_DAYS" -delete

echo "✔ تم نشر الفرونت بنجاح — $STAMP"
echo "  الـ API المستخدم: $API_URL"
