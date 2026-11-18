// OpenCart 2.3.0.2 (:8082): admin over HTTP, routes, the product form. See shops/index.js.
const ORIGIN = 'http://localhost:8082'
const BASE = 'extension/module/neocarts'

/** logs in over plain HTTP; returns { cookie, token } */
const login = async () => {
  const res = await fetch(`${ORIGIN}/admin/index.php?route=common/login`, {
    method: 'POST',
    body: new URLSearchParams({ username: 'admin', password: 'admin' }),
    redirect: 'manual',
  })
  const cookie = (res.headers.getSetCookie?.() ?? [res.headers.get('set-cookie')]).map((c) => c.split(';')[0]).join('; ')
  const token = new URLSearchParams((res.headers.get('location') ?? '').split('?')[1]?.replace(/&amp;/g, '&')).get('token')
  if (!token) throw new Error(`oc2: admin login failed (${res.status})`)
  return { cookie, token }
}

export default {
  /** installs the module once; a second call sees the uninstall link and does nothing */
  install: async () => {
    const { cookie, token } = await login()
    const get = (route) => fetch(`${ORIGIN}/admin/index.php?route=${route}&token=${token}`, { headers: { cookie } }).then((r) => r.text())
    const list = await get('extension/extension/module')
    if (!/extension\/extension\/module\/uninstall[^"]*extension=neocarts"/.test(list.replace(/&amp;/g, '&'))) {
      await get('extension/extension/module/install&extension=neocarts')
    }
    // opening the settings page runs the schema migration
    await get(BASE)
  },

  adminLogin: async (page) => {
    await page.goto('/admin/index.php?route=common/login')
    await page.locator('input[name="username"]').fill('admin')
    await page.locator('input[name="password"]').fill('admin')
    await page.locator('button[type="submit"]').click()
    await page.waitForURL(/token=/)
    return new URL(page.url()).searchParams.get('token')
  },

  routes: {
    base: BASE,
    sep: '/',
    cartAdd: 'checkout/cart/add',
    checkout: 'checkout/checkout',
    extensionForm: (token) => `/admin/index.php?route=${BASE}&token=${token}`,
    productForm: (id, token) => `/admin/index.php?route=catalog/product/edit&product_id=${id}&token=${token}`,
    subscriptions: (token) => `/admin/index.php?route=${BASE}/subscriptions&token=${token}`,
  },

  prefix: 'neocarts_',
  code: 'neocarts',

  /** 2.3 posts the form and redirects to the product list */
  saveProductForm: async (page) => {
    await Promise.all([page.waitForURL(/route=catalog\/product&/), page.locator('button[form="form-product"]').click()])
  },
}
