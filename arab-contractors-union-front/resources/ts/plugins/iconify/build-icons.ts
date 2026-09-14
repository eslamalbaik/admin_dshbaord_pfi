/**
 * This is an advanced example for creating icon bundles for Iconify SVG Framework.
 *
 * It creates a bundle from:
 * - All SVG files in a directory.
 * - Custom JSON files.
 * - Iconify icon sets.
 * - SVG framework.
 *
 * This example uses Iconify Tools to import and clean up icons.
 * For Iconify Tools documentation visit https://docs.iconify.design/tools/tools2/
 */
import { promises as fs } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, join } from 'node:path'

// Installation: npm install --save-dev @iconify/tools @iconify/utils @iconify/json @iconify/iconify
import { cleanupSVG, importDirectory, isEmptyColor, parseColors, runSVGO } from '@iconify/tools'
import type { IconifyJSON } from '@iconify/types'
import { getIcons, getIconsCSS, stringToIcon } from '@iconify/utils'

// Create require function for ES modules
const require = createRequire(import.meta.url)

/**
 * Script configuration
 */
interface BundleScriptCustomSVGConfig {

  // Path to SVG files
  dir: string

  // True if icons should be treated as monotone: colors replaced with currentColor
  monotone: boolean

  // Icon set prefix
  prefix: string
}

interface BundleScriptCustomJSONConfig {

  // Path to JSON file
  filename: string

  // List of icons to import. If missing, all icons will be imported
  icons?: string[]
}

interface BundleScriptConfig {

  // Custom SVG to import and bundle
  svg?: BundleScriptCustomSVGConfig[]

  // Icons to bundled from @iconify/json packages
  icons?: string[]

  // List of JSON files to bundled
  // Entry can be a string, pointing to filename or a BundleScriptCustomJSONConfig object (see type above)
  // If entry is a string or object without 'icons' property, an entire JSON file will be bundled
  json?: (string | BundleScriptCustomJSONConfig)[]
}

