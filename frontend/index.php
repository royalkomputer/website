<?php
date_default_timezone_set('Asia/Jakarta');

// Baca tagline toko
$tagline_file = 'tagline.json';
$tagline = 'Bingung mau rakit atau upgrade komputer? Ke Royal Komputer aja. Bisa tukar tambah loh.';
if (file_exists($tagline_file)) {
    $tagline_data = json_decode(file_get_contents($tagline_file), true);
    if (!empty($tagline_data['tagline'])) $tagline = $tagline_data['tagline'];
}

// Baca heading toko
$heading_file = 'heading.json';
$heading_prefix = 'Solusi Hardware di';
$heading_brand = 'Royal Komputer';
if (file_exists($heading_file)) {
    $heading_data = json_decode(file_get_contents($heading_file), true);
    if (!empty($heading_data['prefix'])) $heading_prefix = $heading_data['prefix'];
    if (!empty($heading_data['brand'])) $heading_brand = $heading_data['brand'];
}

// Baca teks info produk
$product_info_file = 'product_info.json';
$product_info_text = 'Perhatian! Harga tidak selalu update. Silahkan hubungi Kami di WhatsApp.';
if (file_exists($product_info_file)) {
    $info_data = json_decode(file_get_contents($product_info_file), true);
    if (!empty($info_data['text'])) $product_info_text = $info_data['text'];
}
// Langsung gunakan teks (tanpa {count})
$product_info_html = htmlspecialchars($product_info_text);

?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/webp" href="logo/logo.webp">
    <title>Royal Komputer - Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'], },
                    colors: {
                        astra: {
                            50: '#f0f7ff', 100: '#e0effe', 200: '#b9dffd', 300: '#7cc3fc', 400: '#36a4fa',
                            500: '#0c87eb', 600: '#0069c9', 700: '#0254A3', 800: '#064787', 900: '#0b3c70', 950: '#07162c',
                        }
                    }
                }
            },
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #1e293b; }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #64748b; }

        @keyframes slideRightFade {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(80px); }
        }
        @keyframes slideDownFade {
            from { opacity: 0; transform: translateY(-100%); }
            to { opacity: 1; transform: translateY(0); }
        }
        .notif-enter {
            animation: slideDownFade 0.4s ease forwards;
        }

        /* Banner slide-up */
        @keyframes bannerSlideUp {
            from { opacity: 1; transform: translateY(0); max-height: 2000px; }
            to { opacity: 0; transform: translateY(-30px); max-height: 0; padding: 0; margin: 0; }
        }
        .banner-leave {
            animation: bannerSlideUp 0.35s ease-in forwards;
            overflow: hidden;
        }

        /* Banner slide-down */
        @keyframes bannerSlideDown {
            from { opacity: 0; transform: translateY(-30px); max-height: 0; padding: 0; margin: 0; }
            to { opacity: 1; transform: translateY(0); max-height: 2000px; }
        }
        .banner-enter {
            animation: bannerSlideDown 0.4s ease-out forwards;
            overflow: hidden;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        .shimmer {
            background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0) 100%);
            background-size: 200% 100%;
            animation: shimmer 1.8s ease-in-out infinite;
        }

        @media (min-width: 1024px) {
            #filter-icon {
                display: none !important;
            }
        }

        /* Dark mode overrides untuk product cards (dibuat oleh JavaScript) */
        .dark #product-grid > div {
            background-color: rgba(30, 41, 59, 0.6) !important;
            border-color: rgba(255, 255, 255, 0.05) !important;
        }
        .dark #product-grid h3 {
            color: #e2e8f0 !important;
        }
        .dark #product-grid .text-astra-700 {
            color: #60a5fa !important;
        }
        .dark #product-grid img[src*="data:image"] + .bg-white\/90,
        .dark #product-grid [class*="bg-white/90"] {
            background-color: rgba(30, 41, 59, 0.6) !important;
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 min-h-screen flex flex-col font-sans">

    <!-- Safelist untuk Tailwind CDN JIT (class dipakai oleh JavaScript) -->
    <div class="hidden" aria-hidden="true">
        <span class="bg-astra-700 text-white font-semibold shadow-sm bg-slate-700 hover:bg-slate-700/50 hover:border-astra-500/30 hover:bg-slate-700/50 border-astra-500/30"></span>
        <span class="bg-astra-900/40 bg-white/20 bg-slate-800/40"></span>
        <span class="text-slate-400 hover:text-slate-200"></span>
        <span class="bg-slate-100 text-slate-500 bg-slate-800/60 bg-slate-800"></span>
        <span class="bg-white w-4 bg-white/50 hover:bg-white/80 banner-leave banner-enter"></span>
    </div>

    <!-- Navbar -->
