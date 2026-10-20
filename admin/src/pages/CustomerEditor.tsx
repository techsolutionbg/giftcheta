import { CloseButton } from '../components/CloseButton'
import { api, json } from '../lib/api'
import type { Customer, CustomerAddress, ToastState } from '../types/admin'
import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react'
import { KeyRound, Plus, Trash2 } from 'lucide-react'
import { createPortal } from 'react-dom'
import { useLocation, useNavigate } from 'react-router-dom'
import { FormField } from '../components/CatalogComponents'
import { Dropdown } from '../components/Dropdown'
import { SelectionControl } from '../components/SelectionControl'
import { DialogFooter } from '../components/DialogFooter'
import { SaveButton } from '../components/SaveButton'
import { useConfirmation, useUnsavedChanges } from '../components/UnsavedChanges'

type CustomerForm = { first_name: string; last_name: string; email: string; phone: string; phone_country_code: string; status: string; admin_notes: string }
type AddressForm = { recipient_name: string; phone: string; country_code: string; city: string; postal_code: string; address_line1: string; address_line2: string; is_default: boolean }
const blankCustomer: CustomerForm = { first_name: '', last_name: '', email: '', phone: '', phone_country_code: 'BG', status: 'active', admin_notes: '' }
const blankAddress: AddressForm = { recipient_name: '', phone: '', country_code: 'BG', city: '', postal_code: '', address_line1: '', address_line2: '', is_default: false }
const countryCodes = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW'.split(' ')
const countryDisplayNames = new Intl.DisplayNames(['bg'], { type: 'region' })
const countryOptions = countryCodes.map(code => ({ value: code, label: `${countryDisplayNames.of(code) || code} (${code})` })).sort((a, b) => a.label.localeCompare(b.label, 'bg'))
const countryDialCodes = Object.fromEntries('AD:376 AE:971 AF:93 AG:1268 AI:1264 AL:355 AM:374 AO:244 AQ:672 AR:54 AS:1684 AT:43 AU:61 AW:297 AX:358 AZ:994 BA:387 BB:1246 BD:880 BE:32 BF:226 BG:359 BH:973 BI:257 BJ:229 BL:590 BM:1441 BN:673 BO:591 BQ:599 BR:55 BS:1242 BT:975 BV:47 BW:267 BY:375 BZ:501 CA:1 CC:61 CD:243 CF:236 CG:242 CH:41 CI:225 CK:682 CL:56 CM:237 CN:86 CO:57 CR:506 CU:53 CV:238 CW:599 CX:61 CY:357 CZ:420 DE:49 DJ:253 DK:45 DM:1767 DO:1809 DZ:213 EC:593 EE:372 EG:20 EH:212 ER:291 ES:34 ET:251 FI:358 FJ:679 FK:500 FM:691 FO:298 FR:33 GA:241 GB:44 GD:1473 GE:995 GF:594 GG:44 GH:233 GI:350 GL:299 GM:220 GN:224 GP:590 GQ:240 GR:30 GS:500 GT:502 GU:1671 GW:245 GY:592 HK:852 HM:672 HN:504 HR:385 HT:509 HU:36 ID:62 IE:353 IL:972 IM:44 IN:91 IO:246 IQ:964 IR:98 IS:354 IT:39 JE:44 JM:1876 JO:962 JP:81 KE:254 KG:996 KH:855 KI:686 KM:269 KN:1869 KP:850 KR:82 KW:965 KY:1345 KZ:7 LA:856 LB:961 LC:1758 LI:423 LK:94 LR:231 LS:266 LT:370 LU:352 LV:371 LY:218 MA:212 MC:377 MD:373 ME:382 MF:590 MG:261 MH:692 MK:389 ML:223 MM:95 MN:976 MO:853 MP:1670 MQ:596 MR:222 MS:1664 MT:356 MU:230 MV:960 MW:265 MX:52 MY:60 MZ:258 NA:264 NC:687 NE:227 NF:672 NG:234 NI:505 NL:31 NO:47 NP:977 NR:674 NU:683 NZ:64 OM:968 PA:507 PE:51 PF:689 PG:675 PH:63 PK:92 PL:48 PM:508 PN:64 PR:1787 PS:970 PT:351 PW:680 PY:595 QA:974 RE:262 RO:40 RS:381 RU:7 RW:250 SA:966 SB:677 SC:248 SD:249 SE:46 SG:65 SH:290 SI:386 SJ:47 SK:421 SL:232 SM:378 SN:221 SO:252 SR:597 SS:211 ST:239 SV:503 SX:1721 SY:963 SZ:268 TC:1649 TD:235 TG:228 TH:66 TJ:992 TK:690 TL:670 TM:993 TN:216 TO:676 TR:90 TT:1868 TV:688 TW:886 TZ:255 UA:380 UG:256 UM:1808 US:1 UY:598 UZ:998 VA:379 VC:1784 VE:58 VG:1284 VI:1340 VN:84 VU:678 WF:681 WS:685 YE:967 YT:262 ZA:27 ZM:260 ZW:263'.split(' ').map(pair => pair.split(':')))
const phoneCountryOptions = countryCodes.filter(code => countryDialCodes[code]).map(code => ({ value: code, label: `${countryDisplayNames.of(code) || code} (+${countryDialCodes[code]}) · ${code}`, selectedLabel: countryDisplayNames.of(code) || code, searchText: `${code} +${countryDialCodes[code]}` })).sort((a, b) => (countryDisplayNames.of(a.value) || a.value).localeCompare(countryDisplayNames.of(b.value) || b.value, 'bg'))
const getPhoneParts = (phone: string) => {
  const normalized = phone.replace(/[^\d+]/g, '')
  if (!normalized) return { phone: '', country: 'BG' }
  const international = normalized.startsWith('+') ? normalized : `+${normalized}`
  const match = phoneCountryOptions.slice().sort((a, b) => countryDialCodes[b.value].length - countryDialCodes[a.value].length).find(option => international.startsWith(`+${countryDialCodes[option.value]}`))
  return match ? { phone: international.slice(`+${countryDialCodes[match.value]}`.length).replace(/\D/g, ''), country: match.value } : { phone: normalized.replace(/\D/g, ''), country: 'BG' }
}

