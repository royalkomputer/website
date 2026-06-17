import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [tailwindcss()],

  server: {
    port: 5173,
    proxy: {
      // Proxy API calls to the PHP backend during development
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
      // Also proxy direct JSON file requests for fallback data
      '/cache_produk.json': {
        target: 'http://localhost:8081',
        changeOrigin: true,
      },
      '/jam_operasional.json': {
        target: 'http://localhost:8081',
        changeOrigin: true,
      },
      '/jadwal_tutup.json': {
        target: 'http://localhost:8081',
        changeOrigin: true,
      },
      '/status_toko.txt': {
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
