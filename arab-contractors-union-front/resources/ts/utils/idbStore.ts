/**
 * مخزن مفتاح/قيمة صغير فوق IndexedDB — بدون أي تبعية خارجية.
 *
 * سبب وجوده: `localStorage` يخزّن نصوصاً فقط، والحفظ التلقائي للمسودات يحتاج
 * الاحتفاظ بالمرفقات (File/Blob) أيضاً. IndexedDB يخزّنها أصلاً عبر خوارزمية
 * الاستنساخ البنيوي (structured clone) بلا تحويل يدوي إلى base64.
 */

const DB_NAME = 'pcu-drafts'
const STORE_NAME = 'drafts'
const DB_VERSION = 1

let dbPromise: Promise<IDBDatabase> | null = null

/** IndexedDB غائب في التصفح الخاص ببعض المتصفحات وفي التصيير على الخادم. */
export function isIdbAvailable(): boolean {
  return typeof indexedDB !== 'undefined'
}

function openDb(): Promise<IDBDatabase> {
  if (dbPromise)
    return dbPromise

  dbPromise = new Promise<IDBDatabase>((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION)

    request.onupgradeneeded = () => {
      if (!request.result.objectStoreNames.contains(STORE_NAME))
        request.result.createObjectStore(STORE_NAME)
    }

    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })

  // لا نُبقي وعداً فاشلاً مخزّناً — وإلا فشلت كل محاولة لاحقة بنفس الخطأ.
  dbPromise.catch(() => { dbPromise = null })

  return dbPromise
}

function runTx<T>(
  mode: IDBTransactionMode,
  run: (store: IDBObjectStore) => IDBRequest,
): Promise<T | undefined> {
  if (!isIdbAvailable())
    return Promise.resolve(undefined)

  return openDb().then(db => new Promise<T | undefined>((resolve, reject) => {
    const request = run(db.transaction(STORE_NAME, mode).objectStore(STORE_NAME))

    request.onsuccess = () => resolve(request.result as T | undefined)
    request.onerror = () => reject(request.error)
  }))
}

export function idbGet<T>(key: string): Promise<T | undefined> {
  return runTx<T>('readonly', store => store.get(key))
}

export function idbSet(key: string, value: unknown): Promise<void> {
  return runTx('readwrite', store => store.put(value, key)).then(() => undefined)
}

export function idbDelete(key: string): Promise<void> {
  return runTx('readwrite', store => store.delete(key)).then(() => undefined)
}
