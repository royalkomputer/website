<?php
require_once 'config.php';
requireLogin();

date_default_timezone_set('Asia/Jakarta');

$current_admin = getCurrentAdmin();
$is_super      = isSuperAdmin();

// Jika session tidak valid (admin tidak ditemukan di DB/file), redirect ke logout
if (!$current_admin) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$current_status = loadStatus();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/webp" href="logo/logo.webp">
    <title>Admin Dashboard - Royal Komputer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: { astra: { 50:'#f0f7ff',100:'#e0effe',200:'#b9dffd',300:'#7cc3fc',400:'#36a4fa',500:'#0c87eb',600:'#0069c9',700:'#0254A3',800:'#064787',900:'#0b3c70',950:'#07162c' } }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar{width:8px;height:8px}::-webkit-scrollbar-track{background:#f1f5f9}::-webkit-scrollbar-thumb{background:#0254A3;border-radius:4px}
        .tab-btn.active{background:#0254A3;color:#fff;box-shadow:0 2px 8px #0254a355}.tab-btn{transition:all .2s}
        @keyframes fadeIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col font-sans">

<nav class="bg-astra-950 text-white sticky top-0 z-50 shadow-md border-b border-slate-800">
    <div class="container mx-auto px-4 py-3 flex items-center justify-between gap-3">
        <a href="#" class="flex items-center gap-3 flex-shrink-0">
            <img src="logo/logo.webp" alt="Logo" class="h-10 w-auto object-contain rounded">
            <span class="font-bold text-xl tracking-wider">ROYAL<span class="text-astra-400">ADMIN</span></span>
        </a>
        <div class="flex items-center gap-3">
            <span class="text-xs text-slate-400 hidden sm:inline">
                Login sebagai <strong class="text-white"><?php echo htmlspecialchars($current_admin['username']); ?></strong>
                <?php if ($is_super): ?>
                <span class="ml-1 bg-yellow-500/20 border border-yellow-500/40 text-yellow-400 text-[10px] px-2 py-0.5 rounded-full font-bold">Super Admin</span>
                <?php endif; ?>
            </span>
            <span class="bg-green-500/20 text-green-400 border border-green-500/30 px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1.5">
                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> Aktif
            </span>
            <a href="logout.php" class="bg-red-500/20 hover:bg-red-500/40 text-red-300 border border-red-500/30 px-3 py-1 rounded-full text-xs font-bold transition-colors">
                <i class="fa-solid fa-right-from-bracket mr-1"></i> Logout
            </a>
        </div>
    </div>
</nav>

<!-- NOTIFICATION TOAST -->
<div id="notification-bar" class="fixed top-16 left-0 right-0 z-[100] transition-all duration-300 -translate-y-full opacity-0 pointer-events-none">
    <div class="container mx-auto px-4 max-w-7xl">
        <div id="notification-content" class="mt-4 px-5 py-3 rounded-xl shadow-lg border flex items-center gap-3 text-sm font-semibold"></div>
    </div>
</div>

<main class="container mx-auto px-4 py-8 flex-grow">

    <div class="mb-6 border-b border-slate-200 pb-4">
        <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">Dashboard Admin</h2>
        <p class="text-slate-500 text-sm mt-1">Kelola katalog produk, jadwal, dan akun admin.</p>
    </div>

    <!-- TAB NAV -->
    <div class="flex gap-2 mb-6 bg-white p-2 rounded-xl border border-slate-200 shadow-sm flex-wrap">
        <button onclick="switchTab('katalog')" id="tab-katalog" class="tab-btn active flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold">
            <i class="fa-solid fa-boxes-stacked"></i> Katalog Produk
        </button>
        <?php if ($is_super): ?>
        <button onclick="switchTab('admin')" id="tab-admin" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-users-gear"></i> Kelola Admin
        </button>
        <?php endif; ?>

        <button onclick="switchTab('kategori')" id="tab-kategori" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-tags"></i> Kategori
        </button>

        <button onclick="switchTab('promo')" id="tab-promo" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-tag"></i> Promo
        </button>

        <button onclick="switchTab('banner')" id="tab-banner" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-images"></i> Banner
        </button>

        <button onclick="switchTab('aset')" id="tab-aset" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-chart-pie"></i> Aset
        </button>

        <button onclick="switchTab('hutang')" id="tab-hutang" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-hand-holding-dollar"></i> Hutang
        </button>

        <button onclick="switchTab('penghasilan')" id="tab-penghasilan" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-money-bill-trend-up"></i> Penghasilan
        </button>

        <button onclick="switchTab('profil')" id="tab-profil" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-circle-user"></i> Profil Saya
        </button>
    </div>

    <!-- PANEL KATALOG -->
    <div id="panel-katalog">

        <!-- FILTER + HEADER KATALOG MENYATU -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4 pb-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 flex items-center gap-2 text-lg"><i class="fa-solid fa-store text-astra-700"></i> Katalog Produk</h3>
                <div class="text-sm font-semibold bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-lg">
                    Total: <span id="total-count" class="text-astra-700">0</span>
                </div>
            </div>
            <div class="flex items-center gap-3 text-xs text-slate-500 mb-4 pb-3 border-b border-slate-100 flex-wrap">
                <span class="flex items-center gap-1.5">
                    <i class="fa-solid fa-rotate text-astra-500"></i>
                    Sync terakhir: <strong id="sync-time" class="text-slate-700">--</strong> WIB
                </span>
                <span class="text-slate-300">|</span>
                <span id="sync-products" class="text-slate-600">-</span>
                <span class="text-slate-300">|</span>
                <span id="sync-photos" class="text-slate-600">-</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Cari Produk / Kode</label>
                <div class="relative">
                    <input type="text" id="search-admin" oninput="handleAdminSearch(this.value)" placeholder="Ketik nama atau ID item..." class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-lg px-4 py-2.5 pl-10 focus:outline-none focus:border-astra-500 text-sm">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Kategori</label>
                <select id="filter-kategori-admin" onchange="handleKategoriFilterAdmin(this.value)" class="w-full bg-slate-50 border border-slate-300 text-slate-700 text-sm rounded-lg p-2.5 outline-none focus:border-astra-500 cursor-pointer">
                    <option value="all">Semua Kategori</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Status Foto</label>
                <select id="filter-photo" onchange="handlePhotoFilter(this.value)" class="w-full bg-slate-50 border border-slate-300 text-slate-700 text-sm rounded-lg p-2.5 outline-none focus:border-astra-500 cursor-pointer">
                    <option value="all">Semua Foto</option><option value="no-photo">Belum Ada</option><option value="has-photo">Sudah Ada</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Kondisi</label>
                <select id="filter-condition" onchange="handleConditionFilterAdmin(this.value)" class="w-full bg-slate-50 border border-slate-300 text-slate-700 text-sm rounded-lg p-2.5 outline-none focus:border-astra-500 cursor-pointer">
                    <option value="all">Semua Kondisi</option><option value="baru">Baru</option><option value="bekas">Bekas (Ada 2ND)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Urutkan</label>
                <select id="sort-admin" onchange="handleAdminSort(this.value)" class="w-full bg-slate-50 border border-slate-300 text-slate-700 text-sm rounded-lg p-2.5 outline-none focus:border-astra-500 cursor-pointer">
                    <option value="name-asc">Nama (A-Z)</option><option value="name-desc">Nama (Z-A)</option>
                    <option value="stock-desc">Stok Terbanyak</option><option value="stock-asc">Stok Paling Sedikit</option>
                    <option value="price-desc">Harga Tertinggi</option><option value="price-asc">Harga Terendah</option>
                </select>
            </div>
        </div>
    </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-4" id="stat-cards">
            <div class="bg-astra-50 p-3 rounded-lg border border-astra-200"><span id="stat-total" class="text-xl font-black text-astra-700">-</span><p class="text-[10px] text-astra-600 font-semibold">Total Produk</p></div>
            <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-200"><span id="stat-kategori" class="text-xl font-black text-emerald-700">-</span><p class="text-[10px] text-emerald-600 font-semibold">Kategori Aktif</p></div>
            <div class="bg-amber-50 p-3 rounded-lg border border-amber-200"><span id="stat-nophoto" class="text-xl font-black text-amber-700">-</span><p class="text-[10px] text-amber-600 font-semibold">Tanpa Foto</p></div>
            <div class="bg-sky-50 p-3 rounded-lg border border-sky-200"><span id="stat-stok" class="text-xl font-black text-sky-700">-</span><p class="text-[10px] text-sky-600 font-semibold">Total Stok</p></div>
            <div class="bg-violet-50 p-3 rounded-lg border border-violet-200"><span id="stat-nilai" class="text-xl font-black text-violet-700">-</span><p class="text-[10px] text-violet-600 font-semibold">Nilai Jual</p></div>
            <div class="bg-slate-50 p-3 rounded-lg border border-slate-200"><span id="stat-modal" class="text-xl font-black text-slate-700">-</span><p class="text-[10px] text-slate-600 font-semibold">Total Modal</p></div>
        </div>

        <div id="loading-spinner" class="py-12 flex flex-col items-center justify-center gap-3">
            <i class="fa-solid fa-circle-notch text-3xl text-astra-700 animate-spin"></i>
            <p class="text-slate-500 text-sm font-medium">Memuat data...</p>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden hidden" id="table-container">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                        <tr>
                            <th class="px-5 py-4 w-16">Foto</th><th class="px-5 py-4">Kode Item</th>
                            <th class="px-5 py-4">Nama Produk</th><th class="px-5 py-4">Kategori</th>
                            <th class="px-5 py-4 text-right">Harga Jual</th><th class="px-5 py-4 text-right">Harga Pokok</th><th class="px-5 py-4 text-center">Stok</th><th class="px-5 py-4 text-center">Status Foto</th>
                            <th class="px-5 py-4 text-center w-16">Tampil</th>
                            <th class="px-5 py-4 text-center w-28">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="admin-table-body" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
            <div id="empty-state" class="hidden p-12 text-center">
                <i class="fa-solid fa-folder-open text-4xl text-slate-300 mb-3"></i>
                <h4 class="text-slate-700 font-bold">Tidak ada data</h4>
            </div>
        </div>
    </div>

    <!-- PANEL KELOLA ADMIN (super admin only) -->
    <?php if ($is_super): ?>
    <div id="panel-admin" class="hidden">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-5">
            <div>
                <h3 class="font-extrabold text-slate-900 text-lg flex items-center gap-2">
                    <i class="fa-solid fa-users-gear text-astra-700"></i> Manajemen Akun Admin
                </h3>
                <p class="text-sm text-slate-500 mt-0.5">Tambah, edit, atau hapus akun admin dan super admin.</p>
            </div>
            <button onclick="openModalAdmin('tambah')"
                class="bg-astra-700 hover:bg-astra-800 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2 flex-shrink-0">
                <i class="fa-solid fa-user-plus"></i> Tambah Admin Baru
            </button>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                        <tr>
                            <th class="px-5 py-4">Nama</th><th class="px-5 py-4">Username</th>
                            <th class="px-5 py-4 text-center">Role</th><th class="px-5 py-4 text-center">Dibuat</th>
                            <th class="px-5 py-4 text-center w-36">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="admin-list-body" class="divide-y divide-slate-100">
                        <tr><td colspan="5" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner animate-spin mr-2"></i> Memuat...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- PANEL BANNER -->
    <div id="panel-banner" class="hidden">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="font-extrabold text-slate-900 text-lg flex items-center gap-2">
                        <i class="fa-solid fa-images text-astra-700"></i> Manajemen Banner
                    </h3>
                    <p class="text-sm text-slate-500 mt-0.5">Kelola banner/slideshow untuk halaman toko. <span class="text-amber-600 font-semibold"><i class="fa-solid fa-triangle-exclamation text-xs"></i> Maks 6 foto/playlist.</span></p>
                </div>
                <button onclick="createNewPlaylist()" class="bg-astra-700 hover:bg-astra-800 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2 flex-shrink-0">
                    <i class="fa-solid fa-plus"></i> Playlist Baru
                </button>
            </div>
            
            <div id="banner-list" class="space-y-4">
                <div class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-spinner animate-spin text-2xl mb-2"></i>
                    <p class="text-sm">Memuat banner...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL MANAJEMEN FOTO BANNER -->
    <div id="banner-photo-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-xl border border-slate-200 w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">
            <div class="bg-astra-950 text-white p-4 flex items-center justify-between rounded-t-xl flex-shrink-0">
                <h3 class="font-bold text-sm flex items-center gap-2"><i class="fa-solid fa-images text-astra-400"></i> <span id="bpm-title">Kelola Foto</span></h3>
                <button onclick="closeBannerPhotoModal()" class="text-slate-400 hover:text-white text-lg"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-5 overflow-y-auto flex-grow">
                <!-- Settings -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5 pb-4 border-b border-slate-200">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Interval (ms)</label>
                        <input type="number" id="bpm-interval" min="2000" step="500" value="5000" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-sm outline-none focus:border-astra-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Aspek Rasio</label>
                        <select id="bpm-aspect" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-sm outline-none focus:border-astra-500">
                            <option value="16/9">16:9 (Lanscape)</option>
                            <option value="4/3">4:3</option>
                            <option value="1/1">1:1 (Square)</option>
                            <option value="21/9">21:9 (Ultrawide)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Status</label>
                        <select id="bpm-active" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-sm outline-none focus:border-astra-500">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button onclick="saveBannerSettings()" class="w-full bg-astra-700 hover:bg-astra-800 text-white px-3 py-2 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan
                        </button>
                    </div>
                </div>

                <!-- Upload Area -->
                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Upload Foto Baru</label>
                    <div class="flex items-center gap-3">
                        <label id="bpm-upload-label" class="flex-1 bg-slate-50 border-2 border-dashed border-slate-300 rounded-lg p-4 text-center cursor-pointer hover:border-astra-400 hover:bg-astra-50/30 transition-all">
                            <i class="fa-solid fa-cloud-arrow-up text-2xl text-slate-400 mb-1"></i>
                            <p class="text-xs text-slate-500 font-medium">Klik atau seret foto ke sini</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Maks 6 foto total. Format: JPG, PNG, WEBP</p>
                            <input type="file" id="bpm-file-input" multiple accept="image/*" class="hidden" onchange="handleBannerPhotoUpload(event)">
                        </label>
                    </div>
                </div>

                <!-- Photo Grid -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Foto Tersimpan <span id="bpm-count" class="text-slate-400 font-normal"></span></label>
                    <div id="bpm-photos" class="grid grid-cols-3 sm:grid-cols-4 gap-3"></div>
                    <div id="bpm-empty" class="hidden py-8 text-center text-slate-400">
                        <i class="fa-solid fa-images text-3xl text-slate-300 mb-2"></i>
                        <p class="text-sm">Belum ada foto. Upload foto di atas.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PANEL KATEGORI -->
    <div id="panel-kategori" class="hidden">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="font-extrabold text-slate-900 text-lg flex items-center gap-2">
                        <i class="fa-solid fa-tags text-astra-700"></i> Manajemen Kategori
                    </h3>
                    <p class="text-sm text-slate-500 mt-0.5">Atur nama tampilan, grup induk, urutan, dan visibilitas kategori.</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="showTambahKategori()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> Tambah
                    </button>
                    <button onclick="saveAllKategori()" class="bg-astra-700 hover:bg-astra-800 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Semua
                    </button>
                </div>
            </div>
            <div id="kategori-loading" class="py-8 text-center text-slate-400">
                <i class="fa-solid fa-spinner animate-spin text-2xl mb-2"></i>
                <p class="text-sm">Memuat kategori...</p>
            </div>
            <div id="kategori-table-wrap" class="hidden overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 w-12">No</th>
                            <th class="px-4 py-3">Nama Tampilan</th>
                            <th class="px-4 py-3">ID IPOS</th>
                            <th class="px-4 py-3">Induk</th>
                            <th class="px-4 py-3 text-center w-20">Urut</th>
                            <th class="px-4 py-3 text-center w-20">Tampil</th>
                            <th class="px-4 py-3 text-center w-16">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="kategori-table-body" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PANEL PROMO -->
    <div id="panel-promo" class="hidden">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="font-extrabold text-slate-900 text-lg flex items-center gap-2">
                        <i class="fa-solid fa-tag text-astra-700"></i> Manajemen Promo
                    </h3>
                    <p class="text-sm text-slate-500 mt-0.5">Atur harga coret untuk produk yang sedang diskon.</p>
                </div>
                <button onclick="showBuatPromo()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> Buat Promo
                </button>
            </div>
            <div id="promo-loading" class="py-8 text-center text-slate-400">
                <i class="fa-solid fa-spinner animate-spin text-2xl mb-2"></i>
                <p class="text-sm">Memuat promo...</p>
            </div>
            <div id="promo-table-wrap" class="hidden overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 w-16">Foto</th>
                            <th class="px-4 py-3">Produk</th>
                            <th class="px-4 py-3 text-right">Harga Jual</th>
                            <th class="px-4 py-3 text-right">Harga Coret</th>
                            <th class="px-4 py-3 text-center">Label</th>
                            <th class="px-4 py-3 text-center w-24">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="promo-table-body" class="divide-y divide-slate-100"></tbody>
                </table>
                <div id="promo-empty" class="hidden py-8 text-center text-slate-400">
                    <i class="fa-solid fa-tag text-3xl text-slate-300 mb-2"></i>
                    <p class="text-sm">Belum ada promo. Klik <strong>Buat Promo</strong> untuk menambahkan.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- PANEL PROFIL SAYA -->
    <div id="panel-profil" class="hidden">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 max-w-md">
            <h3 class="font-extrabold text-slate-900 text-lg mb-1 flex items-center gap-2">
                <i class="fa-solid fa-circle-user text-astra-700"></i> Profil Saya
            </h3>
            <p class="text-sm text-slate-500 mb-5">Ubah username, nama tampilan, atau password akun Anda.</p>
            <div class="mb-4 p-3 bg-slate-50 rounded-lg border border-slate-200 text-sm flex items-center gap-2">
                <span class="text-slate-500">Role:</span>
                <?php if ($is_super): ?>
                <span class="bg-yellow-100 border border-yellow-300 text-yellow-700 text-xs px-2 py-0.5 rounded-full font-bold">Super Admin</span>
                <?php else: ?>
                <span class="bg-blue-100 border border-blue-300 text-blue-700 text-xs px-2 py-0.5 rounded-full font-bold">Admin</span>
                <?php endif; ?>
            </div>
            <div class="space-y-4">
                <input type="hidden" id="profil-target-id" value="<?php echo htmlspecialchars($current_admin['id']); ?>">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Nama Tampilan</label>
                    <input type="text" id="profil-nama" value="<?php echo htmlspecialchars($current_admin['nama']); ?>"
                        class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Username</label>
                    <input type="text" id="profil-username" value="<?php echo htmlspecialchars($current_admin['username']); ?>"
                        class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Password Baru <span class="text-slate-400 font-normal normal-case">(kosongkan jika tidak diubah)</span></label>
                    <input type="password" id="profil-password" placeholder="Minimal 6 karakter"
                        class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500">
                </div>
                <div class="pt-2 flex items-center gap-3">
                    <button type="button" onclick="submitProfil()"
                        class="bg-astra-700 hover:bg-astra-800 text-white px-6 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                    </button>
                    <span id="profil-feedback" class="text-sm font-semibold hidden"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- PANEL ASET -->
    <div id="panel-aset" class="hidden">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4 pb-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 flex items-center gap-2 text-lg"><i class="fa-solid fa-chart-pie text-astra-700"></i> Data Aset</h3>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                    <div class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Total Modal</div>
                    <div id="aset-total-modal" class="text-2xl font-extrabold text-emerald-800 mt-1">-</div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="text-xs font-bold text-blue-600 uppercase tracking-wider">Total Nilai Jual</div>
                    <div id="aset-total-jual" class="text-2xl font-extrabold text-blue-800 mt-1">-</div>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-xl p-4">
                    <div class="text-xs font-bold text-purple-600 uppercase tracking-wider">Total Item</div>
                    <div id="aset-total-item" class="text-2xl font-extrabold text-purple-800 mt-1">-</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <div class="relative flex-1 min-w-[200px]">
                    <input type="text" id="aset-search" placeholder="Cari nama/kode item..." oninput="renderAset()"
                        class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg px-4 py-2 pl-10 text-sm focus:outline-none focus:border-astra-500">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-sm"></i>
                </div>
                <select id="aset-kategori" onchange="renderAset()" class="bg-slate-50 border border-slate-300 text-slate-700 text-sm rounded-lg p-2 outline-none focus:border-astra-500 cursor-pointer">
                    <option value="all">Semua Kategori</option>
                </select>
                <select id="aset-sort" onchange="renderAset()" class="bg-slate-50 border border-slate-300 text-slate-700 text-sm rounded-lg p-2 outline-none focus:border-astra-500 cursor-pointer">
                    <option value="nama-asc">Nama A-Z</option>
                    <option value="nama-desc">Nama Z-A</option>
                    <option value="modal-desc">Modal Tertinggi</option>
                    <option value="modal-asc">Modal Terendah</option>
                    <option value="jual-desc">Nilai Jual Tertinggi</option>
                    <option value="jual-asc">Nilai Jual Terendah</option>
                </select>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-500 text-xs uppercase tracking-wider">
                            <th class="text-left py-3 px-2 font-semibold">Kode</th>
                            <th class="text-left py-3 px-2 font-semibold">Nama</th>
                            <th class="text-left py-3 px-2 font-semibold">Kategori</th>
                            <th class="text-right py-3 px-2 font-semibold">Stok</th>
                            <th class="text-right py-3 px-2 font-semibold">Hrg Pokok</th>
                            <th class="text-right py-3 px-2 font-semibold">Hrg Jual</th>
                            <th class="text-right py-3 px-2 font-semibold">Total Modal</th>
                            <th class="text-right py-3 px-2 font-semibold">Total Nilai Jual</th>
                        </tr>
                    </thead>
                    <tbody id="aset-tbody"></tbody>
                </table>
            </div>
            <div id="aset-empty" class="hidden text-center py-10 text-slate-400">
                <i class="fa-solid fa-box-open text-4xl mb-3"></i>
                <p>Tidak ada data aset ditemukan.</p>
            </div>
        </div>
    </div>

    <!-- PANEL HUTANG -->
    <div id="panel-hutang" class="hidden">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4 pb-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 flex items-center gap-2 text-lg"><i class="fa-solid fa-hand-holding-dollar text-astra-700"></i> Data Hutang</h3>
            </div>

            <!-- Grand Totals -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                    <div class="text-xs font-bold text-red-600 uppercase tracking-wider">Grand Total Faktur</div>
                    <div id="hutang-grand-faktur" class="text-2xl font-extrabold text-red-800 mt-1">-</div>
                </div>
                <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
                    <div class="text-xs font-bold text-orange-600 uppercase tracking-wider">Grand Total Sisa</div>
                    <div id="hutang-grand-sisa" class="text-2xl font-extrabold text-orange-800 mt-1">-</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <div class="relative flex-1 min-w-[200px]">
                    <input type="text" id="hutang-search" placeholder="Cari supplier..." oninput="renderHutang()"
                        class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg px-4 py-2 pl-10 text-sm focus:outline-none focus:border-astra-500">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-sm"></i>
                </div>
                <div class="flex gap-1 bg-slate-100 rounded-lg p-1" id="hutang-tipe-filter">
                    <button onclick="setHutangTipe('')" class="px-3 py-1.5 text-xs font-bold rounded-md transition-all bg-white text-astra-700 shadow-sm">Semua</button>
                    <button onclick="setHutangTipe('BL')" class="px-3 py-1.5 text-xs font-bold rounded-md transition-all text-slate-500 hover:text-slate-700">Hutang (BL)</button>
                    <button onclick="setHutangTipe('KI')" class="px-3 py-1.5 text-xs font-bold rounded-md transition-all text-slate-500 hover:text-slate-700">Kongsi (KI)</button>
                </div>
                <select id="hutang-sort" onchange="loadHutang()" class="bg-slate-50 border border-slate-300 text-slate-700 rounded-lg px-3 py-2 text-xs font-semibold outline-none focus:border-astra-500">
                    <option value="tgl_desc">Terbaru</option>
                    <option value="jatuh_tempo_asc">Jatuh Tempo Terdekat</option>
                </select>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-500 text-xs uppercase tracking-wider">
                            <th class="text-left py-3 px-2 font-semibold">No. Faktur</th>
                            <th class="text-left py-3 px-2 font-semibold">Supplier</th>
                            <th class="text-left py-3 px-2 font-semibold">Tipe</th>
                            <th class="text-left py-3 px-2 font-semibold">Tgl Faktur</th>
                            <th class="text-right py-3 px-2 font-semibold">Total Faktur</th>
                            <th class="text-right py-3 px-2 font-semibold">Sisa</th>
                            <th class="text-left py-3 px-2 font-semibold">Jatuh Tempo</th>
                            <th class="text-center py-3 px-2 font-semibold">Status</th>
                            <th class="text-right py-3 px-2 font-semibold">Terlambat</th>
                            <th class="text-left py-3 px-2 font-semibold">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody id="hutang-tbody"></tbody>
                </table>
            </div>
            <div id="hutang-empty" class="hidden text-center py-10 text-slate-400">
                <i class="fa-solid fa-file-invoice text-4xl mb-3"></i>
                <p>Tidak ada data hutang ditemukan.</p>
            </div>
        </div>
    </div>

    <!-- PANEL PENGHASILAN -->
    <div id="panel-penghasilan" class="hidden">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4 pb-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 flex items-center gap-2 text-lg"><i class="fa-solid fa-money-bill-trend-up text-astra-700"></i> Penghasilan <span id="penghasilan-label" class="text-sm font-normal text-slate-500">-</span></h3>
            </div>

            <!-- Range Filter Buttons -->
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <div class="flex gap-1 bg-slate-100 rounded-lg p-1" id="penghasilan-range-filter">
                    <button onclick="setPenghasilanRange('day')" class="px-3 py-1.5 text-xs font-bold rounded-md transition-all bg-white text-astra-700 shadow-sm">Hari Ini</button>
                    <button onclick="setPenghasilanRange('week')" class="px-3 py-1.5 text-xs font-bold rounded-md transition-all text-slate-500 hover:text-slate-700">1 Minggu</button>
                    <button onclick="setPenghasilanRange('month')" class="px-3 py-1.5 text-xs font-bold rounded-md transition-all text-slate-500 hover:text-slate-700">Bulan (29-28)</button>
                    <button onclick="setPenghasilanRange('custom')" class="px-3 py-1.5 text-xs font-bold rounded-md transition-all text-slate-500 hover:text-slate-700">Kustom</button>
                </div>
                <div id="penghasilan-custom-range" class="hidden flex items-center gap-2">
                    <input type="date" id="penghasilan-start" class="bg-slate-50 border border-slate-300 text-slate-700 rounded-lg px-3 py-1.5 text-xs outline-none focus:border-astra-500">
                    <span class="text-slate-400 text-xs">s/d</span>
                    <input type="date" id="penghasilan-end" class="bg-slate-50 border border-slate-300 text-slate-700 rounded-lg px-3 py-1.5 text-xs outline-none focus:border-astra-500">
                    <button onclick="applyPenghasilanCustom()" class="bg-astra-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-astra-800 transition-all">Terapkan</button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                    <div class="text-xs font-bold text-green-600 uppercase tracking-wider">Total Penjualan</div>
                    <div id="penghasilan-total" class="text-2xl font-extrabold text-green-800 mt-1">-</div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="text-xs font-bold text-blue-600 uppercase tracking-wider">Total Transaksi</div>
                    <div id="penghasilan-transaksi" class="text-2xl font-extrabold text-blue-800 mt-1">-</div>
                </div>
                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
                    <div class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Rata-rata / Transaksi</div>
                    <div id="penghasilan-rata" class="text-2xl font-extrabold text-indigo-800 mt-1">-</div>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-500 text-xs uppercase tracking-wider">
                            <th class="text-left py-3 px-2 font-semibold">No. Faktur</th>
                            <th class="text-left py-3 px-2 font-semibold">Tanggal</th>
                            <th class="text-right py-3 px-2 font-semibold">Total</th>
                            <th class="text-left py-3 px-2 font-semibold">Item</th>
                        </tr>
                    </thead>
                    <tbody id="penghasilan-tbody"></tbody>
                </table>
            </div>
            <div id="penghasilan-empty" class="hidden text-center py-10 text-slate-400">
                <i class="fa-solid fa-chart-line text-4xl mb-3"></i>
                <p>Belum ada transaksi periode ini.</p>
            </div>
        </div>
    </div>

        </main>

<!-- MODAL KELOLA PRODUK -->
<div id="edit-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 w-full max-w-lg shadow-2xl flex flex-col overflow-hidden">
        <div class="bg-astra-950 text-white p-4 flex items-center justify-between">
            <h3 class="font-bold text-base flex items-center gap-2"><i class="fa-solid fa-pen-to-square text-astra-400"></i> Kelola Item</h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-white text-lg"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="edit-form" onsubmit="submitForm(event)" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" id="modal-id" name="id">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Nama Produk</label>
                <input type="text" id="modal-name" class="w-full bg-slate-100 border border-slate-200 text-slate-500 rounded-lg p-2.5 text-sm outline-none" readonly>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Harga Pokok <span class="text-slate-400 font-normal normal-case">(read-only)</span></label>
                <input type="text" id="modal-harga-pokok" class="w-full bg-slate-100 border border-slate-200 text-slate-600 rounded-lg p-2.5 text-sm font-semibold outline-none" readonly>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Harga Jual</label>
                <input type="text" id="modal-harga-jual" class="w-full bg-slate-100 border border-slate-200 text-astra-800 rounded-lg p-2.5 text-sm font-bold outline-none" readonly>
            </div>
            
            <div id="saved-photos-section" class="hidden">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Foto Tersimpan (Klik ⬅️ ➡️ untuk ubah urutan utama)</label>
                <div id="saved-photos-list" class="flex gap-3 overflow-x-auto pb-2 snap-x"></div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Upload / Tambah Foto Baru (Bisa pilih banyak)</label>
                <input type="file" id="modal-foto" name="new_files[]" multiple accept="image/*" onchange="handlePhotoUpload(event)" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-xs text-slate-700 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-astra-50 file:text-astra-700 hover:file:bg-astra-100 cursor-pointer">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Deskripsi Produk</label>
                <textarea id="modal-desc" name="description" rows="4" placeholder="Tulis rincian spesifikasi produk di sini..." class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500 transition-all"></textarea>
            </div>
            <div class="border-t border-slate-100 pt-4">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Promo / Harga Coret <span class="text-slate-400 font-normal normal-case">(opsional)</span></label>
                <label class="flex items-center gap-2 mb-3 cursor-pointer select-none">
                    <input type="checkbox" id="auto-label" onchange="toggleAutoLabel()" class="w-4 h-4 text-astra-700 border-slate-300 rounded focus:ring-astra-500">
                    <span class="text-sm text-slate-700 font-medium">Hitung persentase diskon otomatis</span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1 font-medium">Harga Coret (Rp) <span class="text-amber-500 font-normal">ⓘ isi harga sebelum diskon</span></label>
                        <input type="number" id="modal-harga-coret" name="harga_coret" oninput="autoLabelHandler()" min="0" step="500" placeholder="0" class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm outline-none focus:border-astra-500">
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1 font-medium">Label Promo</label>
                        <input type="text" id="modal-label-promo" name="label_promo" placeholder="Diskon 20%" class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm outline-none focus:border-astra-500">
                    </div>
                </div>
            </div>
            <div class="pt-2 flex justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-slate-300 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50">Batal</button>
                <button type="submit" id="btn-submit" class="px-5 py-2 bg-astra-700 hover:bg-astra-800 text-white rounded-lg text-xs font-bold flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL BUAT PROMO -->
<div id="buat-promo-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 w-full max-w-lg shadow-2xl flex flex-col overflow-hidden">
        <div class="bg-astra-950 text-white p-4 flex items-center justify-between">
            <h3 class="font-bold text-base flex items-center gap-2"><i class="fa-solid fa-tag text-astra-400"></i> <span id="bp-title">Buat Promo</span></h3>
            <button onclick="closeBuatPromo()" class="text-slate-400 hover:text-white text-lg"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="buat-promo-form" onsubmit="submitBuatPromo(event)" class="p-6 space-y-4">
            <input type="hidden" id="bp-id" value="">

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Cari Produk</label>
                <input type="text" id="bp-search" oninput="filterPromoProduct(this.value)" placeholder="Ketik nama atau ID produk..." autocomplete="off" class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm outline-none focus:border-astra-500">
                <div id="bp-search-results" class="hidden mt-1 max-h-48 overflow-y-auto border border-slate-200 rounded-lg bg-white shadow-lg divide-y divide-slate-100"></div>
            </div>

            <div id="bp-selected" class="hidden p-3 bg-astra-50 rounded-lg border border-astra-200 flex items-center gap-3">
                <img id="bp-selected-img" src="" class="w-10 h-10 object-cover rounded border border-astra-300">
                <div>
                    <p id="bp-selected-name" class="text-sm font-bold text-slate-800"></p>
                    <p id="bp-selected-price" class="text-xs text-slate-500"></p>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-4">
                <label class="flex items-center gap-2 mb-3 cursor-pointer select-none">
                    <input type="checkbox" id="bp-auto-label" onchange="bpToggleAuto()" class="w-4 h-4 text-astra-700 border-slate-300 rounded focus:ring-astra-500">
                    <span class="text-sm text-slate-700 font-medium">Hitung persentase diskon otomatis</span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1 font-medium">Harga Coret (Rp) <span class="text-amber-500 font-normal">ⓘ isi harga sebelum diskon</span></label>
                        <input type="number" id="bp-harga-coret" oninput="bpAutoLabel()" min="0" step="500" placeholder="0" class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm outline-none focus:border-astra-500">
                    </div>
                    <div>
                        <label class="block text-[10px] text-slate-400 mb-1 font-medium">Label Promo</label>
                        <input type="text" id="bp-label" placeholder="Diskon 20%" class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm outline-none focus:border-astra-500">
                    </div>
                </div>
            </div>

            <div class="pt-2 flex justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeBuatPromo()" class="px-4 py-2 border border-slate-300 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50">Batal</button>
                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Promo
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL TAMBAH KATEGORI -->
<div id="tambah-kategori-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 w-full max-w-md shadow-2xl flex flex-col overflow-hidden">
        <div class="bg-astra-950 text-white p-4 flex items-center justify-between">
            <h3 class="font-bold text-base flex items-center gap-2"><i class="fa-solid fa-plus text-astra-400"></i> Tambah Kategori</h3>
            <button onclick="closeTambahKategori()" class="text-slate-400 hover:text-white text-lg"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="tambah-kategori-form" onsubmit="submitTambahKategori(event)" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">ID <span class="text-red-500">*</span></label>
                    <input type="text" id="tk-id" required placeholder="MONITOR" class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm outline-none focus:border-astra-500 uppercase">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Nama Tampilan</label>
                    <input type="text" id="tk-nama" placeholder="Monitor" class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm outline-none focus:border-astra-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Induk</label>
                    <select id="tk-parent" class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm outline-none focus:border-astra-500">
                        <option value="">Tidak Ada (Root)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Urutan</label>
                    <input type="number" id="tk-urut" value="99" min="1" class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm outline-none focus:border-astra-500">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="tk-is-group" class="w-4 h-4 text-astra-700 border-slate-300 rounded focus:ring-astra-500">
                <label for="tk-is-group" class="text-sm text-slate-700 font-medium">Kategori grup (induk virtual, tidak punya produk langsung)</label>
            </div>
            <div class="pt-2 flex justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeTambahKategori()" class="px-4 py-2 border border-slate-300 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50">Batal</button>
                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> Tambah
                </button>
            </div>
        </form>
    </div>
</div>

<!-- KONFIRMASI MODAL -->
<div id="confirm-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[110] hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 w-full max-w-sm shadow-2xl flex flex-col overflow-hidden animate-fade-in" style="animation:fadeIn .2s ease-out">
        <div class="p-6 text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-red-50 border border-red-200 flex items-center justify-center">
                <i class="fa-solid fa-triangle-exclamation text-2xl text-red-500"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-2">Konfirmasi</h3>
            <p id="confirm-message" class="text-sm text-slate-600"></p>
        </div>
        <div class="px-6 pb-6 flex gap-3 justify-center">
            <button id="confirm-cancel" type="button" class="px-5 py-2.5 border border-slate-300 text-slate-600 rounded-lg text-sm font-bold hover:bg-slate-50 w-full">Batal</button>
            <button id="confirm-ok" type="button" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-bold w-full">Ya, Hapus</button>
        </div>
    </div>
</div>

<!-- MODAL PREVIEW FOTO -->
<div id="photo-preview-modal" onclick="if(event.target.id==='photo-preview-modal') closePhotoPreview()" class="fixed inset-0 bg-slate-900/70 z-50 hidden items-center justify-center p-4">
    <div class="relative bg-slate-900 rounded-3xl overflow-hidden shadow-2xl max-w-3xl w-full mx-auto">
        <button onclick="closePhotoPreview()" class="absolute top-4 right-4 z-10 bg-white/90 text-slate-900 rounded-full p-3 shadow hover:bg-white transition"><i class="fa-solid fa-xmark"></i></button>
        <img id="preview-image" src="" alt="Preview Foto" class="w-full h-[80vh] object-contain bg-black" />
    </div>
</div>

<!-- MODAL KELOLA AKUN ADMIN -->
<div id="modal-admin" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl border border-slate-200 w-full max-w-md shadow-2xl flex flex-col overflow-hidden">
        <div class="bg-astra-950 text-white p-4 flex items-center justify-between">
            <h3 id="modal-admin-title" class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-user-gear text-astra-400"></i> Admin
            </h3>
            <button onclick="closeModalAdmin()" class="text-slate-400 hover:text-white text-lg"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="modal-admin-target-id">
            <input type="hidden" id="modal-admin-action" value="tambah_admin">
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Nama Tampilan</label>
                <input type="text" id="modal-admin-nama" placeholder="Nama lengkap admin"
                    class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Username</label>
                <input type="text" id="modal-admin-username" placeholder="Username unik"
                    class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                    Password <span id="pw-hint" class="text-slate-400 font-normal normal-case"></span>
                </label>
                <input type="password" id="modal-admin-password" placeholder="Minimal 6 karakter"
                    class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Role</label>
                <select id="modal-admin-role" class="w-full bg-slate-50 border border-slate-300 text-slate-700 text-sm rounded-lg p-2.5 focus:outline-none focus:border-astra-500 cursor-pointer">
                    <option value="admin">Admin Biasa</option>
                    <option value="super_admin">Super Admin</option>
                </select>
            </div>
            <div id="modal-admin-feedback" class="hidden text-sm font-semibold p-3 rounded-lg"></div>
            <div class="pt-2 flex justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeModalAdmin()" class="px-4 py-2 border border-slate-300 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50">Batal</button>
                <button type="button" onclick="submitAdmin()" id="btn-admin-submit"
                    class="px-5 py-2 bg-astra-700 hover:bg-astra-800 text-white rounded-lg text-xs font-bold flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const IS_SUPER = <?php echo $is_super ? 'true' : 'false'; ?>;
const CURRENT_ADMIN_ID = <?= json_encode((string)$current_admin['id']) ?>;

function escHtml(s){if(!s)return'';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}

// SCROLL POSITION SAVER
let _savedScrollY = 0;

// KONFIRMASI MODAL
function showConfirmModal(message, onConfirm) {
    const modal = document.getElementById('confirm-modal');
    document.getElementById('confirm-message').textContent = message;
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    const okBtn = document.getElementById('confirm-ok');
    const cancelBtn = document.getElementById('confirm-cancel');

    function cleanup() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        okBtn.removeEventListener('click', handleOk);
        cancelBtn.removeEventListener('click', handleCancel);
    }

    function handleOk() {
        cleanup();
        onConfirm();
    }

    function handleCancel() {
        cleanup();
    }

    okBtn.addEventListener('click', handleOk);
    cancelBtn.addEventListener('click', handleCancel);

    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) handleCancel();
    }, { once: true });
}

