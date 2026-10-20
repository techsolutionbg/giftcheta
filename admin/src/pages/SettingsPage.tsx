import { useAdminSettings, formatMoney } from '../lib/adminSettings'
import { Breadcrumbs } from '../components/Navigation'
import { Dropdown } from '../components/Dropdown'
import { SelectionControl } from '../components/SelectionControl'
import { FormField } from '../components/CatalogComponents'
import { api, json } from '../lib/api'
import { useEffect, useState } from 'react'
import { Settings, Truck } from 'lucide-react'
import type { ToastState } from '../types/admin'

export function SettingsPage({ sidebarWidth, onSidebarWidthChange, notify }: { sidebarWidth: number; onSidebarWidthChange: (width: number) => void; notify: (message: string, kind?: ToastState['kind']) => void }) {
  const { settings, updateSettings } = useAdminSettings()
  const [freeShippingEnabled, setFreeShippingEnabled] = useState(true)
  const [freeShippingThreshold, setFreeShippingThreshold] = useState('40.90')
  const [shippingStatus, setShippingStatus] = useState('')
  const [shippingError, setShippingError] = useState('')
  const [shippingLoading, setShippingLoading] = useState(true)
  const [issuer,setIssuer]=useState({legalName:'',companyId:'',vatNumber:'',address:'',phone:'',email:''})
  const [issuerSaving,setIssuerSaving]=useState(false)

  useEffect(() => {
    let active = true
    void api<{ data: { freeShippingEnabled: boolean; freeShippingThreshold: string; invoiceIssuer: {legal_name:string;company_id:string;vat_number:string;address:string;phone:string;email:string} } }>('/preferences')
      .then(result => { if (active) { setFreeShippingEnabled(result.data.freeShippingEnabled); setFreeShippingThreshold(result.data.freeShippingThreshold);setIssuer({legalName:result.data.invoiceIssuer.legal_name,companyId:result.data.invoiceIssuer.company_id,vatNumber:result.data.invoiceIssuer.vat_number,address:result.data.invoiceIssuer.address,phone:result.data.invoiceIssuer.phone,email:result.data.invoiceIssuer.email}) } })
      .catch(error => { if (active) setShippingError(error instanceof Error ? error.message : 'Настройките не бяха заредени.') })
      .finally(() => { if (active) setShippingLoading(false) })
    return () => { active = false }
  }, [])

  const saveShipping = async (patch: { freeShippingEnabled?: boolean; freeShippingThreshold?: number }) => {
    setShippingStatus('')
    setShippingError('')
    try {
      await api('/preferences', { method: 'PATCH', body: json(patch) })
      setShippingStatus('')
      notify('Настройките за доставка са запазени.')
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Настройката не беше запазена.'
      setShippingError(message)
      notify(message, 'error')
    }
  }

  const updateFreeShipping = (enabled: boolean) => {
    setFreeShippingEnabled(enabled)
    void saveShipping({ freeShippingEnabled: enabled })
  }

  const saveThreshold = () => {
    const amount = Number(freeShippingThreshold.replace(',', '.'))
    if (!Number.isFinite(amount) || amount < 0.01 || amount > 1_000_000) {
      setShippingError('Въведете сума между 0,01 € и 1 000 000 €.')
      setShippingStatus('')
      return
    }
    setFreeShippingThreshold(amount.toFixed(2))
    void saveShipping({ freeShippingThreshold: amount })
  }
  const saveIssuer=async()=>{setIssuerSaving(true);try{await api('/preferences',{method:'PATCH',body:json(issuer)});notify('Данните на издателя са запазени.')}catch(error){const message=error instanceof Error?error.message:'Данните не бяха запазени.';notify(message,'error')}finally{setIssuerSaving(false)}}

  return <div className="page-content settings-page">
    <div className="page-heading"><div><h1>Настройки</h1><Breadcrumbs/><p>Настройте начина, по който администрацията показва и създава продукти.</p></div></div>
    <section className="settings-section" aria-labelledby="settings-currency-title">
      <div className="settings-section-heading"><span className="settings-section-icon"><Settings size={19}/></span><div><h2 id="settings-currency-title">Формат и каталог</h2><p>Тези предпочитания се запазват за този браузър.</p></div></div>
      <div className="settings-list">
        <div className="settings-row"><div><strong>Десетичен разделител за цени</strong><p>Изберете дали сумите да се показват със запетая или точка пред стотинките. Пример: {formatMoney(1234.56, settings.decimalSeparator)}.</p></div><Dropdown ariaLabel="Десетичен разделител за цени" value={settings.decimalSeparator} onChange={value=>{updateSettings({decimalSeparator:value as ','|'.'});notify('Настройките на панела са запазени.')}} options={[{value:',',label:'Запетая (1.234,56)'},{value:'.',label:'Точка (1,234.56)'}]}/></div>
        <div className="settings-row"><div><strong>Статус по подразбиране за нов продукт</strong><p>Изберете дали новите продукти да се създават като чернови или да се публикуват веднага.</p></div><Dropdown ariaLabel="Статус по подразбиране за нов продукт" value={settings.defaultProductStatus} onChange={value=>{updateSettings({defaultProductStatus:value as 'draft'|'active'});notify('Настройките на панела са запазени.')}} options={[{value:'draft',label:'Чернова'},{value:'active',label:'Активен'}]}/></div>
        <div className="settings-row"><div><strong>Продукти на страница</strong><p>Брой редове, които се зареждат във всяка страница на продуктовия каталог.</p></div><Dropdown ariaLabel="Продукти на страница" value={String(settings.productsPerPage)} onChange={value=>{updateSettings({productsPerPage:Number(value) as 10|20|50});notify('Настройките на панела са запазени.')}} options={[{value:'10',label:'10 продукта'},{value:'20',label:'20 продукта'},{value:'50',label:'50 продукта'}]}/></div>
      </div>
    </section>
    <section className="settings-section" aria-labelledby="settings-shipping-title">
      <div className="settings-section-heading"><span className="settings-section-icon"><Truck size={19}/></span><div><h2 id="settings-shipping-title">Безплатна доставка</h2><p>Управлявайте съобщението в горната лента на магазина и прага за безплатна доставка.</p></div></div>
      <div className="settings-list">
        <div className="settings-row"><div><strong>Показвай съобщение за безплатна доставка</strong><p>Когато е включено, клиентите ще виждат прага в най-горната лента и индикатора в количката.</p></div><SelectionControl variant="switch" checked={freeShippingEnabled} disabled={shippingLoading} onChange={updateFreeShipping} ariaLabel="Показвай съобщение за безплатна доставка"/></div>
        <div className="settings-row"><div><strong>Минимална сума за безплатна доставка</strong><p>Прагът се прилага към междинната сума на продуктите. Сумата се въвежда в евро.</p></div><div className="shipping-threshold-control"><div><input type="number" min="0.01" max="1000000" step="0.01" value={freeShippingThreshold} disabled={shippingLoading} aria-label="Сума за безплатна доставка в евро" onChange={event => setFreeShippingThreshold(event.target.value)} onBlur={saveThreshold}/><span>€</span></div><small>Запазва се при излизане от полето.</small></div></div>
        {(shippingError || shippingStatus) && <p className={`shipping-setting-feedback${shippingError ? ' is-error' : ''}`} role={shippingError ? 'alert' : 'status'}>{shippingError || shippingStatus}</p>}
      </div>
    </section>
    <section className="settings-section" aria-labelledby="settings-invoice-issuer-title"><div className="settings-section-heading"><span className="settings-section-icon"><Settings size={19}/></span><div><h2 id="settings-invoice-issuer-title">Данни за издателя на фактури</h2><p>Попълнете юридическите данни, които ще се изписват във фактурите.</p></div></div><form className="settings-list issuer-settings-form" onSubmit={event=>{event.preventDefault();void saveIssuer()}}><div className="issuer-settings-grid"><FormField label="Юридическо име" helpText="Името на фирмата или лицето, което издава фактурата."><input autoComplete="organization" value={issuer.legalName} maxLength={190} onChange={e=>setIssuer({...issuer,legalName:e.target.value})}/></FormField><FormField label="ЕИК / Булстат" helpText="Идентификатор на издателя на фактурата."><input value={issuer.companyId} maxLength={40} onChange={e=>setIssuer({...issuer,companyId:e.target.value})}/></FormField><FormField label="ДДС номер" helpText="Попълва се, ако издателят е регистриран по ДДС."><input value={issuer.vatNumber} maxLength={40} onChange={e=>setIssuer({...issuer,vatNumber:e.target.value})}/></FormField><div className="issuer-settings-wide"><FormField label="Адрес" helpText="Пълният адрес, който ще се отпечата във фактурата."><input autoComplete="street-address" value={issuer.address} maxLength={500} onChange={e=>setIssuer({...issuer,address:e.target.value})}/></FormField></div><FormField label="Телефон" helpText="Телефон за контакт на издателя."><input type="tel" autoComplete="tel" value={issuer.phone} maxLength={40} onChange={e=>setIssuer({...issuer,phone:e.target.value})}/></FormField><FormField label="Имейл" helpText="Имейл за контакт на издателя."><input type="email" autoComplete="email" value={issuer.email} maxLength={254} onChange={e=>setIssuer({...issuer,email:e.target.value})}/></FormField></div><footer className="issuer-settings-actions"><button type="submit" className="primary-button" disabled={issuerSaving||shippingLoading}>{issuerSaving?'Запазване…':'Запази данните'}</button></footer></form></section>
    <section className="settings-section" aria-labelledby="settings-layout-title">
      <div className="settings-section-heading"><span className="settings-section-icon"><Settings size={19}/></span><div><h2 id="settings-layout-title">Оформление на панела</h2><p>Ширината се запазва към администраторския профил и се синхронизира между устройствата.</p></div></div>
      <div className="settings-list">
        <div className="settings-row settings-sidebar-width"><div><strong>Ширина на страничната лента</strong><p>Променете ширината с плъзгача. На тесни екрани лентата се прибира автоматично.</p></div><div className="sidebar-width-control"><input aria-label="Ширина на страничната лента" type="range" min="220" max="360" step="4" value={sidebarWidth} onChange={event=>onSidebarWidthChange(Number(event.target.value))}/><output>{sidebarWidth} px</output></div></div>
      </div>
    </section>
  </div>
}
