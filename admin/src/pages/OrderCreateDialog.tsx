import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, json } from '../lib/api'
import { formatMoney, useAdminSettings } from '../lib/adminSettings'
import type { Customer, CustomerAddress, Product, ProductDetails, ToastState } from '../types/admin'
import { DialogFooter } from '../components/DialogFooter'
import { SaveButton } from '../components/SaveButton'
import { FormField } from '../components/CatalogComponents'
import { SearchField } from '../components/SearchField'
import { MoneyInput } from '../components/MoneyInput'
import { Dropdown } from '../components/Dropdown'
import { SelectionControl } from '../components/SelectionControl'
import { Plus, Trash2, X } from 'lucide-react'

type DraftLine = { key: string; product: ProductDetails; variantId: string; quantity: string; values: Record<number,string> }
type ContactForm = { first_name: string; last_name: string; email: string; phone: string }
type AddressForm = { recipient_name: string; phone: string; country_code: string; city: string; postal_code: string; address_line1: string; address_line2: string }
const emptyAddress: AddressForm = { recipient_name:'',phone:'',country_code:'BG',city:'',postal_code:'',address_line1:'',address_line2:'' }

function cents(value: string | number | null | undefined): bigint {
  const text=String(value??'0').trim()
  const match=text.match(/^(\d+)(?:\.(\d{1,2}))?$/)
  if(!match)return 0n
  return BigInt(match[1])*100n+BigInt((match[2]||'').padEnd(2,'0'))
}
function signedCents(value: string | number | null | undefined): bigint {
  const text=String(value??'0').trim();const negative=text.startsWith('-');const magnitude=cents(negative?text.slice(1):text);return negative?-magnitude:magnitude
}
function money(value: bigint): string { return `${value/100n}.${(value%100n).toString().padStart(2,'0')}` }

