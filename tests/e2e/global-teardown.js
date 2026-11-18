import { existsSync, readFileSync, unlinkSync } from 'node:fs'
import { backupFile, shopsUnderTest } from './global-setup.js'
import { applyDemoConfig } from './demo-config.js'
import { clearSettings, setSettings } from './shops/index.js'

// The demo's settings back, then the real demo credentials from .env (if any) on top.
export default async () => {
  for (const name of shopsUnderTest()) {
    if (!existsSync(backupFile(name))) continue
    const values = JSON.parse(readFileSync(backupFile(name), 'utf8'))
    clearSettings(name)
    setSettings(name, values)
    unlinkSync(backupFile(name))
  }
  applyDemoConfig(shopsUnderTest())
}
