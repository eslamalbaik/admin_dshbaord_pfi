# دليل النشر — منصة اتحاد المقاولين (PCU / ACU)

> نشر نظيف بالكامل على سيرفر `31.97.196.73` (Ubuntu — `srv1149850`) — لا يعتمد على أي إعداد قديم.
> آخر تحديث للمرجع: يوليو 2026.

---

## 1. البنية على السيرفر

| الخدمة | الدومين | المجلد / الحاوية | آلية التشغيل |
|---|---|---|---|
| API (Laravel) | `api.fadaa-tech.com` | `/var/www/pcu/backend` | nginx + php8.3-fpm |
| لوحة الأدمن (Vite SPA) | `admin.fadaa-tech.com` | `/var/www/pcu/frontend/dist` | nginx (ملفات ثابتة) |
| قاعدة البيانات | داخلي `127.0.0.1:3307` | حاوية Docker مخصصة `pcu-mariadb` | volume دائم `pcu_db_data` |

**مشاريع أخرى على نفس السيرفر (لا تُلمس):** `earth-innovators.cloud`، `verifydespro.online`، وحاوية `mariadb` القديمة المشتركة.

**البيئة المثبّتة مسبقاً:** PHP 8.3 + FPM، Nginx، Composer، Node.js، Docker، Certbot.

---

## 2. قاعدة بيانات جديدة مخصصة (حاوية مستقلة)

حاوية MariaDB خاصة بالمشروع — معزولة عن حاوية المشاريع الأخرى، على بورت `3307`:

```bash
docker run -d \
  --name pcu-mariadb \
  --restart unless-stopped \
  -p 127.0.0.1:3307:3306 \
  -e MARIADB_ROOT_PASSWORD='ROOT_STRONG_PASSWORD' \
  -e MARIADB_DATABASE=pcu_manager \
  -e MARIADB_USER=pcu_user \
  -e MARIADB_PASSWORD='APP_STRONG_PASSWORD' \
  -v pcu_db_data:/var/lib/mysql \
  mariadb:11 \
  --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci

# تحقق أنها تعمل
docker ps --filter name=pcu-mariadb
docker exec -it pcu-mariadb mariadb -u pcu_user -p pcu_manager -e "SELECT 1;"
```

> البورت مربوط بـ `127.0.0.1` فقط — غير مكشوف للإنترنت.
> البيانات محفوظة في volume `pcu_db_data` وتبقى حتى لو حُذفت الحاوية.

---

## 3. الباك إند (API) — نسخة جديدة

```bash
mkdir -p /var/www/pcu
cd /var/www/pcu
git clone -b development https://github.com/PcuGaza/PCU-Manager-Backend.git backend
cd backend

composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env
```

### إعدادات `.env` للإنتاج
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.fadaa-tech.com
APP_LOCALE=ar

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=pcu_manager
DB_USERNAME=pcu_user
DB_PASSWORD=APP_STRONG_PASSWORD

# مطلوبة لعمل لوحة الأدمن (CORS/Sanctum) — بدونها يُرفض طلب اللوحة
FRONTEND_URL=https://admin.fadaa-tech.com
SANCTUM_STATEFUL_DOMAINS=admin.fadaa-tech.com
SESSION_DOMAIN=.fadaa-tech.com
```

### التهيئة
```bash
php artisan key:generate
php artisan storage:link
php artisan migrate --force

# حساب أدمن للوحة (غيّر البريد وكلمة المرور)
php artisan tinker --execute="
\App\Models\User::create([
    'name' => 'Eslam AlBaik',
    'email' => 'admin@fadaa-tech.com',
    'password' => bcrypt('ADMIN_STRONG_PASSWORD'),
    'role' => 'admin',
    'email_verified_at' => now(),
]);
echo 'Admin created';"

# ⚠️ لا تشغّل php artisan db:seed — الـ seeder يحتوي بيانات تجريبية لمنتج آخر

# يوزر مقاول اختباري (يطبع رقم العضوية + كلمة المرور — أرسلها لمدير المشروع)
php artisan contractor:create-test

# كاش الإنتاج
php artisan config:cache && php artisan route:cache && php artisan event:cache

