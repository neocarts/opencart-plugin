// make demo-config: the demo shop's real credentials from .env into every shop of the stand.
// Also called by global-teardown.js, so the stand is the live demo again after e2e.
import { existsSync, readFileSync } from 'node:fs'
import { setSettings } from './shops/index.js'

export const demoSettings = () => {
  if (!existsSync('.env')) return null
  const env = Object.fromEntries(
    readFileSync('.env', 'utf8').split('\n').filter((l) => /^\w+=/.test(l)).map((l) => [l.slice(0, l.indexOf('=')), l.slice(l.indexOf('=') + 1).trim()]),
  )
  if (!env.NEOCARTS_SHOP_ID || !env.NEOCARTS_API_KEY) return null
  return {
    status: 1,
    shop_id: env.NEOCARTS_SHOP_ID,
    api_key: env.NEOCARTS_API_KEY,
    webhook_secret: env.NEOCARTS_WEBHOOK_SECRET ?? '',
    api_url: env.NEOCARTS_API_URL || 'https://core.neocarts.com',
  }
}

export const applyDemoConfig = (names) => {
  const values = demoSettings()
  if (!values) return false
  for (const name of names) setSettings(name, values)
  return true
}

if (process.argv[1]?.endsWith('demo-config.js')) {
  const names = (process.argv[2] ?? 'oc2,oc3,oc4').split(',')
  console.log(applyDemoConfig(names) ? `demo credentials from .env → ${names.join(', ')}` : '.env has no NEOCARTS_SHOP_ID / NEOCARTS_API_KEY')
}
