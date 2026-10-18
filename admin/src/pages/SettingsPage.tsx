import { useAdminSettings, formatMoney } from '../lib/adminSettings'
import { Breadcrumbs } from '../components/Navigation'
import { Dropdown } from '../components/Dropdown'
import { Settings } from 'lucide-react'

export function SettingsPage() {
  const { settings, updateSettings } = useAdminSettings()
  return <div className="page-content settings-page">
    <div className="page-heading"><div><h1>Настройки</h1><Breadcrumbs/><p>Настройте начина, по който администрацията показва и създава продукти.</p></div></div>
    <section className="settings-section" aria-labelledby="settings-currency-title">
      <div className="settings-section-heading"><span className="settings-section-icon"><Settings size={19}/></span><div><h2 id="settings-currency-title">Формат и каталог</h2><p>Тези предпочитания се запазват за този браузър.</p></div></div>
      <div className="settings-list">
        <div className="settings-row"><div><strong>Десетичен разделител за цени</strong><p>Изберете дали сумите да се показват със запетая или точка пред стотинките. Пример: {formatMoney(1234.56, settings.decimalSeparator)}.</p></div><Dropdown ariaLabel="Десетичен разделител за цени" value={settings.decimalSeparator} onChange={value=>updateSettings({decimalSeparator:value as ','|'.'})} options={[{value:',',label:'Запетая (1.234,56)'},{value:'.',label:'Точка (1,234.56)'}]}/></div>
        <div className="settings-row"><div><strong>Статус по подразбиране за нов продукт</strong><p>Изберете дали новите продукти да се създават като чернови или да се публикуват веднага.</p></div><Dropdown ariaLabel="Статус по подразбиране за нов продукт" value={settings.defaultProductStatus} onChange={value=>updateSettings({defaultProductStatus:value as 'draft'|'active'})} options={[{value:'draft',label:'Чернова'},{value:'active',label:'Активен'}]}/></div>
        <div className="settings-row"><div><strong>Продукти на страница</strong><p>Брой редове, които се зареждат във всяка страница на продуктовия каталог.</p></div><Dropdown ariaLabel="Продукти на страница" value={String(settings.productsPerPage)} onChange={value=>updateSettings({productsPerPage:Number(value) as 10|20|50})} options={[{value:'10',label:'10 продукта'},{value:'20',label:'20 продукта'},{value:'50',label:'50 продукта'}]}/></div>
      </div>
    </section>
  </div>
}
