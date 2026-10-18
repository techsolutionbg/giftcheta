import { api, json, setCsrfToken } from './lib/api'
import type { Admin, ToastState } from './types/admin'
import { useCallback, useEffect, useState } from 'react'
import { Link, Navigate, Route, Routes, useNavigate } from 'react-router-dom'
import { Bell, Check, ChevronRight, CircleHelp, ClipboardList, LayoutDashboard, LogOut, Menu, Moon, Package, Settings, ShoppingBag, Sun, Tags, Users, X } from 'lucide-react'
import { GlobalLoadingBar } from './components/GlobalLoadingBar'
import { NavLink } from './components/Navigation'
import { Login } from './components/Login'
import { Dashboard } from './pages/Dashboard'
import { ProductsPage } from './pages/ProductsPage'
import { CategoriesPage } from './pages/CategoriesPage'
import { Placeholder } from './pages/Placeholder'

export default function App() {
  const [admin, setAdmin] = useState<Admin | null>(null)
  const [ready, setReady] = useState(false)
  const [loading, setLoading] = useState(false)
  const [toast, setToast] = useState<ToastState | null>(null)
  const [dark, setDark] = useState(() => localStorage.getItem('giftcheta-theme') !== 'light')

  const navigate = useNavigate()
  const notify = useCallback((message: string, kind: ToastState['kind'] = 'success') => { setToast({ message, kind }); window.setTimeout(() => setToast(null), 3200) }, [])

  useEffect(() => { document.documentElement.classList.toggle('dark', dark); localStorage.setItem('giftcheta-theme', dark ? 'dark' : 'light') }, [dark])
  useEffect(() => {
    const handleLoading = (event: Event) => setLoading(Boolean((event as CustomEvent<boolean>).detail))
    window.addEventListener('giftcheta:loading', handleLoading)
    return () => window.removeEventListener('giftcheta:loading', handleLoading)
  }, [])
  useEffect(() => {
    void (async () => {
      try { const csrf = await api<{ data: { csrfToken: string } }>('/auth/csrf'); setCsrfToken(csrf.data.csrfToken); const me = await api<{ data: { admin: Admin; csrfToken: string } }>('/me'); setAdmin(me.data.admin); setCsrfToken(me.data.csrfToken) }
      catch { setAdmin(null) } finally { setReady(true) }
    })()
  }, [])

  const login = async (email: string, password: string) => {
    const csrf = await api<{ data: { csrfToken: string } }>('/auth/csrf'); setCsrfToken(csrf.data.csrfToken)
    const result = await api<{ data: { admin: Admin; csrfToken: string } }>('/auth/login', { method: 'POST', body: json({ email, password }) })
    setCsrfToken(result.data.csrfToken); setAdmin(result.data.admin); notify(`Здравей, ${result.data.admin.name}`); navigate('/admin')
  }
  const logout = async () => { try { await api('/auth/logout', { method: 'POST', body: '{}' }) } finally { setAdmin(null); navigate('/admin/login') } }

  if (!ready) return <><GlobalLoadingBar active/><div className="boot-screen"><div className="boot-mark">G</div><span>Подготвяме администрацията…</span></div></>
  if (!admin) return <><GlobalLoadingBar active={loading}/><Routes><Route path="/admin/login" element={<Login onLogin={login} />} /><Route path="*" element={<Navigate to="/admin/login" replace />} /></Routes></>
  return <><GlobalLoadingBar active={loading}/><div className="app-shell">
    <aside className="sidebar">
      <Link className="brand" to="/admin"><span className="brand-name">GIFTCHETA</span><small>Администрация</small></Link>
      <nav className="nav-list">
        <NavLink to="/admin" icon={<LayoutDashboard/>} label="Табло"/>
        <NavLink to="/admin/products" icon={<Package/>} label="Продукти"/>
        <NavLink to="/admin/categories" icon={<Tags/>} label="Категории"/>
      </nav>
      <div className="nav-divider" aria-hidden="true"/>
      <nav className="nav-list">
        <NavLink to="/admin/orders" icon={<ShoppingBag/>} label="Поръчки" disabled/>
        <NavLink to="/admin/customers" icon={<Users/>} label="Клиенти" disabled/>
      </nav>
      <div className="sidebar-spacer"/>
      <div className="sidebar-help"><div className="help-icon"><CircleHelp size={17}/></div><div><strong>Нужда от помощ?</strong><span>Документация и насоки</span></div><ChevronRight size={15}/></div>
      <button className="profile-card" title="Изход от администрацията" aria-label="Изход от администрацията" onClick={() => void logout()}><div className="avatar">{admin.name.slice(0,1).toUpperCase()}</div><div className="profile-copy"><strong>{admin.name}</strong><span>Администратор</span></div><LogOut size={16}/></button>
    </aside>
    <main className="main-area">
      <header className="topbar"><div className="topbar-spacer"/><div className="topbar-actions"><button className="icon-button" title="Смени тема" aria-label="Смени тема" onClick={() => setDark(!dark)}>{dark ? <Sun size={18}/> : <Moon size={18}/>}</button><button className="icon-button notification" title="Известия" aria-label="Известия"><Bell size={18}/></button><div className="top-avatar">{admin.name.slice(0,1).toUpperCase()}</div><button className="mobile-menu" title="Отвори менюто" aria-label="Отвори менюто"><Menu/></button></div></header>
      <Routes>
        <Route path="/admin" element={<Dashboard admin={admin}/>} />
        <Route path="/admin/products" element={<ProductsPage notify={notify}/>} />
        <Route path="/admin/categories" element={<CategoriesPage notify={notify}/>} />
        <Route path="/admin/orders" element={<Placeholder title="Поръчки" icon={<ClipboardList/>}/>} />
        <Route path="/admin/customers" element={<Placeholder title="Клиенти" icon={<Users/>}/>} />
        <Route path="/admin/settings" element={<Placeholder title="Настройки" icon={<Settings/>}/>} />
        <Route path="*" element={<Navigate to="/admin" replace/>}/>
      </Routes>
    </main>
    {toast && <div className={`toast ${toast.kind}`}><span>{toast.kind === 'success' ? <Check size={16}/> : <X size={16}/>}</span>{toast.message}<button onClick={() => setToast(null)} title="Затвори известието" aria-label="Затвори известието"><X size={14}/></button></div>}
  </div></>
}
