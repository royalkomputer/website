/**
 * Navbar Component
 *
 * Renders: logo + brand name, desktop search bar, social media links,
 * hamburger menu (mobile) → expands to show mobile search + social menu.
 *
 * @param {Object} options
 * @param {(query: string) => void} options.onSearch — callback when user types in search
 * @returns {string} HTML string
 */
export function Navbar({ onSearch }) {
  return `
<nav class="bg-astra-950 text-white sticky top-0 z-50">
  <div class="container mx-auto px-4 py-3 flex items-center justify-between gap-4">

    <!-- Logo -->
    <a href="#" class="flex items-center gap-2 flex-shrink-0">
      <img src="/logo/logo.webp" alt="Logo" class="h-8 md:h-10 w-auto">
      <span class="font-bold text-sm md:text-xl tracking-wider text-white">ROYAL<span class="text-astra-400">KOMPUTER</span></span>
    </a>

    <!-- Search Bar (desktop) -->
    <div class="hidden md:flex flex-grow max-w-md relative">
      <input type="text"
             class="search-input js-search-input w-full bg-slate-900 border border-slate-700 text-slate-200 placeholder-slate-400 rounded-lg px-4 py-2 pl-10 focus:outline-none focus:border-astra-400 transition-all text-sm"
             placeholder="Cari hardware..."
             data-sync="search-input">
      <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-sm"></i>
    </div>

    <!-- Social Links (desktop) -->
    <div class="hidden md:flex items-center gap-3 flex-shrink-0">
      <span class="text-xs text-slate-400 font-semibold hidden lg:inline">Ikuti Kami:</span>
      <a href="https://www.facebook.com/royall.komp" target="_blank" class="text-slate-300 hover:text-blue-500 transition-colors" title="Facebook">
        <i class="fa-brands fa-facebook text-lg"></i>
      </a>
      <a href="https://www.facebook.com/royalkomputerkediri?locale=id_ID" target="_blank" class="text-slate-300 hover:text-sky-400 transition-colors" title="Facebook Pages">
        <i class="fa-solid fa-flag text-lg"></i>
      </a>
      <a href="https://www.instagram.com/royalkomputerkediri/" target="_blank" class="text-slate-300 hover:text-pink-500 transition-colors" title="Instagram">
        <i class="fa-brands fa-instagram text-lg"></i>
      </a>
      <a href="https://www.tiktok.com/@royalkomputerkediri" target="_blank" class="text-slate-300 hover:text-white transition-colors" title="TikTok">
        <i class="fa-brands fa-tiktok text-lg"></i>
      </a>
      <a href="https://wa.me/6281380686168" target="_blank" class="text-slate-300 hover:text-green-500 transition-colors" title="WhatsApp">
        <i class="fa-brands fa-whatsapp text-lg"></i>
      </a>
      <a href="https://www.youtube.com/@royalkomputerkediri" target="_blank" class="text-slate-300 hover:text-red-500 transition-colors" title="YouTube">
        <i class="fa-brands fa-youtube text-lg"></i>
      </a>
    </div>

    <!-- Hamburger (mobile) -->
    <button class="js-nav-toggle md:hidden flex items-center justify-center text-slate-300 hover:text-white focus:outline-none h-9 w-9 bg-slate-900 border border-slate-700 rounded-lg flex-shrink-0">
      <i class="fa-solid fa-bars text-lg"></i>
    </button>
  </div>

  <!-- Search Bar (mobile) -->
  <div class="md:hidden px-4 pb-3">
    <div class="relative">
      <input type="text"
             class="js-search-input-mobile w-full bg-slate-900 border border-slate-700 text-slate-200 placeholder-slate-400 rounded-lg px-4 py-2 pl-10 focus:outline-none focus:border-astra-400 transition-all text-sm"
             placeholder="Cari hardware...">
      <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-sm"></i>
    </div>
  </div>

  <!-- Mobile Social Menu (hidden by default) -->
  <div class="js-nav-sosmed-menu hidden md:hidden border-t border-slate-800">
    <div class="container mx-auto px-4 py-3 flex flex-col gap-1">
      <span class="text-xs text-slate-400 font-semibold mb-1">Ikuti Kami:</span>
      ${socialLink('https://www.facebook.com/royall.komp', 'fa-brands fa-facebook', 'text-blue-500', 'Facebook')}
      ${socialLink('https://www.facebook.com/royalkomputerkediri?locale=id_ID', 'fa-solid fa-flag', 'text-sky-500', 'Facebook Pages')}
      ${socialLink('https://www.instagram.com/royalkomputerkediri/', 'fa-brands fa-instagram', 'text-pink-500', 'Instagram')}
      ${socialLink('https://www.tiktok.com/@royalkomputerkediri', 'fa-brands fa-tiktok', 'text-white', 'TikTok')}
      ${socialLink('https://wa.me/6281380686168', 'fa-brands fa-whatsapp', 'text-green-500', 'WhatsApp Admin')}
      ${socialLink('https://www.youtube.com/@royalkomputerkediri', 'fa-brands fa-youtube', 'text-red-500', 'YouTube')}
    </div>
  </div>
</nav>`
}

function socialLink(url, icon, color, label) {
  return `<a href="${url}" target="_blank" class="flex items-center gap-3 text-slate-300 hover:${color.replace('text-', 'text-')} transition-colors py-2 px-2 rounded-lg hover:bg-slate-800">
    <i class="${icon} text-lg w-5 ${color}"></i>
    <span class="text-sm font-medium">${label}</span>
  </a>`
}

/**
 * Bind Navbar event listeners (search, mobile menu toggle).
 * @param {(query: string) => void} onSearch
 */
export function bindNavbarEvents(onSearch) {
  // Toggle mobile social menu
  const toggleBtn = document.querySelector('.js-nav-toggle')
  const sosmedMenu = document.querySelector('.js-nav-sosmed-menu')
  if (toggleBtn && sosmedMenu) {
    toggleBtn.addEventListener('click', () => {
      sosmedMenu.classList.toggle('hidden')
    })
  }

  // Search — sync desktop + mobile inputs
  const desktopInput = document.querySelector('.js-search-input')
  const mobileInput = document.querySelector('.js-search-input-mobile')

  function handleSearchInput(e) {
    const val = e.target.value
    if (desktopInput && desktopInput !== e.target) desktopInput.value = val
    if (mobileInput && mobileInput !== e.target) mobileInput.value = val
    onSearch(val)
  }

  if (desktopInput) desktopInput.addEventListener('input', handleSearchInput)
  if (mobileInput) mobileInput.addEventListener('input', handleSearchInput)
}
