import { useEffect, useRef, useState } from 'react'
import type * as React from 'react'
import { createPortal } from 'react-dom'
import { Check, FileText, Save, X } from 'lucide-react'
import { api, json } from '../lib/api'
import type { Product, ToastState } from '../types/admin'
import { LazyRichTextEditor } from './LazyRichTextEditor'
import { useUnsavedChanges } from './UnsavedChanges'

type ProductDescriptionDialogProps = {
  product: Pick<Product, 'id' | 'name'>
  notify: (message: string, kind?: ToastState['kind']) => void
  onClose: () => void
  onSaved?: (description: string) => void
}

export function ProductDescriptionDialog({ product, notify, onClose, onSaved }: ProductDescriptionDialogProps) {
  const [description, setDescription] = useState('')
  const [busy, setBusy] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [loadError, setLoadError] = useState(false)
  const [retry, setRetry] = useState(0)
  const [closing, setClosing] = useState(false)
  const initialDescription = useRef('')
  const { confirmDiscard, markClean } = useUnsavedChanges('описанието на продукта', !busy && !loadError && description !== initialDescription.current)

  useEffect(() => {
    let active = true
    void api<{ data: Product & { description?: string | null } }>(`/products/${product.id}`).then(result => {
      if (!active) return
      const loadedDescription = result.data.description || ''
      initialDescription.current = loadedDescription
      setDescription(loadedDescription)
      setError('')
      setLoadError(false)
    }).catch(err => {
      if (active) { setError(err instanceof Error ? err.message : 'Продуктът не можа да се зареди.'); setLoadError(true) }
    }).finally(() => { if (active) setBusy(false) })
    return () => { active = false }
  }, [product.id, retry])

  const close = async (afterSave = false) => {
    if (closing || (saving && !afterSave) || (!afterSave && !(await confirmDiscard()))) return
    if (afterSave) markClean()
    setClosing(true)
    window.setTimeout(onClose, 240)
  }

  const save = async (event: React.FormEvent) => {
    event.preventDefault()
    setSaving(true)
    setError('')
    try {
      await api(`/products/${product.id}`, { method: 'PATCH', body: json({ description }) })
      initialDescription.current = description
      onSaved?.(description)
      notify('Описанието е запазено.')
      close(true)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Описанието не можа да се запази.')
    } finally {
      setSaving(false)
    }
  }

  return createPortal(<div className="modal-backdrop description-dialog-backdrop" data-state={closing ? 'closed' : 'open'} onMouseDown={event => { if (event.target === event.currentTarget) close() }}>
    <section className="description-dialog" data-state={closing ? 'closed' : 'open'} role="dialog" aria-modal="true" aria-labelledby="product-description-dialog-title">
      <header className="description-dialog-header">
        <div className="description-dialog-heading"><span className="description-dialog-icon"><FileText size={20}/></span><div><h2 id="product-description-dialog-title">Описание на продукта</h2><p>{product.name}</p></div></div>
        <button type="button" className="icon-button editor-close-button" title="Затвори редактора" aria-label="Затвори редактора" onClick={() => close()}><X size={19}/></button>
      </header>
      <form className="description-dialog-form" onSubmit={save}>
        <main className="description-dialog-content">
          {busy ? <div className="loading-state"><span className="spinner"/> Зареждаме описанието…</div> : loadError ? <div className="table-error" role="alert">{error}<button type="button" onClick={() => { setBusy(true); setError(''); setRetry(value => value + 1) }}>Опитай отново</button></div> : <>
            <p className="description-dialog-help">Редактирайте пълното описание с блокове, форматиране и медийно съдържание.</p>
            <LazyRichTextEditor value={description} onChange={setDescription} ariaLabel="Пълно описание на продукта"/>
            {error && <div className="form-error" role="alert">{error}</div>}
          </>}
        </main>
        <footer className="description-dialog-footer"><button type="button" className="outline-button" onClick={() => close()}>Отказ</button><button type="submit" className="primary-button" disabled={busy || saving || loadError}><Save size={16}/>{saving ? 'Запазване…' : 'Запази описанието'}<Check size={15}/></button></footer>
      </form>
    </section>
  </div>, document.body)
}