// NOTIFIKASI INLINE
function showNotification(message, type = 'success') {
    const bar = document.getElementById('notification-bar');
    const content = document.getElementById('notification-content');
    const config = {
        success: { bg: 'bg-green-50 text-green-700 border-green-200', icon: 'fa-circle-check' },
        error:   { bg: 'bg-red-50 text-red-700 border-red-200',   icon: 'fa-circle-xmark' },
        info:    { bg: 'bg-blue-50 text-blue-700 border-blue-200', icon: 'fa-circle-info' },
    };
    const cfg = config[type] || config.success;
    content.className = cfg.bg + ' px-5 py-3 rounded-xl shadow-lg border flex items-center gap-3 text-sm font-semibold';
    content.innerHTML = `<i class="fa-solid ${cfg.icon} text-lg"></i> ${message} <button onclick="hideNotification()" class="ml-auto text-current opacity-60 hover:opacity-100"><i class="fa-solid fa-xmark text-lg"></i></button>`;
    bar.classList.remove('-translate-y-full', 'opacity-0', 'pointer-events-none');
    bar.classList.add('translate-y-0', 'opacity-100', 'pointer-events-auto');
    clearTimeout(bar._timeout);
    bar._timeout = setTimeout(hideNotification, 4000);
}
function hideNotification() {
    const bar = document.getElementById('notification-bar');
    bar.classList.add('-translate-y-full', 'opacity-0', 'pointer-events-none');
    bar.classList.remove('translate-y-0', 'opacity-100', 'pointer-events-auto');
}

