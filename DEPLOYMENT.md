# النشر والبيئات — PCU

> دليل عملي. آخر تحديث: 2026-09-23.
> كل ما هنا مُتحقَّق منه على الخادم الفعلي، لا نظري.

---

## 1. البيئتان في سطرين

| | **STAGING** | **PRODUCTION** |
|---|---|---|
| الواجهة | `staging.pcuorg.cloud` | `pcuorg.cloud` · `www` · `production.pcuorg.cloud` |
| الـAPI | `api.pcuorg.cloud` | `api-production.pcuorg.cloud` |
| المصدر | `eslamalbaik/admin_dshbaord_pfi` فرع `feature/arab-contractors-union` | `PcuGaza/PCU-Manager-{Backend,Frontend}` فرع `main` |
| المسار | `/var/www/pcuorg/{api,front}` | `/var/www/pcuorg/production/{api,front}` |
| القاعدة | `pcuorg` (مستخدم `pcuorg`) | `pcuorg_production` (مستخدم `pcuorg_prod`) |
| Redis | queue db0 · cache db1 | queue db2 · cache db3 · بادئة `pcuprod_` |
| Reverb | `127.0.0.1:8080` | `127.0.0.1:8081` |
| الخدمات | `pcu-api-queue` · `pcu-api-reverb` | `pcu-prod-queue` · `pcu-prod-reverb` |
| Firebase | **معطّل** → `LogPushSender` | **مفعّل** → `FirebasePushSender` |
| النشر | تلقائي على كل push | **يتطلب موافقة يدوية** |

كلاهما على نفس الـVPS (`187.77.172.48`). العزل بالبيانات كامل؛ المشترك هو CPU/RAM وpool واحد لـPHP-FPM.

---

## 2. تدفّق العمل

```
فرع ميزة ──PR──▶ feature/arab-contractors-union ──push──▶ STAGING (تلقائي، بلا موافقة)
                                │
                                │  بعد الاختبار: مزامنة يدوية (§6)
                                ▼
                    PcuGaza/*/main ──push──▶ [بوابة موافقة] ──▶ PRODUCTION
```

**حاجزان يمنعان النشر العَرَضي على الإنتاج:**
1. الإنتاج في **مستودعين مختلفين** لا يصلهما الـpush اليومي.
2. `environment: production` في الـworkflow يوقف التشغيل حتى توافق من تبويب Actions.

---

## 3. كيف تنشر

### Staging
```bash
git push origin feature/arab-contractors-union
```
يبدأ فورًا. تابع: Actions → "Deploy to staging". لا شيء آخر مطلوب.

### Production
1. زامن الكود من staging إلى مستودعي PcuGaza (§6).
2. ادفع/ادمج في `main`.
3. اذهب إلى **Actions** في المستودع → التشغيل معلّق بانتظار الموافقة → **Review deployments** → **Approve**.
4. راقب حتى `✔ تم نشر الإنتاج بنجاح`.

الباك إند والفرونت مستودعان مستقلان، فلكل منهما تشغيل وموافقة منفصلان.

### يدويًا من الخادم (عند تعطّل GitHub Actions)
```bash
ssh root@187.77.172.48
cd /var/www/pcuorg/production/api  && ./deploy-vps.sh   # الإنتاج
cd /var/www/pcuorg/production/front && ./deploy-vps.sh
cd /var/www/pcuorg/api   && ./deploy-vps.sh              # staging
cd /var/www/pcuorg/front && ./deploy-vps.sh
```

---

## 4. ⛔ ما يجب ألّا يحدث على الإنتاج أبدًا

| الأمر | لماذا |
|---|---|
| `php artisan db:seed` | **`DatabaseSeeder` بقايا قالب LMS**: ينشئ `admin@prometrica.com` بكلمة المرور `Admin@2025!` (معروفة في git)، و5 "طلاب"، ودورات صيدلة NAPLEX/FPGEE. كارثة أمنية ومحتوى خاطئ. |
| `php artisan migrate:fresh` | يحذف كل الجداول. لا يوجد سبب مشروع لتشغيله على الإنتاج. |
| `php artisan migrate --seed` | نفس مشكلة `db:seed`. |
| تشغيل سكربت نشر من البيئة الخطأ | محمي بحارس يتحقق من `DB_DATABASE`/`VITE_API_BASE_URL` ويتوقف — لا تعطّل الحارس. |
| `git checkout -- storage/` داخل مجلد التطبيق | يستبدل الرفعات الحقيقية بملفات نائبة، ثم يحذفها أول `pull`. خُذ `tar` أولًا. |
| إزالة `--exclude='storage/app/private'` من الـrsync | يمسح اعتماد Firebase فتُخفَّض كل إشعارات Push إلى وضع log **بصمت**. |
| تغيير `VITE_API_BASE_URL` بعد البناء | يُخبَز في الحزمة وقت البناء. لا بد من إعادة بناء. |

---

## 5. التراجع (Rollback)

### الفرونت — فوري
سكربت النشر يتراجع تلقائيًا إذا فشل البناء. يدويًا:
```bash
cd /var/www/pcuorg/production/front
ls -t ~/pcu-backups/production/front-dist-*.tar.gz | head
rm -rf dist && tar -xzf ~/pcu-backups/production/front-dist-<STAMP>.tar.gz -C .
```

