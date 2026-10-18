import { lazy, Suspense } from 'react'
import type { ComponentProps } from 'react'

const RichTextEditor = lazy(() => import('./RichTextEditor').then(module => ({ default: module.RichTextEditor })))

type LazyRichTextEditorProps = ComponentProps<typeof RichTextEditor>

export function LazyRichTextEditor(props: LazyRichTextEditorProps) {
  return <Suspense fallback={<div className={`rich-text-editor rich-text-loading${props.compact ? ' rich-text-editor-compact' : ''}`} aria-busy="true"/>}>
    <RichTextEditor {...props}/>
  </Suspense>
}