// TAB
function showPanel(name){
    const panels = ['katalog','admin','banner','kategori','promo','aset','hutang','penghasilan','profil'];
    panels.forEach(p=>{
        const panel = document.getElementById('panel-'+p);
        const btn = document.getElementById('tab-'+p);
        if(!panel) return;
        if(p===name){
            panel.classList.remove('hidden'); panel.style.display='block';
            if(btn){ btn.classList.add('active'); btn.classList.remove('text-slate-600','hover:bg-slate-100'); }
        } else {
            panel.classList.add('hidden'); panel.style.display='none';
            if(btn){ btn.classList.remove('active'); btn.classList.add('text-slate-600','hover:bg-slate-100'); }
        }
    });
    if (name === 'admin' && IS_SUPER) loadAdminList();
    if (name === 'banner') loadBannerData();
    if (name === 'kategori') loadKategoriPanel();
    if (name === 'promo') loadPromoPanel();
    if (name === 'aset') loadAset();
    if (name === 'hutang') loadHutang();
    if (name === 'penghasilan') loadPenghasilan();
}

// BANNER MANAGEMENT
let bannerPlaylists = [];
let bpmCurrentPl = null;

async function loadBannerData() {
    const container = document.getElementById('banner-list');
    container.innerHTML = '<div class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner animate-spin text-2xl mb-2"></i><p class="text-sm">Memuat banner...</p></div>';
    
    try {
        const res = await fetch('api_data.php?file=banners.json&_t=' + Date.now());
        const data = await res.json();
        bannerPlaylists = Array.isArray(data) ? data : [];
        renderBannerList();
    } catch (e) {
        container.innerHTML = '<div class="text-center py-8 text-red-400"><i class="fa-solid fa-circle-exclamation text-2xl mb-2"></i><p class="text-sm">Gagal memuat data banner</p></div>';
    }
}

