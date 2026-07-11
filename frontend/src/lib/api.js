import { API_BASE, DATA_BASE } from './env.js'

/**
 * Fetch a paginated page of products from the API.
 * Falls back to cache_produk.json if the API fails.
 *
 * @param {Object} opts
 * @param {number} [opts.page=1]
 * @param {number} [opts.limit=12]
 * @param {string} [opts.category=''] — filter by category (empty = all)
 * @param {string} [opts.search=''] — search query
 * @param {string} [opts.condition=''] — 'baru', 'bekas', or ''
 * @param {string} [opts.sort='default'] — 'default', 'low-high', 'high-low'
 * @returns {Promise<{data: Array, total: number, page: number, limit: number}>}
 */
export async function fetchProductsPage({ page = 1, limit = 12, category = '', search = '', condition = '', sort = 'default' } = {}) {
  const params = new URLSearchParams({ page, limit })
  if (category) params.set('category', category)
  if (search) params.set('search', search)
  if (condition && condition !== 'Semua') params.set('condition', condition.toLowerCase())
  if (sort !== 'default') params.set('sort', sort)

  const url = `${API_BASE}/api_produk.php?${params}`
  try {
    const res = await fetch(url)
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    if (data.error) throw new Error(data.error)
    return data
  } catch (err) {
    console.warn('API failed, trying cache:', err.message)
    const cacheRes = await fetch(`${DATA_BASE}cache_produk.json`)
    if (!cacheRes.ok) throw new Error('Cache unavailable')
    const allData = await cacheRes.json()
    return paginateLocally(allData, { category, search, condition, sort, page, limit })
  }
}

function paginateLocally(allData, { category, search, condition, sort, page, limit }) {
  let filtered = allData.filter(p =>
    (p.name || '').toLowerCase().includes('pesanan') === false &&
    (p.name || '').toLowerCase().includes('jasa') === false
  )
  if (category) filtered = filtered.filter(p => p.category === category)
  if (search) filtered = filtered.filter(p => (p.name || '').toLowerCase().includes(search.toLowerCase()))
  if (condition === 'baru') filtered = filtered.filter(p => !(p.name || '').toUpperCase().includes('2ND'))
  if (condition === 'bekas') filtered = filtered.filter(p => (p.name || '').toUpperCase().includes('2ND'))
  const total = filtered.length
  if (sort === 'low-high') filtered.sort((a, b) => (a.price || 0) - (b.price || 0))
  else if (sort === 'high-low') filtered.sort((a, b) => (b.price || 0) - (a.price || 0))
  const offset = (page - 1) * limit

  const catMap = {}
  for (const p of allData) {
    const c = (p.category || '').trim() || 'Lainnya'
    catMap[c] = (catMap[c] || 0) + 1
  }

  return { data: filtered.slice(offset, offset + limit), total, page, limit, categories: catMap }
}

// Kept for backward compatibility (used by other parts if needed)
export async function fetchProducts() {
  const result = await fetchProductsPage({ limit: 9999 })
  return result.data
}

/**
 * Fetch store status from the API.
 * Falls back to client-side calculation from JSON files.
 *
 * @returns {Promise<Object>} { isOpen, isTemporarilyClosed, statusText, statusClass, upcomingSchedule, nextOpenDay, nextOpenTime }
 */
export async function fetchStoreStatus() {
  try {
    const res = await fetch(`${API_BASE}/api_status.php`)
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    if (data.error) throw new Error(data.error)
    return data
  } catch {
    // Fallback: calculate status client-side
    return calculateStatusFromFiles()
  }
}

/**
 * Fetch operating hours directly from the JSON file.
 */
async function fetchJamOperasional() {
  const res = await fetch(`${DATA_BASE}jam_operasional.json`)
  return res.json()
}

/**
 * Fetch closure schedules from the JSON file.
 */
