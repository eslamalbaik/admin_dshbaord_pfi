// Cloudflare Turnstile عند الطلب: بيرجّع توكن لطلب واحد (كل توكن بينستعمل مرة وحدة).
// بدون VITE_TURNSTILE_SITE_KEY بيرجّع '' فوراً (الحماية معطّلة وما بتأثر على أي نموذج).
// الودجت "interaction-only": ما بيظهر شي للمستخدم العادي، وبيطلع تحدّي بمنتصف الشاشة بس لو لزم.

const SITE_KEY = (import.meta.env.VITE_TURNSTILE_SITE_KEY ?? '') as string
const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'

interface TurnstileApi {
  render: (el: HTMLElement, opts: Record<string, unknown>) => string
  remove: (id: string) => void
}

let scriptPromise: Promise<TurnstileApi> | null = null

function loadScript(): Promise<TurnstileApi> {
  const w = window as unknown as { turnstile?: TurnstileApi }
  if (w.turnstile) return Promise.resolve(w.turnstile)
  if (!scriptPromise) {
    scriptPromise = new Promise((resolve, reject) => {
      const s = document.createElement('script')
      s.src = SCRIPT_SRC
      s.async = true
      s.onload = () => (w.turnstile ? resolve(w.turnstile) : reject(new Error('turnstile unavailable')))
      s.onerror = () => { scriptPromise = null; reject(new Error('turnstile script failed')) }
      document.head.append(s)
    })
  }

  return scriptPromise
}

export const turnstileEnabled = !!SITE_KEY

/** يرجّع توكن التحقق، أو '' لو الحماية معطّلة/تعذّر تحميلها (الـAPI هو اللي بيقرر وقتها). */
export async function getTurnstileToken(): Promise<string> {
  if (!SITE_KEY) return ''
  let api: TurnstileApi
  try { api = await loadScript() }
  catch { return '' }

  return new Promise<string>((resolve) => {
    const box = document.createElement('div')
    box.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:99999'
    document.body.append(box)
    let id = ''
    const done = (token: string) => {
      try { if (id) api.remove(id) } catch { /* ignore */ }
      box.remove()
      resolve(token)
    }
    id = api.render(box, {
      sitekey: SITE_KEY,
      appearance: 'interaction-only',
      language: 'ar',
      callback: (t: string) => done(t),
      'error-callback': () => done(''),
      'timeout-callback': () => done(''),
    })
  })
}
