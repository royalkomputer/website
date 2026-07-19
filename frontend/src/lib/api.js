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


