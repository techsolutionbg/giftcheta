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

export function normalizeMoneyInput(value: string, separator: AdminSettings['decimalSeparator'] = ',') {
  const raw = value.trim().replace(/[^\d.,-]/g, '')
  if (!raw) return ''
  const negative = raw.startsWith('-')
  const unsigned = raw.replace(/-/g, '')
  const comma = unsigned.lastIndexOf(',')
  const dot = unsigned.lastIndexOf('.')
  const lastIndex = Math.max(comma, dot)
  if (lastIndex < 0) return `${negative ? '-' : ''}${unsigned.replace(/\D/g, '')}`

  const enteredSeparator = unsigned[lastIndex]
  const fraction = unsigned.slice(lastIndex + 1).replace(/\D/g, '')
  const wholePart = unsigned.slice(0, lastIndex).replace(/\D/g, '')
  const isGroupingOnly = enteredSeparator !== separator && (comma < 0) !== (dot < 0) && fraction.length === 3 && wholePart.length > 0
  if (isGroupingOnly) return `${negative ? '-' : ''}${wholePart}${fraction}`
  return `${negative ? '-' : ''}${wholePart}${fraction ? `.${fraction}` : '.'}`
}

export function formatMoneyInput(value: string | number | null | undefined, separator: AdminSettings['decimalSeparator'] = ',') {
  if (value === null || value === undefined || value === '') return ''
  const text = String(value)
  return separator === ',' ? text.replace('.', ',') : text.replace(',', '.')
}