### الباك إند — الكود
```bash
cd /var/www/pcuorg/production/repo-api
git reset --hard <آخر commit سليم>
cd /var/www/pcuorg/production/api && ./deploy-vps.sh
```

### قاعدة البيانات — آخر ملاذ
كل نشرة تأخذ نسخة **مع التحقق من اكتمالها** قبل أي تعديل:
```bash
ls -t ~/pcu-backups/production/db-*.sql | head
mysql -u pcuorg_prod -p pcuorg_production < ~/pcu-backups/production/db-<STAMP>.sql
```
النسخ تُحفظ 30 يومًا. **استرجاع القاعدة يفقد كل ما كُتب بعد وقت النسخة** — تأكد أولًا أن المشكلة تستحق.

### تحويل النطاق الرئيسي — ثوانٍ
```bash
# الرجوع: أعد pcuorg.cloud و www إلى vhost الـstaging
nano /etc/nginx/sites-available/pcuorg.cloud            # أضف الاسمين لـserver_name
nano /etc/nginx/sites-available/production.pcuorg.cloud # واحذفهما من هنا
nginx -t && systemctl reload nginx
```
نسخ nginx الاحتياطية في `~/pcu-backups/nginx-sites-available-<STAMP>/`.

---

## 6. مزامنة الكود من staging إلى الإنتاج

المستودعان **لا يشاركان تاريخ المونوريبو** (`git merge-base` فارغ) — فالمزامنة نسخ شجرة، لا `merge`:

```bash
# مثال للباك إند (الفرونت نفس الخطوات مع arab-contractors-union-front)
git clone git@github.com:PcuGaza/PCU-Manager-Backend.git /tmp/sync && cd /tmp/sync
git remote add mono /path/to/admin_dshbaord_pfi
git fetch mono feature/arab-contractors-union

TREE=$(git rev-parse mono/feature/arab-contractors-union:arab-contractors-union-api)
COMMIT=$(git commit-tree $TREE -p origin/main -m "sync: mirror api from monorepo (through <sha>)")
git update-ref refs/heads/main $COMMIT
git push origin refs/heads/main:refs/heads/main
```

**قبل الدفع تحقّق دائمًا:**
```bash
git ls-tree -r --name-only $TREE | grep -iE '(^|/)\.env$|firebase|adminsdk|\.pem$|id_rsa'
git diff --name-status --diff-filter=D origin/main $COMMIT   # يجب أن يكون فارغًا
```

### انحراف مقصود عن المونوريبو
بعد نسخ الشجرة، **أعد تطبيق** هذين الملفين — هما الاختلاف الوحيد المشروع:
- `deploy-vps.sh` — مسارات الإنتاج وفرع `main` وخدمات `pcu-prod-*`
- `.github/workflows/deploy-production.yml` — بوابة الموافقة

وانتبه: `.env.production` المتتبَّع في الفرونت يشير إلى `api.pcuorg.cloud` (staging). **غير ضار** لأن الـrsync يستثنيه فملف الخادم هو السائد، لكن لا تبنِ منه محليًا وتظن أنك تبني للإنتاج.

---

## 7. متغيرات البيئة

`.env` و`.env.production` **غير متتبَّعة في git** ومستثناة من الـrsync — فملفات الخادم هي المرجع ولا تُمسّ بنشرة.

### ما يجب أن يختلف بين البيئتين (كله يختلف فعلًا)
`APP_ENV` · `APP_KEY` · `APP_URL` · `DB_DATABASE` · `DB_USERNAME` · `DB_PASSWORD` · `REDIS_DB` · `REDIS_CACHE_DB` · `REDIS_PREFIX` · `CACHE_PREFIX` · `REVERB_APP_ID/KEY/SECRET` · `REVERB_HOST` · `REVERB_SERVER_PORT` · `FRONTEND_URL` · `LANDING_URL` · `FIREBASE_CREDENTIALS` · `CERT_SIGNING_KEY_V1` · `VITE_API_BASE_URL` · `VITE_REVERB_*`

### قيود لا تتغير
- **الإنتاج:** `APP_ENV=production` · `APP_DEBUG=false` · `LOG_LEVEL=error`
- **Staging:** `FIREBASE_CREDENTIALS=` **فارغ دائمًا** — وإلا وصلت إشعارات الاختبار لهواتف حقيقية.
- `VITE_REVERB_APP_KEY` في الفرونت **يجب أن يطابق** `REVERB_APP_KEY` في `.env` الخاص بنفس البيئة.
- `VITE_API_BASE_URL` = **أصل الدومين فقط** بدون `/api/v1` (الكود يضيفه).

الأسرار محفوظة في `/root/pcu-secrets-vault/` بصلاحيات `700`:
`prod-db-password.txt` · `prod-reverb-app-key.txt` · `prod-admin-password.txt` · `pcu-gaza-firebase-adminsdk.json`

---

## 8. DNS و SSL

