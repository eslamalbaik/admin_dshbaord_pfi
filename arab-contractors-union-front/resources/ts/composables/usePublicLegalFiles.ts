import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import api from '@/plugins/axios'

/**
 * ملفات "المكتبة القانونية" المنشورة (GET /api/v1/legal-files، بدون تسجيل دخول) لصفحتي
 * التشريعات ومكتبة الملفات بالموقع العام — نفس اللي بيرفعه الأدمن من لوحة التحكم.
 */

export interface PublicLegalFile {
  id: number
  title: string
  description: string | null
  category: string | null
  category_label: string
  url: string
  mime_type: string | null
  formatted_size: string | null
  is_featured: boolean
  created_at: string
}

export interface PublicLegalCategory {
  value: string
  label: string
  count: number
}

interface PublicLegalFilesResponse {
  total: number
  categories: PublicLegalCategory[]
  featured: PublicLegalFile[]
  files: PublicLegalFile[]
}

// امتداد الملف للعرض (PDF، DOCX...) من نوعه أو من الرابط
export function fileExt(f: PublicLegalFile): string {
  const fromUrl = f.url.split('?')[0].split('.').pop()?.toUpperCase()
  if (fromUrl && fromUrl.length <= 4)
    return fromUrl

  return f.mime_type?.includes('pdf') ? 'PDF' : 'ملف'
}

export function usePublicLegalFiles() {
  const query = useQuery({
    queryKey: ['public-legal-files'],
    queryFn: async (): Promise<PublicLegalFilesResponse> => {
      const res = await api.get('/api/v1/legal-files')
      return res.data.items
    },
    staleTime: 5 * 60 * 1000,
  })

  const files = computed(() => query.data.value?.files ?? [])

  // التصنيفات الفاضية ما إلها داعي بالموقع العام
  const categories = computed(() => (query.data.value?.categories ?? []).filter(c => c.count > 0))

  return { files, categories, isLoading: query.isLoading, isError: query.isError }
}
