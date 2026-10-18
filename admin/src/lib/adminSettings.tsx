import { createContext, useContext, useState } from 'react'
import type * as React from 'react'

export type AdminSettings = {
  decimalSeparator: '.' | ','
  defaultProductStatus: 'draft' | 'active'
  productsPerPage: 10 | 20 | 50
}

const STORAGE_KEY = 'giftcheta-admin-settings'
const defaults: AdminSettings = { decimalSeparator: ',', defaultProductStatus: 'draft', productsPerPage: 20 }

function readSettings(): AdminSettings {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') as Partial<AdminSettings>
    return {
      decimalSeparator: saved.decimalSeparator === '.' ? '.' : ',',
      defaultProductStatus: saved.defaultProductStatus === 'active' ? 'active' : 'draft',
      productsPerPage: saved.productsPerPage === 10 || saved.productsPerPage === 50 ? saved.productsPerPage : 20,
    }
  } catch { return defaults }
}

const SettingsContext = createContext<{ settings: AdminSettings; updateSettings: (patch: Partial<AdminSettings>) => void } | null>(null)

export function AdminSettingsProvider({ children }: { children: React.ReactNode }) {
  const [settings, setSettings] = useState(readSettings)
  const updateSettings = (patch: Partial<AdminSettings>) => setSettings(current => {
    const next = { ...current, ...patch }
    localStorage.setItem(STORAGE_KEY, JSON.stringify(next))
    return next
  })
  return <SettingsContext.Provider value={{ settings, updateSettings }}>{children}</SettingsContext.Provider>
}

export function useAdminSettings() {
  const context = useContext(SettingsContext)
  if (!context) throw new Error('useAdminSettings must be used inside AdminSettingsProvider')
  return context
}

export function formatMoney(value: number | string | null | undefined, separator: AdminSettings['decimalSeparator'] = ',') {
  const amount = Number(value)
  if (!Number.isFinite(amount)) return '€0' + separator + '00'
  const formatted = new Intl.NumberFormat(separator === ',' ? 'de-DE' : 'en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)
  return `€${formatted}`
}
