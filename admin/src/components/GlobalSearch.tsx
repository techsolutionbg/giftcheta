import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowRight, Package, Search, Tags } from 'lucide-react'
import { api } from '../lib/api'
import type { Category, Product } from '../types/admin'

type Result = { kind: 'product'; item: Product } | { kind: 'category'; item: Category } | { kind: 'page'; title: string; path: string }
const pages = [
  { title: 'Табло', path: '/admin', terms: 'табло начало dashboard' },
  { title: 'Продукти', path: '/admin/products', terms: 'продукти каталог products' },
  { title: 'Категории', path: '/admin/categories', terms: 'категории структура categories' },
  { title: 'Настройки', path: '/admin/settings', terms: 'настройки предпочитания settings' },
]

export function GlobalSearch() {
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<Result[]>([])
  const [open, setOpen] = useState(false)
  const inputRef = useRef<HTMLInputElement>(null)
  const rootRef = useRef<HTMLDivElement>(null)
  const navigate = useNavigate()

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        inputRef.current?.focus()
        inputRef.current?.select()
        setOpen(true)
      } else if (event.key === 'Escape') setOpen(false)
    }
    const onPointerDown = (event: PointerEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false)
    }
    window.addEventListener('keydown', onKeyDown)
    document.addEventListener('pointerdown', onPointerDown)
    return () => { window.removeEventListener('keydown', onKeyDown); document.removeEventListener('pointerdown', onPointerDown) }
  }, [])

  useEffect(() => {
    const term = query.trim().toLocaleLowerCase('bg')
    if (term.length < 2) { setResults([]); return }
    let active = true
    const timer = window.setTimeout(async () => {
      const [products, categories] = await Promise.allSettled([
        api<{ data: Product[] }>(`/products?search=${encodeURIComponent(query.trim())}&per_page=5`),
        api<{ data: Category[] }>('/categories'),
      ])
      if (!active) return
      const matches: Result[] = []
      if (products.status === 'fulfilled') matches.push(...products.value.data.slice(0, 5).map(item => ({ kind: 'product' as const, item })))
      if (categories.status === 'fulfilled') matches.push(...categories.value.data.filter(item => `${item.name} ${item.slug}`.toLocaleLowerCase('bg').includes(term)).slice(0, 5).map(item => ({ kind: 'category' as const, item })))
      matches.push(...pages.filter(item => `${item.title} ${item.terms}`.toLocaleLowerCase('bg').includes(term)).map(item => ({ kind: 'page' as const, title: item.title, path: item.path })))
      setResults(matches.slice(0, 8))
    }, 220)
    return () => { active = false; window.clearTimeout(timer) }
  }, [query])

  const select = (result: Result) => {
    setOpen(false)
    if (result.kind === 'product') navigate(`/admin/products?dialog=edit&id=${result.item.id}`)
    else if (result.kind === 'category') navigate(`/admin/categories?search=${encodeURIComponent(result.item.name)}`)
    else navigate(result.path)
    setQuery('')
  }

  const submit = (event: React.FormEvent) => {
    event.preventDefault()
    if (results[0]) select(results[0])
    else if (query.trim()) select({ kind: 'page', title: 'Продукти', path: `/admin/products?search=${encodeURIComponent(query.trim())}` })
  }

  return <div className="global-search" ref={rootRef}>
    <form className="global-search-control" role="search" onSubmit={submit}>
      <Search size={17} aria-hidden="true"/>
      <input ref={inputRef} type="search" value={query} onFocus={()=>setOpen(true)} onChange={event=>{setQuery(event.target.value);setOpen(true)}} placeholder="Търси продукти, категории…" aria-label="Глобално търсене" aria-expanded={open&&query.trim().length>=2} aria-controls="global-search-results"/>
      <kbd>Ctrl+K</kbd>
    </form>
    {open&&query.trim().length>=2&&<div className="global-search-results" id="global-search-results" role="listbox" aria-label="Резултати от търсенето">
      {results.length===0?<div className="global-search-empty">Няма намерени резултати</div>:results.map((result,index)=>{
        const key=result.kind==='product'?`product-${result.item.id}`:result.kind==='category'?`category-${result.item.id}`:`page-${result.path}`
        const title=result.kind==='product'||result.kind==='category'?result.item.name:result.title
        const Icon=result.kind==='product'?Package:result.kind==='category'?Tags:ArrowRight
        const detail=result.kind==='product'?`Продукт · ${result.item.sku||result.item.slug}`:result.kind==='category'?'Категория':'Страница'
        return <button type="button" key={key} role="option" aria-selected={index===0} className="global-search-result" onClick={()=>select(result)}><span className="global-search-result-icon"><Icon size={16}/></span><span><strong>{title}</strong><small>{detail}</small></span><ArrowRight size={15}/></button>
      })}
    </div>}
  </div>
}
