// Per-branch facts the specs need. Each branch file (oc2.js / oc3.js / oc4.js) exports:
//   install()            install + enable the extension through the admin over HTTP (idempotent)
//   adminLogin(page)     log the page into the admin, return the user token
//   routes               { base, cartAdd, checkout, productForm(id, token), extensionForm(token) }
//   prefix               setting key prefix: 'neocarts_' (2.3) or 'module_neocarts_'
//   code                 setting code: 'neocarts' or 'module_neocarts'
import { execFileSync } from 'node:child_process'
import oc2 from './oc2.js'
import oc3 from './oc3.js'
import oc4 from './oc4.js'

const branches = { oc2, oc3, oc4 }
const ports = { oc2: 8082, oc3: 8083, oc4: 8084 }

export const shop = (name) => ({ name, port: ports[name], url: `http://localhost:${ports[name]}`, ...branches[name] })

/** SQL against one shop's database; returns rows as arrays of strings (--raw: backslashes in regex settings stay single). */
export const sql = (name, query) =>
  execFileSync('docker', ['compose', 'exec', '-T', 'db', 'mariadb', '-uroot', '-proot', '-N', '-B', '--raw', name, '-e', query], {
    encoding: 'utf8',
  })
    .trim()
    .split('\n')
    .filter(Boolean)
    .map((line) => line.split('\t'))

const quote = (v) => `'${String(v).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`

/** Plugin settings straight into the `setting` table (store 0). Arrays are stored as JSON. */
export const setSettings = (name, values) => {
  const { prefix, code } = branches[name]
  for (const [key, raw] of Object.entries(values)) {
    const value = typeof raw === 'object' ? JSON.stringify(raw) : raw
    sql(name, `DELETE FROM oc_setting WHERE store_id = 0 AND \`key\` = ${quote(prefix + key)};
      INSERT INTO oc_setting (store_id, \`code\`, \`key\`, \`value\`, serialized) VALUES (0, ${quote(code)}, ${quote(prefix + key)}, ${quote(value)}, 0);`)
  }
}

/** Drops every plugin setting except the schema version, so a test starts from the defaults. */
export const clearSettings = (name) => {
  const { prefix } = branches[name]
  sql(name, `DELETE FROM oc_setting WHERE store_id = 0 AND \`key\` LIKE ${quote(prefix + '%')} AND \`key\` <> ${quote(prefix + 'schema_version')}`)
}

export const getSettings = (name) => {
  const { prefix } = branches[name]
  return Object.fromEntries(
    sql(name, `SELECT \`key\`, \`value\` FROM oc_setting WHERE store_id = 0 AND \`key\` LIKE ${quote(prefix + '%')}`).map(([k, v]) => [
      k.slice(prefix.length),
      v ?? '',
    ]),
  )
}
