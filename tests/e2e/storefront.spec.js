import { addToCart, expect, SUB, seedSelection, test } from './fixtures.js'
import { setSettings, sql } from './shops/index.js'

// Demo data of every branch: 40 iPhone (no options), 42 Apple Cinema 30" (required options).
const route = (shop, method) => `/index.php?route=${shop.routes.base}${shop.routes.sep}${method}`

test('the header carries the config, the widget script and neocarts.js once', async ({ page, shop }) => {
  await page.goto('/')
  await expect(page.locator('script[src="https://cdn.neocarts.com/bundle.js"]')).toHaveCount(1)
  await expect(page.locator('script[src="https://cdn.neocarts.com/bundle.js"]')).toHaveAttribute('data-shop-id', '00000000-0000-0000-0000-000000000000')
  await expect(page.locator('script[src*="neocarts.js"]')).toHaveCount(1)
  const cfg = await page.evaluate(() => window.NeocartsOpencart)
  expect(cfg.settings.api_key).toBeUndefined()
  expect(cfg.locale).toBe('en')
  expect(JSON.stringify(cfg)).not.toContain('nc_live_')
})

test('nothing is injected while the plugin is off', async ({ page, shop }) => {
  setSettings(shop.name, { status: 0 })
  await page.goto('/')
  await expect(page.locator('script[src*="cdn.neocarts.com"]')).toHaveCount(0)
})

