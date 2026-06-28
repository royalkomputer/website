import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [tailwindcss()],
  base: process.env.BASE_URL || '/',

  server: {
    port: 5173,
    proxy: {
      // Proxy API calls to the PHP backend during development
      '/api_banner.php': {
        target: 'http://localhost:8081',
        changeOrigin: true,
      },
      '/api_produk.php': {
        target: 'http://localhost:8081',
        changeOrigin: true,
      },
      '/api_status.php': {
        target: 'http://localhost:8081',
        changeOrigin: true,
      },
      '/api_schedules.php': {
        target: 'http://localhost:8081',
        changeOrigin: true,
      },
      // Proxy admin paths during dev
      '/admin.php': {
        target: 'http://localhost:8081',
        changeOrigin: true,
      },
      '/login.php': {
        target: 'http://localhost:8081',
        changeOrigin: true,
      },
    },
  },

  build: {
    outDir: 'dist',
    emptyOutDir: true,
    // Copy public/ assets to dist/
    copyPublicDir: true,
  },
})
