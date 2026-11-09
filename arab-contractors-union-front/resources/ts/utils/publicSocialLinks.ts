// The union's official social channels — the ONLY social links on the public site.
// Used by PublicNavbar (blue strip), PublicFooter, PublicSocialFollow (inner-page heroes) and the contact page.
export type PublicSocialKey = 'facebook' | 'instagram' | 'whatsapp'

export interface PublicSocialLink {
  key: PublicSocialKey
  label: string
  href: string
}

export const PUBLIC_SOCIAL_FOLLOW_TEXT
  = 'تابعونا عبر منصات اتحاد المقاولين الفلسطينيين، للاطلاع على آخر أخبار وفعاليات قطاع المقاولات.'

export const PUBLIC_SOCIAL_LINKS: PublicSocialLink[] = [
  { key: 'facebook', label: 'الفيس بوك', href: 'https://www.facebook.com/share/1LKgaSL36P/' },
  { key: 'instagram', label: 'الإنستغرام', href: 'https://www.instagram.com/pcu_gaza' },
  { key: 'whatsapp', label: 'الواتساب', href: 'https://whatsapp.com/channel/0029VbCnuj9AYlUL3XQAIp23' },
]
