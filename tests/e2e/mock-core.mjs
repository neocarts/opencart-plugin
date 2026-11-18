// In-memory stand-in for the Shop API (core.neocarts.com) that the OpenCart shops call in e2e.
//   POST /v1/shop/orders/create           → 201 order (422 for an empty body, like the real one)
//   PUT  /v1/shop/orders/:id/items        → 200 order, 404 for an unknown id
//   PUT  /v1/shop/products/:id            → 201 first time, then 200
//   PUT  /v1/shop/products/:id/price      → 200 { updated: 1 }
//   GET  /__requests                      → every Shop API request: [{ method, url, headers, body }]
//   POST /__next { status, body, match? } → the next request (whose url contains `match`) answers with this
//   POST /__reset                         → forget everything, restart ids
// Any request without `Authorization: Bearer nc_live_…` gets 401.
import { createServer } from 'node:http'

const port = Number(process.env.PORT ?? 8790)
let requests = []
let queue = []
let orders = new Map()
let products = new Set()
let seq = 0

const read = (req) =>
  new Promise((resolve) => {
    let data = ''
    req.on('data', (c) => {
      data += c
    })
    req.on('end', () => {
      try {
        resolve(data ? JSON.parse(data) : null)
      } catch {
        resolve(data)
      }
    })
  })

const send = (res, status, body) => {
  res.writeHead(status, { 'Content-Type': 'application/json' })
  res.end(JSON.stringify(body))
}

createServer(async (req, res) => {
  const url = req.url
  if (url === '/__requests') return send(res, 200, requests)
  if (req.method === 'POST' && url === '/__next') {
    queue.push(await read(req))
    return send(res, 200, {})
  }
  if (req.method === 'POST' && url === '/__reset') {
    requests = []
    queue = []
    orders = new Map()
    products = new Set()
    seq = 0
    return send(res, 200, {})
  }
  if (!url.startsWith('/v1/shop/')) return send(res, 404, { message: 'not found' })

  const body = await read(req)
  requests.push({ method: req.method, url, headers: req.headers, body })
  if (!/^Bearer nc_live_/.test(req.headers.authorization ?? '')) return send(res, 401, { message: 'Unauthenticated.' })

  const queued = queue.findIndex((q) => !q.match || url.includes(q.match))
  if (queued >= 0) {
    const [{ status, body: reply }] = queue.splice(queued, 1)
    return send(res, status, reply)
  }

  let m
  if (req.method === 'POST' && url === '/v1/shop/orders/create') {
    if (!body?.items?.length) return send(res, 422, { message: 'The items field is required.', errors: { items: ['required'] } })
    seq += 1
    const order = { id: `order-${seq}`, number: `261115-${String(seq).padStart(5, '0')}`, status: 'draft', items: body.items }
    orders.set(order.id, order)
    return send(res, 201, order)
  }
  if (req.method === 'PUT' && (m = url.match(/^\/v1\/shop\/orders\/([^/]+)\/items$/))) {
    const order = orders.get(decodeURIComponent(m[1]))
    if (!order) return send(res, 404, { message: 'Not found.' })
    order.items = body.items
    return send(res, 200, order)
  }
  if (req.method === 'PUT' && (m = url.match(/^\/v1\/shop\/products\/([^/]+)\/price$/))) {
    return send(res, 200, { updated: 1 })
  }
  if (req.method === 'PUT' && (m = url.match(/^\/v1\/shop\/products\/([^/]+)$/))) {
    const id = decodeURIComponent(m[1])
    const created = !products.has(id)
    products.add(id)
    return send(res, created ? 201 : 200, { id: `p-${id}`, external_id: id, ...body })
  }
  send(res, 404, { message: 'not found' })
}).listen(port, () => console.log(`mock core on :${port}`))
