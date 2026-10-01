# pcu-mcp

خادم MCP يعطي Claude Code وصول قراءة/كتابة إلى سيرفر الإنتاج (`srv1962001` / `api.pcuorg.cloud`)
وقاعدة بياناته، بدل نسخ اللوجات يدوياً في كل مرة.

An MCP server exposing the PCU production VPS and its MariaDB to Claude Code.

## كيف يعمل — How it works

يعمل الخادم **محلياً على جهازك** عبر stdio، ويتصل بالسيرفر عبر SSH صادر. لا شيء
يُثبَّت على السيرفر ولا يُفتح أي منفذ جديد على الجدار الناري.

```
Claude Code ──stdio──> pcu-mcp (محلي) ──SSH──> 187.77.172.48
                                         ├── exec / SFTP
                                         └── forwardOut ──> 127.0.0.1:3306 (MariaDB)
```

قاعدة البيانات مربوطة بـ `127.0.0.1` على السيرفر، فالاتصال المباشر مستحيل. الخادم
يفتح قناة داخل جلسة SSH القائمة ويمرّرها لـ `mysql2`، فتصل النتائج **JSON منظّم**
لا نصاً من `mysql` CLI.

اعتماد قاعدة البيانات يُقرأ من `.env` على السيرفر وقت التشغيل — لا نسخة ثانية من
كلمة السر على جهازك.

## التثبيت — Install

```bash
cd tools/pcu-mcp
npm install
```

## الإعداد — Configure

الإعدادات كلها في `tools/pcu-mcp/.env` (مستثنى من git):

```ini
PCU_SSH_HOST=187.77.172.48
PCU_SSH_USER=root
PCU_SSH_KEY=C:/Users/Lenovo/.ssh/id_rsa
PCU_API_DIR=/var/www/pcuorg/api
```

ثم `.mcp.json` في جذر المستودع — بلا أي اعتماد بداخله، فقط إشارة للملف أعلاه:

```json
{
  "mcpServers": {
    "pcu-ops": {
      "command": "node",
      "args": [
        "--env-file-if-exists=F:\\D\\admin_dshbaord_pfi\\tools\\pcu-mcp\\.env",
        "F:\\D\\admin_dshbaord_pfi\\tools\\pcu-mcp\\index.js"
      ]
    }
  }
}
```

> مسار المفتاح يُكتب بصيغة Windows (`C:/...`) لا بصيغة Git Bash (`~/.ssh/...`) —
> Node لا يفكّ `~` ولا مسارات MSYS.

| المتغيّر | الوصف |
|---|---|
| `PCU_SSH_HOST` | عنوان السيرفر — مطلوب |
| `PCU_SSH_USER` | مستخدم SSH — مطلوب |
| `PCU_SSH_KEY` | مسار المفتاح الخاص (أو استخدم `PCU_SSH_PASSWORD`) |
| `PCU_SSH_PASSPHRASE` | عبارة مرور المفتاح، إن وُجدت |
| `PCU_SSH_PORT` | افتراضي `22` |
| `PCU_API_DIR` | افتراضي `/var/www/pcuorg/api` — مصدر قراءة `.env` |

## التحقّق قبل الاستخدام — Verify first

أعطال stdio صامتة داخل Claude Code، فافحص الاتصال مباشرة أولاً:

```bash
cd tools/pcu-mcp && npm run check
```

يفحص: تسجيل الدخول، وجود مجلد الـAPI، توفّر `sudo` بلا كلمة سر، نفق قاعدة البيانات،
وقيمة `memory_limit` في PHP.

> `sudo -n` إن فشل، فأوامر `systemctl` ستتعلّق حتى المهلة (120 ثانية افتراضياً)
> لأن الجلسة غير تفاعلية ولا يمكنها إدخال كلمة السر.

## الأدوات — Tools

| الأداة | الوظيفة |
|---|---|
| `vps_exec` | تنفيذ أي أمر shell — يرجّع stdout/stderr/exit code |
| `vps_read_file` | قراءة ملف عبر SFTP |
| `vps_write_file` | كتابة ملف **مع نسخة احتياطية تلقائية** |
| `vps_list_dir` | سرد مجلد مع الحجم والصلاحيات والتاريخ |
| `db_query` | استعلام قراءة فقط — يرفض أي عبارة تكتب |
| `db_execute` | عبارات الكتابة والـDDL |

## حواجز الأمان — Safety rails

الصلاحية كاملة، لكن العمليات التي يصعب التراجع عنها تحتاج `confirm: true` صراحة
بدل أن تُنفَّذ عرضاً:

- **أوامر**: `rm -rf`، `mkfs`، `dd of=`، `reboot`، `systemctl stop/disable`،
  `git reset --hard`، `chmod -R 777`، fork bomb، أي حذف يمسّ `storage`/`.env`/`dist`
- **SQL**: `DROP`/`TRUNCATE`، تغيير المخطط أو الصلاحيات، و**`DELETE`/`UPDATE` بلا `WHERE`**
- **كتابة الملفات**: أي مسار تحت `/etc` أو أي `.env`

إضافةً إلى ذلك:

- **نسخة احتياطية قبل كل كتابة** — الملف القديم يُحفظ في `<path>.bak-<timestamp>`،
  وتفشل العملية كلياً إن فشلت النسخة.
- **`db_query` لا يكتب** — الفصل بين القراءة والكتابة يجعل الاستكشاف آمناً افتراضياً.
- **عبارة واحدة لكل نداء** — `multipleStatements` مُعطّل، فلا حقن استعلام مكدّس.
- **سجل تدقيق** — كل نداء يُكتب سطر JSONL في `audit.log` (مستثنى من git).
- **قصّ الإخراج** عند 100KB حتى لا يبتلع dump نافذة السياق.

هذه الحواجز تمنع الخطأ العرضي، لا المستخدم. أي عملية مرفوضة تُنفَّذ بإضافة
`confirm: true` مع ذكر السبب في رسالة الرفض.

## ملاحظة عن النشر — Deployment note

هذا المجلد **لا يُنسخ** إلى أي من المستودعين المرآة — المرآة تشمل
`arab-contractors-union-api/` و`arab-contractors-union-front/` فقط، و`deploy-vps.sh`
يزامن مجلد الـAPI وحده. فوجوده هنا لا يؤثر على الإنتاج.
