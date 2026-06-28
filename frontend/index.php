<?php
require_once 'config.php';
// Set zona waktu ke Waktu Indonesia Barat (WIB) / Kediri
date_default_timezone_set('Asia/Jakarta');

// Cek status manual "Tutup Sementara" dari Admin
$status_file = 'status_toko.txt';
$tutup_sementara = false;
if (file_exists($status_file)) {
    $tutup_sementara = trim(file_get_contents($status_file)) === 'tutup';
}

// Cek status berdasarkan jam Buka/Tutup otomatis
$hari_inggris = date('l');
$jam_sekarang = date('H:i');

$jam_buka = loadJamOperasional();

$is_open = false;
$hari_ini = $jam_buka[$hari_inggris];

$is_libur = !empty($hari_ini['libur']);

if (!$is_libur && $jam_sekarang >= $hari_ini['buka'] && $jam_sekarang <= $hari_ini['tutup']) {
    $is_open = true;
}

// Load jadwal tutup sementara (admin)
$schedules = loadSchedules();
$now_dt = date('Y-m-d H:i');
$has_schedule_now = false;
foreach ($schedules as $s) {
    if (!empty($s['start']) && !empty($s['end'])) {
        if ($now_dt >= $s['start'] && $now_dt <= $s['end']) { $has_schedule_now = true; break; }
    }
}
if ($has_schedule_now) { $tutup_sementara = true; }

// Tentukan jadwal berikutnya (untuk ditampilkan pada user)
$upcomingSchedule = null;
$future_schedules = array_filter($schedules, function($s) use ($now_dt){ return (!empty($s['end']) && $s['end'] >= $now_dt); });
usort($future_schedules, function($a,$b){ return strcmp($a['start'],$b['start']); });
if (!empty($future_schedules)) $upcomingSchedule = $future_schedules[0];

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
$product_info_text = 'Menampilkan {count} produk tersedia. Harga tidak selalu update, dan bisa berubah sewaktu-waktu. Hubungi kami di WhatsApp.';
if (file_exists($product_info_file)) {
    $info_data = json_decode(file_get_contents($product_info_file), true);
    if (!empty($info_data['text'])) $product_info_text = $info_data['text'];
}
// Ganti {count} dengan span yang akan diisi JavaScript
$product_info_html = str_replace('{count}', '<span id="product-count" class="font-bold text-slate-900">0</span>', $product_info_text);

