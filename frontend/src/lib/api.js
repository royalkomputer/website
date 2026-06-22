const API_BASE = ''

/**
 * Fetch all products from the API.
 * Falls back to cache_produk.json if the API fails.
 *
 * @returns {Promise<Array>} Array of product objects
 */
export async function fetchProducts() {
  try {
    const res = await fetch(`${API_BASE}/api_produk.php`)
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    if (data.error) throw new Error(data.error)
    return data
  } catch (err) {
    console.warn('API failed, trying cache:', err.message)
    const cacheRes = await fetch(`${API_BASE}/cache_produk.json`)
    if (!cacheRes.ok) throw new Error('Cache unavailable')
    return cacheRes.json()
  }
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
  const res = await fetch(`${API_BASE}/jam_operasional.json`)
  return res.json()
}

/**
 * Fetch closure schedules from the JSON file.
 */
async function fetchSchedules() {
  const res = await fetch(`${API_BASE}/jadwal_tutup.json`)
  return res.json()
}

/**
 * Fetch manual store status from the text file.
 */
async function fetchManualStatus() {
  try {
    const res = await fetch(`${API_BASE}/status_toko.txt`)
    const text = await res.text()
    return text.trim() === 'tutup'
  } catch {
    return false
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