export function OrderCreateDialog({ onClose, notify }: { onClose: () => void; notify: (message:string,kind?:ToastState['kind'])=>void }) {
  const navigate=useNavigate();const {settings}=useAdminSettings()
  const [customerSearch,setCustomerSearch]=useState('');const [customers,setCustomers]=useState<Customer[]>([]);const [customerId,setCustomerId]=useState('');const [customerAddresses,setCustomerAddresses]=useState<CustomerAddress[]>([])
  const [contact,setContact]=useState<ContactForm>({first_name:'',last_name:'',email:'',phone:''})
  const [address,setAddress]=useState<AddressForm>(emptyAddress);const [savedAddressId,setSavedAddressId]=useState('')
  const [productSearch,setProductSearch]=useState('');const [products,setProducts]=useState<Product[]>([]);const [lines,setLines]=useState<DraftLine[]>([])
  const [shippingMethod,setShippingMethod]=useState('Ръчно определена доставка');const [shippingTotal,setShippingTotal]=useState('0.00');const [customerNote,setCustomerNote]=useState('');const [adminNote,setAdminNote]=useState('')
  const [loadingCustomer,setLoadingCustomer]=useState(false);const [loadingProducts,setLoadingProducts]=useState(false);const [saving,setSaving]=useState(false);const [error,setError]=useState('');const [closing,setClosing]=useState(false)

  useEffect(()=>{const timeout=window.setTimeout(()=>{setLoadingCustomer(true);const query=new URLSearchParams({page:'1',per_page:'20'});if(customerSearch.trim())query.set('search',customerSearch.trim());void api<{data:Customer[]}>(`/customers?${query}`).then(result=>setCustomers(result.data)).catch(()=>setCustomers([])).finally(()=>setLoadingCustomer(false))},180);return()=>window.clearTimeout(timeout)},[customerSearch])
  useEffect(()=>{const timeout=window.setTimeout(()=>{setLoadingProducts(true);const query=new URLSearchParams({page:'1',per_page:'100',sort:'name',direction:'asc',status:'active'});if(productSearch.trim())query.set('search',productSearch.trim());void api<{data:Product[]}>(`/products?${query}`).then(result=>setProducts(result.data)).catch(()=>setProducts([])).finally(()=>setLoadingProducts(false))},180);return()=>window.clearTimeout(timeout)},[productSearch])

  const selectCustomer=async(id:string)=>{
    setCustomerId(id);setSavedAddressId('');setCustomerAddresses([])
    if(!id){setContact({first_name:'',last_name:'',email:'',phone:''});setAddress(emptyAddress);return}
    setLoadingCustomer(true)
    try{const result=await api<{data:Customer}>(`/customers/${id}`);const customer=result.data;setContact({first_name:customer.first_name,last_name:customer.last_name,email:customer.email||'',phone:customer.phone||''});setCustomerAddresses(customer.addresses||[]);if(customer.addresses?.length){const primary=customer.addresses.find(item=>item.is_default)||customer.addresses[0];setSavedAddressId(String(primary.id));setAddress(emptyAddress)}}catch(reason){setError(reason instanceof Error?reason.message:'Клиентът не можа да се зареди.')}finally{setLoadingCustomer(false)}
  }

  const addProduct=async(product:Product)=>{
    setError('')
    if(lines.some(line=>line.product.id===product.id)){notify('Този продукт вече е добавен.');return}
    try{const response=await api<{data:ProductDetails}>(`/products/${product.id}`);setLines(old=>[...old,{key:crypto.randomUUID(),product:response.data,variantId:'',quantity:'1',values:{}}]);setProductSearch('')}
    catch(reason){setError(reason instanceof Error?reason.message:'Продуктът не можа да се зареди.')}
  }

  const subtotal=useMemo(()=>lines.reduce((sum,line)=>{
    const variant=line.product.variants.find(item=>String(item.id)===line.variantId)
    const base=variant?.price!==null&&variant?.price!==undefined?cents(variant.price):cents(line.product.sale_price||line.product.price)+(variant?signedCents(variant.price_adjustment):0n)
    const personalization=(line.product.customization_fields||[]).reduce((total,field)=>{
      const selected=line.values[field.id]
      const contributes=field.field_type==='checkbox'?selected==='true':field.field_type==='image'?Boolean(selected):Boolean(selected?.trim())
      return total+(contributes?cents(field.additional_price):0n)
    },0n)
    const quantity=BigInt(Math.max(0,parseInt(line.quantity,10)||0))
    return sum+(base+personalization)*quantity
  },0n),[lines])
  const shippingCents=cents(shippingTotal)

  const updateLine=(key:string,patch:Partial<DraftLine>)=>setLines(old=>old.map(line=>line.key===key?{...line,...patch}:line))
  const setFieldValue=(key:string,fieldId:number,value:string)=>setLines(old=>old.map(line=>line.key===key?{...line,values:{...line.values,[fieldId]:value}}:line))

  const uploadImage=async(lineKey:string,fieldId:number,file:File)=>{
    setError('')
    try{const form=new FormData();form.append('file',file);const result=await api<{data:{file_reference:string}}>('/orders/customization-uploads',{method:'POST',body:form});setFieldValue(lineKey,fieldId,result.data.file_reference)}catch(reason){setError(reason instanceof Error?reason.message:'Изображението не можа да се качи.')}
  }

  const submit=async(event:React.FormEvent)=>{
    event.preventDefault();setError('')
    if(!lines.length){setError('Добавете поне един продукт.');return}
    if(lines.some(line=>line.product.variants.some(variant=>variant.is_active)&&!line.variantId)){setError('Изберете вариант за всеки продукт, който има варианти.');return}
    if(lines.some(line=>!Number.isInteger(Number(line.quantity))||Number(line.quantity)<1||Number(line.quantity)>999)){setError('Количеството трябва да е цяло число между 1 и 999.');return}
    if(!savedAddressId&&(!address.city.trim()||!address.address_line1.trim())){setError('Попълнете град и адрес за доставка.');return}
    setSaving(true)
    try{
      const normalizedShip=shippingTotal.trim().replace(',','.')||'0.00'
      const payload={customer_id:customerId?Number(customerId):null,first_name:contact.first_name,last_name:contact.last_name,email:contact.email,phone:contact.phone||null,shipping_address:savedAddressId?{customer_address_id:Number(savedAddressId)}:{...address,phone:address.phone||contact.phone},shipping_method:shippingMethod,shipping_total:normalizedShip,customer_note:customerNote||null,admin_note:adminNote||null,items:lines.map(line=>({product_id:line.product.id,variant_id:line.variantId?Number(line.variantId):null,quantity:Number(line.quantity),customizations:line.product.customization_fields.filter(field=>field.is_active).reduce<Array<{field_id:number;value?:string|boolean;file_reference?:string}>>((rows,field)=>{
        const value=line.values[field.id]||''
        if(field.field_type==='image'){if(value)rows.push({field_id:field.id,file_reference:value})}
        else rows.push({field_id:field.id,value:field.field_type==='checkbox'?value==='true':value})
        return rows
      },[])}))}
      const result=await api<{data:{id:number;order_number:string}}>('/orders',{method:'POST',headers:{'Idempotency-Key':crypto.randomUUID()},body:json(payload)})
      setClosing(true);notify(`Поръчка ${result.data.order_number} е създадена.`);window.setTimeout(()=>{onClose();navigate(`/admin/orders/${result.data.id}`)},240)
    }catch(reason){setError(reason instanceof Error?reason.message:'Поръчката не можа да бъде създадена.')}finally{setSaving(false)}
  }

  const addressOptions=[{value:'',label:'Въведи нов адрес'},...customerAddresses.map(item=>({value:String(item.id),label:`${item.city} · ${item.address_line1}${item.is_default?' · Основен':''}`}))]
  const isAddressSaved=Boolean(savedAddressId)

  return <div className="modal-backdrop order-dialog-backdrop" data-state={closing?'closed':'open'} onMouseDown={event=>{if(event.target===event.currentTarget&&!saving){setClosing(true);window.setTimeout(onClose,240)}}}>
    <section className="small-modal order-create-dialog" data-state={closing?'closed':'open'} role="dialog" aria-modal="true" aria-labelledby="order-create-title">
      <header className="editor-header order-dialog-header"><div><span className="eyebrow">АДМИНИСТРАЦИЯ / ПОРЪЧКИ</span><h2 id="order-create-title">Нова поръчка</h2></div><button type="button" className="icon-button editor-close-button" aria-label="Затвори" onClick={()=>{if(!saving){setClosing(true);window.setTimeout(onClose,240)}}}><X size={19}/></button></header>
      <form onSubmit={event=>void submit(event)} className="editor-form order-create-form">
        <div className="editor-scroll order-create-scroll">
          {error&&<div className="form-error" role="alert">{error}</div>}
          <div className="order-form-section"><div className="order-section-heading"><h3>Клиент и контакт</h3><span>Клиентът е по избор — може да създадете поръчка като гост.</span></div>
            <SearchField value={customerSearch} onChange={event=>setCustomerSearch(event.target.value)} placeholder="Търси клиент по име, имейл или телефон…" ariaLabel="Търсене на клиент"/>
            <div className="order-customer-results">{loadingCustomer?<span className="muted-cell">Зареждаме…</span>:<Dropdown value={customerId} ariaLabel="Клиент на поръчката" searchable searchPlaceholder="Търси клиент…" onChange={value=>void selectCustomer(value)} options={[{value:'',label:'Гост поръчка'},...customers.map(customer=>({value:String(customer.id),label:`${customer.first_name} ${customer.last_name}${customer.email?' · '+customer.email:''}`}))]}/>}</div>
            {customerId&&customerAddresses.length>0&&<FormField label="Адрес от профила" helpText="Адресът се копира към поръчката и няма да се променя при редактиране на клиентския профил."><Dropdown value={savedAddressId} ariaLabel="Адрес за доставка" onChange={value=>{setSavedAddressId(value);if(!value)setAddress(emptyAddress)}} options={addressOptions}/></FormField>}
            <div className="order-form-grid"><FormField label="Име" required helpText="Името в историческия запис на поръчката."><input required maxLength={100} value={contact.first_name} onChange={event=>setContact({...contact,first_name:event.target.value})}/></FormField><FormField label="Фамилия" required helpText="Фамилията в историческия запис на поръчката."><input required maxLength={100} value={contact.last_name} onChange={event=>setContact({...contact,last_name:event.target.value})}/></FormField><FormField label="Имейл" required helpText="Използва се за имейл потвържденията на поръчката."><input required type="email" maxLength={254} value={contact.email} onChange={event=>setContact({...contact,email:event.target.value})}/></FormField><FormField label="Телефон" helpText="Телефон за контакт по тази поръчка."><input maxLength={32} value={contact.phone} onChange={event=>setContact({...contact,phone:event.target.value})}/></FormField></div>
          </div>
          {!isAddressSaved&&<div className="order-form-section"><div className="order-section-heading"><h3>Адрес за доставка</h3><span>Тези данни се записват като копие в поръчката.</span></div><div className="order-form-grid"><FormField label="Получател" helpText="Ако е празно, ще се използва името на клиента."><input maxLength={160} value={address.recipient_name} onChange={event=>setAddress({...address,recipient_name:event.target.value})}/></FormField><FormField label="Телефон" helpText="Телефон за доставка."><input maxLength={32} value={address.phone} onChange={event=>setAddress({...address,phone:event.target.value})}/></FormField><FormField label="Държава (ISO код)" required helpText="Двуцифрен код, например BG."><input required maxLength={2} value={address.country_code} onChange={event=>setAddress({...address,country_code:event.target.value.toUpperCase()})}/></FormField><FormField label="Град" required helpText="Град на доставка."><input required maxLength={100} value={address.city} onChange={event=>setAddress({...address,city:event.target.value})}/></FormField><FormField label="Пощенски код" helpText="Пощенски код на адреса."><input maxLength={20} value={address.postal_code} onChange={event=>setAddress({...address,postal_code:event.target.value})}/></FormField><FormField label="Адрес, ред 1" required helpText="Улица, номер или друг основен адрес."><input required maxLength={200} value={address.address_line1} onChange={event=>setAddress({...address,address_line1:event.target.value})}/></FormField><FormField label="Адрес, ред 2" helpText="Допълнителни указания, вход, етаж или апартамент."><input maxLength={200} value={address.address_line2} onChange={event=>setAddress({...address,address_line2:event.target.value})}/></FormField></div></div>}
          <div className="order-form-section"><div className="order-section-heading"><h3>Продукти</h3><span>Цените и наличностите се проверяват от сървъра при запис.</span></div>
            <SearchField value={productSearch} onChange={event=>setProductSearch(event.target.value)} placeholder="Търси активен продукт по име или SKU…" ariaLabel="Търсене на продукт"/>
            <div className="order-product-results">{loadingProducts?<span className="muted-cell">Зареждаме продуктите…</span>:products.length===0?<span className="muted-cell">Няма намерени активни продукти.</span>:products.map(product=><button type="button" key={product.id} className="order-product-option" onClick={()=>void addProduct(product)}><span><strong>{product.name}</strong><small>{product.sku||'Без SKU'}</small></span><span>{formatMoney(product.sale_price||product.price,settings.decimalSeparator)}<Plus size={15}/></span></button>)}</div>
            {lines.map(line=><article className="order-line-editor" key={line.key}><div className="order-line-header"><div><strong>{line.product.name}</strong><small>{line.product.sku||'Без SKU'}{line.product.variants.some(variant=>variant.is_active)?' · Изберете вариант':''}</small></div><button type="button" className="icon-button compact danger-icon" title="Премахни продукт" aria-label={`Премахни ${line.product.name}`} onClick={()=>setLines(current=>current.filter(item=>item.key!==line.key))}><Trash2 size={15}/></button></div>
                {line.product.variants.some(variant=>variant.is_active)&&<FormField label="Вариант" required helpText="Цената и наличността ще бъдат проверени спрямо избрания вариант."><Dropdown value={line.variantId} ariaLabel={`Вариант на ${line.product.name}`} onChange={value=>updateLine(line.key,{variantId:value})} options={[{value:'',label:'Изберете вариант'},...line.product.variants.filter(variant=>variant.is_active).map(variant=>({value:String(variant.id),label:`${variant.name}${variant.sku?' · '+variant.sku:''}${variant.price?' · '+formatMoney(variant.price,settings.decimalSeparator):''}`,disabled:!variant.is_available||variant.inventory_mode==='tracked'&&(variant.stock_quantity||0)<1}))]}/></FormField>}
                <FormField label="Количество" required helpText="От 1 до 999 броя."><input type="number" min="1" max="999" step="1" value={line.quantity} onChange={event=>updateLine(line.key,{quantity:event.target.value})}/></FormField>
                {line.product.customization_fields.filter(field=>field.is_active).length>0&&<div className="order-customization-list"><strong>Персонализации</strong>{line.product.customization_fields.filter(field=>field.is_active).map(field=><div className="order-customization-field" key={field.id}>
                  {field.field_type==='checkbox'?<SelectionControl variant="checkbox" checked={line.values[field.id]==='true'} onChange={checked=>setFieldValue(line.key,field.id,String(checked))} label={<>{field.name}{Number(field.additional_price)>0?` (+${formatMoney(field.additional_price,settings.decimalSeparator)})`:''}</>}/>:field.field_type==='select'?<FormField label={field.name+(Number(field.is_required)?' *':'')} helpText="Изберете една от стойностите на продуктовото поле."><Dropdown value={line.values[field.id]||''} ariaLabel={field.name} onChange={value=>setFieldValue(line.key,field.id,value)} options={[{value:'',label:'Без избор'},...(field.options||[]).map(value=>({value,label:value}))]}/></FormField>:field.field_type==='image'?<FormField label={field.name+(Number(field.is_required)?' *':'')} helpText="Изображението се пази извън публичната директория и е достъпно само в администрацията."><input className="order-upload-input" type="file" accept="image/jpeg,image/png,image/webp" onChange={event=>{const file=event.target.files?.[0];if(file)void uploadImage(line.key,field.id,file);event.currentTarget.value=''}}/>{line.values[field.id]&&<small className="order-upload-success">Изображението е качено.</small>}</FormField>:<FormField label={`${field.name}${Number(field.is_required)?' *':''}${Number(field.additional_price)>0?` (+${formatMoney(field.additional_price,settings.decimalSeparator)})`:''}`} helpText={field.field_type==='textarea'?'Текстова персонализация за поръчката.':'Кратка текстова персонализация.'}>{field.field_type==='textarea'?<textarea rows={2} value={line.values[field.id]||''} onChange={event=>setFieldValue(line.key,field.id,event.target.value)}/>:<input value={line.values[field.id]||''} onChange={event=>setFieldValue(line.key,field.id,event.target.value)}/>}</FormField>}
                </div>)}</div>}
              </article>)}
          </div>
          <div className="order-form-section"><div className="order-section-heading"><h3>Доставка и бележки</h3><span>Плащането е с наложен платеж. Данък не се начислява без данъчна настройка.</span></div><div className="order-form-grid"><FormField label="Метод на доставка" required helpText="Ръчна стойност, която се запазва към поръчката."><input required maxLength={100} value={shippingMethod} onChange={event=>setShippingMethod(event.target.value)}/></FormField><FormField label="Цена за доставка (€)" helpText="Цената се записва като snapshot към поръчката."><MoneyInput value={shippingTotal} onChange={setShippingTotal}/></FormField><FormField label="Бележка от клиента" helpText="Бележката остава в поръчката и може да бъде видяна от клиента в бъдещ storefront."><textarea rows={3} value={customerNote} onChange={event=>setCustomerNote(event.target.value)}/></FormField><FormField label="Вътрешна бележка" helpText="Тази бележка е само за администрацията и не се изпраща на клиента."><textarea rows={3} value={adminNote} onChange={event=>setAdminNote(event.target.value)}/></FormField></div>
            <div className="order-total-preview"><span>Междинна сума</span><strong>{formatMoney(money(subtotal),settings.decimalSeparator)}</strong><span>Доставка</span><strong>{formatMoney(money(shippingCents),settings.decimalSeparator)}</strong><span>Данък</span><strong>{formatMoney('0.00',settings.decimalSeparator)}</strong><span className="order-grand-label">Общо · EUR</span><strong className="order-grand-total">{formatMoney(money(subtotal+shippingCents),settings.decimalSeparator)}</strong></div>
          </div>
        </div>
        <DialogFooter className="order-create-footer"><button type="button" className="outline-button" disabled={saving} onClick={()=>{setClosing(true);window.setTimeout(onClose,240)}}>Отказ</button><SaveButton disabled={saving||loadingCustomer||loadingProducts} loading={saving} loadingLabel="Запис…">Създай поръчка</SaveButton></DialogFooter>
      </form>
    </section>
  </div>
}
