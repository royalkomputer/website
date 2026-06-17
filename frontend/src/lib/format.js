/**
 * Format a number as Indonesian Rupiah (IDR).
 * Examples: 1850000 → "Rp1.850.000", 50000 → "Rp50.000"
 */
export function formatIDR(price) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(price)
}

/**
 * Format a date/time string for display in Indonesian locale.
 * "2026-06-20 08:00" → "20 Jun 2026 08:00"
 */
export function formatDateTime(dateStr) {
  const d = new Date(dateStr.replace(' ', 'T') + ':00')
  return d.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * Escape HTML characters to prevent XSS.
 */
export function escapeHtml(str) {
  const div = document.createElement('div')
  div.textContent = str
  return div.innerHTML
}

/**
 * Check if a product is "Bekas" (used) based on name containing "2ND"
 */
export function isBekas(product) {
  return (product.name || '').toUpperCase().includes('2ND')
}

/**
 * Sanitize a product kode for use in filenames.
 */
export function safeKode(kode) {
  return kode.replace(/[^A-Za-z0-9]/g, '_')
}