function renderBannerList() {
    const container = document.getElementById('banner-list');
    if (bannerPlaylists.length === 0) {
        container.innerHTML = '<div class="text-center py-8 text-slate-400"><i class="fa-solid fa-images text-4xl mb-3 text-slate-300"></i><p class="text-sm">Belum ada playlist banner</p><p class="text-xs text-slate-400 mt-1">Klik "Playlist Baru" untuk membuat banner pertama.</p></div>';
        return;
    }
    
    container.innerHTML = bannerPlaylists.map((pl, idx) => {
        const photoCount = (pl.photos || []).length;
        const photos = (pl.photos || []).slice(0, 4);
        const thumbHtml = photos.length > 0
            ? photos.map(p => `<img src="uploads/banners/${escAttr(p.image)}" class="w-20 h-14 object-cover rounded border border-slate-200" onerror="this.style.display='none'">`).join('')
            : '<span class="text-xs text-slate-400 italic">Tidak ada foto</span>';
        const moreCount = photoCount > 4 ? `<span class="text-xs text-slate-400 ml-1">+${photoCount - 4}</span>` : '';

        return `
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 hover:border-slate-300 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="text-xs font-bold text-slate-400 bg-white border border-slate-200 px-2 py-1 rounded">#${pl.order || idx + 1}</span>
                    <span class="font-bold text-slate-800 truncate">${escHtml(pl.name || 'Untitled')}</span>
                    ${pl.active !== false ? '<span class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-bold border border-green-200 flex-shrink-0">Aktif</span>' : '<span class="text-[10px] bg-slate-200 text-slate-500 px-2 py-0.5 rounded-full font-bold border border-slate-300 flex-shrink-0">Off</span>'}
                    <span class="text-xs text-slate-400 flex-shrink-0">${photoCount} foto</span>
                    <span class="text-[10px] text-slate-400 flex-shrink-0">${pl.interval || 5000}ms</span>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button onclick="openBannerPhotoModal('${pl.id}')" class="text-xs bg-astra-50 border border-astra-300 text-astra-700 px-3 py-1.5 rounded-lg hover:bg-astra-100 transition-colors font-bold"><i class="fa-solid fa-images mr-1"></i> Kelola Foto</button>
                    <button onclick="deletePlaylist('${pl.id}')" class="text-xs bg-white border border-red-300 text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-50 transition-colors" title="Hapus playlist"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
            <div class="flex gap-2 items-center">${thumbHtml}${moreCount}</div>
        </div>`;
    }).join('');
}


async function createNewPlaylist() {
    const name = prompt('Nama playlist banner:');
    if (!name || !name.trim()) return;
    
    const fd = new FormData();
    fd.append('action', 'save_playlist');
    fd.append('name', name.trim());
    fd.append('active', '1');
    fd.append('interval', '5000');
    fd.append('aspect', '16/9');
    
    try {
        const res = await fetch('update_banner.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showNotification('Playlist berhasil dibuat! Klik "Kelola Foto" untuk menambah foto.', 'success');
            loadBannerData();
        } else {
            showNotification(data.message || 'Gagal membuat playlist', 'error');
        }
    } catch (e) {
        showNotification('Gagal menghubungi server', 'error');
    }
}

async function deletePlaylist(id) {
    const pl = bannerPlaylists.find(p => p.id === id);
    if (!confirm(`Yakin hapus playlist "${pl?.name || id}"? Semua foto akan dihapus permanen.`)) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_playlist');
    fd.append('id', id);
    
    try {
        const res = await fetch('update_banner.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showNotification('Playlist berhasil dihapus.', 'success');
            loadBannerData();
        } else {
            showNotification(data.message || 'Gagal menghapus playlist', 'error');
        }
    } catch (e) {
        showNotification('Gagal menghubungi server', 'error');
    }
}

// ============================================================
// BANNER PHOTO MANAGEMENT MODAL
// ============================================================

function openBannerPhotoModal(plId) {
    bpmCurrentPl = bannerPlaylists.find(p => p.id === plId);
    if (!bpmCurrentPl) return;
    
    document.getElementById('bpm-title').textContent = bpmCurrentPl.name || 'Kelola Foto';
    document.getElementById('bpm-interval').value = bpmCurrentPl.interval || 5000;
    document.getElementById('bpm-aspect').value = bpmCurrentPl.aspect || '16/9';
    document.getElementById('bpm-active').value = bpmCurrentPl.active !== false ? '1' : '0';
    document.getElementById('bpm-file-input').value = '';
    
    renderBpmPhotos();
    document.getElementById('banner-photo-modal').classList.remove('hidden');
    document.getElementById('banner-photo-modal').classList.add('flex');
}

function closeBannerPhotoModal() {
    document.getElementById('banner-photo-modal').classList.add('hidden');
    document.getElementById('banner-photo-modal').classList.remove('flex');
    bpmCurrentPl = null;
    loadBannerData();
}

function renderBpmPhotos() {
    const container = document.getElementById('bpm-photos');
    const empty = document.getElementById('bpm-empty');
    const countEl = document.getElementById('bpm-count');
    const photos = bpmCurrentPl.photos || [];
    
    countEl.textContent = `(${photos.length}/6)`;
    
    if (photos.length === 0) {
        container.innerHTML = '';
        empty.classList.remove('hidden');
        return;
    }
    empty.classList.add('hidden');
    
    container.innerHTML = photos.map((p, idx) => `
        <div class="relative group rounded-lg overflow-hidden border border-slate-200 bg-slate-100 aspect-[4/3]">
            <img src="uploads/banners/${escAttr(p.image)}" class="w-full h-full object-cover" onerror="this.src='data:image/svg+xml,<svg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'120\\' height=\\'90\\'/>'">
            <div class="absolute inset-0 bg-slate-900/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-2">
                <div class="flex justify-between">
                    <span class="text-[10px] text-white font-bold bg-slate-800/70 px-1.5 py-0.5 rounded">#${idx + 1}</span>
                    <button onclick="deleteBannerPhoto(${idx})" class="text-white hover:text-red-400 transition-colors" title="Hapus"><i class="fa-solid fa-trash text-sm"></i></button>
                </div>
                <div class="flex justify-between items-end">
                    <button onclick="moveBannerPhoto(${idx},-1)" class="text-white hover:text-astra-400 transition-colors ${idx === 0 ? 'invisible' : ''}" title="Geser kiri"><i class="fa-solid fa-circle-chevron-left text-lg"></i></button>
                    <button onclick="editBannerPhotoInfo(${idx})" class="text-white hover:text-yellow-400 transition-colors" title="Edit link/alt"><i class="fa-solid fa-pen text-sm"></i></button>
                    <button onclick="moveBannerPhoto(${idx},1)" class="text-white hover:text-astra-400 transition-colors ${idx === photos.length - 1 ? 'invisible' : ''}" title="Geser kanan"><i class="fa-solid fa-circle-chevron-right text-lg"></i></button>
                </div>
            </div>
            ${p.link ? '<span class="absolute bottom-1 right-1 bg-green-500 text-white text-[8px] font-bold px-1 py-0.5 rounded"><i class="fa-solid fa-link"></i></span>' : ''}
        </div>
    `).join('');
}

async function handleBannerPhotoUpload(event) {
    if (!bpmCurrentPl) return;
    const files = event.target.files;
    if (!files || files.length === 0) return;
    
    const remaining = 6 - (bpmCurrentPl.photos || []).length;
    if (remaining <= 0) {
        showNotification('Sudah mencapai batas maksimal 6 foto.', 'error');
        return;
    }
    
    const fd = new FormData();
    fd.append('action', 'save_playlist');
    fd.append('id', bpmCurrentPl.id);
    fd.append('name', bpmCurrentPl.name);
    fd.append('active', bpmCurrentPl.active !== false ? '1' : '0');
    fd.append('interval', bpmCurrentPl.interval || 5000);
    fd.append('aspect', bpmCurrentPl.aspect || '16/9');
    
    const count = Math.min(files.length, remaining);
    for (let i = 0; i < count; i++) {
        fd.append('photos[]', files[i]);
    }
    
    try {
        const res = await fetch('update_banner.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            const uploaded = count;
            const skipped = files.length - count;
            let msg = `${uploaded} foto berhasil diupload.`;
            if (skipped > 0) msg += ` ${skipped} foto ditolak (batas 6 foto).`;
            showNotification(msg, 'success');
            // Reload data
            const r2 = await fetch('api_data.php?file=banners.json&_t=' + Date.now());
            const d2 = await r2.json();
            bannerPlaylists = Array.isArray(d2) ? d2 : [];
            bpmCurrentPl = bannerPlaylists.find(p => p.id === bpmCurrentPl.id) || bpmCurrentPl;
            renderBpmPhotos();
        } else {
            showNotification(data.message || 'Gagal upload foto', 'error');
        }
    } catch (e) {
        showNotification('Gagal menghubungi server', 'error');
    }
    event.target.value = '';
}

async function deleteBannerPhoto(idx) {
    if (!bpmCurrentPl) return;
    if (!confirm('Hapus foto ini?')) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_playlist_photo');
    fd.append('playlist_id', bpmCurrentPl.id);
    fd.append('photo_index', idx);
    
    try {
        const res = await fetch('update_banner.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showNotification('Foto dihapus.', 'success');
            const r2 = await fetch('api_data.php?file=banners.json&_t=' + Date.now());
            const d2 = await r2.json();
            bannerPlaylists = Array.isArray(d2) ? d2 : [];
            bpmCurrentPl = bannerPlaylists.find(p => p.id === bpmCurrentPl.id) || bpmCurrentPl;
            renderBpmPhotos();
        } else {
            showNotification(data.message || 'Gagal menghapus foto', 'error');
        }
    } catch (e) {
        showNotification('Gagal menghubungi server', 'error');
    }
}

async function moveBannerPhoto(idx, dir) {
    if (!bpmCurrentPl) return;
    const photos = bpmCurrentPl.photos || [];
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= photos.length) return;
    
    // Kirim order sebagai indeks original dalam urutan baru
    // Contoh: swap index 1↔2 pada [A,B,C] → order [0,2,1] → hasil [A,C,B]
    const order = photos.map((_, i) => i);
    [order[idx], order[newIdx]] = [order[newIdx], order[idx]];
    
    const fd = new FormData();
    fd.append('action', 'reorder_playlist_photos');
    fd.append('playlist_id', bpmCurrentPl.id);
    fd.append('order', JSON.stringify(order));
    
    try {
        const res = await fetch('update_banner.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            // Update local state hanya setelah server mengonfirmasi
            const temp = photos[idx];
            photos[idx] = photos[newIdx];
            photos[newIdx] = temp;
            renderBpmPhotos();
        } else {
            showNotification(data.message || 'Gagal mengubah urutan', 'error');
        }
    } catch (e) {
        showNotification('Gagal menghubungi server', 'error');
    }
}

function editBannerPhotoInfo(idx) {
    if (!bpmCurrentPl) return;
    const photo = (bpmCurrentPl.photos || [])[idx];
    if (!photo) return;
    
    const newLink = prompt('Link URL (ketik "-" untuk hapus link):', photo.link || '');
    if (newLink === null) return;
    const newAlt = prompt('Alt text (keterangan foto):', photo.alt || '');
    if (newAlt === null) return;
    
    const fd = new FormData();
    fd.append('action', 'update_photo_info');
    fd.append('playlist_id', bpmCurrentPl.id);
    fd.append('photo_index', idx);
    fd.append('link', newLink === '-' ? '' : newLink);
    fd.append('alt', newAlt);
    
    fetch('update_banner.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                photo.link = newLink === '-' ? '' : newLink;
                photo.alt = newAlt;
                renderBpmPhotos();
                showNotification('Info foto diperbarui.', 'success');
            } else {
                showNotification(data.message || 'Gagal', 'error');
            }
        })
        .catch(() => showNotification('Gagal menghubungi server', 'error'));
}