كل السجلات **A** في نطاق `pcuorg.cloud` → `187.77.172.48`:
`@` · `www` · `api` · `staging` · `production` · `api-production`

> `pcuorg.stagging.cloud` و `pcuorg.production.cloud` **غير موجودين ولا يمكن إنشاؤهما** — يتطلبان ملكية النطاقين المسجَّلين `stagging.cloud`/`production.cloud`. الأسماء الصحيحة هي النطاقات الفرعية أعلاه.

شهادة Let's Encrypt واحدة باسم `pcuorg.cloud` تغطي الستة، والتجديد تلقائي عبر certbot.
```bash
certbot certificates                 # فحص
certbot renew --dry-run              # اختبار التجديد
```
لإضافة نطاق جديد: أنشئ vhost على المنفذ 80، ثم
`certbot --nginx --cert-name pcuorg.cloud --expand -d <كل النطاقات القائمة> -d <الجديد>`

---

## 9. تشغيل وصيانة

```bash
# الحالة
systemctl status pcu-prod-queue pcu-prod-reverb     # الإنتاج
systemctl status pcu-api-queue  pcu-api-reverb      # staging
ss -ltnp | grep -E ':(8080|8081)'                   # 8080=staging 8081=production

# اللوج
tail -f /var/www/pcuorg/production/api/storage/logs/laravel.log
tail -f /var/log/nginx/error.log

# تشخيص Push — الإنتاج يجب أن يُظهر FirebasePushSender وstaging يُظهر LogPushSender
cd /var/www/pcuorg/production/api && php artisan push:diagnose
cd /var/www/pcuorg/api            && php artisan push:diagnose

# cron (مستخدم www-data) — سطران، واحد لكل بيئة
crontab -u www-data -l
```

**بعد أي `php artisan` تشغّله كـroot** أعد الملكية وإلا كسرت كتابة اللوج نفسها:
```bash
chown -R www-data:www-data <مجلد التطبيق>/storage <مجلد التطبيق>/bootstrap/cache
```

**فخّ ملكية `.env` — قنبلة موقوتة صامتة.** لو كان `.env` مملوكًا لـ`root` بصلاحية `640`
فإن PHP-FPM (يعمل بـ`www-data`) **لا يستطيع قراءته**. الموقع يظل يعمل ما دام
`bootstrap/cache/config.php` موجودًا، فلا شيء يبدو خاطئًا — ثم ينهار عند أول
`php artisan config:clear` أو `optimize:clear`. تحقّق دائمًا:
```bash
sudo -u www-data test -r <مجلد التطبيق>/.env && echo ok || echo 'BROKEN'
```
الصحيح: `chown www-data:www-data .env && chmod 640 .env`.

**حدود الرفع:** PHP-FPM مضبوط على `upload_max_filesize=2M` و`post_max_size=8M` بينما قواعد Laravel تسمح بـ10M. الرفع الأكبر يفشل برسالة "الحقل مطلوب" مضلّلة. للرفع حتى 10M عدّل `/etc/php/8.5/fpm/php.ini` ثم `systemctl restart php8.5-fpm`.

---

## 10. إثبات أن البيئتين معزولتان

```bash
# 1. مستخدم كل قاعدة مرفوض على الأخرى
mysql -u pcuorg_prod -p pcuorg            -e "SELECT 1;"   # Access denied ✓
mysql -u pcuorg      -p pcuorg_production -e "SELECT 1;"   # Access denied ✓

# 2. فهارس وبادئات Redis مختلفة
redis-cli -n 1 --scan | head    # staging
redis-cli -n 3 --scan | head    # production

# 3. توكن من بيئة مرفوض في الأخرى (APP_KEY مختلف + قاعدة مختلفة)
curl -s -o /dev/null -w '%{http_code}\n' https://api.pcuorg.cloud/api/v1/user \
  -H "Authorization: Bearer <توكن إنتاج>"                  # 401 ✓

# 4. CORS يرفض أصل البيئة الأخرى
curl -si -X OPTIONS https://api-production.pcuorg.cloud/api/v1/auth/login \
  -H 'Origin: https://staging.pcuorg.cloud' -H 'Access-Control-Request-Method: POST' \
  | grep -i access-control-allow-origin                    # لا رأس ✓
```

---

## 11. أعمال تقوية مؤجّلة

| البند | الوضع |
|---|---|
| مفتاح SSH على الخادم هو **حساب eslamalbaik الشخصي** | يمنح الخادم وصولًا لكل مستودعات الحساب. الأفضل: deploy key للقراءة فقط لكل مستودع. |
| لا يوجد **swap** على خادم 3.8 GB | بناءان متزامنان لـVite قد يسبّبان OOM. |
| `CERTIFICATE_PDF_OWNER_PASSWORD` في staging ضعيفة (`union-cert-2026`) | الإنتاج يستخدم قيمة عشوائية. |
| مفاتيح `AWS_*` في `.env` الخاص بـstaging | غير مستخدمة (`FILESYSTEM_DISK=local`) — يُفضّل تفريغها. |
| `.env.production` للفرونت متتبَّع في git | لا يحوي أسرارًا (مفتاح Reverb قيمة نائبة)، لكنه يشير لدومين staging. |
