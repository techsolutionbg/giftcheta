import { CloseButton } from './CloseButton'
import { useState } from 'react'
import type * as React from 'react'
import { createPortal } from 'react-dom'
import { api, json } from '../lib/api'
import { FormField } from './CatalogComponents'
import { DialogFooter } from './DialogFooter'
import { SaveButton } from './SaveButton'

export function VariantStockDialog({ productId, variants, onClose, onSaved }: {
  productId: number
  variants: { id: number; name: string; stock_quantity?: number | null }[]
  onClose: () => void
  onSaved: (quantity: number) => void
}) {
  const [quantity, setQuantity] = useState(String(variants[0]?.stock_quantity ?? 0))
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [closing, setClosing] = useState(false)
  const close = () => {
    if (saving || closing) return
    setClosing(true)
    window.setTimeout(onClose, 240)
  }
  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    setError('')
    if (!/^\d+$/.test(quantity)) { setError('Въведете цяло неотрицателно число.'); return }
    setSaving(true)
    try {
      const value = Number(quantity)
      for (const variant of variants) {
        await api(`/products/${productId}/variants/${variant.id}/stock`, { method: 'POST', body: json({ quantity: value, note: variants.length > 1 ? 'Групова корекция от администрацията' : 'Корекция от администрацията' }) })
      }
      onSaved(value)
      setClosing(true)
      window.setTimeout(onClose, 240)
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Наличността не можа да се актуализира.')
    } finally { setSaving(false) }
  }
  return createPortal(<div className="modal-backdrop variant-dialog-backdrop" data-state={closing ? 'closed' : 'open'} onMouseDown={event => { if (event.target === event.currentTarget) close() }}>
    <section className="small-modal variant-editor-dialog variant-stock-dialog" data-state={closing ? 'closed' : 'open'} role="dialog" aria-modal="true" aria-labelledby="variant-stock-title">
      <header className="editor-header"><div><h2 id="variant-stock-title">{variants.length > 1 ? 'Наличност на варианти' : 'Наличност на вариант'}</h2><p>{variants.length > 1 ? `Избрани варианти: ${variants.length}` : variants[0]?.name}</p></div><CloseButton className="editor-close-button" aria-label="Затвори" onClick={close}/></header>
      <form className="variant-editor-form" onSubmit={submit}>
        <main className="variant-editor-content"><FormField label="Количество в момента" helpText="Задайте текущия брой налични бройки за този вариант."><input type="number" min="0" step="1" autoFocus value={quantity} onChange={event => setQuantity(event.target.value)}/></FormField>{error && <div className="form-error" role="alert">{error}</div>}</main>
        <DialogFooter className="variant-editor-footer"><button type="button" className="outline-button" onClick={close}>Отказ</button><SaveButton disabled={saving} loading={saving} loadingLabel="Запис…">Запази наличността</SaveButton></DialogFooter>
      </form>
    </section>
  </div>, document.body)
}
