#!/usr/bin/env node
// فحص الاتصال خارج MCP. أعطال stdio صامتة تماماً داخل Claude Code،
// فالتشخيص هنا أسرع بكثير من قراءة سجل العميل.
import * as vps from './vps.js'

const ok = s => console.log(`  \x1b[32m✔\x1b[0m ${s}`)
const bad = s => console.log(`  \x1b[31m✘\x1b[0m ${s}`)

let failed = false

async function step(label, fn) {
  try {
    ok(`${label}: ${await fn()}`)
  } catch (e) {
    bad(`${label}: ${e.message}`)
    failed = true
  }
}

console.log('\npcu-mcp connectivity check\n')

for (const key of ['PCU_SSH_HOST', 'PCU_SSH_USER']) {
  if (process.env[key]) ok(`${key} = ${process.env[key]}`)
  else { bad(`${key} is not set`); failed = true }
}
if (process.env.PCU_SSH_KEY) ok(`PCU_SSH_KEY = ${process.env.PCU_SSH_KEY}`)
else if (process.env.PCU_SSH_PASSWORD) ok('PCU_SSH_PASSWORD is set')
else { bad('neither PCU_SSH_KEY nor PCU_SSH_PASSWORD is set'); failed = true }

if (!failed) {
  await step('SSH login', async () => {
    const r = await vps.exec('whoami && hostname')
    return r.stdout.trim().replace(/\n/g, ' @ ')
  })
}

// كل فحص يفتح اتصالاً جديداً، فالاستمرار بعد فشل المصادقة يكرر المحاولة
// خمس مرات ويستفز fail2ban. نتوقف عند أول فشل.
if (failed) {
  console.log('\n  \x1b[33m!\x1b[0m Stopping here — further checks would retry the failed')
  console.log('    authentication and risk a fail2ban lockout on your IP.\n')
  process.exit(1)
}

{
  await step('API directory', async () => {
    const r = await vps.exec(`test -d ${vps.apiDir()} && echo present`)
    if (r.code !== 0) throw new Error(`${vps.apiDir()} not found (set PCU_API_DIR)`)
    return vps.apiDir()
  })

  await step('sudo without password', async () => {
    const r = await vps.exec('sudo -n true 2>&1', { timeoutMs: 10_000 })
    return r.code === 0 ? 'available' : 'NOT available — systemctl commands will hang'
  })

  await step('database via SSH tunnel', async () => {
    const { rows } = await vps.query('SELECT DATABASE() AS db, VERSION() AS version')
    return `${rows[0].db} (MariaDB ${rows[0].version})`
  })

  // php -i يقرأ إعداد CLI (‎-1 افتراضياً) لا إعداد FPM الذي ينفّذ طلبات الويب،
  // فنقرأ ملفات FPM مباشرة — هذه هي القيمة التي تحكم توليد شهادة PDF.
  await step('PHP-FPM memory_limit', async () => {
    const r = await vps.exec(
      `grep -hE '^[[:space:]]*memory_limit' /etc/php/*/fpm/php.ini 2>/dev/null; ` +
        `grep -rhE '^[[:space:]]*php_admin_value\\[memory_limit\\]' /etc/php/*/fpm/pool.d/ 2>/dev/null`,
    )
    return r.stdout.trim().replace(/\s+/g, ' ') || 'not found in FPM config'
  })
}

console.log(failed ? '\nSome checks failed — fix the above before using the MCP server.\n' : '\nAll checks passed.\n')
process.exit(failed ? 1 : 0)
