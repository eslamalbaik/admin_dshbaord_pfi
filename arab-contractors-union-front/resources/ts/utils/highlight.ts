// تمييز كلمات البحث بعلامة صفراء <mark> — بيتجاهل فروقات الهمزات والتاء المربوطة والياء والتشكيل (نفس منطق الـAPI)

const escapeHtml = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

export function searchTerms(q: string): string[] {
  const parts = q.trim().split(/[\s\p{P}]+/u).filter(t => t.length >= 2)

  return [...new Set(parts)].slice(0, 6)
}

const SPECIAL = '\\^$.*+?()[]{}|/-'

function termPattern(term: string): string {
  const clean = term.replace(/[ـً-ْ]/g, '')
  let out = ''
  for (const c of clean) {
    if ('أإآاٱ'.includes(c)) out += '[أإآاٱ]'
    else if ('ةه'.includes(c)) out += '[ةه]'
    else if ('ىي'.includes(c)) out += '[ىي]'
    else out += SPECIAL.includes(c) ? `\\${c}` : c
    out += '[\u064B-\u0652\u0640]*' // تشكيل/تطويل بين الحروف
  }

  return out
}

export function termRegex(terms: string[]): RegExp | null {
  if (!terms.length) return null

  return new RegExp(terms.map(termPattern).join('|'), 'giu')
}

/** نص عادي ← HTML آمن مع <mark> حول المطابقات. */
export function highlightText(text: string, terms: string[]): string {
  const re = termRegex(terms)
  if (!re) return escapeHtml(text)
  let out = ''
  let last = 0
  for (const m of text.matchAll(re)) {
    if (!m[0]) continue
    out += `${escapeHtml(text.slice(last, m.index))}<mark class="search-mark">${escapeHtml(m[0])}</mark>`
    last = (m.index ?? 0) + m[0].length
  }

  return out + escapeHtml(text.slice(last))
}

/** يلف المطابقات داخل عناصر DOM موجودة (نص الخبر) بدون ما يمس الوسوم؛ بيرجّع عدد المطابقات. */
export function highlightDom(root: HTMLElement, terms: string[]): number {
  const re = termRegex(terms)
  if (!re) return 0
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
    acceptNode: n => (n.parentElement?.closest('script,style,mark') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
  })
  const nodes: Text[] = []
  while (walker.nextNode()) nodes.push(walker.currentNode as Text)
  let count = 0
  for (const node of nodes) {
    const text = node.nodeValue ?? ''
    const matches = [...text.matchAll(re)].filter(m => m[0])
    if (!matches.length) continue
    const frag = document.createDocumentFragment()
    let last = 0
    for (const m of matches) {
      const i = m.index ?? 0
      frag.append(text.slice(last, i))
      const mark = document.createElement('mark')
      mark.className = 'search-mark'
      mark.textContent = m[0]
      frag.append(mark)
      last = i + m[0].length
      count++
    }
    frag.append(text.slice(last))
    node.replaceWith(frag)
  }

  return count
}
