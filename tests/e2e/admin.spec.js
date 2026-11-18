import { expect, sql, test } from './fixtures.js'

test('the settings page saves and keeps the key write-only', async ({ page, shop }) => {
  const token = await shop.adminLogin(page)
  await page.goto(shop.routes.extensionForm(token))
  await expect(page.getByRole('heading', { name: /Neocarts/ }).first()).toBeVisible()
  const key = page.locator('input[name$="api_key"]')
  await expect(key).toHaveValue('')
  await expect(page.locator('body')).not.toContainText('nc_live_e2e')
  await expect(page.locator('body')).toContainText(/index\.php\?route=extension.*webhook/)
})

test('«Test connection» reports a working key', async ({ page, shop }) => {
  const token = await shop.adminLogin(page)
  await page.goto(shop.routes.extensionForm(token))
  await page.getByRole('button', { name: 'Test connection' }).click()
  await expect(page.getByText('The key works.')).toBeVisible()
})

test('saving a product sends its card, never its price', async ({ page, shop, core }) => {
  const token = await shop.adminLogin(page)
  await page.goto(shop.routes.productForm(40, token))
  await shop.saveProductForm(page)
  await expect.poll(async () => (await core.requests()).map((r) => `${r.method} ${r.url}`)).toContain('PUT /v1/shop/products/40')
  const requests = await core.requests()
  const card = requests.find((r) => r.url === '/v1/shop/products/40').body
  expect(Object.keys(card).sort()).toEqual(['image_url', 'product_url', 'title', 'variant'])
  expect(requests.some((r) => r.url.endsWith('/price'))).toBe(false)
})

test('the product form switches the subscription off for the product or one option value', async ({ page, shop }) => {
  const token = await shop.adminLogin(page)
  try {
    await page.goto(shop.routes.productForm(42, token))
    const product = page.locator('input[name="neocarts_subscription[42]"]')
    const red = page.locator('input[name="neocarts_subscription[42-5]"]')
    await expect(product).toBeChecked()
    await expect(red).toBeChecked()
    await red.uncheck()
    await shop.saveProductForm(page)
    await expect.poll(() => sql(shop.name, 'SELECT external_id FROM oc_neocarts_product WHERE product_id = 42')).toEqual([['42-5']])
    await page.goto(shop.routes.productForm(42, token))
    await expect(page.locator('input[name="neocarts_subscription[42-5]"]')).not.toBeChecked()
    await expect(page.locator('input[name="neocarts_subscription[42]"]')).toBeChecked()
  } finally {
    sql(shop.name, 'DELETE FROM oc_neocarts_product WHERE product_id = 42')
  }
})

test('the product block links to the Neocarts panel for subscriptions and prices', async ({ page, shop }) => {
  const token = await shop.adminLogin(page)
  await page.goto(shop.routes.productForm(40, token))
  await expect(page.locator('input[name="neocarts_subscription[40]"]')).toBeChecked()
  await expect(page.locator('a[href="https://shop.neocarts.com/"]').first()).toBeAttached()
  await expect(page.getByRole('button', { name: 'Update subscription price' })).toHaveCount(0)
})

test('the admin has no plugin pages beyond the settings and the product block', async ({ page, shop }) => {
  const token = await shop.adminLogin(page)
  await page.goto(shop.routes.extensionForm(token))
  // tabs are role=link in Bootstrap 3 (2.3/3.0) and role=tab in Bootstrap 5 (4.x): match the text
  const tabs = page.locator('.nav-tabs a')
  for (const tab of ['Connection', 'Placement', 'Checkout', 'Orders']) await expect(tabs.filter({ hasText: new RegExp(`^\\s*${tab}\\s*$`) })).toHaveCount(1)
  for (const tab of ['Subscriptions', 'Products', 'Log']) await expect(tabs.filter({ hasText: tab })).toHaveCount(0)
  await expect(page.locator('body')).toContainText('support@neocarts.com')
})