async function saveBannerSettings() {
    if (!bpmCurrentPl) return;
    
    const fd = new FormData();
    fd.append('action', 'save_playlist');
    fd.append('id', bpmCurrentPl.id);
    fd.append('name', bpmCurrentPl.name);
    fd.append('active', document.getElementById('bpm-active').value);
    fd.append('interval', document.getElementById('bpm-interval').value);
    fd.append('aspect', document.getElementById('bpm-aspect').value);
    
    try {
        const res = await fetch('update_banner.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            bpmCurrentPl.interval = parseInt(document.getElementById('bpm-interval').value);
            bpmCurrentPl.aspect = document.getElementById('bpm-aspect').value;
            bpmCurrentPl.active = document.getElementById('bpm-active').value === '1';
            showNotification('Pengaturan banner disimpan.', 'success');
        } else {
            showNotification(data.message || 'Gagal menyimpan', 'error');
        }
    } catch (e) {
        showNotification('Gagal menghubungi server', 'error');
    }
}

function switchTab(tab){ showPanel(tab); }

// KATALOG
let allProducts=[], filteredProducts=[];
let adminFilters = {search:'',photoStatus:'all',sortBy:'name-asc',condition:'all',kategori:'all'};
let hiddenProductIds = [];
let promoDataGlobal = {};
let kategoriData = [];

window.addEventListener('DOMContentLoaded', () => { fetchProducts();
    showPanel('katalog'); });

async function fetchProducts() {
    _savedScrollY = window.scrollY;
    document.getElementById('loading-spinner').style.display='flex';
    document.getElementById('table-container').classList.add('hidden');

    // Helper: normalize image paths (prepend / if starts with uploads/)
    function normalizeProduct(p) {
        if (p.image && p.image.indexOf('uploads/') === 0) p.image = '/' + p.image;
        if (p.images && Array.isArray(p.images)) {
            p.images = p.images.map(function(img) {
                return img.indexOf('uploads/') === 0 ? '/' + img : img;
            });
        }
        return p;
    }

    // Load kategori + hidden + sync data
    loadKategoriData();
    loadHiddenData();
    loadPromoGlobal();
    loadSyncTime();

    try {
        // Strategy 1: Load from cache file directly (fast, no PHP session locking)
        const cacheRes = await fetch('/data/cache_produk.json?_t=' + Date.now());
        if (cacheRes.ok) {
            let data = await cacheRes.json();
            if (Array.isArray(data) && data.length > 0) {
                data = data.map(normalizeProduct);
                allProducts = data;
                applyAdminFilters();
                updateStatCards();
                document.getElementById('loading-spinner').style.display='none';
                document.getElementById('table-container').classList.remove('hidden');
                requestAnimationFrame(function() { window.scrollTo(0, _savedScrollY); });
                return;
            }
        }
    } catch(e) {
        console.warn('Cache load failed, trying API:', e);
    }

    // Strategy 2: Fallback to API
    try {
        const res = await fetch('api_produk.php?admin=1');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        let data = await res.json();
        if (Array.isArray(data)) {
            allProducts = data;
            applyAdminFilters();
            updateStatCards();
            document.getElementById('loading-spinner').style.display='none';
            document.getElementById('table-container').classList.remove('hidden');
            requestAnimationFrame(function() { window.scrollTo(0, _savedScrollY); });
            return;
        }
    } catch(e) {
        console.warn('API failed:', e);
    }

    // Both strategies failed
    document.getElementById('loading-spinner').innerHTML='<p class="text-red-500 font-bold"><i class="fa-solid fa-triangle-exclamation"></i> Gagal memuat data produk.</p>';
}

function handleConditionFilterAdmin(v){adminFilters.condition=v;applyAdminFilters();}
function handleAdminSearch(v){adminFilters.search=v.toLowerCase();applyAdminFilters();}
function handlePhotoFilter(v){adminFilters.photoStatus=v;applyAdminFilters();}
function handleAdminSort(v){adminFilters.sortBy=v;applyAdminFilters();}
function handleKategoriFilterAdmin(v){adminFilters.kategori=v;applyAdminFilters();}

function applyAdminFilters() {
    filteredProducts = allProducts.filter(p => {
        const s=adminFilters.search;
        const matchSearch=(p.name||'').toLowerCase().includes(s)||(p.id||'').toLowerCase().includes(s);
        const matchKategori=adminFilters.kategori==='all'||p.category===adminFilters.kategori;
        const hasPhoto=(p.image||'')&&!(p.image||'').includes('unsplash.com');
        let matchPhoto=true;
        if(adminFilters.photoStatus==='no-photo') matchPhoto=!hasPhoto;
        else if(adminFilters.photoStatus==='has-photo') matchPhoto=hasPhoto;
        const isBekas=(p.name||'').toUpperCase().includes('2ND');
        let matchCond=true;
        if(adminFilters.condition==='baru') matchCond=!isBekas;
        if(adminFilters.condition==='bekas') matchCond=isBekas;
        return matchSearch&&matchKategori&&matchPhoto&&matchCond;
    });
    filteredProducts.sort((a,b)=>{
        const nA=a.name||'',nB=b.name||'';
        if(adminFilters.sortBy==='name-asc') return nA.localeCompare(nB);
        if(adminFilters.sortBy==='name-desc') return nB.localeCompare(nA);
        if(adminFilters.sortBy==='stock-desc') return(b.stock||0)-(a.stock||0);
        if(adminFilters.sortBy==='stock-asc') return(a.stock||0)-(b.stock||0);
        if(adminFilters.sortBy==='price-desc') return(b.price||0)-(a.price||0);
        if(adminFilters.sortBy==='price-asc') return(a.price||0)-(b.price||0);
        return 0;
    });
    renderAdminTable();
}

function renderAdminTable() {
    const tbody=document.getElementById('admin-table-body');
    const empty=document.getElementById('empty-state');
    document.getElementById('total-count').innerText=filteredProducts.length;
    tbody.innerHTML='';
    empty.classList.toggle('hidden',filteredProducts.length!==0);
    filteredProducts.forEach(p => {
        const tr=document.createElement('tr');
        tr.className="hover:bg-slate-50 transition-colors";
        const hasPhoto=(p.image||'')&&!(p.image||'').includes('unsplash.com');
        const photoBadge=hasPhoto
            ?'<span class="bg-green-100 text-green-700 text-[10px] font-bold px-2.5 py-1 rounded-md border border-green-200 inline-flex items-center gap-1.5 whitespace-nowrap"><i class="fa-solid fa-check"></i> Ada Foto</span>'
            :'<span class="bg-red-100 text-red-700 text-[10px] font-bold px-2.5 py-1 rounded-md border border-red-200 inline-flex items-center gap-1.5 whitespace-nowrap"><i class="fa-solid fa-xmark"></i> Belum Ada</span>';
        const safeName=(p.name||'').replace(/'/g,"\\'").replace(/"/g,'&quot;');
        const safeDesc=(p.description||'').replace(/'/g,"\\'").replace(/"/g,'&quot;');
        const formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(p.price || 0);
        const formattedPokok = p.harga_pokok > 0
            ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(p.harga_pokok)
            : '<span class="text-slate-300">-</span>';
        tr.innerHTML=`
            <td class="px-5 py-3"><img src="${escHtml(p.image)}" alt="" class="w-12 h-12 object-cover rounded shadow-sm border border-slate-200"></td>
            <td class="px-5 py-3 font-mono text-xs text-slate-500">${escHtml(p.id)||'-'}</td>
            <td class="px-5 py-3 font-bold text-slate-800">${escHtml(p.name)||''}</td>
            <td class="px-5 py-3 text-xs"><span class="bg-slate-100 text-slate-600 px-2 py-1 rounded font-semibold">${escHtml(p.category)||'Lainnya'}</span></td>
            <td class="px-5 py-3 text-right font-bold text-astra-700">${formattedPrice}</td>
            <td class="px-5 py-3 text-right font-medium text-slate-500">${formattedPokok}</td>
            <td class="px-5 py-3 text-center font-bold ${(p.stock||0)<5?'text-orange-500':'text-slate-700'}">${p.stock||0}</td>
            <td class="px-5 py-3 text-center">${photoBadge}</td>
            <td class="px-5 py-3 text-center">
                <button onclick="toggleVisibility('${p.id}')" title="${hiddenProductIds.indexOf(p.id) !== -1 ? 'Tampilkan' : 'Sembunyikan'}" class="text-base ${hiddenProductIds.indexOf(p.id) !== -1 ? 'text-slate-300 hover:text-amber-500' : 'text-slate-600 hover:text-amber-500'} transition-colors">
                    <i class="fa-solid ${hiddenProductIds.indexOf(p.id) !== -1 ? 'fa-eye-slash' : 'fa-eye'}"></i>
                </button>
            </td>
            <td class="px-5 py-3 text-center">
                <button onclick="openEditModal('${p.id}')" class="bg-astra-600 hover:bg-astra-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1 mx-auto shadow-sm">
                    <i class="fa-solid fa-pen-to-square"></i> Kelola
                </button>
            </td>`;
        tbody.appendChild(tr);
    });
}

async function toggleVisibility(id) {
    const formData = new FormData();
    formData.append('action', 'toggle_produk');
    formData.append('id', id);
    try {
        const res = await fetch('update_kategori.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
            if (hiddenProductIds.indexOf(id) === -1) hiddenProductIds.push(id);
            else hiddenProductIds = hiddenProductIds.filter(x => x !== id);
            applyAdminFilters();
            showNotification(result.message, 'success');
        }
    } catch(e) {
        showNotification('Gagal mengubah visibilitas', 'error');
    }
}

async function loadKategoriData() {
    try {
        const res = await fetch('update_kategori.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=get'
        });
        const result = await res.json();
        if (result.success && Array.isArray(result.data)) {
            kategoriData = result.data;
            var sel = document.getElementById('filter-kategori-admin');
            if (sel) {
                kategoriData.forEach(function(k) {
                    var opt = document.createElement('option');
                    opt.value = k.id;
                    opt.textContent = k.nama || k.id;
                    sel.appendChild(opt);
                });
            }
        }
    } catch(e) {
        console.warn('Failed to load kategori:', e);
    }
}

async function loadHiddenData() {
    try {
        const res = await fetch('update_kategori.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=get'
        });
        const result = await res.json();
        if (result.success && Array.isArray(result.hidden)) {
            hiddenProductIds = result.hidden;
        }
    } catch(e) {
        console.warn('Failed to load hidden:', e);
    }
}

async function loadSyncTime() {
    try {
        const res = await fetch('data/waktu_sync.json?_t=' + Date.now());
        if (res.ok) {
            var w = await res.json();
            document.getElementById('sync-time').textContent = w.terakhir || '--';
            document.getElementById('sync-products').textContent = (w.produk || 0) + ' produk';
            document.getElementById('sync-photos').textContent = (w.total_foto || 0) + ' foto';
        }
    } catch(e) {
        document.getElementById('sync-time').textContent = 'Belum sync';
    }
}

async function loadPromoGlobal() {
    try {
        const res = await fetch('data/produk_promo.json?_t=' + Date.now());
        if (res.ok) {
            promoDataGlobal = await res.json();
        }
    } catch(e) {
        promoDataGlobal = {};
    }
}

function updateStatCards() {
    var total = allProducts.length;
    var cats = {};
    var noPhoto = 0;
    var totalStok = 0;
    var totalNilai = 0;
    var totalModal = 0;
    allProducts.forEach(function(p) {
        if (p.category) cats[p.category] = true;
        var hasPhoto = (p.image||'') && !(p.image||'').includes('unsplash.com');
        if (!hasPhoto) noPhoto++;
        totalStok += (p.stock||0);
        totalNilai += (p.price||0) * (p.stock||0);
        totalModal += (parseFloat(p.harga_pokok)||0) * (p.stock||0);
    });
    var catCount = Object.keys(cats).length;
    document.getElementById('stat-total').textContent = total;
    document.getElementById('stat-kategori').textContent = catCount;
    document.getElementById('stat-nophoto').textContent = noPhoto;
    document.getElementById('stat-stok').textContent = totalStok;
    document.getElementById('stat-nilai').textContent = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(totalNilai);
    document.getElementById('stat-modal').textContent = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(totalModal);
}

// ── KATEGORI PANEL ──
var kategoriEditData = [];

async function loadKategoriPanel() {
    document.getElementById('kategori-loading').classList.remove('hidden');
    document.getElementById('kategori-table-wrap').classList.add('hidden');
    try {
        const res = await fetch('update_kategori.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=get'
        });
        const result = await res.json();
        if (!result.success) throw new Error(result.message);
        kategoriEditData = result.data || [];
        var newCats = result.new || [];
        renderKategoriTable(newCats);
        document.getElementById('kategori-loading').classList.add('hidden');
        document.getElementById('kategori-table-wrap').classList.remove('hidden');
    } catch(e) {
        document.getElementById('kategori-loading').innerHTML = '<p class="text-red-500"><i class="fa-solid fa-triangle-exclamation"></i> Gagal memuat: ' + e.message + '</p>';
    }
}