<nav class="bg-white dark:bg-astra-950 text-slate-800 dark:text-white sticky top-0 z-50 border-b border-slate-200 dark:border-slate-800/50">
    <div class="container mx-auto px-4 py-2.5 flex items-center justify-between gap-4">
        
        <!-- Logo -->
        <a href="#" class="flex items-center gap-2 flex-shrink-0">
            <img src="logo/logo.webp" alt="Logo" class="h-8 md:h-10 w-auto">
            <span class="font-bold text-sm md:text-xl tracking-wider text-slate-800 dark:text-white">ROYAL<span class="text-astra-400"> KOMPUTER</span></span>
        </a>
        
        <!-- Search Bar (tengah, hanya desktop) -->
        <div class="hidden md:flex flex-grow max-w-md">
            <div class="relative flex-grow">
                <input type="text" id="search-input" onkeydown="if(event.key==='Enter') triggerSearch('desktop')" placeholder="Cari hardware..." class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 placeholder-slate-500 dark:placeholder-slate-400 rounded-lg px-4 py-2 pl-10 focus:outline-none focus:border-astra-400 transition-all text-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-sm"></i>
            </div>
            <button onclick="triggerSearch('desktop')" class="ml-2 bg-astra-600 hover:bg-astra-700 text-white px-3 py-2 rounded-lg transition-colors text-sm flex items-center gap-1 flex-shrink-0">
                <i class="fa-solid fa-magnifying-glass"></i> Cari
            </button>
        </div>
        
        <!-- Sosmed Links (desktop) -->
        <div class="hidden md:flex items-center gap-2.5 flex-shrink-0">
            <span class="text-xs text-slate-400 dark:text-slate-500 font-medium hidden lg:inline">Ikuti Kami:</span>
            <a href="https://www.facebook.com/royall.komp" target="_blank" class="text-slate-400 dark:text-slate-500 hover:text-astra-400 transition-colors" title="Facebook">
                <i class="fa-brands fa-facebook text-lg"></i>
            </a>
            <a href="https://www.instagram.com/royalkomputerkediri/" target="_blank" class="text-slate-400 dark:text-slate-500 hover:text-astra-400 transition-colors" title="Instagram">
                <i class="fa-brands fa-instagram text-lg"></i>
            </a>
            <a href="https://www.tiktok.com/@royalkomputerkediri" target="_blank" class="text-slate-400 dark:text-slate-500 hover:text-astra-400 transition-colors" title="TikTok">
                <i class="fa-brands fa-tiktok text-lg"></i>
            </a>
            <a href="https://www.youtube.com/@royalkomputerkediri" target="_blank" class="text-slate-400 dark:text-slate-500 hover:text-astra-400 transition-colors" title="YouTube">
                <i class="fa-brands fa-youtube text-lg"></i>
            </a>
        </div>

        <!-- Hamburger (mobile) -->
        <div class="flex md:hidden items-center gap-2">
            <button onclick="toggleNavMenu()" class="flex items-center justify-center text-slate-500 hover:text-slate-700 focus:outline-none h-9 w-9 bg-slate-100 border border-slate-300 rounded-lg flex-shrink-0">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
        </div>
    </div>

    <!-- Search Bar (mobile, di bawah row utama) -->
    <div class="md:hidden px-4 pb-3">
        <div class="flex gap-2">
            <div class="relative flex-grow">
                <input type="text" id="search-input-mobile" onkeydown="if(event.key==='Enter') triggerSearch('mobile')" placeholder="Cari hardware..." class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 placeholder-slate-500 dark:placeholder-slate-400 rounded-lg px-4 py-2 pl-10 focus:outline-none focus:border-astra-400 transition-all text-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-sm"></i>
            </div>
            <button onclick="triggerSearch('mobile')" class="bg-astra-600 hover:bg-astra-700 text-white px-3 py-2 rounded-lg transition-colors text-sm flex items-center gap-1 flex-shrink-0">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </div>
    </div>

    <!-- Dropdown Menu Sosmed (mobile) -->
    <div id="nav-sosmed-menu" class="hidden md:hidden border-t border-slate-300 dark:border-slate-800">
        <div class="container mx-auto px-4 py-3 flex flex-col gap-1">
            <div class="flex items-center justify-between mb-1">
            <span class="text-xs text-slate-500 font-semibold">Ikuti Kami:</span>
            </div>
            <a href="https://www.facebook.com/royall.komp" target="_blank" class="flex items-center gap-3 text-slate-600 dark:text-slate-300 hover:text-blue-500 transition-colors py-2 px-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
                <i class="fa-brands fa-facebook text-lg w-5 text-blue-500"></i>
                <span class="text-sm font-medium">Facebook</span>
            </a>
            <a href="https://www.facebook.com/royalkomputerkediri?locale=id_ID" target="_blank" class="flex items-center gap-3 text-slate-600 dark:text-slate-300 hover:text-sky-400 transition-colors py-2 px-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
                <i class="fa-solid fa-flag text-lg w-5 text-sky-500"></i>
                <span class="text-sm font-medium">Facebook Pages</span>
            </a>
            <a href="https://www.instagram.com/royalkomputerkediri/" target="_blank" class="flex items-center gap-3 text-slate-600 dark:text-slate-300 hover:text-pink-500 transition-colors py-2 px-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
                <i class="fa-brands fa-instagram text-lg w-5 text-pink-500"></i>
                <span class="text-sm font-medium">Instagram</span>
            </a>
            <a href="https://www.tiktok.com/@royalkomputerkediri" target="_blank" class="flex items-center gap-3 text-slate-600 dark:text-slate-300 hover:text-black dark:hover:text-white transition-colors py-2 px-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
                <i class="fa-brands fa-tiktok text-lg w-5 text-black dark:text-white"></i>
                <span class="text-sm font-medium">TikTok</span>
            </a>
            <a href="https://www.youtube.com/@royalkomputerkediri" target="_blank" class="flex items-center gap-3 text-slate-600 dark:text-slate-300 hover:text-red-500 transition-colors py-2 px-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
                <i class="fa-brands fa-youtube text-lg w-5 text-red-500"></i>
                <span class="text-sm font-medium">YouTube</span>
            </a>
        </div>
    </div>
