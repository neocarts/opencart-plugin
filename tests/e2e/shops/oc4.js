// OpenCart 4.1.0.4 (:8084). The extension is bind-mounted as extension/neocarts (docker-compose.yml).
import { execFileSync } from 'node:child_process'
import { expect } from '@playwright/test'

const SHOP = 'http://localhost:8084'
const sep = '.'

const sql = (query) =>
  execFileSync('docker', ['compose', 'exec', '-T', 'db', 'mariadb', '-uroot', '-proot', '-N', '-B', 'oc4', '-e', query], { encoding: 'utf8' })

/** Admin login over HTTP: the session cookie and the user token. */
const login = async () => {
  const first = await fetch(`${SHOP}/admin/index.php?route=common/login`)
  const cookie = first.headers.getSetCookie().map((c) => c.split(';')[0]).join('; ')
  const html = await first.text()
  const action = html.match(/id="form-login" action="([^"]+)"/)[1].replace(/&amp;/g, '&')
  const res = await fetch(action, {
    method: 'POST',
    headers: { cookie, 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: 'username=admin&password=admin',
  })
  const { redirect } = await res.json()
  const token = redirect?.match(/user_token=(\w+)/)?.[1]
  if (!token) throw new Error('oc4: admin login failed')
  return { cookie, token }
}

export default {
  prefix: 'module_neocarts_',
  code: 'module_neocarts',
  routes: {
    base: 'extension/neocarts/module/neocarts',
    sep,
    cartAdd: `checkout/cart${sep}add`,
    checkout: 'checkout/checkout',
    extensionForm: (token) => `/admin/index.php?route=extension/neocarts/module/neocarts&user_token=${token}`,
    productForm: (id, token) => `/admin/index.php?route=catalog/product${sep}form&product_id=${id}&user_token=${token}`,
  },

  /**
   * What the Installer does for an uploaded neocarts.ocmod.zip — a row in oc_extension_install
   * (4.x registers an extension's admin namespace only for installed packages) — then Extensions →
   * Modules → Install, which runs the module's install(): tables, events. Idempotent.
   */
  async install() {
    sql(`INSERT INTO oc_extension_install (extension_id, extension_download_id, name, description, code, version, author, link, status, date_added)
      SELECT 0, 0, 'Neocarts — subscriptions', '', 'neocarts', '0.0.0-dev', 'Neocarts', 'https://neocarts.com', 1, NOW()
      FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM oc_extension_install WHERE code = 'neocarts')`)
    const { cookie, token } = await login()
    const res = await fetch(`${SHOP}/admin/index.php?route=extension/module${sep}install&user_token=${token}&extension=neocarts&code=neocarts`, {
      headers: { cookie, 'X-Requested-With': 'XMLHttpRequest' },
    })
    const json = await res.json().catch(() => ({}))
    if (!json.success) throw new Error(`oc4: module install failed: ${JSON.stringify(json)}`)
  },

  async adminLogin(page) {
    await page.goto('/admin/index.php?route=common/login')
    await page.fill('#input-username', 'admin')
    await page.fill('#input-password', 'admin')
    await page.click('#form-login button[type="submit"]')
    await page.waitForURL(/user_token=/)
    return new URL(page.url()).searchParams.get('user_token')
  },

  /** 4.1 saves the product form over AJAX */
  async saveProductForm(page) {
    const saved = page.waitForResponse((r) => r.url().includes(`catalog/product${sep}save`))
    await page.click('button[form="form-product"]')
    const json = await (await saved).json()
    expect(json.success, JSON.stringify(json)).toBeTruthy()
  },
}
