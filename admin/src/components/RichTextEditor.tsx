import { useEffect, useState } from 'react'
import { EditorContent, useEditor } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import type { LucideIcon } from 'lucide-react'
import { Bold, ChevronDown, Heading2, Heading3, Italic, Link2, List, ListOrdered, Plus, Quote, Redo2, Strikethrough, Underline, Undo2, Type } from 'lucide-react'

type RichTextEditorProps = {
  value: string
  onChange: (value: string) => void
  ariaLabel: string
  ariaLabelledBy?: string
  compact?: boolean
}

function toEditorContent(value: string) {
  if (!value) return ''
  if (/<(p|h[1-6]|ul|ol|li|strong|b|em|i|u|s|a|blockquote|pre|code|br|hr)\b/i.test(value)) return value
  const escaped = value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  return `<p>${escaped.replace(/\r?\n/g, '<br>')}</p>`
}

function ToolbarButton({ icon: Icon, label, active = false, disabled = false, onClick }: {
  icon: LucideIcon
  label: string
  active?: boolean
  disabled?: boolean
  onClick: () => void
}) {
  return <button type="button" className={`rich-text-toolbar-button${active ? ' active' : ''}`} title={label} aria-label={label} aria-pressed={active} disabled={disabled} onMouseDown={event => event.preventDefault()} onClick={onClick}>
    <Icon size={16} aria-hidden="true"/>
  </button>
}

type BlockKind = 'paragraph' | 'heading2' | 'heading3' | 'bulletList' | 'orderedList' | 'blockquote' | 'rule'

const blocks: { kind: BlockKind; title: string; detail: string; icon: LucideIcon }[] = [
  { kind: 'paragraph', title: 'Абзац', detail: 'Обикновен текст', icon: Type },
  { kind: 'heading2', title: 'Заглавие', detail: 'Голямо заглавие', icon: Heading2 },
  { kind: 'heading3', title: 'Подзаглавие', detail: 'По-малко заглавие', icon: Heading3 },
  { kind: 'bulletList', title: 'Списък', detail: 'Списък с точки', icon: List },
  { kind: 'orderedList', title: 'Номериран списък', detail: 'Подредени елементи', icon: ListOrdered },
  { kind: 'blockquote', title: 'Цитат', detail: 'Изведен цитат', icon: Quote },
  { kind: 'rule', title: 'Разделител', detail: 'Хоризонтална линия', icon: Plus },
]

export function RichTextEditor({ value, onChange, ariaLabel, ariaLabelledBy, compact = false }: RichTextEditorProps) {
  const [insertMenuOpen, setInsertMenuOpen] = useState(false)
  const editor = useEditor({
    extensions: [StarterKit.configure({ heading: { levels: [2, 3] } })],
    content: toEditorContent(value),
    immediatelyRender: false,
    editorProps: {
      attributes: {
        class: 'rich-text-editor-content', role: 'textbox', 'aria-label': ariaLabel,
        'aria-labelledby': ariaLabelledBy || '', 'aria-multiline': 'true',
      },
    },
    onUpdate: ({ editor: currentEditor }) => onChange(currentEditor.isEmpty ? '' : currentEditor.getHTML()),
  })

  useEffect(() => {
    if (!editor) return
    const content = toEditorContent(value)
    if (editor.getHTML() !== (content || '<p></p>')) editor.commands.setContent(content, { emitUpdate: false })
  }, [editor, value])

  if (!editor) return <div className={`rich-text-editor${compact ? ' rich-text-editor-compact' : ''}`} aria-busy="true" />

  const addBlock = (kind: BlockKind) => {
    setInsertMenuOpen(false)
    const chain = editor.chain().focus()
    if (kind === 'rule') {
      chain.setHorizontalRule().run()
      return
    }
    chain.splitBlock()
    if (kind === 'heading2') chain.toggleHeading({ level: 2 })
    else if (kind === 'heading3') chain.toggleHeading({ level: 3 })
    else if (kind === 'bulletList') chain.toggleBulletList()
    else if (kind === 'orderedList') chain.toggleOrderedList()
    else if (kind === 'blockquote') chain.toggleBlockquote()
    else chain.setParagraph()
    chain.run()
  }

  const addLink = () => {
    const href = window.prompt('Въведете адрес на връзката')
    if (href === null) return
    if (!href.trim()) editor.chain().focus().extendMarkRange('link').unsetLink().run()
    else editor.chain().focus().extendMarkRange('link').setLink({ href: href.trim() }).run()
  }

  return <div className={`rich-text-editor${compact ? ' rich-text-editor-compact' : ''}`}>
    <div className="rich-text-toolbar" role="toolbar" aria-label={`Редактор на блокове: ${ariaLabel}`}>
      <div className="rich-text-inserter-wrap">
        <button type="button" className="rich-text-inserter" aria-haspopup="menu" aria-expanded={insertMenuOpen} onClick={() => setInsertMenuOpen(open => !open)}>
          <Plus size={17} aria-hidden="true"/><span>Добави блок</span><ChevronDown size={14} aria-hidden="true"/>
        </button>
        {insertMenuOpen && <div className="rich-text-block-menu" role="menu" aria-label="Избери тип блок">
          {blocks.map(({ kind, title, detail, icon: Icon }) => <button key={kind} type="button" role="menuitem" className="rich-text-block-option" onMouseDown={event => event.preventDefault()} onClick={() => addBlock(kind)}>
            <span className="rich-text-block-option-icon"><Icon size={17} aria-hidden="true"/></span><span><strong>{title}</strong><small>{detail}</small></span>
          </button>)}
        </div>}
      </div>
      <span className="rich-text-toolbar-divider" aria-hidden="true"/>
      <ToolbarButton icon={Bold} label="Удебелен текст" active={editor.isActive('bold')} onClick={() => editor.chain().focus().toggleBold().run()}/>
      <ToolbarButton icon={Italic} label="Курсив" active={editor.isActive('italic')} onClick={() => editor.chain().focus().toggleItalic().run()}/>
      <ToolbarButton icon={Underline} label="Подчертаване" active={editor.isActive('underline')} onClick={() => editor.chain().focus().toggleUnderline().run()}/>
      <ToolbarButton icon={Strikethrough} label="Зачеркване" active={editor.isActive('strike')} onClick={() => editor.chain().focus().toggleStrike().run()}/>
      <ToolbarButton icon={Link2} label="Добави връзка" active={editor.isActive('link')} onClick={addLink}/>
      <span className="rich-text-toolbar-spacer"/>
      <ToolbarButton icon={Undo2} label="Отмени" disabled={!editor.can().chain().undo().run()} onClick={() => editor.chain().focus().undo().run()}/>
      <ToolbarButton icon={Redo2} label="Повтори" disabled={!editor.can().chain().redo().run()} onClick={() => editor.chain().focus().redo().run()}/>
    </div>
    <div className="rich-text-block-canvas"><EditorContent editor={editor}/></div>
  </div>
}
