import type * as React from 'react'
import { Link, useLocation } from 'react-router-dom'
import { ChevronRight } from 'lucide-react'

export function NavLink({ to, icon, label, badge, disabled = false }: { to: string; icon: React.ReactNode; label: string; badge?: string; disabled?: boolean }) {
  const { pathname } = useLocation()
  const active = pathname === to || (to !== '/admin' && pathname.startsWith(to))
  if (disabled) return <div className="nav-item disabled">{icon}<span>{label}</span><small>скоро</small></div>
  return <Link className={`nav-item ${active ? 'active' : ''}`} to={to}>{icon}<span>{label}</span>{badge && <small>{badge}</small>}</Link>
}

export function Breadcrumbs() {
  const { pathname } = useLocation()
  const route = pathname.startsWith('/admin/products') ? 'Продукти'
    : pathname.startsWith('/admin/categories') ? 'Категории'
    : pathname.startsWith('/admin/orders') ? 'Поръчки'
    : pathname.startsWith('/admin/customers') ? 'Клиенти'
    : pathname.startsWith('/admin/settings') ? 'Настройки'
    : 'Табло'
  const items = route === 'Табло' ? [{ label: route, to: null }] : [{ label: 'Табло', to: '/admin' }, { label: route, to: null }]
  return <nav className="breadcrumbs page-breadcrumbs" aria-label="Навигационна пътека">{items.map((item,index)=><span className="breadcrumb-item" key={item.label}>{index>0&&<ChevronRight size={14}/>}{item.to?<Link to={item.to}>{item.label}</Link>:<strong aria-current="page">{item.label}</strong>}</span>)}</nav>
}
