// Клиент для PHP API старого приложения (backend/routes/api.php).
// Повторяет контракт запросов старого фронтенда (frontend/assets/js/app.js):
// GET/POST идут через ?route=/api/... и добавляют данные пользователя Telegram,
// по которым backend авторизует запрос (см. backend/middleware/AuthMiddleware.php).

const API_ROOT = (import.meta.env.VITE_API_URL as string | undefined)?.replace(/\/$/, '') || ''

export interface ApiResult<T = any> {
  success?: boolean
  data?: T
  error?: { code?: string; message?: string }
  __httpStatus?: number
  [key: string]: any
}

function readTelegramUser(): Record<string, string> {
  try {
    const tg = (window as any).Telegram?.WebApp
    const u = tg?.initDataUnsafe?.user || {}
    const out: Record<string, string> = {}
    if (u?.id) out.telegram_id = String(u.id)
    if (u?.first_name) out.first_name = String(u.first_name)
    if (u?.last_name) out.last_name = String(u.last_name)
    if (u?.username) out.username = String(u.username)
    return out
  } catch {
    return {}
  }
}

function buildUrl(route: string, extra?: Record<string, string | number | undefined>) {
  const qp = new URLSearchParams()
  Object.entries(readTelegramUser()).forEach(([k, v]) => qp.append(k, v))
  Object.entries(extra || {}).forEach(([k, v]) => {
    if (v !== undefined && v !== '') qp.append(k, String(v))
  })
  const query = qp.toString()
  return `${API_ROOT}/routes/api.php?route=${encodeURIComponent(route)}${query ? '&' + query : ''}`
}

async function toResult<T>(res: Response): Promise<ApiResult<T>> {
  const data = await res.json().catch(() => null)
  if (res.status === 401 || res.status === 403) {
    return { __httpStatus: res.status, success: false, ...(data || {}) }
  }
  return data as ApiResult<T>
}

export async function apiGet<T = any>(route: string, extra?: Record<string, string | number | undefined>): Promise<ApiResult<T>> {
  const res = await fetch(buildUrl(route, extra))
  return toResult<T>(res)
}

export async function apiPost<T = any>(route: string, payload?: Record<string, any>): Promise<ApiResult<T>> {
  const res = await fetch(buildUrl(route), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(Object.assign({}, payload || {}, readTelegramUser())),
  })
  return toResult<T>(res)
}

export async function apiPatch<T = any>(route: string, payload?: Record<string, any>): Promise<ApiResult<T>> {
  const res = await fetch(buildUrl(route), {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(Object.assign({}, payload || {}, readTelegramUser())),
  })
  return toResult<T>(res)
}

export async function apiDelete<T = any>(route: string): Promise<ApiResult<T>> {
  const res = await fetch(buildUrl(route), { method: 'DELETE' })
  return toResult<T>(res)
}

export async function apiUploadPhoto(file: File, meta?: Record<string, string>): Promise<ApiResult> {
  const fd = new FormData()
  fd.append('file', file)
  Object.entries(readTelegramUser()).forEach(([k, v]) => fd.append(k, v))
  Object.entries(meta || {}).forEach(([k, v]) => fd.append(k, v))
  const res = await fetch(buildUrl('/api/photos'), { method: 'POST', body: fd })
  return toResult(res)
}

let mePromise: Promise<ApiResult> | null = null
export function getMe(): Promise<ApiResult> {
  if (!mePromise) mePromise = apiGet('/api/users/profile')
  return mePromise
}
export function invalidateMe() {
  mePromise = null
}

export function isTelegramEnv(): boolean {
  try {
    const tg = (window as any).Telegram?.WebApp
    return !!(tg && (String(tg.initData || '').length || tg.initDataUnsafe?.user))
  } catch {
    return false
  }
}