const sources: BundleScriptConfig = {

  svg: [
    // {
    //   dir: 'resources/images/iconify-svg',
    //   monotone: true,
    //   prefix: 'custom',
    // },

    // {
    //   dir: 'emojis',
    //   monotone: false,
    //   prefix: 'emoji',
    // },
  ],

  icons: [
    // 'mdi:home',
    // 'mdi:account',
    // 'mdi:login',
    // 'mdi:logout',
    // 'octicon:book-24',
    // 'octicon:code-square-24',
  ],

  json: [
    // Custom JSON file
    // 'json/gg.json',

    // Iconify JSON file (@iconify/json is a package name, /json/ is directory where files are, then filename)
    // مُقلَّص لأيقونات tabler المستخدمة فعلياً بالمشروع فقط (بدل الحزمة الكاملة ~5900 أيقونة) —
    // لو أضفت أيقونة tabler جديدة بأي صفحة، ضيفها هون وأعد تشغيل build:icons.
    {
      filename: require.resolve('@iconify-json/tabler/icons.json'),
      icons: [
        'adjustments',
        'alert-circle',
        'alert-triangle',
        'align-center',
        'align-justified',
        'align-left',
        'align-right',
        'arrow-down',
        'arrow-left',
        'arrow-right',
        'arrow-up',
        'arrows-move-vertical',
        'ban',
        'barrier-block',
        'bell',
        'bell-off',
        'bold',
        'book',
        'book-2',
        'book-off',
        'brain',
        'briefcase',
        'building',
        'calendar-event',
        'calendar-star',
        'checkbox',
        'currency-dollar',
        'device-mobile-check',
        'device-mobile-off',
        'download',
        'file-certificate',
        'file-off',
        'folder',
        'gavel',
        'home',
        'license',
        'map-2',
        'signature',
        'stamp',
        'target-arrow',
        'users-group',
        'brand-facebook',
        'brand-instagram',
        'brand-linkedin',
        'brand-whatsapp',
        'brand-x',
        'brand-youtube',
        'building-bank',
        'building-factory-2',
        'bulldozer',
        'calendar',
        'calendar-exclamation',
        'calendar-month',
        'camera',
        'caret-down',
        'cash',
        'category',
        'certificate',
        'certificate-off',
        'chart-bar',
        'chart-bar-off',
        'chart-donut',
        'chart-pie',
        'check',
        'check-circle',
        'chevron-down',
        'chevron-left',
        'chevron-right',
        'chevron-up',
        'chevrons-left',
        'chevrons-right',
        'circle',
        'circle-check',
        'circle-check-filled',
        'circle-dot',
        'circle-filled',
        'circle-x',
        'circle-x-filled',
        'clipboard-check',
        'clipboard-list',
        'clipboard-off',
        'clock',
        'clock-check',
        'clock-exclamation',
        'clock-hour-4',
        'clock-off',
        'cloud-upload',
        'code',
        'color-picker',
        'copy',
        'corner-down-left',
        'crane',
        'credit-card',
        'credit-card-off',
        'currency-riyal',
        'currency-shekel',
        'database',
        'database-off',
        'device-desktop-analytics',
        'device-floppy',
        'device-mobile',
        'dots',
        'dots-vertical',
        'download',
        'edit',
        'engine',
        'external-link',
        'eye',
        'eye-off',
        'file',
        'file-alert',
        'file-analytics',
        'file-description',
        'file-dollar',
        'file-info',
        'file-off',
        'file-plus',
        'file-spreadsheet',
        'file-text',
        'file-type-doc',
        'file-type-pdf',
        'file-type-xls',
        'file-upload',
        'files',
        'folder',
        'folder-open',
        'forklift',
        'grip-vertical',
        'handshake',
        'headset',
        'headset-off',
        'help-circle',
        'history',
        'id-badge',
        'id-badge-2',
        'id-badge-off',
        'info-circle',
        'italic',
        'key',
        'language',
        'layout-dashboard',
        'layout-grid-add',
        'layout-list',
        'library',
        'list',
        'list-check',
        'loader-2',
        'lock',
        'lock-open',
        'login',
        'logout',
        'mail',
        'mail-opened',
        'map-pin',
        'maximize',
        'menu-2',
        'message',
        'minimize',
        'minus',
        'moon-stars',
        'news',
        'notes',
        'package',
        'packages',
        'palette',
        'paperclip',
        'pencil',
        'phone',
        'photo',
        'photo-plus',
        'play-circle',
        'player-pause',
        'player-play',
        'player-skip-back',
        'player-skip-forward',
        'plus',
        'receipt',
        'refresh',
        'scale',
        'search',
        'send',
        'settings',
        'settings-2',
        'share',
        'shield-check',
        'shield-lock',
        'shopping-bag',
        'shopping-cart',
        'shovel',
        'sort-ascending',
        'stack-2',
        'star',
        'star-filled',
        'star-half-filled',
        'strikethrough',
        'sun',
        'sun-high',
        'table-export',
        'tag',
        'target',
        'ticket',
        'toggle-left',
        'tools',
        'tractor',
        'transfer',
        'trash',
        'trending-up',
        'trophy',
        'truck',
        'underline',
        'upload',
        'user',
        'user-circle',
        'user-plus',
        'users',
        'video',
        'volume',
        'volume-2',
        'volume-off',
        'world',
        'x',
      ],
    },
    {
      filename: require.resolve('@iconify-json/mdi/icons.json'),
      icons: [
        'close-circle',
        'language-javascript',
        'language-typescript',
      ],
    },
    {
      filename: require.resolve('@iconify-json/fa/icons.json'),
      icons: [
        'circle',
      ],
    },

    // Custom file with only few icons
    // {
    //   filename: require.resolve('@iconify-json/line-md/icons.json'),
    //   icons: [
    //     'home-twotone-alt',
    //     'github',
    //     'document-list',
    //     'document-code',
    //     'image-twotone',
    //   ],
    // },
  ],
}

// File to save bundle to
const target = join(__dirname, 'icons.css')

/**
 * Do stuff!
 */