</nav>

    <header class="bg-slate-900 text-white py-10 md:py-14 px-4 relative overflow-hidden border-b border-slate-800">
        <div class="container mx-auto text-center relative z-10">
            <h1 class="text-2xl md:text-4xl font-bold tracking-tight mb-3"><?php echo htmlspecialchars($heading_prefix); ?> <span class="text-astra-400"><?php echo htmlspecialchars($heading_brand); ?></span></h1>
            <p class="text-slate-400 max-w-xl mx-auto text-sm md:text-base font-light"><?php echo htmlspecialchars($tagline); ?></p>
        </div>
    </header>

    <div id="banner-section" class="w-full container mx-auto px-4 mt-4 mb-2">
        <div id="banner-carousel" class="relative w-full overflow-hidden rounded-xl bg-slate-800/40 min-h-[200px] flex items-center justify-center">
            <div class="text-slate-500 text-sm py-12 text-center px-4">
                <i class="fa-solid fa-image text-2xl mb-2 block"></i>
                Selamat datang di Royal Komputer
            </div>
        </div>
    </div>

    <main id="main-layout" class="container mx-auto px-4 py-6 flex-grow grid grid-cols-1 gap-6">
        
        <aside id="sidebar-filter" class="hidden lg:col-span-1 bg-slate-800/60 rounded-xl border border-white/5 self-start overflow-hidden">
            
            <button onclick="toggleFilterMenu()" class="w-full p-3.5 flex items-center justify-between lg:cursor-default focus:outline-none bg-slate-800/40 border-b border-white/5 lg:border-none">
                <h3 class="font-semibold text-white flex items-center gap-2"><i class="fa-solid fa-sliders text-astra-400 text-sm"></i> Filter & Urutkan</h3>
                <i id="filter-icon" class="fa-solid fa-chevron-down text-slate-500 transition-transform duration-300 lg:hidden"></i>
            </button>

            <div id="filter-content" class="hidden lg:block p-3">
                <div class="flex justify-end mb-4 pb-3 border-b border-white/5">
                    <button id="reset-filter-btn" onclick="resetFilters()" class="text-xs text-astra-400 font-medium hover:text-astra-300 transition-colors">
                        <i class="fa-solid fa-arrow-rotate-right mr-1"></i> Reset Filter
                    </button>
                </div>

                <div class="mb-5">
                    <button type="button" onclick="toggleCategoryPanel()" class="w-full flex items-center justify-between text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2.5 focus:outline-none hover:text-slate-300 transition-colors">
                        <span>Kategori</span>
                        <i id="category-toggle-icon" class="fa-solid fa-chevron-down text-slate-500 transition-transform duration-200"></i>
                    </button>
                    <div id="category-panel" class="space-y-0.5">
                        <div id="category-list" class="space-y-0.5"></div>
                    </div>
                </div>
                
                <div class="mb-5 border-t border-white/5 pt-4">
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2.5">Kondisi</label>
                    <div class="flex gap-2">
                        <button class="js-cond-btn flex-1 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all text-center bg-slate-800 border border-slate-600/50 text-slate-400 hover:bg-slate-700" data-condition="Semua" onclick="handleCondition('Semua')">
                            <i class="fa-solid fa-check hidden"></i> Semua
                        </button>
                        <button class="js-cond-btn flex-1 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all text-center bg-slate-800 border border-slate-600/50 text-slate-400 hover:bg-slate-700" data-condition="Baru" onclick="handleCondition('Baru')">
                            <i class="fa-solid fa-check hidden"></i> Baru
                        </button>
                        <button class="js-cond-btn flex-1 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all text-center bg-slate-800 border border-slate-600/50 text-slate-400 hover:bg-slate-700" data-condition="Bekas" onclick="handleCondition('Bekas')">
                            <i class="fa-solid fa-check hidden"></i> Bekas
                        </button>
                    </div>
                </div>

                <div class="border-t border-white/5 pt-4">
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2.5">Urutkan</label>
                    <div class="space-y-0.5">
                        <button class="js-sort-btn w-full text-left px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all flex items-center gap-2 text-slate-400 hover:bg-slate-700/50" data-sort="default" onclick="handleSort('default')">
                            <i class="fa-regular fa-star text-slate-500 w-3.5"></i> Rekomendasi Teratas
                        </button>
                        <button class="js-sort-btn w-full text-left px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all flex items-center gap-2 text-slate-400 hover:bg-slate-700/50" data-sort="low-high" onclick="handleSort('low-high')">
                            <i class="fa-solid fa-arrow-up-wide-short text-slate-500 w-3.5"></i> Harga: Rendah ke Tinggi
                        </button>
                        <button class="js-sort-btn w-full text-left px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all flex items-center gap-2 text-slate-400 hover:bg-slate-700/50" data-sort="high-low" onclick="handleSort('high-low')">
                            <i class="fa-solid fa-arrow-down-wide-short text-slate-500 w-3.5"></i> Harga: Tinggi ke Rendah
                        </button>
                    </div>
                </div>
            </div>

        </aside>

        <section class="lg:col-span-3 flex flex-col gap-4">
            <div id="product-info-bar" class="flex items-center justify-between bg-slate-800/40 p-3 rounded-xl border border-white/5 hidden">
                <div class="flex items-center gap-3">
                    <div class="text-xs text-slate-400"><?php echo $product_info_html; ?></div>
                    <span id="product-count" class="text-xs text-slate-500 bg-slate-800 px-2 py-0.5 rounded-md"></span>
                </div>
                <div class="flex items-center gap-1 bg-slate-800 rounded-lg p-0.5">
                    <button id="view-grid-btn" onclick="setView('grid')" class="flex items-center gap-1.5 px-2 py-1.5 rounded-md text-xs font-medium transition-all text-slate-400" title="Tampilan Grid">
                        <i class="fa-solid fa-grid-2"></i>
                    </button>
                    <button id="view-detail-btn" onclick="setView('detail')" class="flex items-center gap-1.5 px-2 py-1.5 rounded-md text-xs font-medium transition-all text-slate-400" title="Tampilan Detail">
                        <i class="fa-solid fa-list"></i>
                    </button>
                </div>
            </div>

            <div id="search-prompt" class="bg-slate-800/40 border border-white/5 rounded-xl px-3 py-2.5 flex items-center gap-2.5">
                <div class="flex-shrink-0 w-6 h-6 bg-slate-700 rounded-full flex items-center justify-center">
                    <i class="fa-solid fa-magnifying-glass text-xs text-slate-400"></i>
                </div>
                <p class="text-xs text-slate-500 flex-1">Gunakan pencarian untuk menampilkan produk.</p>
            </div>

            <div id="loading-spinner" class="py-16 flex flex-col items-center justify-center gap-3">
                <i class="fa-solid fa-spinner text-3xl text-slate-600 animate-spin"></i>
                <p class="text-slate-500 text-sm">Memuat produk...</p>
            </div>

            <div id="empty-state" class="hidden bg-slate-800/40 rounded-xl border border-white/5 p-10 text-center">
                <i class="fa-solid fa-box-open text-4xl text-slate-600 mb-3"></i>
                <h4 class="text-base font-semibold text-slate-300 mb-1">Produk Tidak Ditemukan</h4>
                <p class="text-slate-500 text-xs">Tidak ada produk yang sesuai dengan kriteria pencarian Anda.</p>
            </div>

            <div id="product-grid" class="hidden grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4"></div>
        </section>
    </main>

    <footer class="bg-slate-900 text-slate-500 text-xs border-t border-slate-800 mt-10 py-10">
        <div class="container mx-auto px-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 items-start">
            
            <div class="flex flex-col gap-2 items-center md:items-start">
                <img src="logo/logo.webp" alt="Royal Komputer Logo" class="h-10 w-auto object-contain">
                <p class="font-semibold text-slate-300 text-sm">ROYAL KOMPUTER KEDIRI</p>
                <a href="https://www.google.com/maps/place/Royal+Komputer/@-7.8247749,112.0198969,17z/data=!3m1!4b1!4m6!3m5!1s0x2e7857bb27d7da49:0x12d8857ab5c2e60d!8m2!3d-7.8247749!4d112.0198969!16s%2Fg%2F11fn0mc9js?entry=ttu&g_ep=EgoyMDI2MDcxNS4wIKXMDSoASAFQAw%3D%3D" target="_blank" class="text-slate-500 hover:text-astra-400 transition-colors leading-relaxed text-center md:text-left text-xs inline-flex items-start gap-1">
                    <i class="fa-solid fa-location-dot text-red-400 mt-0.5"></i>
                    Gg. Masjid No.22A, Jamsaren, Kec. Pesantren, Kota Kediri, Jawa Timur 64132
                </a>
            </div>
            
            <div class="flex flex-col gap-2 items-center md:items-start w-full">
                <p class="font-semibold text-slate-400 text-sm border-b border-slate-800 pb-1 w-full text-center md:text-left">MEDIA SOSIAL</p>
                <div class="flex flex-wrap gap-3 justify-center md:justify-start">
                    <a href="https://www.facebook.com/royall.komp" target="_blank" class="text-slate-500 hover:text-astra-400 transition-colors" title="Facebook"><i class="fa-brands fa-facebook text-base"></i></a>
                    <a href="https://www.instagram.com/royalkomputerkediri/" target="_blank" class="text-slate-500 hover:text-astra-400 transition-colors" title="Instagram"><i class="fa-brands fa-instagram text-base"></i></a>
                    <a href="https://www.tiktok.com/@royalkomputerkediri" target="_blank" class="text-slate-500 hover:text-astra-400 transition-colors" title="TikTok"><i class="fa-brands fa-tiktok text-base"></i></a>
                    <a href="https://www.youtube.com/@royalkomputerkediri" target="_blank" class="text-slate-500 hover:text-astra-400 transition-colors" title="YouTube"><i class="fa-brands fa-youtube text-base"></i></a>
                </div>
            </div>

            <div class="flex flex-col gap-2 items-center md:items-start w-full">
                <p class="font-semibold text-slate-400 text-sm border-b border-slate-800 pb-1 w-full text-center md:text-left">KONTAK</p>
                <div class="flex flex-col gap-2">
                    <a href="https://wa.me/6281380686168" target="_blank" class="text-slate-500 hover:text-astra-400 transition-colors text-xs flex items-center gap-2">
                        <i class="fa-brands fa-whatsapp text-green-400"></i> 0813-8068-6168
                    </a>
                    <span class="text-slate-500 text-xs flex items-center gap-2"><i class="fa-solid fa-truck text-slate-400"></i> Melayani Seluruh Indonesia</span>
                </div>
            </div>
            
            <div class="flex flex-col gap-1 items-center lg:items-end lg:text-right w-full">
                <p class="text-slate-500 text-xs tracking-wider">ROYAL MARKETPLACE v2.2</p>
                <p class="text-slate-600 text-xs">&copy; <?php echo date("Y"); ?> Royal Komputer</p>
            </div>
            
        </div>
    </footer>

    <!-- MODAL DETAIL PRODUK -->
    <div id="detail-modal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-800 rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col md:flex-row relative">
            <button onclick="closeDetailModal()" class="absolute top-4 right-4 z-10 w-8 h-8 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-full flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark"></i></button>
            
            <!-- Kiri: Galeri Foto -->
            <div class="w-full md:w-1/2 bg-slate-800 relative group min-h-[300px] flex items-center justify-center">
                <img id="detail-image" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E" alt="Detail" class="w-full h-full object-contain max-h-[500px]" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div id="detail-image-fallback" class="hidden absolute inset-0 flex-col items-center justify-center text-slate-500 dark:text-slate-400">
                    <i class="fa-solid fa-image text-5xl mb-2"></i>
                    <span class="text-sm">Gambar tidak tersedia</span>
                </div>
                
                <!-- Navigasi Carousel -->
                <button id="btn-prev-img" onclick="changeImage(-1)" class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/80 dark:bg-slate-700/80 hover:bg-white dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 rounded-full flex items-center justify-center shadow-lg hidden"><i class="fa-solid fa-chevron-left"></i></button>
                <button id="btn-next-img" onclick="changeImage(1)" class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/80 dark:bg-slate-700/80 hover:bg-white dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 rounded-full flex items-center justify-center shadow-lg hidden"><i class="fa-solid fa-chevron-right"></i></button>
                
                <div id="img-indicators" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2"></div>
            </div>

            <!-- Kanan: Info Produk -->
            <div class="w-full md:w-1/2 p-6 md:p-8 flex flex-col max-h-[50vh] md:max-h-full overflow-y-auto">
                <div id="detail-badge" class="mb-3"></div>
                <h2 id="detail-name" class="text-xl font-bold text-white mb-2"></h2>
                <div class="text-2xl font-bold text-astra-400 mb-5" id="detail-price"></div>
                
                <div class="bg-slate-800/30 p-4 rounded-xl border border-white/5 mb-6 flex-grow">
                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Deskripsi & Spesifikasi</h4>
                    <p id="detail-desc" class="text-sm text-slate-300 whitespace-pre-line leading-relaxed"></p>
                </div>
                
                <div class="mt-auto pt-4 border-t border-white/5">
                    <div class="flex items-center justify-between">
                        <a id="detail-wa-btn" href="#" target="_blank" class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 px-4 rounded-xl transition-colors text-sm" title="Pesan via WhatsApp">
                            <i class="fa-brands fa-whatsapp text-lg"></i> <span>Pesan</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fungsi untuk buka-tutup menu sosmed (Navbar) di Mobile
        function toggleNavMenu() {
            const menu = document.getElementById('nav-sosmed-menu');
            menu.classList.toggle('hidden');
        }

        // Fungsi untuk buka-tutup menu Filter di Mobile
        function toggleFilterMenu() {
            if (window.innerWidth < 1024) {
                const content = document.getElementById('filter-content');
                const icon = document.getElementById('filter-icon');
                content.classList.toggle('hidden');
                icon.classList.toggle('rotate-180');
            }
        }
        
        let allProducts = [];
        let filteredProducts = [];
        let activeFilters = { category: 'Semua', search: '', sortBy: 'default', condition: 'Semua' };
        let hasActivated = false;
        let currentView = localStorage.getItem('viewMode') || 'grid';
        let displayLimit = 40;
        
        let currentDetailImages = [];
        let currentImageIndex = 0;

        function setView(mode) {
            var grid = document.getElementById('product-grid');
            if (grid && grid.classList.contains('hidden')) return;
            currentView = mode;
            localStorage.setItem('viewMode', mode);
            var activeClass = 'flex items-center gap-1.5 px-2 py-1.5 rounded-md text-xs font-medium transition-all bg-astra-700 text-white';
            var inactiveClass = 'flex items-center gap-1.5 px-2 py-1.5 rounded-md text-xs font-medium transition-all text-slate-400 bg-slate-800 hover:bg-slate-700';
            document.getElementById('view-grid-btn').className = mode === 'grid' ? activeClass : inactiveClass;
            document.getElementById('view-detail-btn').className = mode === 'detail' ? activeClass : inactiveClass;
            renderProductGrid();
        }

        function openDetailModal(id) {
            const product = allProducts.find(p => p.id === id);
            if (!product) return;

            document.getElementById('detail-name').innerText = product.name;
            
            const formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(product.price);
            document.getElementById('detail-price').innerText = formattedPrice;
            document.getElementById('detail-desc').innerText = product.description || 'Tidak ada deskripsi rinci untuk produk ini.';
            const isBekas = (product.name || '').toUpperCase().includes('2ND');
            var isDark = document.documentElement.classList.contains('dark');
            document.getElementById('detail-badge').innerHTML = isBekas 
                ? `<span class="bg-orange-500/20 text-orange-300 text-xs font-semibold px-2 py-0.5 rounded">KONDISI: BEKAS</span>`
                : `<span class="bg-sky-500/20 text-sky-300 text-xs font-semibold px-2 py-0.5 rounded">KONDISI: BARU</span>`;

            const waNumber = "6281380686168";
            const waText = encodeURIComponent(`Halo Admin Royal Komputer,\nSaya ingin membeli produk ini:\n\n*${product.name}*\nHarga: ${formattedPrice}\n\nApakah stoknya masih ready?`);
            document.getElementById('detail-wa-btn').href = `https://wa.me/${waNumber}?text=${waText}`;

            // Carousel Logic
            currentDetailImages = product.images || [product.image];
            currentImageIndex = 0;
            updateCarousel();

            const prevBtn = document.getElementById('btn-prev-img');
            const nextBtn = document.getElementById('btn-next-img');
            if (currentDetailImages.length > 1) {
                prevBtn.classList.remove('hidden');
                nextBtn.classList.remove('hidden');
            } else {
                prevBtn.classList.add('hidden');
                nextBtn.classList.add('hidden');
            }

            document.body.style.overflow = 'hidden';
            document.getElementById('detail-modal').classList.remove('hidden');
        }

        function closeDetailModal() {
            document.body.style.overflow = 'auto';
            document.getElementById('detail-modal').classList.add('hidden');
        }

        function changeImage(dir) {
            currentImageIndex += dir;
            if (currentImageIndex >= currentDetailImages.length) currentImageIndex = 0;
            if (currentImageIndex < 0) currentImageIndex = currentDetailImages.length - 1;
            updateCarousel();
        }

        function setImage(index) {
            currentImageIndex = index;
            updateCarousel();
        }

        function updateCarousel() {
            var img = document.getElementById('detail-image');
            var fallback = img.nextElementSibling;
            img.style.display = '';
            fallback.style.display = 'none';
            img.src = currentDetailImages[currentImageIndex];
            
            const indicators = document.getElementById('img-indicators');
            indicators.innerHTML = '';
            if (currentDetailImages.length > 1) {
                for (let i = 0; i < currentDetailImages.length; i++) {
                    const dot = document.createElement('button');
                    dot.onclick = () => setImage(i);
                    dot.className = `w-2 h-2 rounded-full transition-all ${i === currentImageIndex ? 'bg-astra-400 w-4' : 'bg-slate-600 hover:bg-slate-500'}`;
                    indicators.appendChild(dot);
                }
            }
        }
        
        // ── Banner Carousel ──
        function renderBanners(playlists) {
            const carousel = document.getElementById('banner-carousel');
            if (!carousel) return;
            const active = (playlists || []).filter(p => p.active !== false && p.photos && p.photos.length > 0);
            if (active.length === 0) return;
            const pl = active[0];
            const photos = pl.photos;
            const hasMultiple = photos.length > 1;
            const aspect = pl.aspect || '16/9';
            const parts = aspect.split('/').map(Number);
            const padPct = (parts[1] / parts[0] * 100);
            let html = `<div class="relative w-full overflow-hidden" style="padding-bottom:${padPct}%"><div class="absolute inset-0 overflow-hidden"><div id="banner-track" class="flex transition-transform duration-500 ease-in-out w-full h-full">`;
            photos.forEach((p, i) => {
                html += `<div class="min-w-full w-full flex-shrink-0 h-full">`;
                if (p.link) html += `<a href="${p.link}" target="_blank" rel="noopener" class="block h-full">`;
                html += `<img src="/uploads/banners/${p.image}" alt="${p.alt || pl.name || 'Banner'}" class="w-full h-full object-cover" onerror="this.style.display='none'">`;
                if (p.link) html += `</a>`;
                html += `</div>`;
            });
            html += `</div>`;
            if (hasMultiple) {
                html += `<div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-2 z-10">`;
                photos.forEach((_, i) => { html += `<button class="banner-dot w-2 h-2 rounded-full transition-all ${i===0?'bg-white w-4':'bg-white/50 hover:bg-white/80'}" data-index="${i}"></button>`; });
                html += `</div>`;
            }
            html += `</div></div>`;
            carousel.innerHTML = html;
            if (hasMultiple) bindCarousel(photos.length, pl.interval || 5000);
        }

        function bindCarousel(total, interval) {
            const track = document.getElementById('banner-track');
            if (!track) return;
            let current = 0;
            function goTo(index) {
                if (index < 0) index = total - 1;
                if (index >= total) index = 0;
                current = index;
                track.style.transform = 'translateX(-' + (current * 100) + '%)';
                document.querySelectorAll('.banner-dot').forEach(function(dot, i) {
                    dot.className = 'banner-dot w-2 h-2 rounded-full transition-all ' + (i === current ? 'bg-white w-4' : 'bg-white/50 hover:bg-white/80');
                });
            }
            document.querySelectorAll('.banner-dot').forEach(function(d) {
                d.addEventListener('click', function() { goTo(parseInt(this.dataset.index) || 0); });
            });
            var autoInterval = setInterval(function() { goTo(current + 1); }, interval);
            var carouselEl = document.getElementById('banner-carousel');
            carouselEl.addEventListener('mouseenter', function() { clearInterval(autoInterval); });
            carouselEl.addEventListener('mouseleave', function() { autoInterval = setInterval(function() { goTo(current + 1); }, interval); });
        }

        // ── Layout Mode (initial vs active) ──
        var isInitialMode = true;

        function setLayoutMode(mode) {
            var sidebar = document.getElementById('sidebar-filter');
            var banner = document.getElementById('banner-section');
            var main = document.getElementById('main-layout');
            var grid = document.getElementById('product-grid');

            if (mode === 'active') {
                isInitialMode = false;
                if (sidebar) sidebar.classList.remove('hidden');
                if (main) main.classList.add('lg:grid-cols-4');
                if (banner && !banner.classList.contains('hidden')) {
                    banner.classList.add('banner-leave');
                    banner.addEventListener('animationend', function handler() {
                        banner.removeEventListener('animationend', handler);
                        banner.classList.add('hidden');
                        banner.classList.remove('banner-leave');
                    });
                }
            } else {
                isInitialMode = true;
                if (sidebar) sidebar.classList.add('hidden');
                if (main) main.classList.remove('lg:grid-cols-4');
                if (banner) {
                    banner.classList.remove('hidden');
                    banner.classList.add('banner-enter');
                    banner.addEventListener('animationend', function handler() {
                        banner.removeEventListener('animationend', handler);
                        banner.classList.remove('banner-enter');
                    });
                }
                if (grid) { grid.classList.add('hidden'); grid.innerHTML = ''; }
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            initPage();
        });
        
        function updateCondUI(val) {
            document.querySelectorAll('.js-cond-btn').forEach(function(btn) {
                var sel = btn.dataset.condition === val;
                var cls = sel
                    ? 'bg-astra-700 text-white font-semibold shadow-sm'
                    : 'bg-slate-800 border border-slate-600/50 text-slate-400 hover:bg-slate-700';
                btn.className = cls + ' js-cond-btn flex-1 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all text-center';
                btn.querySelector('.fa-check').classList.toggle('hidden', !sel);
            });
        }

        function updateSortUI(val) {
            document.querySelectorAll('.js-sort-btn').forEach(function(btn) {
                var sel = btn.dataset.sort === val;
                var base = 'js-sort-btn w-full text-left px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all flex items-center gap-2';
                var cls = sel
                    ? base + ' bg-astra-700 text-white font-semibold shadow-sm'
                    : base + ' text-slate-400 hover:bg-slate-700/50';
                btn.className = cls;
                var ico = btn.querySelector('i');
                if (ico) {
                    if (sel) {
                        ico.className = 'fa-solid fa-check text-white w-3.5';
                    } else {
                        var iconClass = btn.dataset.sort === 'default' ? 'fa-regular fa-star' : btn.dataset.sort === 'low-high' ? 'fa-solid fa-arrow-up-wide-short' : 'fa-solid fa-arrow-down-wide-short';
                        ico.className = iconClass + ' text-slate-500 w-3.5';
                    }
                }
            });
        }

        function handleCondition(val) {
            activeFilters.condition = val;
            updateCondUI(val);
            hasActivated = true;
            applyFiltersAndSort();
        }

        function showLoading(state) {
            document.getElementById('loading-spinner').style.display = state ? 'flex' : 'none';
        }

        function toggleCategoryPanel() {
            const panel = document.getElementById('category-panel');
            const icon = document.getElementById('category-toggle-icon');
            if (!panel || !icon) return;
            panel.classList.toggle('hidden');
            icon.classList.toggle('rotate-180');
        }

        function initPage() {
            showLoading(true);
            Promise.all([
                fetch('api_produk.php').then(r => r.json()),
                fetch('api_banner.php').then(r => r.json())
            ])
            .then(function(results) {
                var data = results[0];
                var banners = results[1];
                if (data.error) throw new Error(data.error);
                allProducts = data;
                generateCategoryFilterOptions();
                initViewToggle();
                renderBanners(banners);
            })
            .catch(function(err) {
                console.error(err);
                document.getElementById('empty-state').classList.remove('hidden');
            })
            .finally(function() { showLoading(false); });
        }

        function initViewToggle() {
            var activeClass = 'flex items-center gap-1.5 px-2 py-1.5 rounded-md text-xs font-medium transition-all bg-astra-700 text-white';
            var inactiveClass = 'flex items-center gap-1.5 px-2 py-1.5 rounded-md text-xs font-medium transition-all text-slate-400 bg-slate-800 hover:bg-slate-700';
            document.getElementById('view-grid-btn').className = currentView === 'grid' ? activeClass : inactiveClass;
            document.getElementById('view-detail-btn').className = currentView === 'detail' ? activeClass : inactiveClass;
        }

        function generateCategoryFilterOptions() {
            const categories = ['Semua', ...new Set(allProducts.map(p => p.category))];
            const container = document.getElementById('category-list');
            container.innerHTML = '';
            
            categories.forEach(cat => {
                const isSelected = activeFilters.category === cat;
                const button = document.createElement('button');
                button.className = `w-full text-left px-2.5 py-1.5 rounded-lg text-xs font-medium transition-all flex items-center justify-between ${
                    isSelected ? 'bg-astra-700 text-white font-semibold shadow-sm' : 'text-slate-400 hover:bg-slate-700/50'
                }`;
                button.innerHTML = `<span>${cat}</span>`;
                button.onclick = () => selectCategory(cat);
                container.appendChild(button);
            });
        }

        function hideSearchPrompt() {
            var prompt = document.getElementById('search-prompt');
            if (!prompt || prompt.classList.contains('hidden')) return;
            prompt.classList.add('hidden');
        }

        function selectCategory(cat) {
            activeFilters.category = cat;
            hasActivated = true;
            generateCategoryFilterOptions();
            applyFiltersAndSort();
        }

        function triggerSearch(source) {
            var desktop = document.getElementById('search-input');
            var mobile = document.getElementById('search-input-mobile');
            var val = source === 'mobile' ? mobile.value : desktop.value;
            desktop.value = val;
            mobile.value = val;
            handleSearch(val);
        }

        function handleSearch(val) {
    activeFilters.search = val.toLowerCase();
    hasActivated = true;
    applyFiltersAndSort();
}

        function handleSort(val) {
            activeFilters.sortBy = val;
            updateSortUI(val);
            hasActivated = true;
            applyFiltersAndSort();
        }

        function resetFilters() {
    if (!hasActivated) return;
    activeFilters = { category: 'Semua', search: '', sortBy: 'default', condition: 'Semua' };
    hasActivated = false;
    displayLimit = 40;
    document.getElementById('search-input').value = '';
    document.getElementById('search-input-mobile').value = '';
    generateCategoryFilterOptions();
    applyFiltersAndSort();
}

        function showInfoBar(el) {
            if (!el) return;
            el.classList.remove('hidden', 'notif-enter');
            void el.offsetHeight;
            el.classList.add('notif-enter');
        }

        function applyFiltersAndSort() {
            var prompt = document.getElementById('search-prompt');
            var grid = document.getElementById('product-grid');
            var emptyState = document.getElementById('empty-state');

            if (!hasActivated) {
                setLayoutMode('initial');
                updateCondUI('Semua');
                updateSortUI('default');
                if (prompt) { prompt.classList.remove('hidden'); }
                if (grid) { grid.classList.add('hidden'); grid.innerHTML = ''; }
                if (emptyState) emptyState.classList.add('hidden');
                var infoBar = document.getElementById('product-info-bar');
                if (infoBar) { infoBar.classList.remove('notif-enter'); infoBar.classList.add('hidden'); }
                var resetBtn = document.getElementById('reset-filter-btn');
                if (resetBtn) { resetBtn.classList.add('opacity-50', 'cursor-not-allowed'); }
                return;
            }

            if (isInitialMode) setLayoutMode('active');
            updateCondUI(activeFilters.condition);
            updateSortUI(activeFilters.sortBy);
            if (prompt) hideSearchPrompt();
            if (grid) grid.classList.remove('hidden');
            var infoBar = document.getElementById('product-info-bar');
            if (infoBar) {
                infoBar.classList.remove('hidden');
            }
            var resetBtn = document.getElementById('reset-filter-btn');
            if (resetBtn) { resetBtn.classList.remove('opacity-50', 'cursor-not-allowed'); }
            filteredProducts = allProducts.filter(p => {
                const matchCategory = activeFilters.category === 'Semua' || p.category === activeFilters.category;
                
                const searchStr = (activeFilters.search || '').toLowerCase();
                const pName = (p.name || '').toLowerCase();
                const matchSearch = pName.includes(searchStr);
                
                const namaItem = (p.name || '').toUpperCase();
                const isBekas = namaItem.includes('2ND');
                let matchCondition = true;
                if (activeFilters.condition === 'Baru') matchCondition = !isBekas;
                if (activeFilters.condition === 'Bekas') matchCondition = isBekas;

                return matchCategory && matchSearch && matchCondition;
            });

            if (activeFilters.sortBy === 'low-high') {
                filteredProducts.sort((a, b) => (a.price || 0) - (b.price || 0));
            } else if (activeFilters.sortBy === 'high-low') {
                filteredProducts.sort((a, b) => (b.price || 0) - (a.price || 0));
            }

            displayLimit = 40;
            renderProductGrid();
        }

        function renderProductGrid() {
            const grid = document.getElementById('product-grid');
            const emptyState = document.getElementById('empty-state');
            var countEl = document.getElementById('product-count');

            if (!grid || grid.classList.contains('hidden')) return;

            grid.innerHTML = '';

            if (filteredProducts.length === 0) {
                emptyState.classList.remove('hidden');
                if (countEl) countEl.textContent = '0 produk';
            } else {
                emptyState.classList.add('hidden');
                const display = filteredProducts.slice(0, displayLimit);
                if (countEl) countEl.textContent = 'Menampilkan ' + display.length + ' dari ' + filteredProducts.length + ' produk';

                if (currentView === 'grid') {
                    grid.style.gridTemplateColumns = '';
                    grid.className = 'grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4';
                } else {
                    grid.style.gridTemplateColumns = '1fr';
                    grid.className = 'flex flex-col gap-3';
                }
                
                display.forEach(product => {
                    const el = currentView === 'grid'
                        ? createGridCard(product)
                        : createDetailCard(product);
                    grid.appendChild(el);
                });

                if (displayLimit < filteredProducts.length) {
                    var loadMoreWrap = document.createElement('div');
                    loadMoreWrap.className = currentView === 'grid' ? 'col-span-full text-center pt-2' : 'text-center pt-2';
                    loadMoreWrap.innerHTML = '<button onclick="loadMore()" class="px-5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium rounded-lg border border-white/10 transition-colors"><i class="fa-solid fa-chevron-down mr-1"></i> Muat Lainnya</button>';
                    grid.appendChild(loadMoreWrap);
                }
            }
        }

        function loadMore() {
            displayLimit += 40;
            renderProductGrid();
        }

        function createGridCard(product) {
            const card = document.createElement('div');
            card.className = "bg-slate-800/60 rounded-xl border border-white/5 overflow-hidden hover:border-astra-500/30 transition-all duration-300 flex flex-col group";
            const formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(product.price);

            const waNumber = "6281380686168";
            const waText = encodeURIComponent(`Halo Admin Royal Komputer,\nSaya ingin membeli produk ini:\n\n*${product.name}*\nHarga: ${formattedPrice}\n\nApakah stoknya masih ready?`);
            const waUrl = `https://wa.me/${waNumber}?text=${waText}`;

            const isBekas = (product.name || '').toUpperCase().includes('2ND');
            const badgeKondisi = isBekas 
                ? `<div class="absolute top-2 left-2 bg-orange-500/80 text-white text-[9px] font-semibold px-1.5 py-0.5 rounded">BEKAS</div>`
                : `<div class="absolute top-2 left-2 bg-sky-500/80 text-white text-[9px] font-semibold px-1.5 py-0.5 rounded">BARU</div>`;

            card.innerHTML = `
                <div class="relative overflow-hidden aspect-[4/3] bg-slate-800 cursor-pointer" onclick="openDetailModal('${product.id}')">
                    <img src="${product.image}" alt="${product.name}" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27400%27 height=%27300%27 viewBox=%270 0 400 300%27%3E%3Crect fill=%27%231e293b%27 width=%27400%27 height=%27300%27/%3E%3Ctext fill=%27%2364748b%27 font-family=%27sans-serif%27 font-size=%2714%27 x=%2750%25%27 y=%2750%25%27 text-anchor=%27middle%27 dy=%27.3em%27%3ETidak ada gambar%3C/text%3E%3C/svg%3E'" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    ${badgeKondisi}
                    <div class="absolute top-2 right-2 bg-slate-900/60 text-slate-300 text-[9px] font-medium px-1.5 py-0.5 rounded">${product.category}</div>
                </div>
                <div class="p-3 flex flex-col flex-grow">
                    <h3 class="font-medium text-white text-xs sm:text-sm leading-snug line-clamp-2 mb-2 cursor-pointer" onclick="openDetailModal('${product.id}')">${product.name}</h3>
                    <div class="mt-auto pt-2 border-t border-white/5 flex items-center justify-between gap-1.5">
                        <div class="text-sm sm:text-base font-bold text-white truncate min-w-0">${formattedPrice}</div>
                        <a href="${waUrl}" target="_blank" class="flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white text-[10px] font-medium px-2 py-1 rounded-lg transition-colors flex-shrink-0" title="Pesan via WhatsApp">
                            <i class="fa-brands fa-whatsapp text-xs"></i>
                        </a>
                    </div>
                </div>
            `;
            return card;
        }

        function createDetailCard(product) {
            const card = document.createElement('div');
            card.className = "bg-slate-800/60 rounded-xl border border-white/5 overflow-hidden hover:border-astra-500/30 transition-all duration-200 flex flex-col sm:flex-row group";
            const formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(product.price);

            const waNumber = "6281380686168";
            const waText = encodeURIComponent(`Halo Admin Royal Komputer,\nSaya ingin membeli produk ini:\n\n*${product.name}*\nHarga: ${formattedPrice}\n\nApakah stoknya masih ready?`);
            const waUrl = `https://wa.me/${waNumber}?text=${waText}`;

            const isBekas = (product.name || '').toUpperCase().includes('2ND');
            const badgeKondisi = isBekas
                ? `<span class="bg-orange-500/80 text-white text-[9px] font-semibold px-1.5 py-0.5 rounded">BEKAS</span>`
                : `<span class="bg-sky-500/80 text-white text-[9px] font-semibold px-1.5 py-0.5 rounded">BARU</span>`;

            card.innerHTML = `
                <div class="w-24 sm:w-32 md:w-36 shrink-0 bg-slate-800 cursor-pointer" onclick="openDetailModal('${product.id}')">
                    <img src="${product.image}" alt="${product.name}" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27200%27 height=%27150%27 viewBox=%270 0 200 150%27%3E%3Crect fill=%27%231e293b%27 width=%27200%27 height=%27150%27/%3E%3Ctext fill=%27%2364748b%27 font-family=%27sans-serif%27 font-size=%2712%27 x=%2750%25%27 y=%2750%25%27 text-anchor=%27middle%27 dy=%27.3em%27%3ETidak ada gambar%3C/text%3E%3C/svg%3E'" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                </div>
                <div class="p-3 sm:p-4 flex flex-col flex-grow min-w-0">
                    <div class="flex items-center gap-1.5 mb-1">
                        ${badgeKondisi}
                        <span class="text-[9px] font-medium text-slate-400 bg-slate-700/50 px-1.5 py-0.5 rounded">${product.category}</span>
                    </div>
                    <h3 class="font-medium text-white text-sm sm:text-base leading-snug cursor-pointer line-clamp-2 mb-1.5" onclick="openDetailModal('${product.id}')">${product.name}</h3>
                    <p class="text-xs text-slate-400 line-clamp-2 mb-2 hidden sm:block">${product.description || 'Tidak ada deskripsi rinci untuk produk ini.'}</p>
                    <div class="mt-auto flex items-center justify-between gap-2 pt-2 border-t border-white/5">
                        <div class="text-sm sm:text-base font-bold text-white">${formattedPrice}</div>
                        <div class="flex items-center gap-1.5">
                            <a href="${waUrl}" target="_blank" class="flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white text-[10px] font-medium px-2 py-1 rounded-lg transition-colors flex-shrink-0" title="Pesan via WhatsApp">
                                <i class="fa-brands fa-whatsapp text-xs"></i> <span class="hidden sm:inline">Pesan</span>
                            </a>
                        </div>
                    </div>
                </div>
            `;
            return card;
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        window.addEventListener('scroll', function() {
            var btn = document.getElementById('back-to-top');
            if (!btn) return;
            if (window.scrollY > 400) {
                btn.classList.remove('opacity-0', 'translate-y-4', 'pointer-events-none');
                btn.classList.add('opacity-100', 'translate-y-0', 'pointer-events-auto');
            } else {
                btn.classList.remove('opacity-100', 'translate-y-0', 'pointer-events-auto');
                btn.classList.add('opacity-0', 'translate-y-4', 'pointer-events-none');
            }
        });
    </script>

    <button id="back-to-top" onclick="scrollToTop()" class="fixed bottom-6 right-6 z-50 w-10 h-10 flex items-center justify-center rounded-full bg-astra-700 text-white shadow-lg transition-all duration-300 opacity-0 translate-y-4 pointer-events-none hover:bg-astra-600">
        <i class="fa-solid fa-chevron-up text-sm"></i>
    </button>
</body>
</html>