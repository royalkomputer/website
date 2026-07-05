const RENDER_URL = import.meta.env.VITE_API_BASE || ''
const BASE_URL = import.meta.env.BASE_URL || '/'

export const API_BASE = RENDER_URL || BASE_URL

// Data files: when using Render remote, use the PHP proxy endpoint with CORS support.
// When local/Netlify, use direct file access (no CORS needed on same origin;
// Netlify handles proxying to Render via redirect rules in netlify.toml).
export const DATA_BASE = RENDER_URL ? `${RENDER_URL}/api_data.php?file=` : BASE_URL
export const LOGO_URL = RENDER_URL ? `${RENDER_URL}/logo/logo.webp` : `${BASE_URL}logo/logo.webp`
