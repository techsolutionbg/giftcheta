import { CloseButton } from './CloseButton'
import { useRef, useState } from 'react'
import type * as React from 'react'
import { createPortal } from 'react-dom'
import { api, json } from '../lib/api'
import { MoneyInput } from './MoneyInput'
import { FormField } from './CatalogComponents'
import { DialogFooter } from './DialogFooter'
import { SaveButton } from './SaveButton'
import { useUnsavedChanges } from './UnsavedChanges'

type VariantRecord = { id: number; name: string; option_values?: Record<string, string>; color_hex?: string | null; sku?: string | null; price?: string | null; stock_quantity?: number | null }

export function ProductVariantDialog({ productId, variant, onClose, onSaved }: { productId: number; variant?: VariantRecord | null; onClose: () => void; onSaved: (variant: VariantRecord) => void }) {
  const originalOptions = variant?.option_values || {}
  const [color, setColor] = useState(originalOptions['Цвят'] || '')
  const [colorHex, setColorHex] = useState(variant?.color_hex || '#B44763')
  const [size, setSize] = useState(originalOptions['Размер'] || '')
  const [sku, setSku] = useState(variant?.sku || '')
  const [price, setPrice] = useState(variant?.price || '')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [closing, setClosing] = useState(false)
  const baseline = useRef(JSON.stringify({ color, colorHex, size, sku, price }))
  const dirty = JSON.stringify({ color, colorHex, size, sku, price }) !== baseline.current
  const { confirmDiscard, markClean } = useUnsavedChanges('варианта', dirty)

  const close = async (saved = false) => {
    if (closing || (!saved && saving) || (!saved && !(await confirmDiscard()))) return
    if (saved) markClean()
    setClosing(true)
    window.setTimeout(onClose, 240)
  }

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    setError('')
    if (!color.trim() && !size.trim()) { setError('Добавете стойност за цвят или размер.'); return }
    const options: Record<string, string> = { ...originalOptions, ...(color.trim() ? { 'Цвят': color.trim() } : {}), ...(size.trim() ? { 'Размер': size.trim() } : {}) }
    if (!color.trim()) delete options['Цвят']
    if (!size.trim()) delete options['Размер']
    const payload = { option_values: options, name: Object.values(options).join(' / '), color_hex: color.trim() ? colorHex : null, sku: sku.trim() || null, price: price || null, inventory_mode: 'tracked', stock_quantity: Number(variant?.stock_quantity ?? 0) }
    setSaving(true)
    try {
      let savedVariant: VariantRecord
      if (variant) {
        await api(`/variants/${variant.id}`, { method: 'PATCH', body: json(payload) })
        savedVariant = { ...variant, ...payload, id: variant.id }
      } else {
        const result = await api<{ data: { id: number } }>(`/products/${productId}/variants`, { method: 'POST', body: json(payload) })
        savedVariant = { ...payload, id: result.data.id }
      }
      onSaved(savedVariant)
      await close(true)
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Вариантът не можа да бъде запазен.')
    } finally { setSaving(false) }
  }

  return createPortal(<div className="modal-backdrop variant-dialog-backdrop" data-state={closing ? 'closed' : 'open'} onMouseDown={event => { if (event.target === event.currentTarget) void close() }}>
    <section className="small-modal variant-editor-dialog" data-state={closing ? 'closed' : 'open'} role="dialog" aria-modal="true" aria-labelledby="variant-editor-title">
      <header className="editor-header"><div><h2 id="variant-editor-title">{variant ? 'Редакция на вариант' : 'Нов вариант'}</h2><p>{variant?.name || 'Добавете опция и складова наличност.'}</p></div><CloseButton className="editor-close-button" aria-label="Затвори" onClick={() => void close()}/></header>
      <form className="variant-editor-form" onSubmit={submit}>
        <main className="variant-editor-content">
          <div className="two-cols"><FormField label="Цвят" helpText="Името на цветния вариант, което ще вижда клиентът."><input value={color} onChange={event => setColor(event.target.value)} placeholder="Напр. Розов"/></FormField><FormField label="Размер" helpText="Размерът на този вариант, ако е приложимо."><input value={size} onChange={event => setSize(event.target.value)} placeholder="Напр. M"/></FormField></div>
          <FormField label="Цветови код" helpText="Изберете цвят за визуалния индикатор на варианта."><input className="variant-color-input" type="color" value={colorHex} onChange={event => setColorHex(event.target.value)} aria-label="Избери цвят"/></FormField>
          <div className="two-cols"><FormField label="SKU" helpText="Незадължителен складов код на варианта."><input value={sku} onChange={event => setSku(event.target.value)} placeholder="По избор"/></FormField><FormField label="Цена (€) · по избор" helpText="Допълнителна цена за този вариант."><MoneyInput value={price} onChange={setPrice}/></FormField></div>
          {error && <div className="form-error" role="alert">{error}</div>}
        </main>
        <DialogFooter className="variant-editor-footer"><button type="button" className="outline-button" onClick={() => void close()}>Отказ</button><SaveButton disabled={saving} loading={saving}>{variant ? 'Запази варианта' : 'Добави вариант'}</SaveButton></DialogFooter>
      </form>
    </section>
  </div>, document.body)
}