// Menentukan jam buka selanjutnya jika sedang tutup
$next_buka = '';
$next_hari = '';
if (!$is_open) {
    if (!$is_libur && $jam_sekarang < $hari_ini['buka']) {
        // Belum buka hari ini
        $next_buka = $hari_ini['buka'];
        $next_hari = $hari_ini['indo'];
    } else {
        // Sudah tutup hari ini, cek besok (lewati hari libur)
        for ($i = 1; $i <= 7; $i++) {
            $check_day = date('l', strtotime("+$i day"));
            $h = $jam_buka[$check_day] ?? null;
            if ($h && !empty($h['buka']) && empty($h['libur'])) {
                $next_buka = $h['buka'];
                $next_hari = $h['indo'];
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Royal Komputer - Marketplace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
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
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #0254A3; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #0b3c70; }

        @keyframes slideRightFade {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(80px); }
        }
        @keyframes slideDownFade {
            from { opacity: 0; transform: translateY(-100%); }
            to { opacity: 1; transform: translateY(0); }
        }
        .banner-hiding {
            animation: slideRightFade 0.4s ease forwards;
        }
        .notif-enter {
            animation: slideDownFade 0.4s ease forwards;
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
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col font-sans">

    <!-- Safelist untuk Tailwind CDN JIT (class dipakai oleh JavaScript) -->
    <div class="hidden" aria-hidden="true">
        <span class="bg-astra-700 text-white font-semibold shadow-sm"></span>
        <span class="bg-astra-900/40 bg-white/20"></span>
        <span class="text-slate-600 hover:bg-slate-100"></span>
        <span class="bg-slate-100 text-slate-500"></span>
    </div>

    <!-- Navbar -->
<nav class="bg-astra-950 text-white sticky top-0 z-50 shadow-lg shadow-black/20">
    <div class="container mx-auto px-4 py-3 flex items-center justify-between gap-4">
        
        <!-- Logo -->
        <a href="#" class="flex items-center gap-2 flex-shrink-0">
            <img src="logo/logo.webp" alt="Logo" class="h-8 md:h-10 w-auto">
            <span class="font-bold text-sm md:text-xl tracking-wider text-white">ROYAL<span class="text-astra-400"> KOMPUTER</span></span>
        </a>
        
        <!-- Search Bar (tengah, hanya desktop) -->
        <div class="hidden md:flex flex-grow max-w-md">
            <div class="relative flex-grow">
                <input type="text" id="search-input" onkeydown="if(event.key==='Enter') triggerSearch('desktop')" placeholder="Cari hardware..." class="w-full bg-slate-900 border border-slate-700 text-slate-200 placeholder-slate-400 rounded-lg px-4 py-2 pl-10 focus:outline-none focus:border-astra-400 transition-all text-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-sm"></i>
            </div>
            <button onclick="triggerSearch('desktop')" class="ml-2 bg-astra-600 hover:bg-astra-700 text-white px-3 py-2 rounded-lg transition-colors text-sm flex items-center gap-1 flex-shrink-0">
                <i class="fa-solid fa-magnifying-glass"></i> Cari
            </button>
        </div>
        
        <!-- Sosmed Links (desktop) -->
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
        <button onclick="toggleNavMenu()" class="md:hidden flex items-center justify-center text-slate-300 hover:text-white focus:outline-none h-9 w-9 bg-slate-900 border border-slate-700 rounded-lg flex-shrink-0">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>
    </div>

    <!-- Search Bar (mobile, di bawah row utama) -->
    <div class="md:hidden px-4 pb-3">
        <div class="flex gap-2">
            <div class="relative flex-grow">
                <input type="text" id="search-input-mobile" onkeydown="if(event.key==='Enter') triggerSearch('mobile')" placeholder="Cari hardware..." class="w-full bg-slate-900 border border-slate-700 text-slate-200 placeholder-slate-400 rounded-lg px-4 py-2 pl-10 focus:outline-none focus:border-astra-400 transition-all text-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-sm"></i>
            </div>
            <button onclick="triggerSearch('mobile')" class="bg-astra-600 hover:bg-astra-700 text-white px-3 py-2 rounded-lg transition-colors text-sm flex items-center gap-1 flex-shrink-0">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </div>
    </div>

    <!-- Dropdown Menu Sosmed (mobile) -->
    <div id="nav-sosmed-menu" class="hidden md:hidden border-t border-slate-800">
        <div class="container mx-auto px-4 py-3 flex flex-col gap-1">
            <span class="text-xs text-slate-400 font-semibold mb-1">Ikuti Kami:</span>
            <a href="https://www.facebook.com/royall.komp" target="_blank" class="flex items-center gap-3 text-slate-300 hover:text-blue-500 transition-colors py-2 px-2 rounded-lg hover:bg-slate-800">
                <i class="fa-brands fa-facebook text-lg w-5 text-blue-500"></i>
                <span class="text-sm font-medium">Facebook</span>
            </a>
            <a href="https://www.facebook.com/royalkomputerkediri?locale=id_ID" target="_blank" class="flex items-center gap-3 text-slate-300 hover:text-sky-400 transition-colors py-2 px-2 rounded-lg hover:bg-slate-800">
                <i class="fa-solid fa-flag text-lg w-5 text-sky-500"></i>
                <span class="text-sm font-medium">Facebook Pages</span>
            </a>
            <a href="https://www.instagram.com/royalkomputerkediri/" target="_blank" class="flex items-center gap-3 text-slate-300 hover:text-pink-500 transition-colors py-2 px-2 rounded-lg hover:bg-slate-800">
                <i class="fa-brands fa-instagram text-lg w-5 text-pink-500"></i>
                <span class="text-sm font-medium">Instagram</span>
            </a>
            <a href="https://www.tiktok.com/@royalkomputerkediri" target="_blank" class="flex items-center gap-3 text-slate-300 hover:text-white transition-colors py-2 px-2 rounded-lg hover:bg-slate-800">
                <i class="fa-brands fa-tiktok text-lg w-5 text-white"></i>
                <span class="text-sm font-medium">TikTok</span>
            </a>
            <a href="https://wa.me/6281380686168" target="_blank" class="flex items-center gap-3 text-slate-300 hover:text-green-500 transition-colors py-2 px-2 rounded-lg hover:bg-slate-800">
                <i class="fa-brands fa-whatsapp text-lg w-5 text-green-500"></i>
                <span class="text-sm font-medium">WhatsApp Admin</span>
            </a>
            <a href="https://www.youtube.com/@royalkomputerkediri" target="_blank" class="flex items-center gap-3 text-slate-300 hover:text-red-500 transition-colors py-2 px-2 rounded-lg hover:bg-slate-800">
                <i class="fa-brands fa-youtube text-lg w-5 text-red-500"></i>
                <span class="text-sm font-medium">YouTube</span>
            </a>
        </div>
    </div>
</nav>

    <header class="bg-gradient-to-r from-astra-950 via-slate-900 to-astra-900 text-white py-12 px-4 shadow-inner relative overflow-hidden">
        <div class="container mx-auto text-center relative z-10">
            
            <?php if ($tutup_sementara): ?>
                <span class="bg-red-500/20 border border-red-500/50 text-red-300 text-xs px-3 py-1.5 rounded-full uppercase font-bold mb-4 inline-flex items-center gap-2 shadow-lg"><i class="fa-solid fa-store-slash"></i> Toko Tutup Sementara</span>
            <?php elseif ($is_open): ?>
                <span class="bg-green-500/20 border border-green-500/50 text-green-300 text-xs px-3 py-1.5 rounded-full uppercase font-bold mb-4 inline-flex items-center gap-2"><i class="fa-solid fa-store"></i> Buka Sekarang (Tutup <?= str_replace(':', '.', $hari_ini['tutup']) ?> WIB)</span>
            <?php else: ?>
                <span class="bg-slate-700 border border-slate-500 text-slate-300 text-xs px-3 py-1.5 rounded-full uppercase font-bold mb-4 inline-flex items-center gap-2"><i class="fa-solid fa-moon"></i> Toko Tutup (Buka <?= $next_hari ?> <?= str_replace(':', '.', $next_buka) ?> WIB)</span>
            <?php endif; ?>

            <?php if (!empty($upcomingSchedule)): ?>
                <div class="mt-3">
                    <span class="bg-yellow-50 border border-yellow-100 text-yellow-700 text-xs px-3 py-1 rounded inline-flex items-center gap-2">
                        <i class="fa-solid fa-calendar-days"></i>
                        Jadwal: Tutup <?= date('d M Y H:i', strtotime($upcomingSchedule['start'])) ?> sampai <?= date('d M Y H:i', strtotime($upcomingSchedule['end'])) ?> <?php echo htmlspecialchars($upcomingSchedule['note'] ?? ''); ?>
                    </span>
                </div>
            <?php endif; ?>

            <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight mb-4"><?php echo htmlspecialchars($heading_prefix); ?> <span class="text-transparent bg-clip-text bg-gradient-to-r from-astra-400 to-sky-300"><?php echo htmlspecialchars($heading_brand); ?></span></h1>
            <p class="text-slate-300 max-w-xl mx-auto text-sm md:text-base font-light"><?php echo htmlspecialchars($tagline); ?></p>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 flex-grow grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <aside class="lg:col-span-1 bg-white rounded-xl border border-slate-200 shadow-sm self-start overflow-hidden">
            
            <button onclick="toggleFilterMenu()" class="w-full p-4 flex items-center justify-between lg:cursor-default focus:outline-none bg-slate-50 lg:bg-white border-b border-slate-100 lg:border-none">
                <h3 class="font-bold text-slate-900 flex items-center gap-2"><i class="fa-solid fa-sliders text-astra-700"></i> Filter & Urutkan</h3>
                <i id="filter-icon" class="fa-solid fa-chevron-down text-slate-500 transition-transform duration-300 lg:hidden"></i>
            </button>

            <div id="filter-content" class="hidden lg:block p-4 pt-4 lg:p-6 lg:pt-0">
                <div class="flex justify-end mb-5 lg:pb-3 lg:border-b lg:border-slate-100">
                    <button onclick="resetFilters()" class="text-xs text-astra-600 font-semibold bg-astra-50 hover:bg-astra-100 lg:bg-transparent lg:hover:bg-transparent lg:p-0 px-3 py-1.5 rounded-lg transition-colors">
                        <i class="fa-solid fa-arrow-rotate-right mr-1"></i> Reset Filter
                    </button>
                </div>

                <div class="mb-6">
                    <button type="button" onclick="toggleCategoryPanel()" class="w-full flex items-center justify-between text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 focus:outline-none">
                        <span>Kategori</span>
                        <i id="category-toggle-icon" class="fa-solid fa-chevron-down text-slate-400 transition-transform duration-200"></i>
                    </button>
                    <div id="category-panel" class="space-y-1">
                        <div id="category-list" class="space-y-1"></div>
                    </div>
                </div>
                
                <div class="mb-6 border-t border-slate-100 pt-5">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Kondisi</label>
                    <div class="relative">
                        <select id="condition-select" onchange="handleCondition(this.value)" class="w-full appearance-none bg-white border border-slate-200 text-slate-700 text-sm rounded-2xl p-3 pr-10 outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500 cursor-pointer transition-all shadow-sm hover:shadow-md">
                            <option value="Semua">Semua Kondisi</option>
                            <option value="Baru">Baru</option>
                            <option value="Bekas">Bekas (2ND)</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Urutkan</label>
                    <div class="relative">
                        <select id="sort-select" onchange="handleSort(this.value)" class="w-full appearance-none bg-white border border-slate-200 text-slate-700 text-sm rounded-2xl p-3 pr-10 outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500 cursor-pointer transition-all shadow-sm hover:shadow-md">
                            <option value="default">Rekomendasi Teratas</option>
                            <option value="low-high">Harga: Rendah ke Tinggi</option>
                            <option value="high-low">Harga: Tinggi ke Rendah</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs"></i>
                    </div>
                </div>
            </div>

        </aside>

        <section class="lg:col-span-3 flex flex-col gap-6">
            <div id="product-info-bar" class="flex items-center justify-between bg-white p-4 rounded-xl border border-slate-200 shadow-sm hidden">
                <div class="text-sm text-slate-600"><?php echo $product_info_html; ?></div>
                <div class="flex items-center gap-1 bg-slate-100 rounded-lg p-0.5">
                    <button id="view-grid-btn" onclick="setView('grid')" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-semibold transition-all" title="Tampilan Grid">
                        <i class="fa-solid fa-grid-2"></i>
                    </button>
                    <button id="view-detail-btn" onclick="setView('detail')" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-semibold transition-all" title="Tampilan Detail">
                        <i class="fa-solid fa-list"></i>
                    </button>
                </div>
            </div>

            <div id="search-prompt" class="bg-gradient-to-r from-astra-50 to-blue-50 border border-astra-200 rounded-xl px-3 py-2 flex items-center gap-2 shadow-sm">
                <div class="flex-shrink-0 w-6 h-6 bg-astra-100 rounded-full flex items-center justify-center">
                    <i class="fa-solid fa-magnifying-glass text-xs text-astra-600"></i>
                </div>
                <p class="text-xs text-slate-500 flex-1">Gunakan pencarian atau pilih kategori untuk menampilkan produk.</p>
            </div>

            <div id="banner-playlists" class="flex flex-col gap-4 mb-6"></div>
            <div id="loading-spinner" class="py-20 flex flex-col items-center justify-center gap-3">
                <i class="fa-solid fa-spinner text-4xl text-astra-700 animate-spin"></i>
                <p class="text-slate-500 text-sm">Sedang memuat data produk...</p>
            </div>

            <div id="empty-state" class="hidden bg-white rounded-xl border border-slate-200 p-12 text-center">
                <i class="fa-solid fa-box-open text-5xl text-slate-300 mb-4"></i>
                <h4 class="text-lg font-bold text-slate-800 mb-1">Produk Tidak Ditemukan</h4>
                <p class="text-slate-500 text-sm">Tidak ada produk yang sesuai dengan kriteria pencarian Anda.</p>
            </div>

            <div id="product-grid" class="hidden grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-6"></div>
        </section>
    </main>

    <footer class="bg-slate-950 text-slate-400 text-xs border-t border-slate-800 mt-12 py-12">
        <div class="container mx-auto px-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 items-start">
            
            <div class="flex flex-col gap-3 items-center md:items-start">
                <img src="logo/logo.webp" alt="Royal Komputer Logo" class="h-12 w-auto object-contain rounded mb-1">
                <p class="font-bold text-slate-200 text-sm tracking-wide">ROYAL KOMPUTER KEDIRI</p>
                <p class="text-slate-400 leading-relaxed text-center md:text-left text-xs">
                    <i class="fa-solid fa-location-dot text-red-500 mr-1"></i> 
                    Gg. Masjid No.22A, Jamsaren, Kec. Pesantren, Kota Kediri, Jawa Timur 64132
                </p>
            </div>
            
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

            <div class="flex flex-col gap-3 items-center md:items-start w-full">
                <p class="font-bold text-slate-200 text-sm tracking-wide border-b border-slate-800 pb-1 w-full text-center md:text-left">JAM BUKA TOKO</p>
                <div class="w-full text-[11px] text-slate-400 max-w-[200px] mx-auto md:mx-0">
                    <?php
                    foreach ($jam_buka as $hari => $waktu) {
                        $is_today = ($hari == $hari_inggris);
                        $highlight = $is_today ? 'text-astra-400 font-bold bg-slate-900 rounded border border-slate-800' : '';
                        $is_libur_item = !empty($waktu['libur']);
                        $buka_tutup = $is_libur_item ? '<span class="text-red-400 font-bold">Libur</span>' : str_replace(':', '.', $waktu['buka']) . '–' . str_replace(':', '.', $waktu['tutup']);
                        
                        echo "<div class='flex justify-between py-1 px-2 mb-1 {$highlight}'>";
                        echo "<span>{$waktu['indo']}</span>";
                        echo "<span>{$buka_tutup}</span>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>
            
            <div class="flex flex-col gap-1 items-center lg:items-end lg:text-right h-full justify-center lg:justify-start lg:pt-6 w-full mt-4 lg:mt-0">
                <p class="font-semibold text-slate-600 tracking-wider">ROYAL MARKETPLACE v2.2</p>
                <p class="text-slate-500">&copy; <?php echo date("Y"); ?> Hak Cipta Dilindungi.</p>
            </div>
            
        </div>
    </footer>

    <!-- MODAL DETAIL PRODUK -->
    <div id="detail-modal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col md:flex-row relative">
            <button onclick="closeDetailModal()" class="absolute top-4 right-4 z-10 w-8 h-8 bg-black/20 hover:bg-black/40 text-white rounded-full flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark"></i></button>
            
            <!-- Kiri: Galeri Foto -->
            <div class="w-full md:w-1/2 bg-slate-100 relative group min-h-[300px] flex items-center justify-center">
                <img id="detail-image" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E" alt="Detail" class="w-full h-full object-contain max-h-[500px]" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div id="detail-image-fallback" class="hidden absolute inset-0 flex-col items-center justify-center text-slate-400">
                    <i class="fa-solid fa-image text-5xl mb-2"></i>
                    <span class="text-sm">Gambar tidak tersedia</span>
                </div>
                
                <!-- Navigasi Carousel -->
                <button id="btn-prev-img" onclick="changeImage(-1)" class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/80 hover:bg-white text-slate-800 rounded-full flex items-center justify-center shadow-lg hidden"><i class="fa-solid fa-chevron-left"></i></button>
                <button id="btn-next-img" onclick="changeImage(1)" class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/80 hover:bg-white text-slate-800 rounded-full flex items-center justify-center shadow-lg hidden"><i class="fa-solid fa-chevron-right"></i></button>
                
                <div id="img-indicators" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2"></div>
            </div>

            <!-- Kanan: Info Produk -->
            <div class="w-full md:w-1/2 p-6 md:p-8 flex flex-col max-h-[50vh] md:max-h-full overflow-y-auto">
                <div id="detail-badge" class="mb-3"></div>
                <h2 id="detail-name" class="text-2xl font-extrabold text-slate-800 mb-2"></h2>
                <div class="text-3xl font-black text-astra-700 mb-6" id="detail-price"></div>
                
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 mb-6 flex-grow">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Deskripsi & Spesifikasi</h4>
                    <p id="detail-desc" class="text-sm text-slate-600 whitespace-pre-line leading-relaxed"></p>
                </div>
                
                <div class="mt-auto pt-4 border-t border-slate-100">
                    <div class="flex items-center justify-between">
                        <a id="detail-wa-btn" href="#" target="_blank" class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 px-4 rounded-xl transition-colors shadow-lg text-sm" title="Pesan via WhatsApp">
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
        let bannerVisible = false;
        let bannerPlaylistCount = 0;
        let currentView = localStorage.getItem('viewMode') || 'grid';
        
        let currentDetailImages = [];
        let currentImageIndex = 0;

        function setView(mode) {
            var grid = document.getElementById('product-grid');
            if (grid && grid.classList.contains('hidden')) return;
            currentView = mode;
            localStorage.setItem('viewMode', mode);
            document.getElementById('view-grid-btn').className = mode === 'grid'
                ? 'flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-semibold transition-all bg-white text-astra-700 shadow-sm'
                : 'flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-semibold transition-all text-slate-500 hover:text-slate-700';
            document.getElementById('view-detail-btn').className = mode === 'detail'
                ? 'flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-semibold transition-all bg-white text-astra-700 shadow-sm'
                : 'flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-semibold transition-all text-slate-500 hover:text-slate-700';
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
            document.getElementById('detail-badge').innerHTML = isBekas 
                ? `<span class="bg-orange-100 text-orange-700 text-xs font-bold px-2.5 py-1 rounded-md border border-orange-200">KONDISI: BEKAS</span>`
                : `<span class="bg-sky-100 text-sky-700 text-xs font-bold px-2.5 py-1 rounded-md border border-sky-200">KONDISI: BARU</span>`;

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
                    dot.className = `w-2 h-2 rounded-full transition-all ${i === currentImageIndex ? 'bg-astra-500 w-4' : 'bg-slate-300 hover:bg-slate-400'}`;
                    indicators.appendChild(dot);
                }
            }
        }
        
        window.addEventListener('DOMContentLoaded', () => {
            initPage();
        });
        
        function handleCondition(val) {
            activeFilters.condition = val;
            var grid = document.getElementById('product-grid');
            if (grid && grid.classList.contains('hidden')) return;
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
            // Step 1: Muat banner dulu (ringan, cepat)
            loadBanners().then(() => {
                // Step 2: Baru muat produk (lebih berat)
                fetch('api_produk.php')
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) throw new Error(data.error);
                        allProducts = data;
                        generateCategoryFilterOptions();
                        initViewToggle();
                    })
                    .catch(err => {
                        console.error(err);
                        document.getElementById('empty-state').classList.remove('hidden');
                    })
                    .finally(() => showLoading(false));
            });
        }

        function initViewToggle() {
            document.getElementById('view-grid-btn').className = currentView === 'grid'
                ? 'flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-semibold transition-all bg-white text-astra-700 shadow-sm'
                : 'flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-semibold transition-all text-slate-500 hover:text-slate-700';
            document.getElementById('view-detail-btn').className = currentView === 'detail'
                ? 'flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-semibold transition-all bg-white text-astra-700 shadow-sm'
                : 'flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-semibold transition-all text-slate-500 hover:text-slate-700';
        }

        function loadBanners() {
            return new Promise(resolve => {
            const container = document.getElementById('banner-playlists');
            fetch('api_banner.php')
                .then(r => r.json())
                .then(playlists => {
                    container.innerHTML = '';
                    if (!playlists || playlists.length === 0) { bannerVisible = false; bannerPlaylistCount = 0; resolve(); return; }
                    const active = playlists.filter(p => p.active !== false);
                    if (active.length === 0) { bannerVisible = false; bannerPlaylistCount = 0; resolve(); return; }
                    bannerVisible = true;
                    bannerPlaylistCount = active.length;

                    active.forEach((pl, plIdx) => {
                        const photos = pl.photos || [];
                        if (photos.length === 0) return;
                        const plId = 'pl-' + plIdx;
                        const interval = pl.interval || 5000;
                        const aspect = pl.aspect || '16/9';
                        const [aw, ah] = aspect.split('/').map(Number);
                        const padPct = (ah / aw * 100) + '%';
                        const hasMultiple = photos.length > 1;

                        let html = '<div class="relative w-full rounded-2xl overflow-hidden bg-slate-100 shadow-sm" style="padding-bottom:' + padPct + '">' +
                            '<div class="pl-carousel absolute inset-0 overflow-hidden rounded-2xl" data-pl="' + plId + '">' +
                                '<div class="pl-track flex transition-transform duration-500 ease-in-out w-full h-full" data-pl="' + plId + '">' +
                                    photos.map(p =>
                                        '<div class="pl-slide min-w-full w-full flex-shrink-0 h-full" data-pl="' + plId + '">' +
                                            (p.link ? '<a href="' + escAttr(p.link) + '" target="_blank" rel="noopener" class="block h-full">' : '') +
                                                '<img src="uploads/banners/' + escAttr(p.image) + '" alt="' + escAttr(p.alt || pl.name || 'Banner') + '" class="w-full h-full object-cover">' +
                                            (p.link ? '</a>' : '') +
                                        '</div>'
                                    ).join('') +
                                '</div>' +
                                (hasMultiple ? 
                                    '<button class="pl-prev absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 bg-black/30 hover:bg-black/50 text-white rounded-full flex items-center justify-center transition-colors z-10 backdrop-blur-sm" data-pl="' + plId + '">' +
                                        '<i class="fa-solid fa-chevron-left text-sm"></i>' +
                                    '</button>' +
                                    '<button class="pl-next absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 bg-black/30 hover:bg-black/50 text-white rounded-full flex items-center justify-center transition-colors z-10 backdrop-blur-sm" data-pl="' + plId + '">' +
                                        '<i class="fa-solid fa-chevron-right text-sm"></i>' +
                                    '</button>' +
                                    '<div class="pl-dots absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-2 z-10" data-pl="' + plId + '">' +
                                        photos.map((_, i) => '<button class="pl-dot w-2 h-2 rounded-full transition-all ' + (i === 0 ? 'bg-white w-4' : 'bg-white/50 hover:bg-white/80') + '" data-pl="' + plId + '" data-index="' + i + '"></button>').join('') +
                                    '</div>' : '') +
                            '</div>' +
                        '</div>';

                        container.innerHTML += html;
                    });

                    // Init carousels for playlists with multiple photos
                    document.querySelectorAll('.pl-carousel').forEach(carousel => {
                        const plId = carousel.dataset.pl;
                        const track = carousel.querySelector('.pl-track');
                        if (!track || track.children.length <= 1) return;
                        const total = track.children.length;

                        let current = 0;
                        const interval = active[parseInt(plId.split('-')[1])]?.interval || 5000;

                        function goTo(index) {
                            if (index < 0) index = total - 1;
                            if (index >= total) index = 0;
                            current = index;
                            track.style.transform = 'translateX(-' + (current * 100) + '%)';
                            carousel.querySelectorAll('.pl-dot').forEach((dot, i) => {
                                dot.className = 'pl-dot w-2 h-2 rounded-full transition-all ' + (i === current ? 'bg-white w-4' : 'bg-white/50 hover:bg-white/80');
                            });
                        }

                        const nextBtn = carousel.querySelector('.pl-next');
                        const prevBtn = carousel.querySelector('.pl-prev');
                        if (nextBtn) nextBtn.addEventListener('click', () => goTo(current + 1));
                        if (prevBtn) prevBtn.addEventListener('click', () => goTo(current - 1));
                        carousel.querySelectorAll('.pl-dot').forEach(d => d.addEventListener('click', () => goTo(parseInt(d.dataset.index) || 0)));

                        let autoInterval = setInterval(() => goTo(current + 1), interval);
                        carousel.addEventListener('mouseenter', () => clearInterval(autoInterval));
                        carousel.addEventListener('mouseleave', () => { autoInterval = setInterval(() => goTo(current + 1), interval); });
                    });
                })
                .catch(() => { bannerVisible = false; })
                .finally(() => resolve());
            });
        }

        function escAttr(str) {
            return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function generateCategoryFilterOptions() {
            const categories = ['Semua', ...new Set(allProducts.map(p => p.category))];
            const container = document.getElementById('category-list');
            container.innerHTML = '';
            
            categories.forEach(cat => {
                const isSelected = activeFilters.category === cat;
                const button = document.createElement('button');
                button.className = `w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center justify-between ${
                    isSelected ? 'bg-astra-700 text-white font-semibold shadow-sm' : 'text-slate-600 hover:bg-slate-100'
                }`;
                const count = cat === 'Semua' ? allProducts.length : allProducts.filter(p => p.category === cat).length;
                button.innerHTML = `<span>${cat}</span><span class="${isSelected ? 'bg-astra-900/40' : 'bg-slate-100 text-slate-500'} text-xs px-2 py-0.5 rounded-full">${count}</span>`;
                button.onclick = () => selectCategory(cat);
                container.appendChild(button);
            });
        }

        function hideBanner() {
            var container = document.getElementById('banner-playlists');
            if (!container || container.classList.contains('hidden') || container.classList.contains('banner-hiding')) return;
            container.classList.add('banner-hiding');
            setTimeout(function() { container.classList.add('hidden'); container.classList.remove('banner-hiding'); }, 400);
        }

        function hideSearchPrompt() {
            var prompt = document.getElementById('search-prompt');
            if (!prompt || prompt.classList.contains('hidden') || prompt.classList.contains('banner-hiding')) return;
            prompt.classList.add('banner-hiding');
            setTimeout(function() { prompt.classList.add('hidden'); prompt.classList.remove('banner-hiding'); }, 400);
        }

        function selectCategory(cat) {
            activeFilters.category = cat;
            hideBanner();
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
    hideBanner();
    hasActivated = true;
    applyFiltersAndSort();
}

        function handleSort(val) {
            activeFilters.sortBy = val;
            var grid = document.getElementById('product-grid');
            if (grid && grid.classList.contains('hidden')) return;
            applyFiltersAndSort();
        }

        function resetFilters() {
    activeFilters = { category: 'Semua', search: '', sortBy: 'default', condition: 'Semua' };
    hasActivated = false;
    document.getElementById('search-input').value = '';
    document.getElementById('search-input-mobile').value = '';
    document.getElementById('sort-select').value = 'default';
    document.getElementById('condition-select').value = 'Semua';
    generateCategoryFilterOptions();
    var banner = document.getElementById('banner-playlists');
    if (banner && bannerVisible) banner.classList.remove('hidden');
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
            var productCount = document.getElementById('product-count');

            if (!hasActivated) {
                if (prompt) { prompt.classList.remove('hidden'); prompt.classList.remove('banner-hiding'); }
                if (grid) { grid.classList.add('hidden'); grid.innerHTML = ''; }
                if (emptyState) emptyState.classList.add('hidden');
                if (productCount) productCount.innerText = '0';
                var infoBar = document.getElementById('product-info-bar');
                if (infoBar) { infoBar.classList.remove('notif-enter'); infoBar.classList.add('hidden'); }
                return;
            }

            if (prompt) hideSearchPrompt();
            if (grid) grid.classList.remove('hidden');
            var infoBar = document.getElementById('product-info-bar');
            var bannerPl = document.getElementById('banner-playlists');
            if (infoBar) {
                if (bannerPl && bannerPl.classList.contains('banner-hiding')) {
                    setTimeout(function() { showInfoBar(infoBar); }, 400);
                } else {
                    showInfoBar(infoBar);
                }
            }
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

            renderProductGrid();
        }

        function renderProductGrid() {
            const grid = document.getElementById('product-grid');
            const emptyState = document.getElementById('empty-state');
            const productCount = document.getElementById('product-count');

            if (!grid || grid.classList.contains('hidden')) return;

            grid.innerHTML = '';
            if (productCount) productCount.innerText = filteredProducts.length;

            if (filteredProducts.length === 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');

                if (currentView === 'grid') {
                    grid.style.gridTemplateColumns = '';
                    grid.className = 'grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-6';
                } else {
                    grid.style.gridTemplateColumns = '1fr';
                    grid.className = 'flex flex-col gap-3 sm:gap-4';
                }
                
                filteredProducts.forEach(product => {
                    const el = currentView === 'grid'
                        ? createGridCard(product)
                        : createDetailCard(product);
                    grid.appendChild(el);
                });
            }
        }

        function createGridCard(product) {
            const card = document.createElement('div');
            card.className = "bg-white rounded-lg sm:rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col group";
            const formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(product.price);

            const waNumber = "6281380686168";
            const waText = encodeURIComponent(`Halo Admin Royal Komputer,\nSaya ingin membeli produk ini:\n\n*${product.name}*\nHarga: ${formattedPrice}\n\nApakah stoknya masih ready?`);
            const waUrl = `https://wa.me/${waNumber}?text=${waText}`;

            const isBekas = (product.name || '').toUpperCase().includes('2ND');
            const badgeKondisi = isBekas 
                ? `<div class="absolute top-1.5 left-1.5 sm:top-3 sm:left-3 bg-orange-500/90 backdrop-blur-sm text-white text-[8px] sm:text-[10px] font-bold px-1.5 py-0.5 sm:px-2.5 sm:py-1 rounded sm:rounded-lg shadow-sm border border-orange-400">BEKAS</div>`
                : `<div class="absolute top-1.5 left-1.5 sm:top-3 sm:left-3 bg-sky-500/90 backdrop-blur-sm text-white text-[8px] sm:text-[10px] font-bold px-1.5 py-0.5 sm:px-2.5 sm:py-1 rounded sm:rounded-lg shadow-sm border border-sky-400">BARU</div>`;

            card.innerHTML = `
                <div class="relative overflow-hidden aspect-[4/3] bg-slate-100 cursor-pointer" onclick="openDetailModal('${product.id}')">
                    <img src="${product.image}" alt="${product.name}" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27400%27 height=%27300%27 viewBox=%270 0 400 300%27%3E%3Crect fill=%27%23f1f5f9%27 width=%27400%27 height=%27300%27/%3E%3Ctext fill=%27%2394a3b8%27 font-family=%27sans-serif%27 font-size=%2714%27 x=%2750%25%27 y=%2750%25%27 text-anchor=%27middle%27 dy=%27.3em%27%3ETidak ada gambar%3C/text%3E%3C/svg%3E'" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    ${badgeKondisi}
                    <div class="absolute top-1.5 right-1.5 sm:top-3 sm:right-3 bg-white/90 backdrop-blur-sm text-astra-700 text-[8px] sm:text-[10px] font-bold px-1.5 py-0.5 sm:px-2 sm:py-1 rounded sm:rounded-lg shadow-sm">
                        ${product.category}
                    </div>
                </div>
                <div class="p-2.5 sm:p-5 flex flex-col flex-grow">
                    <h3 class="font-bold text-slate-800 text-xs sm:text-lg leading-tight line-clamp-2 sm:mb-2 cursor-pointer" onclick="openDetailModal('${product.id}')">${product.name}</h3>
                    <div class="mt-auto pt-2 sm:pt-4 border-t border-slate-100 flex items-center justify-between gap-1.5">
                        <div class="text-sm sm:text-xl font-extrabold text-astra-700 truncate min-w-0">${formattedPrice}</div>
                        <a href="${waUrl}" target="_blank" class="flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white text-[10px] sm:text-xs font-bold px-2 py-1 sm:px-3 sm:py-2 rounded sm:rounded-lg transition-colors shadow-sm flex-shrink-0" title="Pesan via WhatsApp">
                            <i class="fa-brands fa-whatsapp text-xs sm:text-sm"></i>
                        </a>
                    </div>
                </div>
            `;
            return card;
        }

        function createDetailCard(product) {
            const card = document.createElement('div');
            card.className = "bg-white rounded-lg sm:rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300 flex flex-col sm:flex-row group";
            const formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(product.price);

            const waNumber = "6281380686168";
            const waText = encodeURIComponent(`Halo Admin Royal Komputer,\nSaya ingin membeli produk ini:\n\n*${product.name}*\nHarga: ${formattedPrice}\n\nApakah stoknya masih ready?`);
            const waUrl = `https://wa.me/${waNumber}?text=${waText}`;

            const isBekas = (product.name || '').toUpperCase().includes('2ND');
            const badgeKondisi = isBekas
                ? `<span class="bg-orange-100 text-orange-700 text-[9px] sm:text-[10px] font-bold px-1.5 py-0.5 rounded border border-orange-200">BEKAS</span>`
                : `<span class="bg-sky-100 text-sky-700 text-[9px] sm:text-[10px] font-bold px-1.5 py-0.5 rounded border border-sky-200">BARU</span>`;

            card.innerHTML = `
                <div class="w-24 sm:w-32 md:w-48 shrink-0 bg-slate-100 cursor-pointer" onclick="openDetailModal('${product.id}')">
                    <img src="${product.image}" alt="${product.name}" loading="lazy" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27200%27 height=%27150%27 viewBox=%270 0 200 150%27%3E%3Crect fill=%27%23f1f5f9%27 width=%27200%27 height=%27150%27/%3E%3Ctext fill=%27%2394a3b8%27 font-family=%27sans-serif%27 font-size=%2712%27 x=%2750%25%27 y=%2750%25%27 text-anchor=%27middle%27 dy=%27.3em%27%3ETidak ada gambar%3C/text%3E%3C/svg%3E'" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                </div>
                <div class="p-3 sm:p-4 md:p-5 flex flex-col flex-grow min-w-0">
                    <div class="flex items-center gap-1.5 sm:gap-2 mb-1">
                        ${badgeKondisi}
                        <span class="text-[9px] sm:text-[10px] font-semibold text-astra-600 bg-astra-50 px-1.5 sm:px-2 py-0.5 rounded">${product.category}</span>
                    </div>
                    <h3 class="font-bold text-slate-800 text-sm sm:text-base md:text-lg leading-tight cursor-pointer line-clamp-2 sm:mb-1.5" onclick="openDetailModal('${product.id}')">${product.name}</h3>
                    <p class="text-[11px] sm:text-xs text-slate-500 line-clamp-1 sm:line-clamp-2 mb-2 sm:mb-3 hidden sm:block">${product.description || 'Tidak ada deskripsi rinci untuk produk ini.'}</p>
                    <div class="mt-auto flex items-center justify-between gap-2 pt-2 sm:pt-3 border-t border-slate-100">
                        <div class="text-sm sm:text-lg md:text-xl font-extrabold text-astra-700">${formattedPrice}</div>
                        <div class="flex items-center gap-1.5 sm:gap-2">
                            <a href="${waUrl}" target="_blank" class="flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white text-[10px] sm:text-xs font-bold px-2 py-1 sm:px-3.5 sm:py-2 rounded sm:rounded-lg transition-colors shadow-sm flex-shrink-0" title="Pesan via WhatsApp">
                                <i class="fa-brands fa-whatsapp text-xs sm:text-sm"></i> <span class="hidden sm:inline">Pesan</span>
                            </a>
                        </div>
                    </div>
                </div>
            `;
            return card;
        }
    </script>
</body>
</html>