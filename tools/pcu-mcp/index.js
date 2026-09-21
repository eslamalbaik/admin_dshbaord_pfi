#!/usr/bin/env node
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js'
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js'
import { z } from 'zod'

// الملف اسمه vps.js لا ssh.js: cmd.exe يبحث في المجلد الحالي أولاً و PATHEXT
// يتضمن .JS، فملف باسم ssh.js يحجب أمر ssh الحقيقي لمن يعمل cd إلى هنا.
import * as vps from './vps.js'
import { screenCommand, screenSql, isReadOnlySql, stripSqlComments, audit } from './guards.js'

// إخراج ضخم (dump، لوج كامل) يبتلع نافذة السياق — نقصّه ونخبر بذلك صراحة
const MAX_OUTPUT = 100_000

function text(s) {
  const body =
    s.length > MAX_OUTPUT
      ? `${s.slice(0, MAX_OUTPUT)}\n\n…[truncated ${s.length - MAX_OUTPUT} more characters]`
      : s
  return { content: [{ type: 'text', text: body }] }
}

function fail(s) {
  return { isError: true, content: [{ type: 'text', text: s }] }
}

function shellQuote(s) {
  return `'${String(s).replace(/'/g, `'\\''`)}'`
}

function needsConfirm(reason, what) {
  return fail(
    `Blocked: this ${what} looks destructive (${reason}) and targets production.\n` +
      `Re-run with confirm: true if that is genuinely intended.`,
  )
}

const server = new McpServer({ name: 'pcu-ops', version: '1.0.0' })

// ─── VPS ────────────────────────────────────────────────────────────────

server.registerTool(
  'vps_exec',
  {
    title: 'Run a shell command on the PCU production VPS',
    description:
      'Execute an arbitrary shell command on the production server over SSH and return ' +
      'stdout, stderr and the exit code. Use this for diagnostics (tail logs, systemctl ' +
      'status, php -i, ls), deploys, and service restarts. Commands that match a ' +
      'destructive pattern (rm -rf, mkfs, reboot, DROP, git reset --hard, service stop) ' +
      'require confirm: true. Note that sudo without passwordless access will hang until ' +
      'the timeout. Prefer vps_read_file for reading a whole file.',
    inputSchema: {
      command: z.string().describe('Shell command to run, e.g. "tail -50 /var/log/nginx/error.log"'),
      confirm: z
        .boolean()
        .optional()
        .describe('Set true to allow a command flagged as destructive'),
      timeout_ms: z
        .number()
        .int()
        .optional()
        .describe('Kill the command after this many ms (default 120000)'),
      cwd: z.string().optional().describe('Directory to cd into first'),
    },
  },
  async ({ command, confirm, timeout_ms, cwd }) => {
    const reason = screenCommand(command)
    if (reason && !confirm) {
      audit('vps_exec', { command }, { blocked: reason })
      return needsConfirm(reason, 'command')
    }

    const full = cwd ? `cd ${shellQuote(cwd)} && ${command}` : command

    try {
      const r = await vps.exec(full, { timeoutMs: timeout_ms ?? 120_000 })
      audit('vps_exec', { command: full, confirmed: !!confirm }, { exit: r.code })

      const parts = [`exit code: ${r.code}${r.signal ? ` (signal ${r.signal})` : ''}`]
      if (r.stdout) parts.push(`--- stdout ---\n${r.stdout}`)
      if (r.stderr) parts.push(`--- stderr ---\n${r.stderr}`)
      if (!r.stdout && !r.stderr) parts.push('(no output)')
      return text(parts.join('\n'))
    } catch (e) {
      audit('vps_exec', { command: full }, { error: e.message })
      return fail(`SSH exec failed: ${e.message}`)
    }
  },
)

server.registerTool(
  'vps_read_file',
  {
    title: 'Read a file from the production VPS',
    description:
      'Read a text file over SFTP and return its contents. Use for config files ' +
      '(.env, nginx sites, php.ini, systemd units) and source files. For very large logs, ' +
      'prefer vps_exec with tail/grep instead.',
    inputSchema: {
      path: z.string().describe('Absolute path, e.g. "/var/www/pcuorg/api/.env"'),
    },
  },
  async ({ path }) => {
    try {
      const content = await vps.readFile(path)
      audit('vps_read_file', { path }, { bytes: content.length })
      return text(content)
    } catch (e) {
      audit('vps_read_file', { path }, { error: e.message })
      return fail(`Could not read ${path}: ${e.message}`)
    }
  },
)

server.registerTool(
  'vps_write_file',
  {
    title: 'Write a file on the production VPS',
    description:
      'Overwrite a file on the production server. The existing file is always copied to ' +
      '<path>.bak-<timestamp> first, so a bad edit can be reverted. Writing to a path ' +
      'under /etc, or to .env, requires confirm: true.',
    inputSchema: {
      path: z.string().describe('Absolute destination path'),
      content: z.string().describe('Full new file contents (this replaces the file)'),
      confirm: z.boolean().optional().describe('Required for /etc/* and .env targets'),
    },
  },
  async ({ path, content, confirm }) => {
    const sensitive = /^\/etc\//.test(path) || /\.env$/.test(path)
    if (sensitive && !confirm) {
      audit('vps_write_file', { path }, { blocked: 'sensitive path' })
      return needsConfirm('system config or environment file', 'write')
    }

    try {
      let backup = null
      const existing = await vps.stat(path)
      if (existing) {
        backup = `${path}.bak-${new Date().toISOString().replace(/[:.]/g, '-')}`
        const cp = await vps.exec(`cp -p ${shellQuote(path)} ${shellQuote(backup)}`)
        if (cp.code !== 0) {
          audit('vps_write_file', { path }, { error: `backup failed: ${cp.stderr}` })
          return fail(`Refusing to write — backup failed: ${cp.stderr.trim()}`)
        }
      }

      await vps.writeFile(path, content)
      audit('vps_write_file', { path, confirmed: !!confirm }, { bytes: content.length, backup })

      return text(
        `Wrote ${content.length} bytes to ${path}.\n` +
          (backup ? `Previous version saved at ${backup}` : '(new file — no previous version)'),
      )
    } catch (e) {
      audit('vps_write_file', { path }, { error: e.message })
      return fail(`Could not write ${path}: ${e.message}`)
    }
  },
)

server.registerTool(
  'vps_list_dir',
  {
    title: 'List a directory on the production VPS',
    description:
      'List directory entries with size, mode and mtime. Cheaper and more structured than ' +
      'vps_exec with ls.',
    inputSchema: { path: z.string().describe('Absolute directory path') },
  },
  async ({ path }) => {
    try {
      const list = await vps.listDir(path)
      audit('vps_list_dir', { path }, { entries: list.length })
      const rows = list
        .map(e => {
          const a = e.attrs
          const kind = a.isDirectory() ? 'd' : a.isSymbolicLink() ? 'l' : '-'
          const mode = (a.mode & 0o777).toString(8).padStart(3, '0')
          const mtime = new Date(a.mtime * 1000).toISOString().slice(0, 19).replace('T', ' ')
          return `${kind} ${mode} ${String(a.size).padStart(10)} ${mtime}  ${e.filename}`
        })
        .sort()
      return text(rows.join('\n') || '(empty directory)')
    } catch (e) {
      audit('vps_list_dir', { path }, { error: e.message })
      return fail(`Could not list ${path}: ${e.message}`)
    }
  },
)

// ─── MariaDB ────────────────────────────────────────────────────────────

server.registerTool(
  'db_query',
  {
    title: 'Run a read-only SQL query on the production database',
    description:
      'Run a SELECT/SHOW/DESCRIBE/EXPLAIN query against the production MariaDB (tunnelled ' +
      'over SSH) and return rows as JSON. Anything that writes is rejected here — use ' +
      'db_execute for that. Always prefer placeholders (?) with the params array over ' +
      'string-concatenating values into the SQL.',
    inputSchema: {
      sql: z.string().describe('A single read-only statement, e.g. "SELECT * FROM contractors WHERE id = ?"'),
      params: z.array(z.union([z.string(), z.number(), z.boolean(), z.null()])).optional()
        .describe('Values bound to ? placeholders, in order'),
      limit: z.number().int().optional().describe('Max rows to render (default 200)'),
    },
  },
  async ({ sql, params, limit }) => {
    if (!isReadOnlySql(sql)) {
      audit('db_query', { sql }, { blocked: 'not read-only' })
      return fail(
        'db_query only accepts SELECT / SHOW / DESCRIBE / EXPLAIN / WITH. ' +
          'Use db_execute for statements that modify data.',
      )
    }

    try {
      const { rows } = await vps.query(sql, params ?? [])
      const cap = limit ?? 200
      const shown = Array.isArray(rows) ? rows.slice(0, cap) : rows
      audit('db_query', { sql }, { rows: Array.isArray(rows) ? rows.length : 1 })

      const note =
        Array.isArray(rows) && rows.length > cap
          ? `\n\n…showing ${cap} of ${rows.length} rows (raise limit to see more)`
          : ''
      return text(JSON.stringify(shown, null, 2) + note)
    } catch (e) {
      audit('db_query', { sql }, { error: e.message })
      return fail(`Query failed: ${e.message}`)
    }
  },
)

server.registerTool(
  'db_execute',
  {
    title: 'Run a writing SQL statement on the production database',
    description:
      'Run INSERT/UPDATE/DELETE/DDL against the production MariaDB and return the affected ' +
      'row count. Statements that drop or truncate objects, change schema or privileges, or ' +
      'UPDATE/DELETE without a WHERE clause require confirm: true. Take a backup first for ' +
      'anything non-trivial — deploy-vps.sh writes one to ~/pcu-backups.',
    inputSchema: {
      sql: z.string().describe('A single writing statement'),
      params: z.array(z.union([z.string(), z.number(), z.boolean(), z.null()])).optional()
        .describe('Values bound to ? placeholders, in order'),
      confirm: z.boolean().optional().describe('Required for statements flagged as destructive'),
    },
  },
  async ({ sql, params, confirm }) => {
    const reason = screenSql(stripSqlComments(sql))
    if (reason && !confirm) {
      audit('db_execute', { sql }, { blocked: reason })
      return needsConfirm(reason, 'statement')
    }

    try {
      const { rows } = await vps.query(sql, params ?? [])
      audit('db_execute', { sql, confirmed: !!confirm }, { result: rows?.affectedRows ?? null })
      return text(JSON.stringify(rows, null, 2))
    } catch (e) {
      audit('db_execute', { sql }, { error: e.message })
      return fail(`Statement failed: ${e.message}`)
    }
  },
)

// stdout محجوز كلياً لرسائل بروتوكول MCP — أي console.log هنا يفسد الجلسة.
// كل تشخيص يذهب إلى stderr.
process.on('unhandledRejection', err => {
  console.error('[pcu-mcp] unhandled rejection:', err)
})

await server.connect(new StdioServerTransport())
console.error('[pcu-mcp] ready')
