/**
 * Footer Component
 *
 * Renders: logo + address, social media links, operating hours table,
 * version + copyright.
 *
 * @param {Object} hours — operating hours object from jam_operasional.json
 * @returns {string} HTML string
 */
export function Footer(hours) {
  const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
  const currentDay = dayNames[new Date().getDay()]

  let hoursRows = ''
  if (hours && typeof hours === 'object') {
    hoursRows = dayNames.map(day => {
      const h = hours[day]
      if (!h) return ''
      const isToday = day === currentDay
      const highlight = isToday ? 'text-astra-400 font-bold bg-slate-900 rounded border border-slate-800' : ''
      const times = `${h.buka.replace(':', '.')}–${h.tutup.replace(':', '.')}`
      return `<div class="flex justify-between py-1 px-2 mb-1 ${highlight}">
        <span>${h.indo || day}</span>
        <span>${times}</span>
      </div>`
    }).join('')
  }

  return `
<footer class="bg-slate-950 text-slate-400 text-xs border-t border-slate-800 mt-12 py-12">
  <div class="container mx-auto px-4 max-w-6xl grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 items-start">

    <!-- Logo & Address -->
    <div class="flex flex-col gap-3 items-center md:items-start">
      <img src="/logo/logo.webp" alt="Royal Komputer Logo" class="h-12 w-auto object-contain rounded mb-1">
      <p class="font-bold text-slate-200 text-sm tracking-wide">ROYAL KOMPUTER KEDIRI</p>
      <p class="text-slate-400 leading-relaxed text-center md:text-left text-xs">
        <i class="fa-solid fa-location-dot text-red-500 mr-1"></i>
        Gg. Masjid No.22A, Jamsaren, Kec. Pesantren, Kota Kediri, Jawa Timur 64132
      </p>
    </div>

    <!-- Social Media -->
    <div class="flex flex-col gap-3 items-center md:items-start w-full">
      <p class="font-bold text-slate-200 text-sm tracking-wide border-b border-slate-800 pb-1 w-full text-center md:text-left">MEDIA SOSIAL</p>
      <div class="flex flex-col gap-2.5 text-sm items-center md:items-start w-full">
        <a href="https://www.facebook.com/royall.komp" target="_blank" class="hover:text-blue-500 flex items-center gap-2 transition-colors text-xs">
          <i class="fa-brands fa-facebook text-sm text-blue-600"></i> Facebook Resmi
        </a>
        <a href="https://www.facebook.com/royalkomputerkediri?locale=id_ID" target="_blank" class="hover:text-blue-400 flex items-center gap-2 transition-colors text-xs">
          <i class="fa-solid fa-layer-group text-sm text-sky-500"></i> Facebook Pages
        </a>
        <a href="https://www.instagram.com/royalkomputerkediri/" target="_blank" class="hover:text-pink-500 flex items-center gap-2 transition-colors text-xs">
          <i class="fa-brands fa-instagram text-sm text-pink-500"></i> Instagram
        </a>
        <a href="https://www.tiktok.com/@royalkomputerkediri" target="_blank" class="hover:text-white flex items-center gap-2 transition-colors text-xs">
          <i class="fa-brands fa-tiktok text-sm text-white"></i> TikTok
        </a>
        <a href="https://wa.me/6281380686168" target="_blank" class="hover:text-green-500 flex items-center gap-2 transition-colors text-xs">
          <i class="fa-brands fa-whatsapp text-sm text-green-500"></i> WhatsApp Admin
        </a>
        <a href="https://www.youtube.com/@royalkomputerkediri" target="_blank" class="hover:text-red-500 flex items-center gap-2 transition-colors text-xs">
          <i class="fa-brands fa-youtube text-sm text-red-500"></i> YouTube
        </a>
      </div>
    </div>

    <!-- Operating Hours -->
    <div class="flex flex-col gap-3 items-center md:items-start w-full">
      <p class="font-bold text-slate-200 text-sm tracking-wide border-b border-slate-800 pb-1 w-full text-center md:text-left">JAM BUKA TOKO</p>
      <div class="w-full text-[11px] text-slate-400 max-w-[200px] mx-auto md:mx-0">
        ${hoursRows || '<p class="text-slate-500">Tidak tersedia</p>'}
      </div>
    </div>

    <!-- Version & Copyright -->
    <div class="flex flex-col gap-1 items-center lg:items-end lg:text-right h-full justify-center lg:justify-start lg:pt-6 w-full mt-4 lg:mt-0">
      <p class="font-semibold text-slate-600 tracking-wider">ROYAL MARKETPLACE v3.0</p>
      <p class="text-slate-500">&copy; ${new Date().getFullYear()} Hak Cipta Dilindungi.</p>
    </div>

  </div>
</footer>`
}