test('product page: a button under «Add to cart» with the server price', async ({ page, shop, request }) => {
  const { products } = await (await request.get(route(shop, 'products') + '&ids=40,42')).json()
  expect(products['40'].external_id).toBe('40')
  expect(products['40'].has_options).toBe(false)
  expect(products['42'].has_options).toBe(true)
  expect(products['40'].image_url).toMatch(/^https?:\/\//)

  await page.goto('/index.php?route=product/product&product_id=40')
  const button = page.locator('neocarts-button')
  await expect(button).toHaveCount(1)
  await expect(button).toHaveAttribute('product-id', '40')
  await expect(button).toHaveAttribute('amount', String(products['40'].price))
  await expect(button).toHaveAttribute('title', products['40'].title)
  await expect(button).toHaveAttribute('checkout-url', /checkout\/cart/)
  // in its box directly after the add-to-cart button
  expect(await page.evaluate(() => document.querySelector('#button-cart').nextElementSibling.className)).toContain('neocarts-oc-slot--product')
})

test('product page: no button for a product with required options', async ({ page }) => {
  await page.goto('/index.php?route=product/product&product_id=42')
  await page.waitForTimeout(500)
  // 42's related products (40, 41) get their card buttons; 42 itself none
  await expect(page.locator('neocarts-button[product-id^="42"]')).toHaveCount(0)
})

test('product page placement can be switched off and moved', async ({ page, shop }) => {
  setSettings(shop.name, { product_status: 0 })
  await page.goto('/index.php?route=product/product&product_id=40')
  await page.waitForTimeout(500)
  await expect(page.locator('neocarts-button')).toHaveCount(0)

  setSettings(shop.name, { product_status: 1, product_position: 'before', product_class: 'my-nc', product_style: 'margin-bottom: 17px;' })
  await page.goto('/index.php?route=product/product&product_id=40')
  const box = page.locator('.neocarts-oc-slot.my-nc')
  await expect(box.locator('neocarts-button')).toHaveCount(1)
  await expect(box).toHaveCSS('margin-bottom', '17px')
  expect(await page.evaluate(() => document.querySelector('#button-cart').previousElementSibling.className)).toContain('my-nc')
})

test('cards: buttons on the home page and in a category, per-page toggles', async ({ page, shop }) => {
  await page.goto('/')
  await expect(page.locator('.product-thumb neocarts-button').first()).toBeAttached()
  const ids = await page.locator('.product-thumb neocarts-button').evaluateAll((els) => els.map((e) => e.getAttribute('product-id')))
  expect(ids).not.toContain('42')

  setSettings(shop.name, { card_on_home: 0 })
  await page.goto('/')
  await page.waitForTimeout(500)
  await expect(page.locator('.product-thumb neocarts-button')).toHaveCount(0)

  await page.goto('/index.php?route=product/category&path=20')
  await expect(page.locator('.product-thumb neocarts-button').first()).toBeAttached()
})

test('cart: a button on every line, variant id for options, removed products forgotten', async ({ page, shop }) => {
  await page.goto('/')
  await addToCart(page, shop, 40, 2)
  await seedSelection(page, { 40: SUB, 99: SUB })
  await page.goto('/index.php?route=checkout/cart')
  const button = page.locator('neocarts-button[product-id="40"]')
  await expect(button).toHaveCount(1)
  // 99 is not in the cart: the widget forgets it
  await expect.poll(() => page.evaluate(() => window.__nc.removed)).toContain('99')

  const { lines } = await (await page.request.get(route(shop, 'cart'))).json()
  expect(lines).toHaveLength(1)
  expect(lines[0]).toMatchObject({ external_id: '40', quantity: 2 })
})

test('subscribing adds the product to the cart', async ({ page, shop }) => {
  await page.goto('/index.php?route=product/product&product_id=40')
  await expect(page.locator('neocarts-button')).toHaveCount(1)
  await page.evaluate((sub) => window.__nc.select('40', sub), SUB)
  await expect
    .poll(async () => (await (await page.request.get(route(shop, 'cart'))).json()).lines.map((l) => l.external_id))
    .toEqual(['40'])
  // switching another product to one-time adds nothing
  await page.evaluate(() => window.__nc.select('33', { mode: 'one_time' }))
  await page.waitForTimeout(500)
  expect((await (await page.request.get(route(shop, 'cart'))).json()).lines).toHaveLength(1)
})

test('no button for a product switched off in the admin or out of stock', async ({ page, shop, request }) => {
  sql(shop.name, `REPLACE INTO oc_neocarts_product (external_id, product_id, updated_at) VALUES ('40', 40, NOW())`)
  sql(shop.name, `UPDATE oc_product SET quantity = 0 WHERE product_id = 43`)
  try {
    const { products } = await (await request.get(route(shop, 'products') + '&ids=40,43,44')).json()
    expect(products['40'].subscribable).toBe(false)
    expect(products['43'].subscribable).toBe(false)
    expect(products['44'].subscribable).toBe(true)
    await page.goto('/index.php?route=product/product&product_id=40')
    await page.waitForTimeout(500)
    await expect(page.locator('neocarts-button')).toHaveCount(0)
    await page.goto('/index.php?route=product/category&path=20')
    await expect(page.locator('neocarts-button[product-id="44"]')).toHaveCount(1)
    await expect(page.locator('neocarts-button[product-id="40"]')).toHaveCount(0)
    await expect(page.locator('neocarts-button[product-id="43"]')).toHaveCount(0)
  } finally {
    sql(shop.name, `DELETE FROM oc_neocarts_product WHERE product_id = 40; UPDATE oc_product SET quantity = 929 WHERE product_id = 43`)
  }
})

test('the plugin styles and the custom CSS reach the page', async ({ page, shop }) => {
  setSettings(shop.name, { custom_css: 'neocarts-button.neocarts-oc-button{margin-top:21px}' })
  await page.goto('/index.php?route=product/product&product_id=40')
  await expect(page.locator('neocarts-button')).toHaveCSS('margin-top', '21px')
  await expect(page.locator('#neocarts-oc-styles')).toHaveCount(1)
})

test('cards: the button sits inside the card', async ({ page }) => {
  await page.goto('/index.php?route=product/category&path=20')
  const button = page.locator('neocarts-button[product-id="40"]')
  await expect(button).toHaveCount(1)
  expect(await button.evaluate((b) => !!b.closest('.product-thumb'))).toBe(true)
  // the default box keeps the button off the theme's button row below it
  await expect(page.locator('.neocarts-oc-slot--card').first()).toHaveCSS('margin-bottom', '10px')
})
