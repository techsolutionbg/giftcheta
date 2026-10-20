import { CloseButton } from './components/CloseButton'
import { api, json, setCsrfToken } from './lib/api'
import type { Admin, ToastState } from './types/admin'
import { useCallback, useEffect, useRef, useState, type CSSProperties } from 'react'
import { Link, Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom'
import { Bell, Check, ChevronRight, CircleHelp, LayoutDashboard, LogOut, Menu, Moon, Package, PanelLeftClose, PanelLeftOpen, Settings, ShoppingBag, Sun, Tags, Users, X, Images, BadgePercent } from 'lucide-react'
import { GlobalLoadingBar } from './components/GlobalLoadingBar'
import { NavLink } from './components/Navigation'
import { Login } from './components/Login'
import { Dashboard } from './pages/Dashboard'
import { ProductsPage } from './pages/ProductsPage'
import { CategoriesPage } from './pages/CategoriesPage'
import { SettingsPage } from './pages/SettingsPage'
import { CustomersPage } from './pages/CustomersPage'
import { OrdersPage } from './pages/OrdersPage'
import { BannersPage } from './pages/BannersPage'
import { DiscountCodesPage } from './pages/DiscountCodesPage'
import { useConfirmDiscardAll } from './components/UnsavedChanges'
import { GlobalSearch } from './components/GlobalSearch'
import { SidePanel } from './components/SidePanel'

export default function App() {
  const [admin, setAdmin] = useState<Admin | null>(null)
  const [ready, setReady] = useState(false)
  const [loading, setLoading] = useState(false)
  const [toast, setToast] = useState<ToastState | null>(null)
  const [dark, setDark] = useState(() => localStorage.getItem('giftcheta-theme') !== 'light')
  const [sidebarOpen, setSidebarOpen] = useState(() => window.innerWidth >= 1100)
  const [compactViewport, setCompactViewport] = useState(() => window.innerWidth < 1100)
  const [mobileViewport, setMobileViewport] = useState(() => window.innerWidth <= 820)
  const [mobileNavOpen, setMobileNavOpen] = useState(false)
  const [sidebarWidth, setSidebarWidth] = useState(252)
  const [sidebarPreferencesReady, setSidebarPreferencesReady] = useState(false)
  const sidebarResizeStart = useRef<{ pointerX: number; width: number } | null>(null)

  const navigate = useNavigate()
  const { pathname } = useLocation()
  const topbarTitle = pathname.startsWith('/admin/products') ? 'Продукти'
    : pathname.startsWith('/admin/categories') ? 'Категории'
      : pathname.startsWith('/admin/orders') ? 'Поръчки'
        : pathname.startsWith('/admin/customers') ? 'Клиенти'
          : pathname.startsWith('/admin/banners') ? 'Банери'
          : pathname.startsWith('/admin/discount-codes') ? 'Кодове за отстъпка'
          : pathname.startsWith('/admin/settings') ? 'Настройки' : 'Табло'
  const confirmDiscardAll = useConfirmDiscardAll()
  const notify = useCallback((message: string, kind: ToastState['kind'] = 'success') => { setToast({ message, kind }); window.setTimeout(() => setToast(null), 3200) }, [])

  useEffect(() => { document.documentElement.classList.toggle('dark', dark); localStorage.setItem('giftcheta-theme', dark ? 'dark' : 'light') }, [dark])
  useEffect(() => {
    const viewport = window.matchMedia('(max-width: 1099px)')
    const updateSidebarForViewport = (event: MediaQueryListEvent) => {
      setCompactViewport(event.matches)
    }
    viewport.addEventListener('change', updateSidebarForViewport)
    return () => viewport.removeEventListener('change', updateSidebarForViewport)
  }, [])
  useEffect(() => {
    const viewport = window.matchMedia('(max-width: 820px)')
    const updateMobileViewport = (event: MediaQueryListEvent) => setMobileViewport(event.matches)
    viewport.addEventListener('change', updateMobileViewport)
    return () => viewport.removeEventListener('change', updateMobileViewport)
  }, [])
  useEffect(() => { if (!mobileViewport) setMobileNavOpen(false) }, [mobileViewport])
  useEffect(() => { if (mobileNavOpen) setMobileNavOpen(false) }, [pathname])
  useEffect(() => {
    if (!mobileViewport) return
    const openMobileSearch = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') setMobileNavOpen(true)
    }
    window.addEventListener('keydown', openMobileSearch)
    return () => window.removeEventListener('keydown', openMobileSearch)
  }, [mobileViewport])
  useEffect(() => {
    if (!mobileNavOpen) return
    const frame = window.requestAnimationFrame(() => document.querySelector<HTMLInputElement>('.mobile-navigation-panel input[type="search"]')?.focus())
    return () => window.cancelAnimationFrame(frame)
  }, [mobileNavOpen])
  useEffect(() => {
    if (!admin || !sidebarPreferencesReady) return
    const timeout = window.setTimeout(() => {
      const preferenceChanges = { sidebarWidth, ...(!compactViewport ? { sidebarOpen } : {}) }
      void api('/preferences', { method: 'PATCH', body: json(preferenceChanges) })
        .catch(error => console.error('Неуспешно запазване на ширината на страничната лента.', error))
    }, 350)
    return () => window.clearTimeout(timeout)
  }, [admin, sidebarWidth, sidebarOpen, compactViewport, sidebarPreferencesReady])
  useEffect(() => {
    const handleLoading = (event: Event) => setLoading(Boolean((event as CustomEvent<boolean>).detail))
    window.addEventListener('giftcheta:loading', handleLoading)
    return () => window.removeEventListener('giftcheta:loading', handleLoading)
  }, [])
  useEffect(() => {
    void (async () => {
      try {
        const csrf = await api<{ data: { csrfToken: string } }>('/auth/csrf')
        setCsrfToken(csrf.data.csrfToken)
        const me = await api<{ data: { admin: Admin; csrfToken: string } }>('/me')
        setCsrfToken(me.data.csrfToken)
        try {
          const preferences = await api<{ data: { sidebarWidth: number; sidebarOpen: boolean } }>('/preferences')
          setSidebarWidth(preferences.data.sidebarWidth)
          setSidebarOpen(preferences.data.sidebarOpen)
        } catch (error) { console.error('Неуспешно зареждане на предпочитанията за страничната лента.', error) }
        setSidebarPreferencesReady(true)
        setAdmin(me.data.admin)
      }
      catch { setAdmin(null) } finally { setReady(true) }
    })()
  }, [])

  const login = async (email: string, password: string) => {
    const csrf = await api<{ data: { csrfToken: string } }>('/auth/csrf'); setCsrfToken(csrf.data.csrfToken)
    const result = await api<{ data: { admin: Admin; csrfToken: string } }>('/auth/login', { method: 'POST', body: json({ email, password }) })
    setCsrfToken(result.data.csrfToken)
    try {
      const preferences = await api<{ data: { sidebarWidth: number; sidebarOpen: boolean } }>('/preferences')
      setSidebarWidth(preferences.data.sidebarWidth)
      setSidebarOpen(preferences.data.sidebarOpen)
    } catch (error) { console.error('Неуспешно зареждане на предпочитанията за страничната лента.', error) }
    setSidebarPreferencesReady(true); setAdmin(result.data.admin); notify(`Здравей, ${result.data.admin.name}`); navigate('/admin')
  }
  const logout = async () => { if (!(await confirmDiscardAll())) return; try { await api('/auth/logout', { method: 'POST', body: '{}' }) } finally { setAdmin(null); navigate('/admin/login') } }

  if (!ready) return <><GlobalLoadingBar active/><div className="boot-screen"><div className="boot-mark">G</div><span>Подготвяме администрацията…</span></div></>
  if (!admin) return <><GlobalLoadingBar active={loading}/><Routes><Route path="/admin/login" element={<Login onLogin={login} />} /><Route path="*" element={<Navigate to="/admin/login" replace />} /></Routes></>
  const sidebarVisible = sidebarOpen && !compactViewport
  return <><GlobalLoadingBar active={loading}/><div className={`app-shell${sidebarVisible?'':' sidebar-closed'}`} style={{ '--sidebar-width': `${sidebarWidth}px` } as CSSProperties}>
    <aside className="sidebar" id="admin-sidebar" aria-hidden={!sidebarVisible}>
      <Link className="brand" to="/admin"><span className="brand-name">GIFTCHETA</span><small>Администрация</small></Link>
      <nav className="nav-list">
        <NavLink to="/admin" icon={<LayoutDashboard/>} label="Табло"/>
        <NavLink to="/admin/products" icon={<Package/>} label="Продукти"/>
        <NavLink to="/admin/categories" icon={<Tags/>} label="Категории"/>
        <NavLink to="/admin/banners" icon={<Images/>} label="Банери"/>
        <NavLink to="/admin/discount-codes" icon={<BadgePercent/>} label="Кодове за отстъпка"/>
        <NavLink to="/admin/settings" icon={<Settings/>} label="Настройки"/>
      </nav>
      <div className="nav-divider" aria-hidden="true"/>
      <nav className="nav-list">
        <NavLink to="/admin/orders" icon={<ShoppingBag/>} label="Поръчки"/>
        <NavLink to="/admin/customers" icon={<Users/>} label="Клиенти"/>
      </nav>
      <div className="sidebar-spacer"/>
      <div className="sidebar-help"><div className="help-icon"><CircleHelp size={17}/></div><div><strong>Нужда от помощ?</strong><span>Документация и насоки</span></div><ChevronRight size={15}/></div>
      <button className="profile-card" title="Изход от администрацията" aria-label="Изход от администрацията" onClick={() => void logout()}><div className="avatar">{admin.name.slice(0,1).toUpperCase()}</div><div className="profile-copy"><strong>{admin.name}</strong><span>Администратор</span></div><LogOut size={16}/></button>
      <div className="sidebar-resize-handle" role="separator" aria-label="Промени ширината на страничната лента" aria-orientation="vertical" aria-valuemin={220} aria-valuemax={360} aria-valuenow={sidebarWidth} tabIndex={0}
        onPointerDown={event=>{sidebarResizeStart.current={pointerX:event.clientX,width:sidebarWidth};event.currentTarget.setPointerCapture(event.pointerId)}}
        onPointerMove={event=>{if(sidebarResizeStart.current){const next=sidebarResizeStart.current.width+event.clientX-sidebarResizeStart.current.pointerX;setSidebarWidth(Math.max(220,Math.min(360,next)))}}}
        onPointerUp={()=>{sidebarResizeStart.current=null}}
        onPointerCancel={()=>{sidebarResizeStart.current=null}}
        onKeyDown={event=>{if(event.key==='ArrowLeft')setSidebarWidth(width=>Math.max(220,width-8));if(event.key==='ArrowRight')setSidebarWidth(width=>Math.min(360,width+8))}}/>
    </aside>
    <main className="main-area">
      <header className="topbar"><div className="topbar-heading">{mobileViewport
        ? <SidePanel open={mobileNavOpen} onOpenChange={setMobileNavOpen} side="left" title="Навигация" panelClassName="mobile-navigation-panel" trigger={<button className="icon-button sidebar-toggle" title="Отвори менюто" aria-label="Отвори менюто"><Menu size={18}/></button>}>
          <div className="mobile-navigation-content"><GlobalSearch/><nav className="nav-list"><NavLink to="/admin" icon={<LayoutDashboard/>} label="Табло"/><NavLink to="/admin/products" icon={<Package/>} label="Продукти"/><NavLink to="/admin/categories" icon={<Tags/>} label="Категории"/><NavLink to="/admin/banners" icon={<Images/>} label="Банери"/>
        <NavLink to="/admin/discount-codes" icon={<BadgePercent/>} label="Кодове за отстъпка"/><NavLink to="/admin/settings" icon={<Settings/>} label="Настройки"/><NavLink to="/admin/orders" icon={<ShoppingBag/>} label="Поръчки"/><NavLink to="/admin/customers" icon={<Users/>} label="Клиенти"/></nav></div>
          </SidePanel>
        : <button className="icon-button sidebar-toggle" title={sidebarOpen?'Затвори страничната лента':'Отвори страничната лента'} aria-label={sidebarOpen?'Затвори страничната лента':'Отвори страничната лента'} aria-controls="admin-sidebar" aria-expanded={sidebarOpen} onClick={()=>setSidebarOpen(open=>!open)}>{sidebarOpen?<PanelLeftClose size={18}/>:<PanelLeftOpen size={18}/>}</button>}<h1 className="topbar-title">{topbarTitle}</h1></div>{!mobileViewport&&<GlobalSearch/>}<div className="topbar-actions"><button className="icon-button" title="Смени тема" aria-label="Смени тема" onClick={() => setDark(!dark)}>{dark ? <Sun size={18}/> : <Moon size={18}/>}</button><button className="icon-button notification" title="Известия" aria-label="Известия"><Bell size={18}/></button><div className="top-avatar">{admin.name.slice(0,1).toUpperCase()}</div><button className="mobile-menu" title="Отвори менюто" aria-label="Отвори менюто"><Menu/></button></div></header>
      <Routes>
        <Route path="/admin" element={<Dashboard admin={admin}/>} />
        <Route path="/admin/products" element={<ProductsPage notify={notify}/>} />
        <Route path="/admin/categories" element={<CategoriesPage notify={notify}/>} />
        <Route path="/admin/banners" element={<BannersPage notify={notify}/>} />
        <Route path="/admin/discount-codes" element={<DiscountCodesPage notify={notify}/>} />
        <Route path="/admin/orders" element={<OrdersPage notify={notify}/>} />
        <Route path="/admin/orders/:id" element={<OrdersPage notify={notify}/>} />
        <Route path="/admin/customers" element={<CustomersPage notify={notify}/>} />
        <Route path="/admin/customers/new" element={<CustomersPage notify={notify}/>} />
        <Route path="/admin/customers/:id/edit" element={<CustomersPage notify={notify}/>} />
        <Route path="/admin/settings" element={<SettingsPage sidebarWidth={sidebarWidth} onSidebarWidthChange={setSidebarWidth} notify={notify}/>} />
        <Route path="*" element={<Navigate to="/admin" replace/>}/>
      </Routes>
    </main>
    {toast && <div className={`toast ${toast.kind}`}><span>{toast.kind === 'success' ? <Check size={16}/> : <X size={16}/>}</span>{toast.message}<CloseButton className="toast-close-button" onClick={() => setToast(null)} title="Затвори известието" aria-label="Затвори известието"/></div>}
  </div></>
}
