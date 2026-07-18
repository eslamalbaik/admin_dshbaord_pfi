import type { RouteLocationNormalized } from 'vue-router'
import type { NavGroup } from '@layouts/types'

/**
 * Simplified permission check - always returns true.
 * CASL has been removed; role-based access is handled by route guards.
 *
 * @param {string} action Action identifier (unused, kept for API compat)
 * @param {string} subject Subject identifier (unused, kept for API compat)
 */
export const can = (_action: string | undefined, _subject: string | undefined) => {
  return true
}

/**
 * Check if user can view nav menu group.
 * With CASL removed, all groups are visible. Route guards handle access control.
 * @param {object} item navigation object item
 */
export const canViewNavMenuGroup = (_item: NavGroup) => {
  return true
}

/**
 * Check if user can navigate to a route.
 * With CASL removed, always returns true. Route guards handle access control.
 */
export const canNavigate = (_to: RouteLocationNormalized) => {
  return true
}
