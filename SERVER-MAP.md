# خريطة سيرفر 31.97.196.73 — حماية المواقع الأخرى

> مرجع أمان قبل أي حذف أو تعديل على السيرفر المشترك.
> السيرفر يستضيف **عدة مواقع مستقلة** — أي أمر يجب أن يمس **مشروعنا فقط**.

---

## 🔴 ممنوع المساس نهائياً (مواقع أخرى حيّة)

| الموقع | الدومين | المسار / العملية | لا تفعل |
|---|---|---|---|
| Earth Innovators | `earth-innovators.cloud` / `.ae` | `/var/www/earth-innovators` (Laravel) | لا تحذف المجلد، لا تعدّل nginx `earth-innovators` |
| Verify DesPro | `verifydespro.online` | proxy → `localhost:5000` | لا توقف الخدمة على بورت 5000، لا تعدّل nginx `certificates` |
| MariaDB مشترك | — | حاوية Docker `mariadb` | **لا تحذف هذه الحاوية** — قد تستخدمها مواقع أخرى |

**ملفات nginx المحمية (لا تحذفها):**
```
/etc/nginx/sites-enabled/certificates
/etc/nginx/sites-enabled/earth-innovators
```

**قاعدة ذهبية:** حاوية `mariadb` القديمة نتركها كما هي. مشروعنا الجديد يستخدم حاوية **منفصلة** `pcu-mariadb` على بورت `3307` — لا تعارض إطلاقاً.

---

## 🟢 يخص مشروعنا (آمن للحذف/التعديل)

| العنصر | المسار | الحالة |
|---|---|---|
| باك إند ACU القديم | `/var/www/acu-api` | يُحذف **بعد** التأكد أن الجديد يعمل |
| مجلدات kanaan | `/root/kanaan-backend`, `/root/kanaan-image-engine`, `/root/kanaan-repo` | يُحذف (أكّد المستخدم) |
| عمليات PM2 | `kanaan-api`, `image-engine` | تُوقف وتُحذف |
| nginx kanaan | `/etc/nginx/sites-*/kanaan-frontend` | يُحذف |
| ملفات مؤقتة | `~/acu-api.tar.gz`, `~/backend-deploy.tar.gz`, `~/acu-db.sql`, `~/deploy.sh` | تُحذف |
| مشروعنا الجديد | `/var/www/pcu/backend`, `/var/www/pcu/frontend` | نُنشئه |
| قاعدة بياناتنا | حاوية `pcu-mariadb` (بورت 3307) | نُنشئها |

---

## ✅ قواعد قبل أي أمر حذف

1. **لا تستخدم `rm -rf` على مسار عام** مثل `/var/www/*` أو `/etc/nginx/*` — حدّد المجلد بالاسم الكامل.
2. **لا تحذف `/var/www/acu-api` إلا بعد** أن يرد الـ API الجديد بنجاح من `/var/www/pcu/backend`.
3. **لا تلمس حاوية `mariadb`** — مشروعنا على `pcu-mariadb`.
4. **قبل حذف أي موقع nginx** تأكد أن اسمه ليس `certificates` ولا `earth-innovators`.
5. **بعد أي تعديل nginx:** `nginx -t` قبل `systemctl reload nginx` — إذا فشل الاختبار لا تُعِد التحميل.

---

## 🧹 أوامر التنظيف الآمنة (منسوخة بالترتيب)

### أ. حذف kanaan (خدمات + nginx + مجلدات)
```bash
# نسخة احتياطية أولاً
cd /root
tar -czf kanaan-backup-$(date +%F).tar.gz kanaan-backend kanaan-image-engine kanaan-repo deploy.sh

# إيقاف وإزالة عمليات PM2 (مشروعنا فقط — لا تلمس أي عملية أخرى)
pm2 delete kanaan-api image-engine
pm2 save

# إزالة موقع kanaan من nginx (بالاسم الكامل — لا تلمس certificates/earth-innovators)
rm -f /etc/nginx/sites-enabled/kanaan-frontend
rm -f /etc/nginx/sites-available/kanaan-frontend
nginx -t && systemctl reload nginx

# حذف المجلدات والملفات المؤقتة
rm -rf /root/kanaan-backend /root/kanaan-image-engine /root/kanaan-repo
rm -f /root/deploy.sh /root/acu-api.tar.gz /root/backend-deploy.tar.gz /root/acu-db.sql
```

### ب. حذف باك إند ACU القديم — **فقط بعد نجاح الجديد**
```bash
# تحقق أولاً أن الجديد يعمل:
curl -s -X POST https://api.fadaa-tech.com/api/v1/contractor/auth/login \
  -H "Accept: application/json" -F "membership_number=999999_g" -F "password=x"
# إذا رد: {"status":false,"message":"لا توجد لديك عضوية في الاتحاد",...} → الجديد يعمل

rm -rf /var/www/acu-api
```

> ملاحظة: قاعدة بيانات ACU القديمة داخل حاوية `mariadb` المشتركة — نتركها كما هي (لا صلاحية root، وغير ضارة). مشروعنا الجديد لا يعتمد عليها.

---

## 🔍 التحقق النهائي أن كل المواقع سليمة

```bash
pm2 list                                              # يجب ألا تبقى kanaan-api/image-engine
curl -sI https://api.fadaa-tech.com | head -1         # مشروعنا
curl -sI https://earth-innovators.cloud | head -1     # موقع آخر — يجب أن يبقى 200
curl -sI https://verifydespro.online | head -1        # موقع آخر — يجب أن يبقى 200
docker ps --format '{{.Names}}'                        # mariadb (قديم) + pcu-mariadb (جديد)
```
