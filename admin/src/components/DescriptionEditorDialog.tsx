import { useRef, useState } from 'react'
import type * as React from 'react'
import { createPortal } from 'react-dom'
import { FileText, X } from 'lucide-react'
import { LazyRichTextEditor } from './LazyRichTextEditor'
import { DialogFooter } from './DialogFooter'
import { SaveButton } from './SaveButton'
import { useUnsavedChanges } from './UnsavedChanges'

export function DescriptionEditorDialog({ title, subject, initialValue, onSave, onClose }: {
  title: string
  subject: string
  initialValue: string
  onSave: (value: string) => Promise<void>
  onClose: () => void
}) {
  const [value, setValue] = useState(initialValue)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [closing, setClosing] = useState(false)
  const baseline = useRef(initialValue)
  const { confirmDiscard, markClean } = useUnsavedChanges('описанието на категорията', value !== baseline.current)

  const close = async (afterSave = false) => {
    if (closing || (saving && !afterSave) || (!afterSave && !(await confirmDiscard()))) return
    if (afterSave) markClean()
    setClosing(true)
    window.setTimeout(onClose, 240)
  }

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    setSaving(true)
    setError('')
    try {
      await onSave(value)
      baseline.current = value
      await close(true)
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Описанието не можа да се запази.')
    } finally {
      setSaving(false)
    }
  }

  return createPortal(<div className="modal-backdrop description-dialog-backdrop" data-state={closing ? 'closed' : 'open'} onMouseDown={event => { if (event.target === event.currentTarget) void close() }}>
    <section className="description-dialog" data-state={closing ? 'closed' : 'open'} role="dialog" aria-modal="true" aria-labelledby="category-description-dialog-title">
      <header className="description-dialog-header">
        <div className="description-dialog-heading"><span className="description-dialog-icon"><FileText size={20}/></span><div><h2 id="category-description-dialog-title">{title}</h2><p>{subject}</p></div></div>
        <button type="button" className="icon-button editor-close-button" title="Затвори редактора" aria-label="Затвори редактора" onClick={() => void close()}><X size={19}/></button>
      </header>
      <form className="description-dialog-form" onSubmit={submit}>
        <main className="description-dialog-content"><p className="description-dialog-help">Редактирайте описанието с блокове, форматиране и медийно съдържание.</p><LazyRichTextEditor value={value} onChange={setValue} ariaLabel={title}/>{error&&<div className="form-error" role="alert">{error}</div>}</main>
        <DialogFooter className="description-dialog-footer"><button type="button" className="outline-button" onClick={() => void close()}>Отказ</button><SaveButton disabled={saving} loading={saving}>Запази описанието</SaveButton></DialogFooter>
      </form>
    </section>
  </div>, document.body)
}
