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
  <div class="px-4 md:px-8 lg:px-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 items-start">

    <!-- Logo & Address -->
    <div class="flex flex-col gap-3 items-center md:items-start">
      <img src="${LOGO_URL}" alt="Royal Komputer Logo" class="h-12 w-auto object-contain rounded-lg shadow-md mb-1">
      <p class="font-bold text-slate-700 dark:text-slate-200 text-sm tracking-wide">ROYAL KOMPUTER KEDIRI</p>
      <p class="text-slate-400 leading-relaxed text-center md:text-left text-xs">
        <span class="text-red-500 mr-1 inline-flex">${icon('location-dot')}</span>
        Gg. Masjid No.22A, Jamsaren, Kec. Pesantren, Kota Kediri, Jawa Timur 64132
      </p>
    </div>

    <!-- Social Media -->
    <div class="flex flex-col gap-3 items-center md:items-start w-full">
      <p class="font-bold text-slate-700 dark:text-slate-200 text-sm tracking-wide border-b border-slate-200 dark:border-slate-800 pb-1 w-full text-center md:text-left">MEDIA SOSIAL</p>
      <div class="flex gap-3 items-center justify-center md:justify-start w-full text-lg">
        <a href="https://www.facebook.com/royall.komp" target="_blank" class="text-slate-500 hover:text-blue-500 transition-colors" title="Facebook">${icon('facebook')}</a>
        <a href="https://www.instagram.com/royalkomputerkediri/" target="_blank" class="text-slate-500 hover:text-pink-500 transition-colors" title="Instagram">${icon('instagram')}</a>
        <a href="https://www.tiktok.com/@royalkomputerkediri" target="_blank" class="text-slate-500 hover:text-white transition-colors" title="TikTok">${icon('tiktok')}</a>
        <a href="https://wa.me/6281380686168" target="_blank" class="text-slate-500 hover:text-green-500 transition-colors" title="WhatsApp">${icon('whatsapp')}</a>
        <a href="https://www.youtube.com/@royalkomputerkediri" target="_blank" class="text-slate-500 hover:text-red-500 transition-colors" title="YouTube">${icon('youtube')}</a>
      </div>
    </div>

    <!-- Version & Copyright -->
    <div class="flex flex-col gap-1 items-center lg:items-end lg:text-right h-full justify-center lg:justify-start lg:pt-6 w-full mt-4 lg:mt-0">
      <p class="font-semibold text-slate-400 dark:text-slate-500 tracking-wider">ROYAL MARKETPLACE v3.0</p>
      <p class="text-slate-400 dark:text-slate-500">&copy; ${new Date().getFullYear()} Hak Cipta Dilindungi.</p>
    </div>

  </div>
</footer>`
}