function renderKategoriTable(newCats) {
    var tbody = document.getElementById('kategori-table-body');
    tbody.innerHTML = '';
    var newIds = {};
    newCats.forEach(function(n) { newIds[n.id] = true; });

    kategoriEditData.forEach(function(k, i) {
        var tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors' + (newIds[k.id] ? ' bg-yellow-50' : '');
        var parentOpts = '<option value="">Tidak Ada (Root)</option>';
        kategoriEditData.forEach(function(p) {
            if (p.id !== k.id && !p.parent) {
                parentOpts += '<option value="' + p.id + '"' + (p.id === k.parent ? ' selected' : '') + '>' + (p.nama || p.id) + '</option>';
            }
        });

        var escId = (k.nama || k.id || '').replace(/'/g, "\\'");
        tr.innerHTML = `
            <td class="px-4 py-3 text-xs text-slate-400">${i+1}</td>
            <td class="px-4 py-3"><input type="text" value="${escId}" data-idx="${i}" data-field="nama" class="kategori-input w-full bg-transparent border border-transparent hover:border-slate-300 focus:border-astra-500 rounded px-2 py-1 text-sm font-medium text-slate-800 outline-none transition-colors"></td>
            <td class="px-4 py-3 text-xs font-mono text-slate-500">${k.id}</td>
            <td class="px-4 py-3"><select data-idx="${i}" data-field="parent" class="kategori-input w-full bg-slate-50 border border-slate-200 rounded px-2 py-1 text-xs outline-none focus:border-astra-500">${parentOpts}</select></td>
            <td class="px-4 py-3 text-center"><input type="number" value="${k.urut||99}" data-idx="${i}" data-field="urut" class="kategori-input w-16 text-center bg-slate-50 border border-slate-200 rounded px-2 py-1 text-xs outline-none focus:border-astra-500"></td>
            <td class="px-4 py-3 text-center">
                <button onclick="toggleKategoriVis(${i})" class="text-lg ${k.visible === false ? 'text-slate-300 hover:text-green-500' : 'text-green-600 hover:text-slate-400'} transition-colors">
                    <i class="fa-solid ${k.visible === false ? 'fa-eye-slash' : 'fa-eye'}"></i>
                </button>
            </td>
            <td class="px-4 py-3 text-center">
                <button onclick="hapusKategori('${k.id}')" class="text-red-400 hover:text-red-600 transition-colors text-sm" title="Hapus kategori">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </td>`;
        tbody.appendChild(tr);
    });

    // Jika ada new cats, scroll ke bawah
    if (newCats.length > 0) {
        setTimeout(function() {
            tbody.parentElement.scrollTop = tbody.parentElement.scrollHeight;
        }, 100);
    }

    // Bind input events
    document.querySelectorAll('.kategori-input').forEach(function(el) {
        el.addEventListener('change', function() {
            var idx = parseInt(this.dataset.idx);
            var field = this.dataset.field;
            var val = field === 'urut' ? parseInt(this.value) || 99 : this.value;
            if (kategoriEditData[idx]) {
                kategoriEditData[idx][field] = val;
            }
        });
    });
}

function toggleKategoriVis(idx) {
    if (kategoriEditData[idx]) {
        kategoriEditData[idx].visible = kategoriEditData[idx].visible === false ? true : false;
        var btn = document.querySelector('#kategori-table-body tr:nth-child(' + (idx + 1) + ') button');
        if (btn) {
            var icon = btn.querySelector('i');
            if (kategoriEditData[idx].visible === false) {
                btn.className = 'text-lg text-slate-300 hover:text-green-500 transition-colors';
                icon.className = 'fa-solid fa-eye-slash';
            } else {
                btn.className = 'text-lg text-green-600 hover:text-slate-400 transition-colors';
                icon.className = 'fa-solid fa-eye';
            }
        }
    }
}

async function saveAllKategori() {
    try {
        var btn = document.querySelector('#panel-kategori .bg-astra-700');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> Menyimpan...';
        var res = await fetch('update_kategori.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=simpan&kategori=' + encodeURIComponent(JSON.stringify(kategoriEditData))
        });
        var result = await res.json();
        if (result.success) {
            showNotification('Kategori berhasil disimpan', 'success');
        } else {
            showNotification(result.message, 'error');
        }
    } catch(e) {
        showNotification('Gagal menyimpan: ' + e.message, 'error');
    } finally {
        var btn2 = document.querySelector('#panel-kategori .bg-astra-700');
        btn2.disabled = false;
        btn2.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Semua';
    }
}

function showTambahKategori() {
    var sel = document.getElementById('tk-parent');
    sel.innerHTML = '<option value="">Tidak Ada (Root)</option>';
    kategoriEditData.forEach(function(k) {
        if (!k.parent) {
            var opt = document.createElement('option');
            opt.value = k.id;
            opt.textContent = (k.nama || k.id);
            sel.appendChild(opt);
        }
    });
    document.getElementById('tambah-kategori-modal').classList.remove('hidden');
    document.getElementById('tambah-kategori-modal').classList.add('flex');
    document.getElementById('tk-id').focus();
}

function closeTambahKategori() {
    document.getElementById('tambah-kategori-modal').classList.add('hidden');
    document.getElementById('tambah-kategori-modal').classList.remove('flex');
    document.getElementById('tambah-kategori-form').reset();
}

async function submitTambahKategori(e) {
    e.preventDefault();
    var id = document.getElementById('tk-id').value.trim().toUpperCase();
    var nama = document.getElementById('tk-nama').value.trim() || id;
    var parent = document.getElementById('tk-parent').value || '';
    var urut = parseInt(document.getElementById('tk-urut').value) || 99;
    var isGroup = document.getElementById('tk-is-group').checked;

    try {
        var btn = document.querySelector('#tambah-kategori-modal button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> Menambah...';

        var body = 'action=tambah&id=' + encodeURIComponent(id)
            + '&nama=' + encodeURIComponent(nama)
            + '&parent=' + encodeURIComponent(parent)
            + '&urut=' + urut
            + (isGroup ? '&is_group=1' : '');

        var res = await fetch('update_kategori.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: body
        });
        var result = await res.json();
        if (result.success) {
            showNotification(result.message, 'success');
            closeTambahKategori();
            loadKategoriPanel();
        } else {
            showNotification(result.message, 'error');
        }
    } catch(e) {
        showNotification('Gagal: ' + e.message, 'error');
    } finally {
        var btn2 = document.querySelector('#tambah-kategori-modal button[type="submit"]');
        btn2.disabled = false;
        btn2.innerHTML = '<i class="fa-solid fa-plus"></i> Tambah';
    }
}

async function hapusKategori(id) {
    if (!confirm('Hapus kategori "' + id + '"?\nAnak kategori akan otomatis menjadi root.')) return;
    try {
        var res = await fetch('update_kategori.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=hapus&id=' + encodeURIComponent(id)
        });
        var result = await res.json();
        if (result.success) {
            showNotification(result.message, 'success');
            loadKategoriPanel();
        } else {
            showNotification(result.message, 'error');
        }
    } catch(e) {
        showNotification('Gagal: ' + e.message, 'error');
    }
}

// ── PROMO PANEL ──
async function loadPromoPanel() {
    document.getElementById('promo-loading').classList.remove('hidden');
    document.getElementById('promo-table-wrap').classList.add('hidden');
    try {
        const res = await fetch('update_kategori.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=get'
        });
        const result = await res.json();
        if (!result.success) throw new Error(result.message);
        var promoData = result.promo || {};
        promoDataGlobal = promoData;
        var products = allProducts.length ? allProducts : [];
        renderPromoTable(promoData, products);
        document.getElementById('promo-loading').classList.add('hidden');
        document.getElementById('promo-table-wrap').classList.remove('hidden');
    } catch(e) {
        document.getElementById('promo-loading').innerHTML = '<p class="text-red-500"><i class="fa-solid fa-triangle-exclamation"></i> Gagal memuat: ' + e.message + '</p>';
    }
}

function renderPromoTable(promoData, products) {
    var tbody = document.getElementById('promo-table-body');
    var empty = document.getElementById('promo-empty');
    tbody.innerHTML = '';

    var entries = Object.keys(promoData);
    if (entries.length === 0) {
        empty.classList.remove('hidden');
        return;
    }
    empty.classList.add('hidden');

    entries.forEach(function(id) {
        var p = products.find(function(x) { return x.id === id; });
        var promo = promoData[id];
        if (!promo) return;
        var name = p ? (p.name || id) : id;
        var img = p ? (p.image || '') : '';
        var price = p ? (p.price || 0) : 0;
        var formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(price);
        var formattedCoret = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(promo.harga_coret || 0);
        var label = promo.label || '-';

        var tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors';
        tr.innerHTML = `
            <td class="px-4 py-3"><img src="${img}" alt="" class="w-10 h-10 object-cover rounded border border-slate-200"></td>
            <td class="px-4 py-3 font-medium text-slate-800 text-sm">${name}<br><span class="text-[10px] font-mono text-slate-400">${id}</span></td>
            <td class="px-4 py-3 text-right font-bold text-slate-600 text-sm">${formattedPrice}</td>
            <td class="px-4 py-3 text-right font-bold text-red-600 text-sm">${formattedCoret}</td>
            <td class="px-4 py-3 text-center"><span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-1 rounded">${label}</span></td>
            <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                    <button onclick="editPromo('${id}')" class="text-blue-500 hover:text-blue-700 text-xs font-bold transition-colors" title="Edit"><i class="fa-solid fa-pen"></i></button>
                    <button onclick="hapusPromo('${id}')" class="text-red-500 hover:text-red-700 text-xs font-bold transition-colors" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                </div>
            </td>`;
        tbody.appendChild(tr);
    });
}

async function hapusPromo(id) {
    if (!confirm('Hapus promo untuk produk ini?')) return;
    try {
        var res = await fetch('update_kategori.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=hapus_promo&id=' + encodeURIComponent(id)
        });
        var result = await res.json();
        if (result.success) {
            showNotification('Promo dihapus', 'success');
            loadPromoPanel();
        } else {
            showNotification(result.message, 'error');
        }
    } catch(e) {
        showNotification('Gagal: ' + e.message, 'error');
    }
}

// ── BUAT / EDIT PROMO ──
var _bpSelectedId = null;
var _bpJual = 0;

function showBuatPromo(id) {
    document.getElementById('bp-id').value = id || '';
    document.getElementById('bp-title').textContent = id ? 'Edit Promo' : 'Buat Promo';
    document.getElementById('bp-selected').classList.add('hidden');
    document.getElementById('bp-search').value = '';
    document.getElementById('bp-search-results').classList.add('hidden');
    document.getElementById('bp-harga-coret').value = '';
    document.getElementById('bp-label').value = '';
    document.getElementById('bp-auto-label').checked = false;
    document.getElementById('bp-label').readOnly = false;
    _bpSelectedId = id || null;
    _bpJual = 0;

    if (id) {
        var p = allProducts.find(function(x) { return x.id === id; });
        var promo = promoDataGlobal && promoDataGlobal[id];
        if (p) {
            _bpJual = p.price || 0;
            _bpSelectedId = id;
            document.getElementById('bp-search').value = p.name || id;
            document.getElementById('bp-selected').classList.remove('hidden');
            document.getElementById('bp-selected-img').src = p.image || '';
            document.getElementById('bp-selected-name').textContent = p.name || id;
            document.getElementById('bp-selected-price').textContent = 'Rp' + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(p.price || 0);
            document.getElementById('bp-harga-coret').value = promo ? (promo.harga_coret || '') : '';
            var lbl = promo ? (promo.label || '') : '';
            document.getElementById('bp-label').value = lbl;
            var isAuto = /^Diskon \d+%$/.test(lbl);
            document.getElementById('bp-auto-label').checked = isAuto;
            document.getElementById('bp-label').readOnly = isAuto;
        }
    }

    document.getElementById('buat-promo-modal').classList.remove('hidden');
    document.getElementById('buat-promo-modal').classList.add('flex');
    if (!id) document.getElementById('bp-search').focus();
}

function closeBuatPromo() {
    document.getElementById('buat-promo-modal').classList.add('hidden');
    document.getElementById('buat-promo-modal').classList.remove('flex');
    _bpSelectedId = null;
}

function filterPromoProduct(q) {
    var results = document.getElementById('bp-search-results');
    if (q.trim().length < 1) {
        results.classList.add('hidden');
        results.innerHTML = '';
        return;
    }
    var ql = q.toLowerCase();
    var matches = allProducts.filter(function(p) {
        return (p.name || '').toLowerCase().includes(ql) || (p.id || '').toLowerCase().includes(ql);
    }).slice(0, 20);
    if (matches.length === 0) {
        results.innerHTML = '<div class="px-3 py-2 text-xs text-slate-400">Tidak ditemukan</div>';
        results.classList.remove('hidden');
        return;
    }
    results.innerHTML = '';
    matches.forEach(function(p) {
        var d = document.createElement('div');
        d.className = 'px-3 py-2 text-sm cursor-pointer hover:bg-astra-50 flex items-center gap-2';
        d.innerHTML = '<img src="' + (p.image || '') + '" class="w-7 h-7 object-cover rounded border border-slate-200 flex-shrink-0">'
            + '<span class="font-medium text-slate-800">' + (p.name || '') + '</span>'
            + '<span class="text-[10px] font-mono text-slate-400 ml-auto">' + (p.id || '') + '</span>';
        d.onclick = function() { pilihPromo(p.id, p.name, p.price, p.image); };
        results.appendChild(d);
    });
    results.classList.remove('hidden');
}

function pilihPromo(id, name, price, img) {
    _bpSelectedId = id;
    _bpJual = price || 0;
    document.getElementById('bp-id').value = id;
    document.getElementById('bp-search').value = name || id;
    document.getElementById('bp-search-results').classList.add('hidden');
    document.getElementById('bp-selected').classList.remove('hidden');
    document.getElementById('bp-selected-img').src = img || '';
    document.getElementById('bp-selected-name').textContent = name || id;
    document.getElementById('bp-selected-price').textContent = 'Rp' + new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0 }).format(price || 0);
}

function bpToggleAuto() {
    var auto = document.getElementById('bp-auto-label').checked;
    var label = document.getElementById('bp-label');
    label.readOnly = auto;
    if (auto) bpAutoLabel();
}

function bpAutoLabel() {
    if (!document.getElementById('bp-auto-label').checked) return;
    var coret = parseInt(document.getElementById('bp-harga-coret').value) || 0;
    var label = document.getElementById('bp-label');
    if (coret > 0 && _bpJual > 0 && coret > _bpJual) {
        var pct = Math.round((coret - _bpJual) / coret * 100);
        label.value = 'Diskon ' + pct + '%';
    } else {
        label.value = '';
    }
}

async function submitBuatPromo(e) {
    e.preventDefault();
    var id = _bpSelectedId;
    if (!id) {
        showNotification('Pilih produk terlebih dahulu.', 'error');
        return;
    }
    var harga_coret = parseInt(document.getElementById('bp-harga-coret').value) || 0;
    var label = document.getElementById('bp-label').value.trim();

    try {
        var btn = document.querySelector('#buat-promo-modal button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> Menyimpan...';

        var body = 'action=simpan_promo&id=' + encodeURIComponent(id)
            + '&harga_coret=' + harga_coret
            + '&label=' + encodeURIComponent(label);

        var res = await fetch('update_kategori.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: body
        });
        var result = await res.json();
        if (result.success) {
            showNotification(result.message, 'success');
            closeBuatPromo();
            loadPromoPanel();
        } else {
            showNotification(result.message, 'error');
        }
    } catch(e) {
        showNotification('Gagal: ' + e.message, 'error');
    } finally {
        var btn2 = document.querySelector('#buat-promo-modal button[type="submit"]');
        btn2.disabled = false;
        btn2.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Promo';
    }
}

