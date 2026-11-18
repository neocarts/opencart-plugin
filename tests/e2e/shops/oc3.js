// OpenCart 3.0.5.1 (:8083): user_token in admin URLs, the module under Extensions → Modules.
import { sql } from './index.js'

const BASE = 'http://localhost:8083'
const admin = (route, token, args = '') => `/admin/index.php?route=${route}&user_token=${token}${args}`

/** logs in over plain HTTP: { token, cookie } */
const login = async () => {
  const res = await fetch(`${BASE}/admin/index.php?route=common/login`, {
    method: 'POST',
    body: new URLSearchParams({ username: 'admin', password: 'admin' }),
    redirect: 'manual',
  })
  const token = (res.headers.get('location') ?? '').match(/user_token=(\w+)/)?.[1]
  const cookie = res.headers.getSetCookie().map((c) => c.split(';')[0]).join('; ')
  if (!token) throw new Error(`oc3 admin login failed: HTTP ${res.status}`)
  return { token, cookie }
}

export default {
  prefix: 'module_neocarts_',
  code: 'module_neocarts',
  routes: {
    base: 'extension/module/neocarts',
    sep: '/',
    cartAdd: 'checkout/cart/add',
    checkout: 'checkout/checkout',
    extensionForm: (token) => admin('extension/module/neocarts', token),
    productForm: (id, token) => admin('catalog/product/edit', token, `&product_id=${id}`),
  },

  async install() {
    const { token, cookie } = await login()
    const get = (path) => fetch(`${BASE}${path}`, { headers: { cookie } })
    const installed = sql('oc3', "SELECT COUNT(*) FROM oc_extension WHERE type = 'module' AND code = 'neocarts'")[0][0] !== '0'
    if (!installed) {
      const res = await get(admin('extension/extension/module/install', token, '&extension=neocarts'))
      if (!res.ok) throw new Error(`oc3 install failed: HTTP ${res.status}`)
    }
    // opening the settings page migrates the tables and re-registers the events
    const res = await get(admin('extension/module/neocarts', token))
    const html = await res.text()
    if (!res.ok || !html.includes('form-neocarts')) throw new Error(`oc3 settings page failed: HTTP ${res.status}\n${html.slice(0, 500)}`)
  },

  async adminLogin(page) {
    await page.goto('/admin/index.php?route=common/login')
    await page.fill('#input-username', 'admin')
    await page.fill('#input-password', 'admin')
    await page.click('button[type=submit]')
    await page.waitForURL(/user_token=/)
    return new URL(page.url()).searchParams.get('user_token')
  },

  async saveProductForm(page) {
    await page.click('button[form="form-product"]')
    await page.locator('.alert-success').waitFor()
  },
}
