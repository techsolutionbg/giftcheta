import { createContext, useCallback, useContext, useEffect, useId, useMemo, useRef, useState } from 'react'
import type { ReactNode } from 'react'
import { useBlocker } from 'react-router-dom'

type UnsavedEntry = { label: string }
type UnsavedChangesContextValue = {
  register: (id: string, label: string, dirty: boolean) => void
  confirmDiscard: (id: string) => boolean
  confirmDiscardAll: () => boolean
}

const UnsavedChangesContext = createContext<UnsavedChangesContextValue | null>(null)

export function UnsavedChangesProvider({ children }: { children: ReactNode }) {
  const entries = useRef(new Map<string, UnsavedEntry>())
  const [revision, setRevision] = useState(0)
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

  const discardMessage = 'Имате незапазени промени. Ако затворите редактора или напуснете страницата, те ще бъдат загубени. Искате ли да продължите?'

  const confirmDiscard = useCallback((id: string) => {
    if (!entries.current.has(id)) return true
    if (!window.confirm(discardMessage)) return false
    register(id, '', false)
    return true
  }, [register])

  const confirmDiscardAll = useCallback(() => {
    if (entries.current.size === 0) return true
    if (!window.confirm(discardMessage)) return false
    entries.current.clear()
    setRevision(value => value + 1)
    return true
  }, [])

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
    if (blocker.state !== 'blocked') return
    if (window.confirm(discardMessage)) blocker.proceed()
    else blocker.reset()
  }, [blocker.state, revision])

  const value = useMemo(() => ({ register, confirmDiscard, confirmDiscardAll }), [register, confirmDiscard, confirmDiscardAll])
  return <UnsavedChangesContext.Provider value={value}>{children}</UnsavedChangesContext.Provider>
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