function editPromo(id) {
    showBuatPromo(id);
}

function openEditModal(id){
    const p = allProducts.find(x => x.id === id);
    if(!p) return;
    window._currentEditPrice = p.price;
    document.getElementById('modal-id').value=id;
    document.getElementById('modal-name').value=(p.name||'').replace(/&quot;/g,'"');
    document.getElementById('modal-harga-pokok').value=p.harga_pokok>0
        ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(p.harga_pokok)
        : '-';
    document.getElementById('modal-desc').value=(p.description||'').replace(/&quot;/g,'"');
    document.getElementById('modal-foto').value='';

    document.getElementById('modal-harga-jual').value = p.price > 0
        ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(p.price)
        : '-';

    // Promo fields
    document.getElementById('modal-harga-coret').value = (promoDataGlobal && promoDataGlobal[id] && promoDataGlobal[id].harga_coret) || '';
    var existingLabel = (promoDataGlobal && promoDataGlobal[id] && promoDataGlobal[id].label) || '';
    document.getElementById('modal-label-promo').value = existingLabel;

    // Auto-label checkbox
    var isAuto = /^Diskon \d+%$/.test(existingLabel);
    document.getElementById('auto-label').checked = isAuto;
    document.getElementById('modal-label-promo').readOnly = isAuto;

    renderSavedPhotos(id, p.images);
    document.getElementById('edit-modal').classList.remove('hidden');
    document.getElementById('edit-modal').classList.add('flex');
}

function toggleAutoLabel() {
    var auto = document.getElementById('auto-label').checked;
    var label = document.getElementById('modal-label-promo');
    label.readOnly = auto;
    if (auto) autoLabelHandler();
}

function autoLabelHandler() {
    if (!document.getElementById('auto-label').checked) return;
    var coret = parseInt(document.getElementById('modal-harga-coret').value) || 0;
    var jual = window._currentEditPrice || 0;
    var label = document.getElementById('modal-label-promo');
    if (coret > 0 && jual > 0 && coret > jual) {
        var pct = Math.round((coret - jual) / coret * 100);
        label.value = 'Diskon ' + pct + '%';
    } else {
        label.value = '';
    }
}

let currentEditId = '';
let currentEditImages = [];
let currentNewImageCounter = 0;

function imageItemFromSource(src) {
    return { type: 'existing', src };
}

function imageItemFromFile(file) {
    currentNewImageCounter += 1;
    return { type: 'new', src: URL.createObjectURL(file), file, tempId: 'new_' + currentNewImageCounter };
}

function normalizeImageItems(images) {
    if (!Array.isArray(images)) return [];
    return images
        .filter(img => typeof img === 'string' && !img.includes('unsplash.com'))
        .map(imageItemFromSource);
}

function renderSavedPhotos(id, images) {
    currentEditId = id;
    if (Array.isArray(images) && images.length && typeof images[0] === 'object' && images[0] !== null) {
        currentEditImages = images;
    } else {
        currentEditImages = normalizeImageItems(images);
    }

    const container = document.getElementById('saved-photos-section');
    const list = document.getElementById('saved-photos-list');
    list.innerHTML = '';

    if (currentEditImages.length === 0) {
        container.classList.add('hidden');
        return;
    }

    container.classList.remove('hidden');
    currentEditImages.forEach((item, idx) => {
        const element = document.createElement('div');
        element.className = "flex-shrink-0 relative w-24 h-24 rounded-lg border border-slate-200 overflow-hidden group snap-center bg-slate-50 shadow-sm cursor-pointer";
        element.innerHTML = `
            <img src="${item.src}" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-slate-900/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-1.5">
                <button type="button" onclick="deleteSavedPhoto(${idx})" class="self-end text-white hover:text-red-400 transition-colors"><i class="fa-solid fa-trash"></i></button>
                <div class="flex justify-between w-full">
                    <button type="button" onclick="moveSavedPhoto(${idx}, -1)" class="text-white hover:text-astra-400 transition-colors ${idx===0?'invisible':''}"><i class="fa-solid fa-circle-chevron-left text-lg"></i></button>
                    <button type="button" onclick="moveSavedPhoto(${idx}, 1)" class="text-white hover:text-astra-400 transition-colors ${idx===currentEditImages.length-1?'invisible':''}"><i class="fa-solid fa-circle-chevron-right text-lg"></i></button>
                </div>
            </div>
        `;
        element.addEventListener('click', (event) => {
            if (event.target.closest('button')) return;
            openPhotoPreview(item.src);
        });
        list.appendChild(element);
    });
}

function handlePhotoUpload(event) {
    const files = Array.from(event.target.files || []);
    if (!files.length) return;

    files.forEach(file => currentEditImages.push(imageItemFromFile(file)));
    renderSavedPhotos(currentEditId, currentEditImages);
    event.target.value = '';
}

function updateProductImagesInMemory(id, images) {
    const product = allProducts.find(p => p.id === id);
    if (!product) return;
    const fallback = 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=500';
    product.images = images;
    product.image = images.length ? images[0] : fallback;
}

function deleteSavedPhoto(idx) {
    showConfirmModal('Hapus foto ini permanen?', function() {
    const fileItem = currentEditImages[idx];
    if (fileItem.type === 'existing') {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', currentEditId);
        formData.append('file', fileItem.src);
        fetch('api_manage_photos.php', { method:'POST', body:formData })
          .then(r=>r.json()).then(data=>{
              // Hapus dari array terlepas dari hasil, agar tidak ada referensi stale
              currentEditImages.splice(idx, 1);
              renderSavedPhotos(currentEditId, currentEditImages);
              updateProductImagesInMemory(currentEditId, currentEditImages.filter(i=>i.type==='existing').map(i=>i.src));
              applyAdminFilters();

              if(data.success && !data.warning){
                  showNotification('Foto berhasil dihapus!', 'success');
              } else if(data.success && data.warning){
                  showNotification('Foto sudah tidak tersedia dan telah dihapus dari tampilan.', 'info');
              } else {
                  showNotification(data.message, 'error');
              }
          }).catch(() => showNotification('Terjadi kesalahan koneksi.', 'error'));
    } else {
        currentEditImages.splice(idx, 1);
        renderSavedPhotos(currentEditId, currentEditImages);
    }
    });
}

function moveSavedPhoto(idx, dir) {
    if (idx + dir < 0 || idx + dir >= currentEditImages.length) return;
    const temp = currentEditImages[idx];
    currentEditImages[idx] = currentEditImages[idx+dir];
    currentEditImages[idx+dir] = temp;
    renderSavedPhotos(currentEditId, currentEditImages);
    // Update table thumbnail & memory immediately
    const orderSrcs = currentEditImages.filter(i => i.type === 'existing').map(i => i.src);
    if (orderSrcs.length > 0 && currentEditImages.every(i => i.type === 'existing')) {
        updateProductImagesInMemory(currentEditId, orderSrcs);
        applyAdminFilters();
    }
}

