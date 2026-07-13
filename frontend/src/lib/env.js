const BASE_URL = import.meta.env.BASE_URL || '/'

// API base: empty string = relative URLs (same origin via Nginx proxy)
export const API_BASE = ''

// Data files: direct file access via Nginx (same origin)
export const DATA_BASE = BASE_URL
export const LOGO_URL = `${BASE_URL}logo/logo.webp`