async function fetchSchedules() {
  const res = await fetch(`${DATA_BASE}jadwal_tutup.json`)
  return res.json()
}

/**
 * Fetch manual store status from the text file.
 */
async function fetchManualStatus() {
  try {
    const res = await fetch(`${DATA_BASE}status_toko.txt`)
    const text = await res.text()
    return text.trim() === 'tutup'
  } catch {
    return false
  }
}

/**
 * Fetch banners from the API.
 * @returns {Promise<Array>} Array of banner objects
 */
export async function fetchBanners() {
  try {
    const res = await fetch(`${API_BASE}/api_banner.php`)
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    return Array.isArray(data) ? data : []
  } catch {
    return []
  }
}

/**
 * Client-side store status calculation (fallback when API is unavailable).
 */
async function calculateStatusFromFiles() {
  const now = new Date()
  const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
  const dayIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']

  const currentDay = dayNames[now.getDay()]
  const currentTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  const nowISO = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')} ${currentTime}`

  // Load hours
  let jamBuka = {}
  try {
    jamBuka = await fetchJamOperasional()
  } catch {
    return { isOpen: false, statusText: 'Tidak dapat memuat jadwal', statusClass: 'bg-gray-500' }
  }

  // Load schedules
  let schedules = []
  try {
    schedules = await fetchSchedules()
  } catch { /* empty */ }

  // Check manual status
  const tutupSementara = await fetchManualStatus()

  // Check active schedule
  let hasActiveSchedule = false
  for (const s of schedules) {
    if (s.start && s.end && nowISO >= s.start && nowISO <= s.end) {
      hasActiveSchedule = true
      break
    }
  }

  const isTemporarilyClosed = tutupSementara || hasActiveSchedule

  // Check operating hours
  const todayHours = jamBuka[currentDay]
  let isOpen = false
  const isLibur = todayHours?.libur === true
  if (todayHours && !isTemporarilyClosed && !isLibur) {
    isOpen = currentTime >= todayHours.buka && currentTime <= todayHours.tutup
  }

  // Find next opening
  let nextOpenDay = ''
  let nextOpenTime = ''
  if (!isOpen || isTemporarilyClosed) {
    if (!isTemporarilyClosed && todayHours && !isLibur && currentTime < todayHours.buka) {
      // Opens later today
      nextOpenDay = todayHours.indo
      nextOpenTime = todayHours.buka
    } else {
      // Check tomorrow onwards, skip libur days
      for (let i = 1; i <= 7; i++) {
        const checkDay = dayNames[(now.getDay() + i) % 7]
        const h = jamBuka[checkDay]
        if (h && h.buka && !h.libur) {
          nextOpenDay = h.indo
          nextOpenTime = h.buka
          break
        }
      }
    }
  }

  // Find upcoming schedule
  let upcomingSchedule = null
  const futureSchedules = schedules
    .filter(s => s.end && s.end >= nowISO)
    .sort((a, b) => a.start.localeCompare(b.start))
  if (futureSchedules.length > 0) {
    upcomingSchedule = futureSchedules[0]
  }

  // ── Effective close time (adjusted for today's closure schedules) ──
  let effectiveClose = isLibur ? '' : (todayHours?.tutup || '')
  if (isOpen && effectiveClose) {
    const todayDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
    for (const s of schedules) {
      if (s.start) {
        const schedDate = s.start.substring(0, 10)
        const schedTime = s.start.substring(11, 16)
        // Schedule starts today, hasn't started yet, and is before normal closing
        if (schedDate === todayDate && schedTime > currentTime && schedTime < effectiveClose) {
          effectiveClose = schedTime
        }
      }
    }
  }

  return {
    isOpen,
    isTemporarilyClosed,
    hasActiveSchedule,
    upcomingSchedule,
    nextOpenDay,
    nextOpenTime,
    closeTime: effectiveClose,
    currentDayIndo: dayIndo[now.getDay()],
  }
}