function openPhotoPreview(src) {
    const modal = document.getElementById('photo-preview-modal');
    const img = document.getElementById('preview-image');
    img.src = src;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closePhotoPreview() {
    const modal = document.getElementById('photo-preview-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.getElementById('preview-image').src = '';
}

function closeEditModal(){document.getElementById('edit-modal').classList.add('hidden');document.getElementById('edit-modal').classList.remove('flex');}

function submitForm(event){
    event.preventDefault();
    const btn=document.getElementById('btn-submit');
    btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner animate-spin"></i> Menyimpan...';

    const formData = new FormData();
    formData.append('id', document.getElementById('modal-id').value);
    formData.append('description', document.getElementById('modal-desc').value);
    formData.append('harga_coret', document.getElementById('modal-harga-coret').value);
    formData.append('label_promo', document.getElementById('modal-label-promo').value);

    const imageOrder = currentEditImages.map(item => item.type === 'existing' ? item.src : item.tempId);
    formData.append('image_order', JSON.stringify(imageOrder));
    currentEditImages.filter(item => item.type === 'new').forEach(item => formData.append('new_files[]', item.file));

    fetch('update_produk.php',{method:'POST',body:formData})
        .then(r=>r.json()).then(data=>{
            if(data.success && !data.warning){showNotification('Data berhasil diperbarui!', 'success');closeEditModal();fetchProducts();}
            else if(data.success && data.warning){showNotification(data.message, 'info');closeEditModal();fetchProducts();}
            else showNotification('Error: '+data.message, 'error');
        }).catch(()=>showNotification('Terjadi kesalahan jaringan.', 'error'))
        .finally(()=>{btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan';});
}

// KELOLA ADMIN
function loadAdminList(){
    const tbody=document.getElementById('admin-list-body');
    if(!tbody) return;
    tbody.innerHTML='<tr><td colspan="5" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner animate-spin mr-2"></i> Memuat...</td></tr>';
    const fd=new FormData(); fd.append('action','get_admins');
    fetch('update_admin.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
        if(!data.success){tbody.innerHTML=`<tr><td colspan="5" class="text-center py-8 text-red-500">${data.message}</td></tr>`;return;}
        tbody.innerHTML='';
        data.data.forEach(a=>{
            const tr=document.createElement('tr'); tr.className="hover:bg-slate-50";
            const roleBadge=a.role==='super_admin'
                ?'<span class="bg-yellow-100 border border-yellow-300 text-yellow-700 text-[10px] font-bold px-2 py-1 rounded-full">Super Admin</span>'
                :'<span class="bg-blue-100 border border-blue-300 text-blue-700 text-[10px] font-bold px-2 py-1 rounded-full">Admin</span>';
            const isSelf=a.id===CURRENT_ADMIN_ID;
            let deleteBtn = '';
            if (!isSelf) {
                deleteBtn = "<button onclick=\"hapusAdmin('" + a.id + "','" + escHtml(a.username) + "')\" class=\"bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1.5 rounded-lg text-xs font-bold\">\n                            <i class=\"fa-solid fa-trash\"></i>\n                        </button>";
            }
            tr.innerHTML=`
                <td class="px-5 py-3 font-semibold text-slate-800">${escHtml(a.nama)}${isSelf?' <span class="text-[10px] text-astra-600 font-bold">(Anda)</span>':''}</td>
                <td class="px-5 py-3 font-mono text-sm text-slate-600">@${escHtml(a.username)}</td>
                <td class="px-5 py-3 text-center">${roleBadge}</td>
                <td class="px-5 py-3 text-center text-xs text-slate-500">${a.created_at||'-'}</td>
                <td class="px-5 py-3 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <button onclick="openModalAdmin('edit','${a.id}','${escHtml(a.username)}','${escHtml(a.nama)}','${a.role}')"
                            class="bg-astra-100 hover:bg-astra-200 text-astra-800 px-3 py-1.5 rounded-lg text-xs font-bold">
                            <i class="fa-solid fa-pen"></i> Edit
                        </button>
                        ${deleteBtn}
                    </div>
                </td>`;
            tbody.appendChild(tr);
        });
    }).catch(()=>{tbody.innerHTML='<tr><td colspan="5" class="text-center py-8 text-red-500">Gagal memuat data admin.</td></tr>';});
}

function openModalAdmin(mode,id='',username='',nama='',role='admin'){
    document.getElementById('modal-admin').classList.remove('hidden');
    document.getElementById('modal-admin').classList.add('flex');
    document.getElementById('modal-admin-feedback').classList.add('hidden');
    document.getElementById('modal-admin-action').value=mode==='tambah'?'tambah_admin':'edit_admin';
    document.getElementById('modal-admin-target-id').value=id;
    document.getElementById('modal-admin-username').value=username;
    document.getElementById('modal-admin-nama').value=nama;
    document.getElementById('modal-admin-password').value='';
    document.getElementById('modal-admin-role').value=role;
    const title=document.getElementById('modal-admin-title');
    const hint=document.getElementById('pw-hint');
    if(mode==='tambah'){title.innerHTML='<i class="fa-solid fa-user-plus text-astra-400"></i> Tambah Admin Baru';hint.textContent='(wajib diisi)';}
    else{title.innerHTML='<i class="fa-solid fa-user-gear text-astra-400"></i> Edit Admin';hint.textContent='(kosongkan jika tidak diubah)';}
}
function closeModalAdmin(){document.getElementById('modal-admin').classList.add('hidden');document.getElementById('modal-admin').classList.remove('flex');}

function submitAdmin(){
    const btn=document.getElementById('btn-admin-submit');
    const fb=document.getElementById('modal-admin-feedback');
    btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner animate-spin"></i> Menyimpan...';
    const fd=new FormData();
    fd.append('action',document.getElementById('modal-admin-action').value);
    fd.append('target_id',document.getElementById('modal-admin-target-id').value);
    fd.append('username',document.getElementById('modal-admin-username').value);
    fd.append('nama',document.getElementById('modal-admin-nama').value);
    fd.append('password',document.getElementById('modal-admin-password').value);
    fd.append('role',document.getElementById('modal-admin-role').value);
    fetch('update_admin.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
        fb.classList.remove('hidden');
        if(data.success){
            fb.className='text-sm font-semibold p-3 rounded-lg bg-green-50 text-green-700 border border-green-200';
            fb.innerHTML='<i class="fa-solid fa-check-circle mr-1"></i>'+data.message;
            setTimeout(()=>{closeModalAdmin();loadAdminList();},1200);



        }else{
            fb.className='text-sm font-semibold p-3 rounded-lg bg-red-50 text-red-700 border border-red-200';
            fb.innerHTML='<i class="fa-solid fa-triangle-exclamation mr-1"></i>'+data.message;
        }
    }).catch(()=>{fb.classList.remove('hidden');fb.className='text-sm font-semibold p-3 rounded-lg bg-red-50 text-red-700 border border-red-200';fb.textContent='Gagal. Cek koneksi.';})
    .finally(()=>{btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-floppy-disk"></i> Simpan';});
}

function hapusAdmin(id,username){
    showConfirmModal(`Hapus admin "@${username}"? Tindakan ini tidak dapat dibatalkan.`, function() {
    const fd=new FormData(); fd.append('action','hapus_admin'); fd.append('target_id',id);
    fetch('update_admin.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
        if(data.success) loadAdminList(); else showNotification('Gagal: '+data.message, 'error');
    });
    });
}

// PROFIL SAYA
function submitProfil(){
    const fb=document.getElementById('profil-feedback');
    fb.classList.add('hidden');
    const fd=new FormData();
    fd.append('action','edit_admin');
    fd.append('target_id',document.getElementById('profil-target-id').value);
    fd.append('username',document.getElementById('profil-username').value);
    fd.append('nama',document.getElementById('profil-nama').value);
    fd.append('password',document.getElementById('profil-password').value);
    fetch('update_admin.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
        fb.classList.remove('hidden');
        if(data.success){fb.className='text-sm font-semibold text-green-600';fb.innerHTML='<i class="fa-solid fa-check-circle mr-1"></i>'+data.message;document.getElementById('profil-password').value='';}
        else{fb.className='text-sm font-semibold text-red-600';fb.innerHTML='<i class="fa-solid fa-triangle-exclamation mr-1"></i>'+data.message;}
        setTimeout(()=>fb.classList.add('hidden'),4000);
    }).catch(()=>{fb.classList.remove('hidden');fb.className='text-sm font-semibold text-red-600';fb.textContent='Gagal. Cek koneksi.';});
}

function formatRupiah(n){
    return 'Rp' + Number(n).toLocaleString('id-ID');
}

// ─── ASET PANEL ─────────────────────────────────────────────────────────
let asetData = [];

async function loadAset() {
    try {
        const res = await fetch('api_aset.php?_t=' + Date.now());
        asetData = await res.json();
        if (!Array.isArray(asetData)) asetData = [];

        // Populate kategori filter
        const kategoriSet = new Set();
        asetData.forEach(p => kategoriSet.add(p.kategori));
        const sel = document.getElementById('aset-kategori');
        sel.innerHTML = '<option value="all">Semua Kategori</option>';
        [...kategoriSet].sort().forEach(k => {
            sel.innerHTML += '<option value="' + escAttr(k) + '">' + escAttr(k) + '</option>';
        });

        renderAset();
    } catch (e) {
        document.getElementById('aset-tbody').innerHTML = '<tr><td colspan="8" class="text-center py-8 text-red-500">Gagal memuat data</td></tr>';
    }
}

function renderAset() {
    const search = (document.getElementById('aset-search').value || '').toLowerCase();
    const kategori = document.getElementById('aset-kategori').value;
    const sort = document.getElementById('aset-sort').value;

    let filtered = asetData.filter(p => {
        if (kategori !== 'all' && p.kategori !== kategori) return false;
        if (search && !p.nama.toLowerCase().includes(search) && !p.kode.toLowerCase().includes(search)) return false;
        return true;
    });

    filtered.sort((a, b) => {
        switch (sort) {
            case 'nama-desc': return a.nama.localeCompare(b.nama) * -1;
            case 'modal-desc': return b.total_modal - a.total_modal;
            case 'modal-asc': return a.total_modal - b.total_modal;
            case 'jual-desc': return b.total_nilai_jual - a.total_nilai_jual;
            case 'jual-asc': return a.total_nilai_jual - b.total_nilai_jual;
            default: return a.nama.localeCompare(b.nama);
        }
    });

    // Update summary
    let totalModal = 0, totalJual = 0;
    filtered.forEach(p => { totalModal += p.total_modal; totalJual += p.total_nilai_jual; });
    document.getElementById('aset-total-modal').textContent = formatRupiah(totalModal);
    document.getElementById('aset-total-jual').textContent = formatRupiah(totalJual);
    document.getElementById('aset-total-item').textContent = filtered.length;

    const tbody = document.getElementById('aset-tbody');
    if (filtered.length === 0) {
        tbody.innerHTML = '';
        document.getElementById('aset-empty').classList.remove('hidden');
        return;
    }
    document.getElementById('aset-empty').classList.add('hidden');

    tbody.innerHTML = filtered.map(p => `
        <tr class="border-b border-slate-100 hover:bg-slate-50">
            <td class="py-2.5 px-2 font-mono text-xs text-slate-500">${escAttr(p.kode)}</td>
            <td class="py-2.5 px-2 font-semibold text-slate-800">${escAttr(p.nama)}</td>
            <td class="py-2.5 px-2 text-slate-500">${escAttr(p.kategori)}</td>
            <td class="py-2.5 px-2 text-right font-semibold">${Number(p.stok).toLocaleString('id-ID')}</td>
            <td class="py-2.5 px-2 text-right text-slate-600">${formatRupiah(p.harga_pokok)}</td>
            <td class="py-2.5 px-2 text-right text-slate-600">${formatRupiah(p.harga_jual)}</td>
            <td class="py-2.5 px-2 text-right font-semibold text-emerald-700">${formatRupiah(p.total_modal)}</td>
            <td class="py-2.5 px-2 text-right font-semibold text-blue-700">${formatRupiah(p.total_nilai_jual)}</td>
        </tr>
    `).join('');
}

// ─── HUTANG PANEL ────────────────────────────────────────────────────────
let hutangData = { data: [], grand_total_faktur: 0, grand_total_sisa: 0, total: 0 };
let hutangTipeFilter = '';

function setHutangTipe(tipe) {
    hutangTipeFilter = tipe;
    document.querySelectorAll('#hutang-tipe-filter button').forEach(function(b) {
        var match = (tipe === '' && b.textContent.includes('Semua')) || (tipe === 'BL' && b.textContent.includes('BL')) || (tipe === 'KI' && b.textContent.includes('KI'));
        b.className = 'px-3 py-1.5 text-xs font-bold rounded-md transition-all ' + (match ? 'bg-white text-astra-700 shadow-sm' : 'text-slate-500 hover:text-slate-700');
    });
    loadHutang();
}

async function loadHutang() {
    try {
        var params = '?_t=' + Date.now();
        if (hutangTipeFilter) params += '&tipe=' + hutangTipeFilter;
        params += '&sort=' + document.getElementById('hutang-sort').value;
        const res = await fetch('api_hutang.php' + params);
        hutangData = await res.json();
        if (!hutangData || !Array.isArray(hutangData.data)) hutangData = { data: [], grand_total_faktur: 0, grand_total_sisa: 0, total: 0 };
        renderHutang();
    } catch (e) {
        document.getElementById('hutang-tbody').innerHTML = '<tr><td colspan="10" class="text-center py-8 text-red-500">Gagal memuat data</td></tr>';
    }
}

function renderHutang() {
    const search = (document.getElementById('hutang-search').value || '').toLowerCase();

    let filtered = hutangData.data.filter(function(h) {
        if (search && !h.supplier.toLowerCase().includes(search) && !h.no_faktur.toLowerCase().includes(search)) return false;
        return true;
    });

    document.getElementById('hutang-grand-faktur').textContent = formatRupiah(hutangData.grand_total_faktur);
    document.getElementById('hutang-grand-sisa').textContent = formatRupiah(hutangData.grand_total_sisa);

    const tbody = document.getElementById('hutang-tbody');
    if (filtered.length === 0) {
        tbody.innerHTML = '';
        document.getElementById('hutang-empty').classList.remove('hidden');
        return;
    }
    document.getElementById('hutang-empty').classList.add('hidden');

    tbody.innerHTML = filtered.map(function(h) {
        var tipeBadge = h.tipe === 'KI'
            ? '<span class="bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full text-xs font-bold">Kongsi</span>'
            : '<span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full text-xs font-bold">Hutang</span>';
        var statusBadge = '';
        if (h.status === 'lunas') statusBadge = '<span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs font-bold">Lunas</span>';
        else if (h.status === 'terlambat') statusBadge = '<span class="bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-xs font-bold">Terlambat</span>';
        else statusBadge = '<span class="bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full text-xs font-bold">Aktif</span>';

        var jatuhTempo = h.tgl_jatuh_tempo ? formatDate(h.tgl_jatuh_tempo) : '-';

        return '<tr class="border-b border-slate-100 hover:bg-slate-50">'
            + '<td class="py-2.5 px-2 font-mono text-xs text-slate-500">' + escAttr(h.no_faktur) + '</td>'
            + '<td class="py-2.5 px-2 font-semibold text-slate-800">' + escAttr(h.supplier) + '</td>'
            + '<td class="py-2.5 px-2">' + tipeBadge + '</td>'
            + '<td class="py-2.5 px-2 text-slate-500">' + formatDate(h.tanggal) + '</td>'
            + '<td class="py-2.5 px-2 text-right">' + formatRupiah(h.total_faktur) + '</td>'
            + '<td class="py-2.5 px-2 text-right font-bold ' + (h.total_sisa > 0 ? 'text-red-600' : 'text-green-600') + '">' + formatRupiah(h.total_sisa) + '</td>'
            + '<td class="py-2.5 px-2 text-slate-500">' + jatuhTempo + '</td>'
            + '<td class="py-2.5 px-2 text-center">' + statusBadge + '</td>'
            + '<td class="py-2.5 px-2 text-right text-slate-500">' + (h.hari_terlambat > 0 ? h.hari_terlambat + ' hari' : '-') + '</td>'
            + '<td class="py-2.5 px-2 text-slate-500 max-w-[200px] truncate">' + escAttr(h.keterangan || '-') + '</td>'
            + '</tr>';
    }).join('');
}

// ─── PENGHASILAN PANEL ───────────────────────────────────────────────────
let penghasilanData = { summary: { total_transaksi: 0, total_penjualan: 0, total_item: 0, rata_rata: 0, label: '' }, transactions: [] };
let penghasilanRange = 'month';

function setPenghasilanRange(range) {
    penghasilanRange = range;
    document.querySelectorAll('#penghasilan-range-filter button').forEach(function(b) {
        var match = (b.textContent.includes('Hari Ini') && range === 'day') || (b.textContent.includes('Minggu') && range === 'week') || (b.textContent.includes('29-28') && range === 'month') || (b.textContent.includes('Kustom') && range === 'custom');
        b.className = 'px-3 py-1.5 text-xs font-bold rounded-md transition-all ' + (match ? 'bg-white text-astra-700 shadow-sm' : 'text-slate-500 hover:text-slate-700');
    });
    if (range === 'custom') {
        document.getElementById('penghasilan-custom-range').classList.remove('hidden');
        document.getElementById('penghasilan-custom-range').classList.add('flex');
    } else {
        document.getElementById('penghasilan-custom-range').classList.add('hidden');
        document.getElementById('penghasilan-custom-range').classList.remove('flex');
        loadPenghasilan();
    }
}

function applyPenghasilanCustom() {
    var start = document.getElementById('penghasilan-start').value;
    var end = document.getElementById('penghasilan-end').value;
    if (!start || !end) {
        showNotification('Pilih tanggal mulai dan selesai', 'error');
        return;
    }
    loadPenghasilan(start, end);
}

async function loadPenghasilan(start, end) {
    try {
        var params = '?_t=' + Date.now();
        if (start && end) {
            params += '&start=' + start + '&end=' + end;
        } else if (penghasilanRange === 'day') {
            params += '&range=day';
        } else if (penghasilanRange === 'week') {
            params += '&range=week';
        } else {
            params += '&range=month';
        }
        const res = await fetch('api_penghasilan.php' + params);
        penghasilanData = await res.json();
        if (!penghasilanData || !penghasilanData.summary) penghasilanData = { summary: { total_transaksi: 0, total_penjualan: 0, total_item: 0, rata_rata: 0, label: '' }, transactions: [] };
        renderPenghasilan();
    } catch (e) {
        document.getElementById('penghasilan-tbody').innerHTML = '<tr><td colspan="4" class="text-center py-8 text-red-500">Gagal memuat data</td></tr>';
    }
}

function renderPenghasilan() {
    const s = penghasilanData.summary;
    document.getElementById('penghasilan-label').textContent = s.label ? '\u2014 ' + s.label : '';
    document.getElementById('penghasilan-total').textContent = formatRupiah(s.total_penjualan);
    document.getElementById('penghasilan-transaksi').textContent = s.total_transaksi.toLocaleString('id-ID');
    document.getElementById('penghasilan-rata').textContent = formatRupiah(s.rata_rata);

    const tbody = document.getElementById('penghasilan-tbody');
    const tx = penghasilanData.transactions || [];
    if (tx.length === 0) {
        tbody.innerHTML = '';
        document.getElementById('penghasilan-empty').classList.remove('hidden');
        return;
    }
    document.getElementById('penghasilan-empty').classList.add('hidden');

    tbody.innerHTML = tx.map(function(t) {
        var itemList = (t.items || []).map(function(it) {
            return escAttr(it.nama || it.kode) + ' x' + Number(it.qty).toLocaleString('id-ID');
        }).join(', ');
        return '<tr class="border-b border-slate-100 hover:bg-slate-50">'
            + '<td class="py-2.5 px-2 font-mono text-xs text-slate-500">' + escAttr(t.no_faktur) + '</td>'
            + '<td class="py-2.5 px-2 text-slate-600">' + formatDate(t.tanggal) + '</td>'
            + '<td class="py-2.5 px-2 text-right font-semibold text-green-700">' + formatRupiah(t.total) + '</td>'
            + '<td class="py-2.5 px-2 text-slate-500 text-xs max-w-[300px] truncate">' + (itemList || '-') + '</td>'
            + '</tr>';
    }).join('');
}

function escAttr(str){
    return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function formatDate(str){
    if(!str) return '-';
    const d=new Date(str+(str.includes('T')?'':'T00:00:00'));
    if(isNaN(d)) return str;
    return d.toLocaleDateString('id-ID',{year:'numeric',month:'short',day:'numeric'});
}

// ============================================================
</script>
</body>
</html>