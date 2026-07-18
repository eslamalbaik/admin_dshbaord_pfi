import lms from './lms'
import type { VerticalNavItems } from '@layouts/types'

/**
 * Vue admin app navigation only.
 * Students are redirected to the landing app by router guards — do not add student routes here.
 */
export default lms as VerticalNavItems
