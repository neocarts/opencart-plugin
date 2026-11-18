import { mkdirSync, writeFileSync } from 'node:fs'
import { getSettings, setSettings, shop } from './shops/index.js'

export const E2E = {
  status: 1,
  shop_id: '00000000-0000-0000-0000-000000000000',
  api_key: 'nc_live_e2e',
  webhook_secret: 'whsec_e2e',
}

/** the shop's own mock core (docker-compose.yml) */
export const e2eSettings = (name) => ({ ...E2E, api_url: `http://mock-core-${name}:8790` })

/** CI brings up one shop per job (E2E_SHOPS=oc3); locally all three */
export const shopsUnderTest = () => (process.env.E2E_SHOPS ?? 'oc2,oc3,oc4').split(',')

/** one file per shop: parallel runs on different shops must not restore each other's settings */
export const backupFile = (name) => `test-results/settings-backup-${name}.json`

// Installs the extension in every shop under test, keeps the demo's own settings aside and points
// the shops at their mock core. global-teardown.js puts the demo settings back.
export default async () => {
  mkdirSync('test-results', { recursive: true })
  for (const name of shopsUnderTest()) {
    await shop(name).install()
    writeFileSync(backupFile(name), JSON.stringify(getSettings(name)))
    setSettings(name, e2eSettings(name))
  }
}
