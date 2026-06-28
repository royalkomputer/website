import { DATA_BASE } from '../lib/env.js'

/** Default heading text */
const HEADING_DEFAULT_PREFIX = 'Solusi Hardware di'
const HEADING_DEFAULT_BRAND = 'Royal Komputer'

let _headingPrefix = HEADING_DEFAULT_PREFIX
let _headingBrand = HEADING_DEFAULT_BRAND

/**
 * Fetch the heading text from heading.json (synced from admin).
 */
export async function loadHeadingText() {
  try {
    const res = await fetch(`${DATA_BASE}/heading.json`)
    const data = await res.json()
    if (data && data.prefix) _headingPrefix = data.prefix
    if (data && data.brand) _headingBrand = data.brand
  } catch {
    _headingPrefix = HEADING_DEFAULT_PREFIX
    _headingBrand = HEADING_DEFAULT_BRAND
  }
}

/**
 * StoreStatus Component
 *
 * Renders: hero section with store open/closed/temporarily-closed badge,
 * upcoming schedule warning, heading, and tagline.
 *
 * @param {Object} status — result from fetchStoreStatus()
 * @returns {string} HTML string
 */
export function StoreStatus(status) {
  if (!status) {
    return `<header class="bg-gradient-to-r from-astra-100 via-white to-astra-50 dark:from-astra-950 dark:via-slate-900 dark:to-astra-900 text-slate-800 dark:text-white py-12 px-4 relative overflow-hidden">
      <div class="px-4 md:px-8 lg:px-12 text-center relative z-10">
        <div class="animate-pulse space-y-4">
          <div class="h-6 bg-slate-300 dark:bg-slate-700/50 rounded-full w-48 mx-auto shimmer"></div>
          <div class="h-10 bg-slate-300 dark:bg-slate-700/50 rounded-lg w-96 mx-auto max-w-full shimmer"></div>
          <div class="h-4 bg-slate-300 dark:bg-slate-700/50 rounded w-64 mx-auto shimmer"></div>
        </div>
      </div>
    </header>`
  }

  const { isOpen, isTemporarilyClosed, upcomingSchedule, closeTime, nextOpenDay, nextOpenTime, tagline } = status

  // Determine status badge
  let badgeHTML = ''
  if (isTemporarilyClosed) {
    badgeHTML = `<span class="bg-red-500/20 border border-red-500/50 text-red-600 dark:text-red-300 text-xs px-3 py-1.5 rounded-full uppercase font-bold mb-4 inline-flex items-center gap-2 shadow-lg">
      <i class="fa-solid fa-store-slash"></i> Toko Tutup Sementara</span>`
  } else if (isOpen) {
    badgeHTML = `<span class="bg-green-500/20 border border-green-500/50 text-green-600 dark:text-green-300 text-xs px-3 py-1.5 rounded-full uppercase font-bold mb-4 inline-flex items-center gap-2">
      <i class="fa-solid fa-store"></i> Buka Sekarang (Tutup ${closeTime.replace(':', '.')} WIB)</span>`
  } else {
    badgeHTML = `<span class="bg-slate-200 dark:bg-slate-700 border border-slate-400 dark:border-slate-500 text-slate-600 dark:text-slate-300 text-xs px-3 py-1.5 rounded-full uppercase font-bold mb-4 inline-flex items-center gap-2">
      <i class="fa-solid fa-moon"></i> Toko Tutup (Buka ${nextOpenDay || ''} ${nextOpenTime ? nextOpenTime.replace(':', '.') : ''} WIB)</span>`
  }

  // Upcoming schedule warning
  let scheduleHTML = ''
  if (upcomingSchedule) {
    const startDate = new Date(upcomingSchedule.start.replace(' ', 'T') + ':00')
    const endDate = new Date(upcomingSchedule.end.replace(' ', 'T') + ':00')
    const fmtStart = startDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    const fmtEnd = endDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    scheduleHTML = `<div class="mt-3">
      <span class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-100 dark:border-yellow-800 text-yellow-700 dark:text-yellow-300 text-xs px-3 py-1 rounded inline-flex items-center gap-2">
        <i class="fa-solid fa-calendar-days"></i>
        Jadwal: Tutup ${fmtStart} sampai ${fmtEnd} ${upcomingSchedule.note || ''}
      </span>
    </div>`
  }

  return `
<header class="bg-gradient-to-r from-astra-100 via-white to-astra-50 dark:from-astra-950 dark:via-slate-900 dark:to-astra-900 text-slate-800 dark:text-white py-12 px-4 shadow-inner relative overflow-hidden">
  <div class="px-4 md:px-8 lg:px-12 text-center relative z-10">
    ${badgeHTML}
    ${scheduleHTML}
    <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight mb-4">${_headingPrefix} <span class="text-transparent bg-clip-text bg-gradient-to-r from-astra-400 to-sky-300">${_headingBrand}</span></h1>
    <p class="text-slate-500 dark:text-slate-300 max-w-xl mx-auto text-sm md:text-base font-light">${tagline || 'Bingung mau rakit atau upgrade komputer? Ke Royal Komputer aja. Bisa tukar tambah loh.'}</p>
  </div>
</header>`
}
