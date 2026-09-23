#!/bin/bash
#
# نشر الفرونت إند على بيئة الـSTAGING (Ubuntu VPS — staging.pcuorg.cloud)
#   الاستخدام:  ./deploy-vps.sh
#
# ⚠ هذا سكربت الـstaging. الإنتاج له مستودع منفصل تماماً بسكربته الخاص:
#   PcuGaza/PCU-Manager-Frontend فرع main → /var/www/pcuorg/production/front
#   انظر DEPLOYMENT.md.
#
# السيرفر يبني من المصدر: يسحب المونوريبو ثم ينفّذ npm run build محلياً،
# والناتج في dist/ هو ما يُقدَّم للزوار.
#
set -euo pipefail

APP_DIR="/var/www/pcuorg/front"
MONOREPO_DIR="/var/www/pcuorg/monorepo"
MONOREPO_BRANCH="feature/arab-contractors-union"
BACKUP_DIR="$HOME/pcu-backups"
KEEP_DAYS=30
STAMP="$(date +%F-%H%M)"
BACKUP="$BACKUP_DIR/front-dist-$STAMP.tar.gz"

cd "$APP_DIR"
mkdir -p "$BACKUP_DIR"

# ---------------------------------------------------------------
#  حارس البيئة — VITE_API_BASE_URL يُخبَز وقت البناء ولا يُصحَّح بعده
# ---------------------------------------------------------------
GUARD_URL="$(grep -E '^VITE_API_BASE_URL=' .env.production | cut -d= -f2-)"
if [ "$GUARD_URL" != "https://api.pcuorg.cloud" ]; then
  echo "✘ توقّف: .env.production يشير إلى '$GUARD_URL' وليس api.pcuorg.cloud (staging)."
  exit 1
fi

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
echo "==> سحب آخر التعديلات من المونوريبو ($MONOREPO_BRANCH)"
git -C "$MONOREPO_DIR" fetch origin "$MONOREPO_BRANCH"
git -C "$MONOREPO_DIR" reset --hard "origin/$MONOREPO_BRANCH"

echo "==> مزامنة arab-contractors-union-front/ إلى $APP_DIR"
rsync -a --delete \
  --exclude='.env' \
  --exclude='.env.production' \
  --exclude='node_modules' \
  --exclude='dist' \
  --exclude='.git' \
  "$MONOREPO_DIR/arab-contractors-union-front/" "$APP_DIR/"

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
