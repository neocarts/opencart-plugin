import { addToCart, expect, SUB, seedSelection, test } from './fixtures.js'
import { setSettings, sql } from './shops/index.js'

const route = (shop, method) => `/index.php?route=${shop.routes.base}${shop.routes.sep}${method}`
const checkoutLink = (page) => page.locator('#content a[href*="checkout/checkout"]').first()

test('a cart with a subscription opens Neocarts checkout with server prices', async ({ page, shop, core }) => {
  await page.goto('/')
  await addToCart(page, shop, 40, 2)
  await seedSelection(page, { 40: SUB })
  await page.goto('/index.php?route=checkout/cart')
  await checkoutLink(page).click()
  await expect.poll(() => page.evaluate(() => window.__nc.open)).toEqual(['order-1'])

  const [req] = await core.requests()
  expect(req.method).toBe('POST')
  expect(req.headers.authorization).toBe('Bearer nc_live_e2e')
  expect(req.headers['accept-language']).toBe('en')
  expect(req.headers['user-agent']).toMatch(/^neocarts-opencart\//)
  expect(req.body.locale).toBe('en')
  expect(req.body.redirect_url).toMatch(/checkout\/cart.*neocarts=checkout/)
  const { products } = await (await page.request.get(route(shop, 'products') + '&ids=40')).json()
  expect(req.body.items).toEqual([
    expect.objectContaining({ external_id: '40', price: products['40'].price, quantity: 2, mode: 'subscription', interval_value: 4, interval_unit: 'week' }),
  ])
  await expect(page).toHaveURL(/checkout\/cart/)
})

test('the second checkout replaces the items of the same order; a gone order is recreated', async ({ page, shop, core }) => {
  await page.goto('/')
  await addToCart(page, shop, 40)
  await seedSelection(page, { 40: SUB })
  await page.goto('/index.php?route=checkout/cart')
  await checkoutLink(page).click()
  await expect.poll(() => page.evaluate(() => window.__nc.open.length)).toBe(1)
  await checkoutLink(page).click()
  await expect.poll(() => page.evaluate(() => window.__nc.open)).toEqual(['order-1', 'order-1'])
  await core.next(409, { message: 'The items of an order in checkout can no longer be changed.' }, '/items')
  await checkoutLink(page).click()
  await expect.poll(() => page.evaluate(() => window.__nc.open)).toEqual(['order-1', 'order-1', 'order-2'])
  expect((await core.requests()).map((r) => `${r.method} ${r.url}`)).toEqual([
    'POST /v1/shop/orders/create',
    'PUT /v1/shop/orders/order-1/items',
    'PUT /v1/shop/orders/order-1/items',
    'POST /v1/shop/orders/create',
  ])
})

test('a cart without subscriptions goes to the store checkout', async ({ page, shop, core }) => {
  await page.goto('/')
  await addToCart(page, shop, 40)
  await page.goto('/index.php?route=checkout/cart')
  await checkoutLink(page).click()
  await expect(page).toHaveURL(/checkout\/checkout/)
  expect(await core.requests()).toEqual([])
})

test('interception can be switched off', async ({ page, shop, core }) => {
  setSettings(shop.name, { checkout_intercept: 0 })
  await page.goto('/')
  await addToCart(page, shop, 40)
  await seedSelection(page, { 40: SUB })
  await page.goto('/index.php?route=checkout/cart')
  await checkoutLink(page).click()
  await expect(page).toHaveURL(/checkout\/checkout/)
  expect(await core.requests()).toEqual([])
})

test('a Shop API error is shown, a quantity over 9 is refused before the API', async ({ page, shop, core }) => {
  await page.goto('/')
  await addToCart(page, shop, 40, 10)
  await seedSelection(page, { 40: SUB })
  await page.goto('/index.php?route=checkout/cart')
  await checkoutLink(page).click()
  await expect(page.locator('.neocarts-oc-toast')).toContainText('9')
  expect(await core.requests()).toEqual([])
})

test('paid: the cart is emptied, closing goes to the success page', async ({ page, shop }) => {
  await page.goto('/')
  await addToCart(page, shop, 40)
  await seedSelection(page, { 40: SUB })
  await page.goto('/index.php?route=checkout/cart')
  await checkoutLink(page).click()
  await expect.poll(() => page.evaluate(() => window.__nc.open.length)).toBe(1)
  await page.evaluate(() => window.__nc.emit('neocarts:paid', { number: '261115-00001' }))
  await expect.poll(async () => (await (await page.request.get(route(shop, 'cart'))).json()).lines).toEqual([])
  await page.evaluate(() => window.__nc.emit('neocarts:close', {}))
  await expect(page).toHaveURL(/checkout\/success/)
})

test('the checkout page shows the banner for a subscription cart', async ({ page, shop }) => {
  await page.goto('/')
  await addToCart(page, shop, 40)
  await seedSelection(page, { 40: SUB })
  await page.goto('/index.php?route=checkout/checkout')
  await expect(page.locator('.neocarts-oc-banner')).toBeVisible()
})

test('a variant with a switched-off option value gets no cart button and goes one-time', async ({ page, shop, core }) => {
  sql(shop.name, `REPLACE INTO oc_neocarts_product (external_id, product_id, updated_at) VALUES ('42-5', 42, NOW())`)
  // the demo's required file option needs an upload: not what this test is about
  const fileOption = `oc_product_option po JOIN oc_option o ON o.option_id = po.option_id SET po.required = %d WHERE po.product_id = 42 AND o.type = 'file'`
  sql(shop.name, `UPDATE ${fileOption.replace('%d', '0')}`)
  try {
    await page.goto('/')
    // product 42 with its required options: select 1, radio 5 (switched off), checkbox 8, text, textarea, date…
    const { lines } = await (await page.request.get(`/index.php?route=${shop.routes.base}${shop.routes.sep}cart`)).json()
    expect(lines).toEqual([])
    await addToCart(page, shop, 40)
    await seedSelection(page, { 40: SUB })
    const opts = sql(shop.name, `SELECT po.product_option_id, o.type, IFNULL(MIN(pov.product_option_value_id), '') FROM oc_product_option po
      JOIN oc_option o ON o.option_id = po.option_id LEFT JOIN oc_product_option_value pov ON pov.product_option_id = po.product_option_id
      WHERE po.product_id = 42 AND po.required = 1 AND o.type <> 'file' GROUP BY po.product_option_id, o.type`)
    const form = {}
    for (const [poid, type, firstValue] of opts) {
      const value = type === 'radio' ? '5' : firstValue
      if (type === 'checkbox') form[`option[${poid}][]`] = value
      else form[`option[${poid}]`] = value || (type === 'date' ? '2026-12-01' : type === 'time' ? '10:00' : type === 'datetime' ? '2026-12-01 10:00' : 'x')
    }
    await addToCart(page, shop, 42, 1, form)
    const cart = (await (await page.request.get(`/index.php?route=${shop.routes.base}${shop.routes.sep}cart`)).json()).lines
    const variant = cart.find((l) => l.external_id.startsWith('42-'))
    expect(variant.external_id).toContain('5')
    expect(variant.subscribable).toBe(false)
    await page.goto('/index.php?route=checkout/cart')
    await expect(page.locator(`neocarts-button[product-id="${variant.external_id}"]`)).toHaveCount(0)
  } finally {
    sql(shop.name, `DELETE FROM oc_neocarts_product WHERE product_id = 42`)
    sql(shop.name, `UPDATE ${fileOption.replace('%d', '1')}`)
  }
})

test('a switched-off product goes one-time even if the browser says subscription', async ({ page, shop, core }) => {
  sql(shop.name, `REPLACE INTO oc_neocarts_product (external_id, product_id, updated_at) VALUES ('40', 40, NOW())`)
  try {
    await page.goto('/')
    await addToCart(page, shop, 40)
    await addToCart(page, shop, 44)
    await seedSelection(page, { 40: SUB, 44: SUB })
    await page.goto('/index.php?route=checkout/cart')
    await expect(page.locator('neocarts-button[product-id="40"]')).toHaveCount(0)
    await expect(page.locator('neocarts-button[product-id="44"]')).toHaveCount(1)
    await checkoutLink(page).click()
    await expect.poll(() => page.evaluate(() => window.__nc.open.length)).toBe(1)
    const [req] = await core.requests()
    expect(req.body.items.find((i) => i.external_id === '40').mode).toBe('one_time')
    expect(req.body.items.find((i) => i.external_id === '44').mode).toBe('subscription')
  } finally {
    sql(shop.name, `DELETE FROM oc_neocarts_product WHERE product_id = 40`)
  }
})