# الصلاحيات
chown -R www-data:www-data /var/www/pcu/backend
chmod -R 775 storage bootstrap/cache
```

---

## 4. تحويل `api.fadaa-tech.com` إلى النسخة الجديدة

عدّل ملف nginx الموجود (يحافظ على شهادة SSL الحالية):

```bash
sed -i 's|root /var/www/acu-api/public;|root /var/www/pcu/backend/public;|' /etc/nginx/sites-available/acu-api
nginx -t && systemctl reload nginx

# تحقق فوراً
curl -s -X POST https://api.fadaa-tech.com/api/v1/contractor/auth/login \
  -H "Accept: application/json" -F "membership_number=999999_g" -F "password=x"
# متوقع: {"status":false,"message":"لا توجد لديك عضوية في الاتحاد","status_code":404,...}
```

بعد التأكد أن كل شيء يعمل من المسار الجديد، احذف النسخة القديمة:
```bash
rm -rf /var/www/acu-api
```

---

## 5. لوحة الأدمن (Frontend) — نسخة جديدة

```bash
cd /var/www/pcu
git clone -b development https://github.com/PcuGaza/PCU-Manager-Frontend.git frontend
cd frontend

# عنوان الـ API يُدمج داخل الملفات وقت البناء — لازم قبل build
echo "VITE_API_BASE_URL=https://api.fadaa-tech.com" > .env

npm ci
npm run build        # ينتج مجلد dist/
```

### Nginx + SSL

> سجل DNS مطلوب: `A  admin  31.97.196.73` (انتظر انتشاره قبل certbot).

```bash
cat > /etc/nginx/sites-available/pcu-admin <<'EOF'
server {
    listen 80;
    server_name admin.fadaa-tech.com;
    root /var/www/pcu/frontend/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|svg|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
EOF

ln -s /etc/nginx/sites-available/pcu-admin /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

certbot --nginx -d admin.fadaa-tech.com
```

---

## 6. التحقق النهائي

```bash
# API: عضوية غير موجودة → 404 بالرسالة المعتمدة
curl -s -X POST https://api.fadaa-tech.com/api/v1/contractor/auth/login \
  -H "Accept: application/json" -F "membership_number=999999_g" -F "password=x"

# اللوحة تستجيب
curl -sI https://admin.fadaa-tech.com | head -1

# باقي مواقع السيرفر لم تتأثر
curl -sI https://earth-innovators.cloud | head -1
```

ثم من المتصفح: `https://admin.fadaa-tech.com` → سجّل الدخول بحساب الأدمن الذي أنشأته.
أي خطأ CORS في الـ Console = `FRONTEND_URL` غير مضبوطة أو لم تُنفَّذ `config:cache`.

---

## 7. التحديثات المستقبلية (بعد كل push)

الباك إند:
```bash
cd /var/www/pcu/backend && ./deploy.sh
```

الفرونت إند:
```bash
cd /var/www/pcu/frontend
git pull origin development
npm ci && npm run build
```

---

## 8. النسخ الاحتياطي لقاعدة البيانات

```bash
# نسخة يدوية
docker exec pcu-mariadb mariadb-dump -u root -p'ROOT_STRONG_PASSWORD' pcu_manager > /root/backups/pcu-$(date +%F).sql

# مجدولة يومياً (crontab -e)
0 3 * * * mkdir -p /root/backups && docker exec pcu-mariadb mariadb-dump -u root -p'ROOT_STRONG_PASSWORD' pcu_manager > /root/backups/pcu-$(date +\%F).sql
```

---

## 9. ملاحظات أمان

- لا ترفع أي dump (`*.sql`) أو أرشيف (`*.tar.gz`) إلى مجلد عام (`public/`).
- `APP_DEBUG=false` دائماً في الإنتاج.
- بعد أي `git pull` نفّذ `config:cache` و `route:cache` من جديد.
- لا تستخدم `migrate:fresh` على الإنتاج أبداً (يمسح كل البيانات) — `migrate --force` فقط.
- لا تشغّل `db:seed` على الإنتاج — يحتوي بيانات تجريبية.
