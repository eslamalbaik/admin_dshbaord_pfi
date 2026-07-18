import type { User } from '@/stores/authStore'

export function normalizeUser(data: Record<string, unknown> | User | null | undefined): User | null {
  if (!data || typeof data !== 'object')
    return null

  const id = data.id
  const email = data.email

  if (id == null || !email)
    return null

  return {
    id: Number(id),
    name: String(data.name ?? data.fullName ?? ''),
    email: String(email),
    role: String(data.role ?? 'student'),
    avatar: data.avatar != null ? String(data.avatar) : undefined,
  }
}
