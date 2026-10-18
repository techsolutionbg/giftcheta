export type ApiError = { error?: { code: string; message: string } }
export type Admin = { id: number; name: string; email: string; role: string }
export type Category = { id: number; parent_id: number | null; name: string; slug: string; description?: string | null; image_path?: string | null; is_active: number; sort_order: number; product_count: number }
export type Product = { id: number; name: string; slug: string; sku: string | null; price: string; sale_price: string | null; status: string; inventory_mode: string; stock_quantity: number | null; is_available: number; updated_at: string; deleted_at: string | null; image_path: string | null; categories: string | null }
export type PageMeta = { page: number; per_page: number; total: number; pages: number }
export type ToastState = { message: string; kind: 'success' | 'error' }
