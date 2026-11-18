import { test as base, expect } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { createHmac } from 'node:crypto'
import { E2E, e2eSettings } from './global-setup.js'
import { clearSettings, setSettings, shop, sql } from './shops/index.js'

export { expect, sql }

// the Shop API stand-in of one shop (tests/e2e/mock-core.mjs; host port 879<branch>)
const mockCore = (name) => {
  const url = `http://localhost:879${name.slice(2)}`
  return {
    requests: () => fetch(`${url}/__requests`).then((r) => r.json()),
    next: (status, body, match) => fetch(`${url}/__next`, { method: 'POST', body: JSON.stringify({ status, body, match }) }),
    reset: () => fetch(`${url}/__reset`, { method: 'POST' }),
  }
}

export const test = base.extend({
  shop: async ({}, use, info) => {
    await use(shop(info.project.name))
  },
  // every test: a clean mock core, the plugin defaults + the e2e credentials (a spec may change settings)
  core: [
    async ({ shop }, use) => {
      const core = mockCore(shop.name)
      await core.reset()
      clearSettings(shop.name)
      setSettings(shop.name, e2eSettings(shop.name))
      await use(core)
    },
    { auto: true },
  ],
  page: async ({ page }, use) => {
    // the widget is not under test: its CDN script never loads, a stub of what neocarts.js calls stands in
    await page.route('https://cdn.neocarts.com/**', (route) => route.fulfill({ contentType: 'text/javascript', body: '' }))
    await page.route(/fonts\.(googleapis|gstatic)\.com/, (route) => route.abort())
    await page.addInitScript(stubWidget)
    await use(page)
  },
})

// window.NeocartsSubscription reduced to what src/shared/js/neocarts.js calls. Records calls in
// window.__nc; specs drive it: __nc.emit(event, detail), __nc.select(id, selection) (the shopper
// picking a frequency, delivered to subscribe()), __nc.openError = '…'. Selections live in
// sessionStorage so they survive navigation, like the real widget's storage.
const stubWidget = () => {
  const subscribers = []
  const load = () => JSON.parse(sessionStorage.getItem('e2e:selection') ?? '{}')
  const nc = {
    open: [],
    removed: [],
    openError: null,
    emit: (name, detail) => document.dispatchEvent(new CustomEvent(name, { detail })),
    select: (id, selection) => {
      const all = { ...load(), [id]: selection }
      sessionStorage.setItem('e2e:selection', JSON.stringify(all))
      for (const fn of subscribers) fn(all)
    },
  }
  window.__nc = nc
  window.NeocartsSubscription = {
    events: { Ready: 'neocarts:ready', Paid: 'neocarts:paid', OrderMissing: 'neocarts:order-missing', Close: 'neocarts:close' },
    all: load,
    getOrderItems: (lines) => lines.map((l) => ({ ...l, ...(load()[l.external_id] ?? { mode: 'one_time' }) })),
    remove: (ids) => {
      const all = load()
      for (const id of [].concat(ids)) {
        nc.removed.push(id)
        delete all[id]
      }
      sessionStorage.setItem('e2e:selection', JSON.stringify(all))
    },
    subscribe: (fn) => {
      subscribers.push(fn)
      return () => {}
    },
    on: (name, fn) => {
      const h = (e) => fn(e.detail)
      document.addEventListener(name, h)
      return () => document.removeEventListener(name, h)
    },
    open: async ({ orderId }) => {
      nc.open.push(orderId)
      if (nc.openError) {
        const message = nc.openError
        nc.openError = null
        throw new Error(message)
      }
    },
  }
}

export const SUB = { mode: 'subscription', interval_value: 4, interval_unit: 'week' }

/** the shopper's choice, set before the page loads */
export const seedSelection = (page, selection) =>
  page.addInitScript((value) => {
    if (!sessionStorage.getItem('e2e:seeded')) {
      sessionStorage.setItem('e2e:selection', JSON.stringify(value))
      sessionStorage.setItem('e2e:seeded', '1')
    }
  }, selection)

/** adds a product through the shop's own cart endpoint, in the page's session */
export const addToCart = async (page, shop, productId, quantity = 1, extra = {}) => {
  const res = await page.request.post(`${shop.url}/index.php?route=${shop.routes.cartAdd}`, {
    form: { product_id: String(productId), quantity: String(quantity), ...extra },
  })
  expect(res.ok()).toBeTruthy()
}

/** POSTs a fixture from tests/fixtures as a signed webhook; `patch` changes the envelope */
export const webhook = async (shop, fixture, patch = (e) => e, secret = E2E.webhook_secret) => {
  const envelope = patch(JSON.parse(readFileSync(`tests/fixtures/${fixture}.json`, 'utf8')))
  const body = JSON.stringify(envelope)
  const t = Math.floor(Date.now() / 1000)
  const v1 = createHmac('sha256', secret).update(`${t}.${body}`).digest('hex')
  const res = await fetch(`${shop.url}/index.php?route=${shop.routes.base}${shop.routes.sep}webhook`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Neocarts-Event': envelope.event,
      'X-Neocarts-Delivery': envelope.id,
      'X-Neocarts-Signature': `t=${t},v1=${v1}`,
    },
    body,
  })
  return { status: res.status, envelope }
}
