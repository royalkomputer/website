const RENDER_URL = import.meta.env.VITE_API_BASE || ''
const BASE_URL = import.meta.env.BASE_URL || '/'

export const API_BASE = RENDER_URL || BASE_URL
export const DATA_BASE = RENDER_URL ? `${RENDER_URL}/data` : BASE_URL
export const LOGO_URL = RENDER_URL ? `${RENDER_URL}/logo/logo.webp` : `${BASE_URL}logo/logo.webp`