export function CustomerEditor({ customerId, onClose, onSaved, notify }: { customerId: number | null; onClose: () => void; onSaved: (id?: number) => void; notify: (message: string, kind?: ToastState['kind']) => void }) {
  const isNew = customerId === null
  const location = useLocation()
  const navigate = useNavigate()
  const addressDialogRequested = new URLSearchParams(location.search).get('subdialog') === 'addresses'
  const confirm = useConfirmation()
  const [form, setForm] = useState<CustomerForm>(blankCustomer)
  const [hasCustomerAccount, setHasCustomerAccount] = useState(false)
  const [accountEmail, setAccountEmail] = useState('')
  const [sendingReset, setSendingReset] = useState(false)
  const [addresses, setAddresses] = useState<CustomerAddress[]>([])
  const [addressesOpen, setAddressesOpen] = useState(false)
  const [addressesClosing, setAddressesClosing] = useState(false)
  const [addressForm, setAddressForm] = useState<AddressForm | null>(null)
  const [editingAddressId, setEditingAddressId] = useState<number | null>(null)
  const addressBaseline = useRef('')
  const [busy, setBusy] = useState(!isNew)
  const [saving, setSaving] = useState(false)
  const [closing, setClosing] = useState(false)
  const [error, setError] = useState('')
  const baseline = useRef(JSON.stringify(blankCustomer))
  const dirty = JSON.stringify(form) !== baseline.current
  const { confirmDiscard, markClean } = useUnsavedChanges('клиента', dirty)

  const load = useCallback(async () => {
    if (customerId === null) return
    setBusy(true)
    try {
      const result = await api<{ data: Customer }>(`/customers/${customerId}`)
      const phone = getPhoneParts(result.data.phone || '')
      const next: CustomerForm = { first_name: result.data.first_name, last_name: result.data.last_name, email: result.data.email || '', phone: phone.phone, phone_country_code: phone.country, status: result.data.status, admin_notes: result.data.admin_notes || '' }
      setForm(next)
      setHasCustomerAccount(Boolean(result.data.has_account))
      setAccountEmail(result.data.email || '')
      baseline.current = JSON.stringify(next)
      setAddresses(result.data.addresses || [])
      setError('')
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Клиентът не можа да бъде зареден.')
    } finally {
      setBusy(false)
    }
  }, [customerId])

  useEffect(() => { void load() }, [load])

  const close = async () => {
    if (closing || saving || !(await confirmDiscard())) return
    setClosing(true)
    window.setTimeout(onClose, 240)
  }
  const submit = async (event: FormEvent) => {
    event.preventDefault()
    if (saving) return
    setSaving(true)
    setError('')
    try {
      const { phone_country_code, ...customerFields } = form
      const payload = { ...customerFields, phone: form.phone.trim() ? `+${countryDialCodes[phone_country_code]}${form.phone.replace(/\D/g, '')}` : null }
      let id: number
      if (isNew) {
        const result = await api<{ data: { id: number } }>('/customers', { method: 'POST', body: json(payload) })
        id = result.data.id
      } else {
        await api(`/customers/${customerId}`, { method: 'PATCH', body: json(payload) })
        id = customerId!
      }
      baseline.current = JSON.stringify(form)
      if (hasCustomerAccount) setAccountEmail(form.email)
      markClean()
      notify(isNew ? 'Клиентът е създаден.' : 'Промените по клиента са запазени.')
      onSaved(isNew ? id : undefined)
    } catch (reason) {
      const message = reason instanceof Error ? reason.message : 'Клиентът не можа да бъде запазен.'
      setError(message)
      notify(message, 'error')
    } finally {
      setSaving(false)
    }
  }

  const sendPasswordReset = async () => {
    if (!customerId || !hasCustomerAccount || saving || sendingReset || form.email.trim().toLowerCase() !== accountEmail.trim().toLowerCase()) return
    setSendingReset(true)
    try {
      await api(`/customers/${customerId}/password-reset`, { method: 'POST', body: '{}' })
      notify('Линкът за възстановяване на паролата е изпратен по имейл.')
    } catch (reason) {
      notify(reason instanceof Error ? reason.message : 'Линкът за възстановяване не можа да бъде изпратен.', 'error')
    } finally {
      setSendingReset(false)
    }
  }

  const openAddress = (address?: CustomerAddress) => {
    const next = address ? { recipient_name: address.recipient_name, phone: address.phone || '', country_code: address.country_code, city: address.city, postal_code: address.postal_code || '', address_line1: address.address_line1, address_line2: address.address_line2 || '', is_default: Boolean(address.is_default) } : { ...blankAddress, is_default: addresses.length === 0 }
    setEditingAddressId(address?.id ?? null)
    addressBaseline.current = JSON.stringify(next)
    setAddressForm(next)
  }
  const openAddressManager = () => {
    openAddress()
    setAddressesOpen(true)
    const params = new URLSearchParams(location.search)
    params.set('subdialog', 'addresses')
    navigate(`${location.pathname}?${params.toString()}`)
  }
  const updateAddressDialogUrl = (open: boolean, replace = false) => {
    const params = new URLSearchParams(location.search)
    if (open) params.set('subdialog', 'addresses')
    else params.delete('subdialog')
    navigate(`${location.pathname}${params.size ? `?${params.toString()}` : ''}`, { replace })
  }
  const closeAddressManager = async () => {
    if (saving || addressesClosing) return
    if (addressForm && JSON.stringify(addressForm) !== addressBaseline.current && !(await confirm({ title: 'Отказ от промените по адреса?', message: 'Въведената информация за адреса няма да бъде запазена.', confirmLabel: 'Откажи промените', danger: true }))) {
      if (!addressDialogRequested) updateAddressDialogUrl(true, true)
      return
    }
    setAddressesClosing(true)
    window.setTimeout(() => {
      setAddressForm(null)
      setEditingAddressId(null)
      setAddressesOpen(false)
      setAddressesClosing(false)
      if (new URLSearchParams(window.location.search).get('subdialog') === 'addresses') updateAddressDialogUrl(false)
    }, 240)
  }
  useEffect(() => {
    if (addressDialogRequested && customerId && !busy && !addressesOpen) {
      openAddress()
      setAddressesOpen(true)
    } else if (!addressDialogRequested && addressesOpen && !addressesClosing) {
      void closeAddressManager()
    }
  }, [addressDialogRequested, customerId, busy, addressesOpen, addressesClosing])
  const saveAddress = async (event: FormEvent) => {
    event.preventDefault()
    if (!customerId || !addressForm) return
    setSaving(true)
    try {
      const path = `/customers/${customerId}/addresses${editingAddressId ? `/${editingAddressId}` : ''}`
      const wasEditing = editingAddressId !== null
      await api(path, { method: wasEditing ? 'PATCH' : 'POST', body: json(addressForm) })
      const next = { ...blankAddress, is_default: addresses.length === 0 }
      addressBaseline.current = JSON.stringify(next)
      setAddressForm(next)
      setEditingAddressId(null)
      notify(wasEditing ? 'Адресът е обновен.' : 'Адресът е добавен.')
      void load()
    } catch (reason) {
      const message = reason instanceof Error ? reason.message : 'Адресът не можа да бъде запазен.'
      notify(message, 'error')
    } finally {
      setSaving(false)
    }
  }
  const deleteAddress = async (address: CustomerAddress) => {
    if (!customerId || !(await confirm({ title: 'Изтриване на адрес?', message: `Адресът в ${address.city} ще бъде премахнат от клиента.`, confirmLabel: 'Изтрий адреса', danger: true }))) return
    try {
      await api(`/customers/${customerId}/addresses/${address.id}`, { method: 'DELETE', body: '{}' })
      notify('Адресът е изтрит.')
      void load()
    } catch (reason) {
      notify(reason instanceof Error ? reason.message : 'Адресът не можа да бъде изтрит.', 'error')
    }
  }
  const setAddressValue = <K extends keyof AddressForm>(key: K, value: AddressForm[K]) => setAddressForm(current => current ? { ...current, [key]: value } : current)
  const setCustomerValue = <K extends keyof CustomerForm>(key: K, value: CustomerForm[K]) => setForm(current => ({ ...current, [key]: value }))

  return <><div className="modal-backdrop customer-editor-backdrop" data-state={closing?'closed':'open'} onMouseDown={event=>{if(event.target===event.currentTarget)void close()}}>
    <section className="small-modal customer-editor-dialog" data-state={closing?'closed':'open'} role="dialog" aria-modal="true" aria-labelledby="customer-editor-title">
      <header className="editor-header customer-editor-header"><div><h2 id="customer-editor-title">{isNew?'Създаване на клиент':'Редакция на клиент'}</h2>{!isNew&&<p>{form.first_name} {form.last_name}</p>}</div><div className="customer-editor-header-actions">{!isNew&&<button type="button" className="outline-button tiny customer-header-address-button" onClick={openAddressManager} disabled={busy}><Plus size={15}/> Добави адрес</button>}<CloseButton className="editor-close-button" title="Затвори редактора" aria-label="Затвори редактора" onClick={()=>void close()}/></div></header>
      <div className="customer-editor-scroll">
    {busy?<div className="loading-state"><span className="spinner"/> Зареждаме клиента…</div>:<>
      {error&&<div className="form-error" role="alert">{error}</div>}
      <form id="customer-editor-form" className="customer-editor-form" onSubmit={submit}>
          <div className="customer-form-grid">
            <FormField label="Име" required helpText="Собственото име на клиента."><input value={form.first_name} onChange={event=>setCustomerValue('first_name',event.target.value)} maxLength={100} required/></FormField>
            <FormField label="Фамилия" required helpText="Фамилията на клиента."><input value={form.last_name} onChange={event=>setCustomerValue('last_name',event.target.value)} maxLength={100} required/></FormField>
            <div className="customer-form-wide"><FormField label="Имейл" helpText="Контактен имейл. Акаунт за вход и парола не се създават от този формуляр."><input type="email" value={form.email} onChange={event=>setCustomerValue('email',event.target.value)} maxLength={254}/></FormField></div>
            <div className="customer-form-wide"><FormField label="Телефон" helpText="Изберете код на държавата и въведете телефонния номер."><div className="customer-phone-control"><Dropdown ariaLabel="Код на държавата за телефона" value={form.phone_country_code} onChange={value=>setCustomerValue('phone_country_code',value)} options={phoneCountryOptions} searchable searchPlaceholder="Търси държава или код…"/><input type="tel" inputMode="tel" autoComplete="tel-national" value={form.phone} onChange={event=>{const raw=event.target.value;if(raw.startsWith('+')){const parsed=getPhoneParts(raw);setForm(current=>({...current,phone:parsed.phone,phone_country_code:parsed.country}))}else setCustomerValue('phone',raw)}} maxLength={24} placeholder="88 123 45 67"/></div></FormField></div>
            <FormField label="Статус" helpText="Блокираните клиенти няма да могат да влизат, когато бъде добавен клиентски вход."><Dropdown ariaLabel="Статус на клиента" value={form.status} onChange={value=>setCustomerValue('status',value)} options={[{value:'active',label:'Активен'},{value:'inactive',label:'Неактивен'},{value:'blocked',label:'Блокиран'}]}/></FormField>
            <div className="customer-account-note"><strong>Защита на акаунта</strong><p>{hasCustomerAccount?'Изпратете еднократен линк за възстановяване на паролата до имейла на клиента. Линкът изтича след 60 минути.':'Този клиент все още няма клиентски акаунт с парола.'}</p>{hasCustomerAccount&&<button type="button" className="outline-button customer-reset-password-button" onClick={()=>void sendPasswordReset()} disabled={sendingReset||saving||busy||!accountEmail||form.email.trim().toLowerCase()!==accountEmail.trim().toLowerCase()}><KeyRound size={15}/>{sendingReset?'Изпращане…':'Изпрати линк за възстановяване'}</button>}</div>
            <div className="customer-form-full"><FormField label="Административни бележки" helpText="Вътрешни бележки, видими само в администрацията. Не записвайте пароли или токени."><textarea rows={4} value={form.admin_notes} onChange={event=>setCustomerValue('admin_notes',event.target.value)} maxLength={10000}/></FormField></div>
          </div>
      </form>

    </>}
      </div>
      <DialogFooter className="customer-editor-footer"><button type="button" className="outline-button" onClick={()=>void close()}>Отказ</button><SaveButton form="customer-editor-form" disabled={saving} loading={saving}>Запази клиента</SaveButton></DialogFooter>
    </section>
  </div>
  {addressesOpen&&createPortal(<div className="modal-backdrop customer-address-dialog-backdrop" data-state={addressesClosing?'closed':'open'} onMouseDown={event=>{if(event.target===event.currentTarget)void closeAddressManager()}}>
    <section className="small-modal customer-address-dialog" data-state={addressesClosing?'closed':'open'} role="dialog" aria-modal="true" aria-labelledby="customer-address-title">
      <header className="editor-header customer-address-dialog-header"><div><h2 id="customer-address-title">Адреси</h2><p>Адреси за клиента {form.first_name} {form.last_name}</p></div><CloseButton className="editor-close-button" title="Затвори адресите" aria-label="Затвори адресите" onClick={()=>void closeAddressManager()}/></header>
      <div className="customer-address-manager-content">
      {addressForm&&<form id="customer-address-form" className="customer-address-dialog-form" onSubmit={saveAddress}><h3>{editingAddressId?'Редакция на адрес':'Нов адрес'}</h3><div className="customer-form-grid">
        <FormField label="Получател" required helpText="Името на получателя на адреса."><input value={addressForm!.recipient_name} onChange={event=>setAddressValue('recipient_name',event.target.value)} maxLength={160} required/></FormField>
        <FormField label="Телефон" helpText="Телефон за доставка с международен код, ако е приложимо."><input type="tel" value={addressForm!.phone} onChange={event=>setAddressValue('phone',event.target.value)} maxLength={32}/></FormField>
        <FormField label="Държава" required helpText="Потърсете по име или ISO код на държавата."><Dropdown ariaLabel="Държава на адреса" value={addressForm!.country_code} onChange={value=>setAddressValue('country_code',value)} options={countryOptions} searchable searchPlaceholder="Търси държава или код…"/></FormField>
        <FormField label="Град" required helpText="Населеното място на адреса."><input value={addressForm!.city} onChange={event=>setAddressValue('city',event.target.value)} maxLength={100} required/></FormField>
        <FormField label="Пощенски код" helpText="Пощенски код на адреса, ако е наличен."><input value={addressForm!.postal_code} onChange={event=>setAddressValue('postal_code',event.target.value)} maxLength={20}/></FormField>
        <FormField label="Адрес, ред 1" required helpText="Улица, номер и вход или друг основен адресен ред."><input value={addressForm!.address_line1} onChange={event=>setAddressValue('address_line1',event.target.value)} maxLength={200} required/></FormField>
        <div className="customer-form-wide"><FormField label="Адрес, ред 2" helpText="Допълнителна информация като етаж, апартамент или офис."><input value={addressForm!.address_line2} onChange={event=>setAddressValue('address_line2',event.target.value)} maxLength={200}/></FormField></div>
        <div className="check-setting-row customer-default-toggle"><SelectionControl variant="switch" checked={addressForm!.is_default} onChange={checked=>setAddressValue('is_default',checked)} label="Основен адрес"/></div>
      </div></form>}
      {addresses.length===0?<div className="customer-address-empty">Няма добавени адреси.</div>:<div className="customer-address-list">{addresses.map(address=><article className="customer-address-card" key={address.id}><div><strong>{address.recipient_name}{Boolean(address.is_default)&&<span className="customer-default-badge">Основен</span>}</strong><p>{address.address_line1}{address.address_line2?`, ${address.address_line2}`:''}, {address.postal_code?`${address.postal_code} `:''}{address.city}, {address.country_code}</p><small>{address.phone||'Без телефон'}</small></div><div className="customer-address-actions"><button type="button" className="outline-button tiny" onClick={()=>openAddress(address)}>Редактирай</button><button type="button" className="icon-button compact danger-icon" title="Изтрий адреса" aria-label="Изтрий адреса" onClick={()=>void deleteAddress(address)}><Trash2 size={15}/></button></div></article>)}</div>}
      </div>
      <DialogFooter className="customer-address-form-actions customer-address-dialog-footer"><button type="button" className="outline-button" onClick={()=>void closeAddressManager()}>Отказ</button><SaveButton form="customer-address-form" disabled={saving}>Запази адреса</SaveButton></DialogFooter>
    </section>
  </div>, document.body)}</>
}
