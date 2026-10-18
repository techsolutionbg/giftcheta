import { createContext, useCallback, useContext, useEffect, useId, useMemo, useRef, useState } from 'react'
import type { ReactNode } from 'react'
import { AlertTriangle } from 'lucide-react'
import { useBlocker } from 'react-router-dom'

type ConfirmOptions = { title: string; message: string; confirmLabel?: string; cancelLabel?: string; danger?: boolean }
type UnsavedEntry = { label: string }
type UnsavedChangesContextValue = {
  register: (id: string, label: string, dirty: boolean) => void
  confirm: (options: ConfirmOptions) => Promise<boolean>
  confirmDiscard: (id: string) => Promise<boolean>
  confirmDiscardAll: () => Promise<boolean>
}

const UnsavedChangesContext = createContext<UnsavedChangesContextValue | null>(null)

export function UnsavedChangesProvider({ children }: { children: ReactNode }) {
  const entries = useRef(new Map<string, UnsavedEntry>())
  const resolver = useRef<((confirmed: boolean) => void) | null>(null)
  const blockerPrompting = useRef(false)
  const [revision, setRevision] = useState(0)
  const [request, setRequest] = useState<ConfirmOptions | null>(null)
  const [closingConfirmation, setClosingConfirmation] = useState(false)
  const hasUnsavedChanges = entries.current.size > 0
  const blocker = useBlocker(hasUnsavedChanges)

  const register = useCallback((id: string, label: string, dirty: boolean) => {
    const current = entries.current.get(id)
    if (dirty && current?.label === label) return
    if (!dirty && !current) return
    if (dirty) entries.current.set(id, { label })
    else entries.current.delete(id)
    setRevision(value => value + 1)
  }, [])

  const confirm = useCallback((options: ConfirmOptions) => new Promise<boolean>(resolve => {
    resolver.current?.(false)
    resolver.current = resolve
    setClosingConfirmation(false)
    setRequest(options)
  }), [])

  const settle = useCallback((confirmed: boolean) => {
    if (!resolver.current || closingConfirmation) return
    const resolve = resolver.current
    resolver.current = null
    setClosingConfirmation(true)
    window.setTimeout(() => {
      setRequest(null)
      setClosingConfirmation(false)
      resolve(confirmed)
    }, 170)
  }, [closingConfirmation])

  const confirmDiscard = useCallback(async (id: string) => {
    const entry = entries.current.get(id)
    if (!entry) return true
    const confirmed = await confirm({
      title: 'Отказ от незапазаните промени?',
      message: `Промените по ${entry.label} ще бъдат загубени. Искате ли да продължите?`,
      confirmLabel: 'Откажи промените',
      danger: true,
    })
    if (confirmed) register(id, entry.label, false)
    return confirmed
  }, [confirm, register])

  const confirmDiscardAll = useCallback(async () => {
    const labels = [...new Set([...entries.current.values()].map(entry => entry.label))]
    if (!labels.length) return true
    const confirmed = await confirm({
      title: 'Отказ от незапазаните промени?',
      message: `Промените по ${labels.join(', ')} ще бъдат загубени. Искате ли да продължите?`,
      confirmLabel: 'Откажи промените',
      danger: true,
    })
    if (confirmed) {
      entries.current.clear()
      setRevision(value => value + 1)
    }
    return confirmed
  }, [confirm])

  useEffect(() => {
    if (!hasUnsavedChanges) return
    const warnBeforeUnload = (event: BeforeUnloadEvent) => {
      event.preventDefault()
      event.returnValue = ''
    }
    window.addEventListener('beforeunload', warnBeforeUnload)
    return () => window.removeEventListener('beforeunload', warnBeforeUnload)
  }, [hasUnsavedChanges])

  useEffect(() => {
    if (!request) return
    const handleKeyDown = (event: KeyboardEvent) => { if (event.key === 'Escape') settle(false) }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [request, settle])

  useEffect(() => {
    if (blocker.state !== 'blocked' || blockerPrompting.current) return
    blockerPrompting.current = true
    void confirmDiscardAll().then(confirmed => {
      if (confirmed) blocker.proceed()
      else blocker.reset()
    }).finally(() => { blockerPrompting.current = false })
  }, [blocker, confirmDiscardAll, revision])

  const value = useMemo(() => ({ register, confirm, confirmDiscard, confirmDiscardAll }), [register, confirm, confirmDiscard, confirmDiscardAll])
  return <UnsavedChangesContext.Provider value={value}>
    {children}
    {request && <div className="confirm-dialog-backdrop" data-state={closingConfirmation?'closed':'open'} onMouseDown={event => { if (event.target === event.currentTarget) settle(false) }}>
      <section className="confirm-dialog" data-state={closingConfirmation?'closed':'open'} role="alertdialog" aria-modal="true" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message">
        <div className={`confirm-dialog-icon${request.danger ? ' is-danger' : ''}`}><AlertTriangle size={22}/></div>
        <h2 id="confirm-dialog-title">{request.title}</h2>
        <p id="confirm-dialog-message">{request.message}</p>
        <footer><button className="outline-button confirm-dialog-cancel" autoFocus onClick={() => settle(false)}>{request.cancelLabel || 'Отказ'}</button><button className={request.danger ? 'confirm-dialog-action is-danger' : 'primary-button confirm-dialog-action'} onClick={() => settle(true)}>{request.confirmLabel || 'Потвърди'}</button></footer>
      </section>
    </div>}
  </UnsavedChangesContext.Provider>
}

export function useUnsavedChanges(label: string, dirty: boolean) {
  const context = useContext(UnsavedChangesContext)
  if (!context) throw new Error('useUnsavedChanges must be used within UnsavedChangesProvider')
  const id = useId()

  useEffect(() => {
    context.register(id, label, dirty)
    return () => context.register(id, label, false)
  }, [context.register, dirty, id, label])

  return {
    confirmDiscard: () => context.confirmDiscard(id),
    markClean: () => context.register(id, label, false),
  }
}

export function useConfirmDiscardAll() {
  const context = useContext(UnsavedChangesContext)
  if (!context) throw new Error('useConfirmDiscardAll must be used within UnsavedChangesProvider')
  return context.confirmDiscardAll
}

export function useConfirmation() {
  const context = useContext(UnsavedChangesContext)
  if (!context) throw new Error('useConfirmation must be used within UnsavedChangesProvider')
  return context.confirm
}