;(async function () {
  // Create directory for output if missing
  const dir = dirname(target)
  try {
    await fs.mkdir(dir, {
      recursive: true,
    })
  }
  catch (err) {
    //
  }

  const allIcons: IconifyJSON[] = []

  /**
   * Convert sources.icons to sources.json
   */
  if (sources.icons) {
    const sourcesJSON = sources.json ? sources.json : (sources.json = [])

    // Sort icons by prefix
    const organizedList = organizeIconsList(sources.icons)

    for (const prefix in organizedList) {
      const filename = require.resolve(`@iconify/json/json/${prefix}.json`)

      sourcesJSON.push({
        filename,
        icons: organizedList[prefix],
      })
    }
  }

  /**
   * Bundle JSON files and collect icons
   */
  if (sources.json) {
    for (let i = 0; i < sources.json.length; i++) {
      const item = sources.json[i]

      // Load icon set
      const filename = typeof item === 'string' ? item : item.filename
      const content = JSON.parse(await fs.readFile(filename, 'utf8')) as IconifyJSON

      for (const key in content) {
        if (key === 'prefix' && content.prefix === 'tabler') {
          for (const k in content.icons)
            content.icons[k].body = content.icons[k].body.replace(/stroke-width="2"/g, 'stroke-width="1.5"')
        }
      }

      // Filter icons
      if (typeof item !== 'string' && item.icons?.length) {
        const filteredContent = getIcons(content, item.icons)

        if (!filteredContent)
          throw new Error(`Cannot find required icons in ${filename}`)

        // Collect filtered icons
        allIcons.push(filteredContent)
      }
      else {
        // Collect all icons from the JSON file
        allIcons.push(content)
      }
    }
  }

  /**
   * Bundle custom SVG icons and collect icons
   */
  if (sources.svg) {
    for (let i = 0; i < sources.svg.length; i++) {
      const source = sources.svg[i]

      // Import icons
      const iconSet = await importDirectory(source.dir, {
        prefix: source.prefix,
      })

      // Validate, clean up, fix palette, etc.
      await iconSet.forEach(async (name, type) => {
        if (type !== 'icon')
          return

        // Get SVG instance for parsing
        const svg = iconSet.toSVG(name)

        if (!svg) {
          // Invalid icon
          iconSet.remove(name)

          return
        }

        // Clean up and optimise icons
        try {
          // Clean up icon code
          await cleanupSVG(svg)

          if (source.monotone) {
            // Replace color with currentColor, add if missing
            // If icon is not monotone, remove this code
            await parseColors(svg, {
              defaultColor: 'currentColor',
              callback: (attr, colorStr, color) => {
                return !color || isEmptyColor(color) ? colorStr : 'currentColor'
              },
            })
          }

          // Optimise
          await runSVGO(svg)
        }
        catch (err) {
          // Invalid icon
          console.error(`Error parsing ${name} from ${source.dir}:`, err)
          iconSet.remove(name)

          return
        }

        // Update icon from SVG instance
        iconSet.fromSVG(name, svg)
      })

      // Collect the SVG icon
      allIcons.push(iconSet.export())
    }
  }

  // Generate CSS from collected icons
  const cssContent = allIcons
    .map(iconSet => getIconsCSS(
      iconSet,
      Object.keys(iconSet.icons),
      {
        iconSelector: '.{prefix}-{name}',
        mode: 'mask',
      },
    ))
    .join('\n')

  // Save the CSS to a file
  await fs.writeFile(target, cssContent, 'utf8')

  console.log(`Saved CSS to ${target}!`)
})().catch(err => {
  console.error(err)
})

/**
 * Sort icon names by prefix
 */
function organizeIconsList(icons: string[]): Record<string, string[]> {
  const sorted: Record<string, string[]> = Object.create(null)

  icons.forEach(icon => {
    const item = stringToIcon(icon)

    if (!item)
      return

    const prefix = item.prefix
    const prefixList = sorted[prefix] ? sorted[prefix] : (sorted[prefix] = [])

    const name = item.name

    if (!prefixList.includes(name))
      prefixList.push(name)
  })

  return sorted
}
