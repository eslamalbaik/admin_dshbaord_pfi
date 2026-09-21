import { Client } from 'ssh2'
import { readFileSync } from 'node:fs'
import mysql from 'mysql2/promise'

const API_DIR = process.env.PCU_API_DIR || '/var/www/pcuorg/api'

function sshConfig() {
  const host = process.env.PCU_SSH_HOST
  const username = process.env.PCU_SSH_USER
  if (!host || !username) {
    throw new Error(
      'PCU_SSH_HOST and PCU_SSH_USER must be set. See tools/pcu-mcp/README.md.',
    )
  }

  const cfg = {
    host,
    username,
    port: Number(process.env.PCU_SSH_PORT || 22),
    // بدون keepalive يسقط الاتصال الخامل خلف NAT ويفشل أول نداء بعد فترة صمت
    keepaliveInterval: 20_000,
    readyTimeout: 20_000,
  }

  if (process.env.PCU_SSH_KEY) {
    cfg.privateKey = readFileSync(process.env.PCU_SSH_KEY)
    if (process.env.PCU_SSH_PASSPHRASE) cfg.passphrase = process.env.PCU_SSH_PASSPHRASE
  } else if (process.env.PCU_SSH_PASSWORD) {
    cfg.password = process.env.PCU_SSH_PASSWORD
  } else {
    throw new Error('Set PCU_SSH_KEY (path to a private key) or PCU_SSH_PASSWORD.')
  }

  return cfg
}

let conn = null
let pending = null

/** اتصال SSH واحد مُعاد الاستخدام — إعادة الاتصال تلقائية بعد أي انقطاع. */
export function connect() {
  if (conn) return Promise.resolve(conn)
  if (pending) return pending

  pending = new Promise((resolve, reject) => {
    const client = new Client()
    client
      .on('ready', () => {
        conn = client
        pending = null
        resolve(client)
      })
      .on('error', err => {
        conn = null
        pending = null
        reject(err)
      })
      .on('close', () => {
        conn = null
      })
      .connect(sshConfig())
  })

  return pending
}

export async function exec(command, { timeoutMs = 120_000 } = {}) {
  const client = await connect()

  return new Promise((resolve, reject) => {
    client.exec(command, (err, stream) => {
      if (err) return reject(err)

      let stdout = ''
      let stderr = ''
      // sudo التفاعلي ينتظر كلمة سر إلى الأبد — المهلة تمنع تعليق الخادم
      const timer = setTimeout(() => {
        stream.close()
        reject(new Error(`Command timed out after ${timeoutMs}ms: ${command}`))
      }, timeoutMs)

      stream
        .on('close', (code, signal) => {
          clearTimeout(timer)
          resolve({ code: code ?? null, signal: signal ?? null, stdout, stderr })
        })
        .on('data', d => { stdout += d })
        .stderr.on('data', d => { stderr += d })
    })
  })
}

async function sftp() {
  const client = await connect()
  return new Promise((resolve, reject) =>
    client.sftp((err, s) => (err ? reject(err) : resolve(s))),
  )
}

export async function readFile(path) {
  const s = await sftp()
  return new Promise((resolve, reject) =>
    s.readFile(path, 'utf8', (err, data) => (err ? reject(err) : resolve(data))),
  )
}

export async function writeFile(path, content) {
  const s = await sftp()
  return new Promise((resolve, reject) =>
    s.writeFile(path, content, 'utf8', err => (err ? reject(err) : resolve())),
  )
}

export async function stat(path) {
  const s = await sftp()
  return new Promise(resolve =>
    s.stat(path, (err, attrs) => resolve(err ? null : attrs)),
  )
}

export async function listDir(path) {
  const s = await sftp()
  return new Promise((resolve, reject) =>
    s.readdir(path, (err, list) => (err ? reject(err) : resolve(list))),
  )
}

// ─── MariaDB عبر نفق SSH ────────────────────────────────────────────────
// MySQL على السيرفر مربوط بـ 127.0.0.1 فقط، فالاتصال المباشر من الجهاز
// المحلي مستحيل. forwardOut يفتح قناة داخل جلسة SSH القائمة ونمرّرها
// لـ mysql2 كـ stream — بلا فتح أي منفذ جديد على الجدار الناري.

let cachedCreds = null

/** يقرأ اعتماد قاعدة البيانات من .env على السيرفر — لا نسخة ثانية محلياً. */
async function dbCredentials() {
  if (cachedCreds) return cachedCreds

  const env = await readFile(`${API_DIR}/.env`)
  const pick = key => {
    const m = env.match(new RegExp(`^${key}=(.*)$`, 'm'))
    if (!m) return undefined
    return m[1].trim().replace(/^["'](.*)["']$/, '$1')
  }

  cachedCreds = {
    database: pick('DB_DATABASE'),
    user: pick('DB_USERNAME'),
    password: pick('DB_PASSWORD'),
    host: pick('DB_HOST') || '127.0.0.1',
    port: Number(pick('DB_PORT') || 3306),
  }

  if (!cachedCreds.database || !cachedCreds.user) {
    cachedCreds = null
    throw new Error(`Could not parse DB_DATABASE/DB_USERNAME from ${API_DIR}/.env`)
  }

  return cachedCreds
}

async function dbConnection() {
  const client = await connect()
  const creds = await dbCredentials()

  const stream = await new Promise((resolve, reject) =>
    client.forwardOut('127.0.0.1', 0, creds.host, creds.port, (err, s) =>
      err ? reject(err) : resolve(s),
    ),
  )

  return mysql.createConnection({
    user: creds.user,
    password: creds.password,
    database: creds.database,
    stream,
    // multipleStatements يبقى false (الافتراضي) — يمنع حقن استعلام مكدّس
    // عبر معامل sql، فكل نداء عبارة واحدة فقط.
    dateStrings: true,
  })
}

/** ينفّذ استعلاماً ويغلق الاتصال دائماً — لا نفق معلّق عند الخطأ. */
export async function query(sql, params = []) {
  const db = await dbConnection()
  try {
    const [rows, fields] = await db.query(sql, params)
    return { rows, fields }
  } finally {
    await db.end().catch(() => {})
  }
}

export function apiDir() {
  return API_DIR
}
