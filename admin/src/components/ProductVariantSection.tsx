import { useState } from 'react'
import { Boxes, Camera, Pencil, Plus, Trash2 } from 'lucide-react'
import { api } from '../lib/api'
import { formatMoney } from '../lib/adminSettings'
import { useAdminSettings } from '../lib/adminSettings'
import { useConfirmation } from './UnsavedChanges'
import { ProductVariantDialog } from './ProductVariantDialog'
import { VariantStockDialog } from './VariantStockDialog'
import { SelectionControl } from './SelectionControl'

type VariantRecord = Record<string, any> & { id: number; name: string }

export function ProductVariantSection({ productId, variants, onVariantsChange, notify, onError }: {
  productId: number
  variants: VariantRecord[]
  onVariantsChange: (update: (variants: VariantRecord[]) => VariantRecord[]) => void
  notify: (message: string) => void
  onError: (message: string) => void
}) {
  const { settings } = useAdminSettings()
  const confirm = useConfirmation()
  const [dialogVariant, setDialogVariant] = useState<VariantRecord | null | 'create'>(null)
  const [stockVariants, setStockVariants] = useState<VariantRecord[] | null>(null)
  const [selectedIds, setSelectedIds] = useState<number[]>([])
  const allSelected = variants.length > 0 && variants.every(variant => selectedIds.includes(variant.id))

  const toggleSelected = (id: number, checked: boolean) => setSelectedIds(current => checked ? [...new Set([...current, id])] : current.filter(selectedId => selectedId !== id))
  const updateStock = (quantity: number) => {
    const ids = new Set((stockVariants || []).map(variant => variant.id))
    onVariantsChange(items => items.map(item => ids.has(item.id) ? { ...item, stock_quantity: quantity, inventory_mode: 'tracked', is_available: quantity > 0 } : item))
    setSelectedIds(current => current.filter(id => !ids.has(id)))
    notify(`Наличността е актуализирана за ${ids.size} ${ids.size === 1 ? 'вариант' : 'варианта'}.`)
  }

  const deleteSelected = async () => {
    const selected = variants.filter(variant => selectedIds.includes(variant.id))
    if (!selected.length) return
    if (!(await confirm({ title: 'Изтриване на варианти?', message: `Ще бъдат изтрити ${selected.length} избрани варианта.`, confirmLabel: 'Изтрий вариантите', danger: true }))) return
    const deleted = new Set<number>()
    const failed: VariantRecord[] = []
    for (const variant of selected) {
      try { await api(`/variants/${variant.id}`, { method: 'DELETE', body: '{}' }); deleted.add(variant.id) }
      catch { failed.push(variant) }
    }
    if (deleted.size) onVariantsChange(items => items.filter(item => !deleted.has(item.id)))
    setSelectedIds(failed.map(variant => variant.id))
    if (failed.length) onError(`Неуспешно изтриване на: ${failed.map(variant => variant.name).join(', ')}.`)
    else notify(`Изтрити са ${deleted.size} ${deleted.size === 1 ? 'вариант' : 'варианта'}.`)
  }

  const saveVariant = (saved: VariantRecord) => {
    onVariantsChange(items => dialogVariant === 'create' ? [...items, saved] : items.map(item => item.id === saved.id ? saved : item))
    notify(dialogVariant === 'create' ? 'Вариантът е добавен.' : 'Вариантът е актуализиран.')
  }

  return <>
    {variants.length > 0 && <div className="variant-bulk-toolbar"><SelectionControl variant="switch" checked={allSelected} onChange={checked => setSelectedIds(checked ? variants.map(variant => variant.id) : [])} label={selectedIds.length ? `Избрани: ${selectedIds.length}` : 'Избери всички'} ariaLabel="Избери всички варианти"/><div className={`variant-bulk-actions${selectedIds.length === 0 ? ' is-empty' : ''}`} aria-hidden={selectedIds.length === 0}>{selectedIds.length > 0 && <><button type="button" className="outline-button" onClick={() => setStockVariants(variants.filter(variant => selectedIds.includes(variant.id)))}><Boxes size={15}/> Наличност ({selectedIds.length})</button><button type="button" className="outline-button danger-icon" onClick={() => void deleteSelected()}><Trash2 size={15}/> Изтрий ({selectedIds.length})</button></>}</div></div>}
    <div className="nested-list">
      {variants.map(variant => <div className="variant-nested-row" key={variant.id}>
        <SelectionControl variant="switch" checked={selectedIds.includes(variant.id)} onChange={checked => toggleSelected(variant.id, checked)} ariaLabel={`Избери вариант ${variant.name}`}/>
        <label className="variant-image-picker" title={variant.image_path ? `Смени изображението на варианта ${variant.name}` : `Качи изображение за варианта ${variant.name}`}>
          <input type="file" accept="image/jpeg,image/png,image/webp" aria-label={`Изображение на вариант ${variant.name}`} onChange={async event => {
            const input = event.currentTarget
            const selected = input.files?.[0]
            if (!selected) return
            const body = new FormData()
            body.append('image', selected)
            try {
              const result = await api<{ data: { image_path: string; image_thumbnail_path: string } }>(`/variants/${variant.id}/image`, { method: 'POST', body })
              onVariantsChange(items => items.map(item => item.id === variant.id ? { ...item, ...result.data } : item))
              notify('Изображението на варианта е качено.')
            } catch (reason) { onError(reason instanceof Error ? reason.message : 'Изображението на варианта не беше качено.') }
            finally { input.value = '' }
          }}/>
          {variant.image_thumbnail_path ? <img src={`/${variant.image_thumbnail_path}`} alt=""/> : <span className="variant-image-empty"><Camera size={24} aria-hidden="true"/><small>Добави снимка</small></span>}
        </label>
        <div className="variant-row-details"><strong>{variant.color_hex && <i className="variant-admin-swatch" style={{ backgroundColor: variant.color_hex }} aria-hidden="true"/>}{variant.name}</strong><span>SKU: {variant.sku || '—'} · Наличност: {variant.stock_quantity ?? 0} бр.</span></div>
        <span>{variant.price ? formatMoney(variant.price, settings.decimalSeparator) : `Корекция ${formatMoney(variant.price_adjustment, settings.decimalSeparator)}`}</span>
        <button type="button" className="icon-button compact" title="Промени наличността" aria-label={`Промени наличността за ${variant.name}`} onClick={() => setStockVariants([variant])}><Boxes size={16}/></button>
        <button type="button" className="icon-button compact" title="Редакция на варианта" aria-label={`Редакция на варианта ${variant.name}`} onClick={() => setDialogVariant(variant)}><Pencil size={16}/></button>
        <button type="button" className="icon-button compact danger-icon" title="Изтрий варианта" aria-label={`Изтрий варианта ${variant.name}`} onClick={async () => {
          if (await confirm({ title: 'Изтриване на вариант?', message: `Вариантът „${variant.name}“ ще бъде изтрит.`, confirmLabel: 'Изтрий варианта', danger: true })) {
            try { await api(`/variants/${variant.id}`, { method: 'DELETE', body: '{}' }); onVariantsChange(items => items.filter(item => item.id !== variant.id)) }
            catch (reason) { onError(reason instanceof Error ? reason.message : 'Вариантът не можа да бъде изтрит.') }
          }
        }}><Trash2 size={16}/></button>
      </div>)}
    </div>
    <button type="button" className="outline-button variant-add-action" onClick={() => setDialogVariant('create')}><Plus size={15}/> Добави вариант</button>
    {dialogVariant !== null && <ProductVariantDialog productId={productId} variant={dialogVariant === 'create' ? null : dialogVariant} onClose={() => setDialogVariant(null)} onSaved={saveVariant}/>}
    {stockVariants && <VariantStockDialog productId={productId} variants={stockVariants} onClose={() => setStockVariants(null)} onSaved={updateStock}/>}
  </>
}
