import { randomUUID } from 'node:crypto'
import { expect, sql, test, webhook } from './fixtures.js'
import { setSettings } from './shops/index.js'

// every test uses fresh ids: the shop's tables keep earlier runs
const fresh = (e) => ({ ...e, id: randomUUID(), data: { ...e.data, id: randomUUID(), number: `E2E-${Date.now()}` } })
const ocOrder = (shop, neocartsId) =>
  sql(shop.name, `SELECT o.order_id, o.total, o.payment_method, o.order_status_id, o.email, o.shipping_city, o.comment
    FROM oc_order o JOIN oc_neocarts_order n ON n.oc_order_id = o.order_id WHERE n.neocarts_order_id = '${neocartsId}'`)

test('order.paid creates an ordinary OpenCart order, once', async ({ shop }) => {
  const { status, envelope } = await webhook(shop, 'order.paid', fresh)
  expect(status).toBe(200)
  const rows = ocOrder(shop, envelope.data.id)
  expect(rows).toHaveLength(1)
  // payment_method: «Neocarts (…)» text in 2.3/3.0, JSON with code `neocarts…` in 4.1 (no payment_code column)
  const [orderId, total, paymentMethod, statusId, email, city, comment] = rows[0]
  expect(Number(total)).toBeCloseTo(2315.7, 2)
  expect(paymentMethod).toMatch(/neocarts/i)
  expect(statusId).toBe('2')
  expect(email).toBe('olena@example.com')
  expect(city).toBe('Київ')
  expect(comment).toContain(envelope.data.number)
  expect(comment).toContain('https://shop.neocarts.com/')

  const products = sql(shop.name, `SELECT product_id, quantity FROM oc_order_product WHERE order_id = ${orderId}`)
  expect(products).toEqual([['42', '1']])
  const options = sql(shop.name, `SELECT product_option_value_id FROM oc_order_option WHERE order_id = ${orderId} ORDER BY 1`)
  expect(options.map((r) => r[0])).toEqual(['1', '5', '8'])

  // the retry (same delivery id) and a resend of the same order change nothing
  expect((await webhook(shop, 'order.paid', () => envelope)).status).toBe(200)
  expect((await webhook(shop, 'order.paid', (e) => ({ ...envelope, id: randomUUID() }))).status).toBe(200)
  expect(ocOrder(shop, envelope.data.id)).toHaveLength(1)
})

test('renewals and widget orders can each be switched off', async ({ shop }) => {
  setSettings(shop.name, { orders_renewal: 0 })
  const { envelope } = await webhook(shop, 'order.paid', (e) => {
    const f = fresh(e)
    return { ...f, data: { ...f.data, source: 'subscription' } }
  })
  expect(ocOrder(shop, envelope.data.id)).toHaveLength(0)
  const log = sql(shop.name, `SELECT status FROM oc_neocarts_webhook WHERE delivery_id = '${envelope.id}'`)
  expect(log).toEqual([['ignored']])
})

test('a bad signature is refused and nothing is created', async ({ shop }) => {
  const { status, envelope } = await webhook(shop, 'order.paid', fresh, 'whsec_wrong')
  expect(status).toBe(401)
  expect(ocOrder(shop, envelope.data.id)).toHaveLength(0)
})

test('payment.refunded moves the order to the refund status', async ({ shop }) => {
  const paid = await webhook(shop, 'order.paid', fresh)
  const [[orderId]] = ocOrder(shop, paid.envelope.data.id)
  const full = await webhook(shop, 'payment.refunded', (e) => ({
    ...e, id: randomUUID(), data: { ...e.data, order_id: paid.envelope.data.id, status: 'refunded', refunded_amount: 231570, refund_amount: 231570 },
  }))
  expect(full.status).toBe(200)
  expect(sql(shop.name, `SELECT order_status_id FROM oc_order WHERE order_id = ${orderId}`)).toEqual([['11']])
  const history = sql(shop.name, `SELECT comment FROM oc_order_history WHERE order_id = ${orderId} ORDER BY order_history_id DESC LIMIT 1`)
  expect(history[0][0]).toContain('2315')
})

test('an unknown event is answered 200 and logged as ignored', async ({ shop }) => {
  const { status, envelope } = await webhook(shop, 'order.paid', (e) => ({ ...fresh(e), event: 'something.new' }))
  expect(status).toBe(200)
  expect(sql(shop.name, `SELECT status FROM oc_neocarts_webhook WHERE delivery_id = '${envelope.id}'`)).toEqual([['ignored']])
})

test('subscription and payment.failed events are acknowledged and left to the Neocarts panel', async ({ shop }) => {
  for (const event of ['subscription.created', 'payment.failed']) {
    const { status, envelope } = await webhook(shop, 'order.paid', (e) => ({ ...fresh(e), event }))
    expect(status).toBe(200)
    expect(sql(shop.name, `SELECT status FROM oc_neocarts_webhook WHERE delivery_id = '${envelope.id}'`)).toEqual([['ignored']])
  }
})
