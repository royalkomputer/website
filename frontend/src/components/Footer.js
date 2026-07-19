/**
 * Footer Component
 *
 * Renders: logo + address, social media links, version + copyright.
 *
 * @returns {string} HTML string
 */
import { LOGO_URL } from '../lib/env.js'
import { icon } from '../lib/icons.js'

export function Footer() {
  return `
<footer class="bg-slate-100 dark:bg-slate-950 text-slate-500 dark:text-slate-400 text-xs border-t border-slate-200 dark:border-slate-800 mt-12 py-12">
  <div class="px-4 flex flex-col items-center gap-8">

    <!-- Logo & Address -->
    <div class="flex flex-col gap-3 items-center">
      <img src="${LOGO_URL}" alt="Royal Komputer Logo" class="h-12 w-auto object-contain rounded-lg shadow-md mb-1">
      <p class="font-bold text-slate-700 dark:text-slate-200 text-sm tracking-wide">ROYAL KOMPUTER KEDIRI</p>
      <a href="https://www.google.com/maps/place/Royal+Komputer/@-7.8247749,112.0198969,17z/data=!3m1!4b1!4m6!3m5!1s0x2e7857bb27d7da49:0x12d8857ab5c2e60d!8m2!3d-7.8247749!4d112.0198969!16s%2Fg%2F11fn0mc9js?entry=ttu&g_ep=EgoyMDI2MDcxNS4wIKXMDSoASAFQAw%3D%3D" target="_blank" class="text-slate-400 hover:text-astra-400 transition-colors leading-relaxed text-center text-xs inline-flex items-start gap-1">
        <span class="text-red-500 mt-0.5 inline-flex">${icon('location-dot')}</span>
        Gg. Masjid No.22A, Jamsaren, Kec. Pesantren, Kota Kediri, Jawa Timur 64132
      </a>
    </div>

    <!-- Social Media -->
    <div class="flex flex-col gap-3 items-center w-full max-w-xs">
      <p class="font-bold text-slate-700 dark:text-slate-200 text-sm tracking-wide border-b border-slate-200 dark:border-slate-800 pb-1 w-full text-center">MEDIA SOSIAL</p>
      <div class="flex gap-3 items-center justify-center w-full text-lg">
        <a href="https://www.facebook.com/royall.komp" target="_blank" class="text-slate-500 hover:text-blue-500 transition-colors" title="Facebook">${icon('facebook')}</a>
        <a href="https://www.instagram.com/royalkomputerkediri/" target="_blank" class="text-slate-500 hover:text-pink-500 transition-colors" title="Instagram">${icon('instagram')}</a>
        <a href="https://www.tiktok.com/@royalkomputerkediri" target="_blank" class="text-slate-500 hover:text-white transition-colors" title="TikTok">${icon('tiktok')}</a>
        <a href="https://www.youtube.com/@royalkomputerkediri" target="_blank" class="text-slate-500 hover:text-red-500 transition-colors" title="YouTube">${icon('youtube')}</a>
      </div>
    </div>

    <!-- Version & Copyright -->
    <div class="flex flex-col gap-1 items-center w-full">
      <p class="font-semibold text-slate-400 dark:text-slate-500 tracking-wider">ROYAL MARKETPLACE v3.0</p>
      <p class="text-slate-400 dark:text-slate-500">&copy; ${new Date().getFullYear()} Hak Cipta Dilindungi.</p>
    </div>

  </div>
</footer>`
}
