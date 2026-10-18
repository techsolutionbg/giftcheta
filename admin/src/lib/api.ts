import type { ApiError } from '../types/admin'

const apiBase = '/api/v1/admin'
let csrfToken = ''
let activeApiRequests = 0
export async function api<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers)
  if (!(options.body instanceof FormData) && options.body) headers.set('Content-Type', 'application/json')
  if (!['GET', 'HEAD'].includes((options.method || 'GET').toUpperCase()) && csrfToken) headers.set('X-CSRF-Token', csrfToken)
  activeApiRequests += 1
  if (activeApiRequests === 1) window.dispatchEvent(new CustomEvent('giftcheta:loading', { detail: true }))
  try {
    const response = await fetch(`${apiBase}${path}`, { ...options, headers, credentials: 'include' })
    const result = await response.json() as T & ApiError
    if (!response.ok) throw new Error(result.error?.message || 'Възникна неочаквана грешка.')
    return result
  } finally {
    activeApiRequests = Math.max(0, activeApiRequests - 1)
    if (activeApiRequests === 0) window.dispatchEvent(new CustomEvent('giftcheta:loading', { detail: false }))
  }
}
export function setCsrfToken(token: string) { csrfToken = token }
export const json = (value: unknown) => JSON.stringify(value)
