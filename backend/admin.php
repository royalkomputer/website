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

$jam_buka     = loadJamOperasional();
$hari_inggris = date('l');
$jam_sekarang = date('H:i');
$hari_ini_jam = $jam_buka[$hari_inggris];
$hari_libur = !empty($hari_ini_jam['libur']);
$is_open_system = !$hari_libur && ($jam_sekarang >= $hari_ini_jam['buka'] && $jam_sekarang <= $hari_ini_jam['tutup']);

if ($current_status === 'tutup') {
    $notif_text  = "Pelanggan saat ini melihat: TOKO TUTUP SEMENTARA (Manual)";
    $notif_class = "bg-red-100 text-red-700 border-red-200";
    $notif_icon  = "fa-store-slash";
} elseif ($is_open_system) {
    $notif_text  = "Pelanggan saat ini melihat: TOKO BUKA (Sesuai Jam Operasional)";
    $notif_class = "bg-green-100 text-green-700 border-green-200";
    $notif_icon  = "fa-store";
} else {
    $notif_text  = "Pelanggan saat ini melihat: TOKO TUTUP (Luar Jam Operasional)";
    $notif_class = "bg-slate-200 text-slate-700 border-slate-300";
    $notif_icon  = "fa-moon";
}

$urutan_hari = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$heading = loadHeading();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <p class="text-slate-500 text-sm mt-1">Kelola katalog produk, jam operasional, dan akun admin.</p>
    </div>

    <!-- SYNC STATUS -->
    <?php
    $sync_file = __DIR__ . '/data/last_sync.json';
    $last_sync = null;
    if (file_exists($sync_file)) {
        $last_sync = json_decode(file_get_contents($sync_file), true);
    }
    ?>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm px-5 py-3 mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 text-sm">
            <i class="fa-solid fa-rotate text-astra-700"></i>
            <span class="text-slate-600">Sinkronisasi IPOS:</span>
            <?php if ($last_sync): ?>
                <span class="font-semibold text-slate-800"><?= date('d M Y H:i', strtotime($last_sync['last_sync'])) ?> WIB</span>
                <span class="text-slate-400 hidden sm:inline">(&bull; <?= number_format($last_sync['products']) ?> produk, <?= $last_sync['duration'] ?>s)</span>
            <?php else: ?>
                <span class="text-red-600 font-semibold">Belum pernah sinkron</span>
            <?php endif; ?>
        </div>
        <?php if ($last_sync): ?>
        <?php
        $sync_time = strtotime($last_sync['last_sync']);
        $diff_hours = (time() - $sync_time) / 3600;
        $badge_class = $diff_hours < 2 ? 'bg-green-100 text-green-700 border-green-200' : ($diff_hours < 6 ? 'bg-yellow-100 text-yellow-700 border-yellow-200' : 'bg-red-100 text-red-700 border-red-200');
        $badge_text = $diff_hours < 2 ? 'Terkini' : ($diff_hours < 6 ? $diff_hours < 2 ? 'Terkini' : round($diff_hours) . ' jam lalu' : '>' . round($diff_hours) . ' jam');
        ?>
        <span class="text-xs font-bold px-2.5 py-1 rounded-full border <?= $badge_class ?>">
            <?= $diff_hours < 2 ? '<i class="fa-solid fa-circle-check mr-1"></i>Terkini' : '<i class="fa-solid fa-clock mr-1"></i>' . round($diff_hours) . ' jam lalu' ?>
        </span>
        <?php endif; ?>
        <button onclick="triggerSync()" id="sync-btn" class="text-xs font-bold px-3 py-1.5 rounded-full border border-astra-700 bg-astra-700 text-white hover:bg-astra-800 transition-colors flex items-center gap-1.5">
            <i class="fa-solid fa-rotate" id="sync-icon"></i> Sync Now
        </button>
    </div>

    <!-- SYNC MODAL -->
    <div id="sync-modal" class="fixed inset-0 z-[200] hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 max-h-[80vh] flex flex-col">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                <h3 class="font-bold text-lg text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-rotate text-astra-700"></i> Sinkronisasi IPOS
                </h3>
                <button onclick="closeSyncModal()" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
            </div>
            <pre id="sync-output" class="p-6 overflow-auto text-sm font-mono bg-slate-900 text-green-300 leading-relaxed whitespace-pre-wrap flex-grow rounded-b-2xl">Memulai sinkronisasi...</pre>
        </div>
    </div>

    <!-- TAB NAV -->
    <div class="flex gap-2 mb-6 bg-white p-2 rounded-xl border border-slate-200 shadow-sm flex-wrap">
        <button onclick="switchTab('katalog')" id="tab-katalog" class="tab-btn active flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold">
            <i class="fa-solid fa-boxes-stacked"></i> Katalog Produk
        </button>
        <button onclick="switchTab('jam')" id="tab-jam" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-clock"></i> Jam Operasional
        </button>
        <button onclick="switchTab('schedule')" id="tab-schedule" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-calendar-xmark"></i> Tutup Sementara
        </button>
        <?php if ($is_super): ?>
        <button onclick="switchTab('admin')" id="tab-admin" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-users-gear"></i> Kelola Admin
        </button>
        <?php endif; ?>
        <button onclick="switchTab('ui')" id="tab-ui" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-paintbrush"></i> UI Toko
        </button>
        <button onclick="switchTab('banner')" id="tab-banner" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-images"></i> Playlist Banner
        </button>
        <button onclick="switchTab('profil')" id="tab-profil" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-circle-user"></i> Profil Saya
        </button>
        <button onclick="switchTab('serial')" id="tab-serial" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-barcode"></i> Serial Number
        </button>
        <button onclick="switchTab('push')" id="tab-push" class="tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100">
            <i class="fa-solid fa-upload"></i> Push ke Git
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Cari Produk / Kode</label>
                <div class="relative">
                    <input type="text" id="search-admin" oninput="handleAdminSearch(this.value)" placeholder="Ketik nama atau ID item..." class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-lg px-4 py-2.5 pl-10 focus:outline-none focus:border-astra-500 text-sm">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
                </div>
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
                            <th class="px-5 py-4 text-right">Harga</th><th class="px-5 py-4 text-center">Stok</th><th class="px-5 py-4 text-center">Status Foto</th>
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

    <!-- PANEL JAM OPERASIONAL -->
    <div id="panel-jam" class="hidden">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 max-w-2xl">
            <h3 class="font-extrabold text-slate-900 text-lg mb-1 flex items-center gap-2">
                <i class="fa-solid fa-clock text-astra-700"></i> Jadwal Jam Buka Toko
            </h3>
            <div class="mb-3">
                <div class="<?php echo $notif_class; ?> text-xs font-bold px-3 py-1.5 rounded inline-flex items-center gap-2 border">
                    <i class="fa-solid <?php echo $notif_icon; ?>"></i> <?php echo $notif_text; ?>
                </div>
            </div>
            <p class="text-sm text-slate-500 mb-6">Atur jam buka dan tutup setiap hari. Perubahan langsung berlaku di halaman toko.</p>
            <form id="form-jam" class="space-y-3">
                <?php foreach ($urutan_hari as $hari):
                    $d = $jam_buka[$hari];
                    $today = ($hari === $hari_inggris);
                    $is_libur = $d['libur'] ?? false;
                ?>
                <div class="grid grid-cols-[130px_auto_1fr_1fr] gap-3 items-center p-3 rounded-lg <?php echo $today ? 'bg-astra-50 border border-astra-200' : 'bg-slate-50 border border-slate-100'; ?>">
                    <div class="font-semibold text-sm <?php echo $today ? 'text-astra-700' : 'text-slate-700'; ?> flex items-center gap-2">
                        <?php if ($today): ?><span class="w-2 h-2 bg-astra-500 rounded-full"></span><?php endif; ?>
                        <?php echo $d['indo']; ?>
                        <?php if ($today): ?><span class="text-[10px] text-astra-500 font-bold">(Hari ini)</span><?php endif; ?>
                    </div>
                    <div>
                        <label class="flex items-center gap-1.5 cursor-pointer select-none <?php echo $is_super ? '' : 'pointer-events-none opacity-60'; ?>">
                            <input type="checkbox" name="libur_<?php echo $hari; ?>" value="1"
                                <?php echo $is_libur ? 'checked' : ''; ?>
                                <?php echo $is_super ? '' : 'disabled'; ?>
                                onchange="toggleLibur(this, '<?php echo $hari; ?>')"
                                class="w-4 h-4 text-red-500 border-slate-300 rounded focus:ring-red-500 cursor-pointer">
                            <span class="text-xs font-bold text-red-500">Libur</span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Buka</label>
                        <input type="time" name="buka_<?php echo $hari; ?>" value="<?php echo $d['buka']; ?>" <?php echo $is_super ? '' : 'disabled'; ?>
                            id="buka-<?php echo $hari; ?>"
                            class="w-full bg-white border border-slate-300 text-slate-800 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500 <?php echo $is_libur ? 'opacity-40' : ''; ?>"
                            <?php echo $is_libur ? 'disabled' : ''; ?>>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tutup</label>
                        <input type="time" name="tutup_<?php echo $hari; ?>" value="<?php echo $d['tutup']; ?>" <?php echo $is_super ? '' : 'disabled'; ?>
                            id="tutup-<?php echo $hari; ?>"
                            class="w-full bg-white border border-slate-300 text-slate-800 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500 <?php echo $is_libur ? 'opacity-40' : ''; ?>"
                            <?php echo $is_libur ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="pt-4 flex items-center gap-3">
                                    <?php if ($is_super): ?>
                                    <button type="button" onclick="submitJam()" id="btn-simpan-jam"
                                        class="bg-astra-700 hover:bg-astra-800 text-white px-6 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                                        <i class="fa-solid fa-floppy-disk"></i> Simpan Jam Operasional
                                    </button>
                                    <?php else: ?>
                                    <button type="button" disabled class="bg-slate-200 text-slate-600 px-6 py-2.5 rounded-lg text-sm font-bold flex items-center gap-2 border">Hanya Super Admin</button>
                                    <?php endif; ?>
                                    <span id="jam-feedback" class="text-sm font-semibold hidden"></span>
                                </div>
            </form>
        </div>
    </div>

    <!-- PANEL TUTUP SEMENTARA -->
    <div id="panel-schedule" class="hidden">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 max-w-2xl">
            <h3 class="font-extrabold text-slate-900 text-lg mb-1 flex items-center gap-2">
                <i class="fa-solid fa-calendar-xmark text-astra-700"></i> Jadwal Tutup Sementara
            </h3>
            <p class="text-sm text-slate-500 mb-4">Buat jadwal kapan toko ditutup sementara. Pengunjung akan melihat informasi ini di halaman utama.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Tanggal Mulai</label>
                    <input type="date" id="sched-start-date" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Waktu Mulai</label>
                    <input type="time" id="sched-start-time" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Tanggal Selesai</label>
                    <input type="date" id="sched-end-date" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Waktu Selesai</label>
                    <input type="time" id="sched-end-time" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-sm">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Catatan (opsional)</label>
                <input type="text" id="sched-note" placeholder="Contoh: Libur Lebaran" class="w-full bg-slate-50 border border-slate-300 rounded-lg p-2 text-sm">
            </div>

            <!-- Manual override moved here -->
            <div class="mb-4 p-3 bg-slate-50 border border-slate-100 rounded-lg">
                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Override Manual Status Toko</label>
                <div class="flex gap-3 items-center">
                    <select id="manual-status" class="bg-white border border-slate-300 rounded-lg p-2 text-sm">
                        <option value="buka" <?php echo $current_status==='buka'?'selected':''; ?>>Buka (Sesuai Jadwal)</option>
                        <option value="tutup" <?php echo $current_status==='tutup'?'selected':''; ?>>Tutup Sementara (Dipaksa)</option>
                    </select>
                    <button type="button" onclick="setManualStatus()" id="btn-set-manual" class="bg-astra-600 hover:bg-astra-700 text-white px-4 py-2 rounded-lg text-sm font-bold">Simpan Status Manual</button>
                </div>
            </div>

            <div class="flex items-center gap-3 mb-6">
                <button id="btn-add-schedule" type="button" onclick="submitSchedule()" class="bg-astra-700 hover:bg-astra-800 text-white px-4 py-2 rounded-lg text-sm font-bold">Tambah Jadwal Tutup</button>
                <span class="text-sm text-slate-500">Daftar jadwal aktif ditampilkan di bawah dan akan terlihat di halaman user.</span>
            </div>
            <div id="schedule-list"></div>
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

    <!-- PANEL UI TOKO -->
    <div id="panel-ui" class="hidden">
        <div class="mb-5">
            <h3 class="font-extrabold text-slate-900 text-lg flex items-center gap-2">
                <i class="fa-solid fa-paintbrush text-astra-700"></i> Tampilan Toko (UI)
            </h3>
            <p class="text-sm text-slate-500 mt-0.5">Sesuaikan teks yang muncul di halaman utama toko.</p>
        </div>

        <!-- HEADING EDITOR -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6 max-w-2xl">
            <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-2"><i class="fa-solid fa-heading text-astra-700"></i> Judul Header Toko</h4>
            <p class="text-xs text-slate-500 mb-3">Judul besar di halaman utama. Bagian <strong>brand</strong> akan di-highlight dengan warna gradasi biru.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Teks Awal</label>
                    <input type="text" id="heading-prefix-input"
                        value="<?php echo htmlspecialchars($heading['prefix']); ?>"
                        class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500"
                        placeholder="Contoh: Solusi Hardware di">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Nama Brand <span class="text-slate-400 font-normal normal-case">(akan di-highlight)</span></label>
                    <input type="text" id="heading-brand-input"
                        value="<?php echo htmlspecialchars($heading['brand']); ?>"
                        class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500"
                        placeholder="Contoh: Royal Komputer">
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="saveHeading()" id="btn-simpan-heading"
                    class="bg-astra-700 hover:bg-astra-800 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Heading
                </button>
                <span id="heading-feedback" class="text-sm font-semibold hidden"></span>
            </div>
            <!-- Preview -->
            <div class="mt-4 p-3 bg-gradient-to-r from-astra-950 via-slate-900 to-astra-900 rounded-lg text-center">
                <p class="text-white text-lg font-bold" id="heading-preview"><?php echo htmlspecialchars($heading['prefix']); ?> <span class="text-transparent bg-clip-text bg-gradient-to-r from-astra-400 to-sky-300"><?php echo htmlspecialchars($heading['brand']); ?></span></p>
            </div>
        </div>

        <!-- TAGLINE EDITOR -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6 max-w-2xl">
            <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-2"><i class="fa-solid fa-quote-right text-astra-700"></i> Tagline Toko</h4>
            <p class="text-xs text-slate-500 mb-3">Teks yang muncul di bawah judul header toko.</p>
            <div class="flex gap-3 items-start">
                <textarea id="tagline-input" rows="2"
                    class="flex-grow bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500"
                    placeholder="Tulis tagline toko..."><?php echo htmlspecialchars(loadTagline()); ?></textarea>
                <button type="button" onclick="saveTagline()" id="btn-simpan-tagline"
                    class="bg-astra-700 hover:bg-astra-800 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2 flex-shrink-0">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan
                </button>
            </div>
            <span id="tagline-feedback" class="text-sm font-semibold hidden mt-2"></span>
        </div>

        <!-- PRODUCT INFO TEXT EDITOR -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6 max-w-2xl">
            <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-2"><i class="fa-solid fa-circle-info text-astra-700"></i> Teks Info Produk</h4>
            <p class="text-xs text-slate-500 mb-3">Teks yang muncul di atas daftar produk, &quot;Menampilkan X produk tersedia...&quot;. Gunakan <code class="bg-slate-100 text-slate-800 px-1.5 py-0.5 rounded text-[11px] font-mono font-bold">{count}</code> untuk menampilkan jumlah produk.</p>
            <div class="flex gap-3 items-start">
                <textarea id="product-info-input" rows="3"
                    class="flex-grow bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500"
                    placeholder="Tulis teks info produk..."><?php echo htmlspecialchars(loadProductInfoText()); ?></textarea>
                <button type="button" onclick="saveProductInfo()" id="btn-simpan-produk-info"
                    class="bg-astra-700 hover:bg-astra-800 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2 flex-shrink-0">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan
                </button>
            </div>
            <span id="product-info-feedback" class="text-sm font-semibold hidden mt-2"></span>
        </div>
    </div>

        <!-- PANEL BANNER: PLAYLIST -->
    <div id="panel-banner" class="hidden">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-5">
            <div>
                <h3 class="font-extrabold text-slate-900 text-lg flex items-center gap-2">
                    <i class="fa-solid fa-images text-astra-700"></i> Kelola Playlist Banner
                </h3>
                <p class="text-sm text-slate-500 mt-0.5">Buat playlist banner. Masing-masing playlist berisi beberapa foto yang akan auto-slide berurutan. Playlist ditampilkan ke bawah di halaman utama.</p>
            </div>
            <button onclick="openPlaylistModal()" 
                class="bg-astra-700 hover:bg-astra-800 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2 flex-shrink-0">
                <i class="fa-solid fa-plus-circle"></i> Tambah Playlist
            </button>
        </div>

        <!-- Daftar Playlist -->
        <div id="playlist-list" class="space-y-4 max-w-3xl">
            <p class="text-slate-400 text-sm text-center py-8">Memuat data playlist...</p>
        </div>

        <!-- MODAL PLAYLIST -->
        <div id="playlist-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-xl border border-slate-200 w-full max-w-lg shadow-2xl flex flex-col overflow-hidden">
                <div class="bg-astra-950 text-white p-4 flex items-center justify-between">
                    <h3 id="playlist-modal-title" class="font-bold text-base flex items-center gap-2">
                        <i class="fa-solid fa-images text-astra-400"></i> <span id="playlist-modal-title-text">Tambah Playlist Baru</span>
                    </h3>
                    <button onclick="closePlaylistModal()" class="text-slate-400 hover:text-white text-lg"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" id="playlist-edit-id" value="">

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Nama Playlist</label>
                        <input type="text" id="playlist-name-input" placeholder="Contoh: Promo Akhir Tahun"
                            class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Interval Slide (detik)</label>
                        <input type="number" id="playlist-interval-input" value="5" min="2" max="30"
                            class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Ukuran Banner (rasio)</label>
                        <select id="playlist-aspect-input"
                            class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500">
                            <option value="2/1">2 : 1 (Panorama) — Lebar</option>
                            <option value="3/1">3 : 1 (Banner) — Sangat Lebar</option>
                            <option value="16/9" selected>16 : 9 (Video) — Standar</option>
                            <option value="21/9">21 : 9 (Ultrawide)</option>
                            <option value="4/3">4 : 3 (Standar)</option>
                            <option value="1/1">1 : 1 (Persegi)</option>
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">Pilih rasio yang paling sesuai dengan foto banner agar semua playlist tampil seragam.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="playlist-active-input" value="1" checked
                            class="w-4 h-4 rounded border-slate-300 text-astra-700 focus:ring-astra-500">
                        <label for="playlist-active-input" class="text-sm text-slate-700 font-medium">Aktif</label>
                    </div>

                    <div id="playlist-modal-feedback" class="hidden text-sm font-semibold p-3 rounded-lg"></div>

                    <div class="pt-2 flex justify-end gap-3 border-t border-slate-100">
                        <button type="button" onclick="closePlaylistModal()" class="px-4 py-2 border border-slate-300 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50">Batal</button>
                        <button type="button" onclick="submitPlaylist()" id="btn-playlist-submit"
                            class="px-5 py-2 bg-astra-700 hover:bg-astra-800 text-white rounded-lg text-xs font-bold flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Playlist
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL FOTO PLAYLIST -->
        <div id="playlist-photo-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-xl border border-slate-200 w-full max-w-2xl shadow-2xl flex flex-col overflow-hidden max-h-[90vh]">
                <div class="bg-astra-950 text-white p-4 flex items-center justify-between flex-shrink-0">
                    <h3 class="font-bold text-base flex items-center gap-2">
                        <i class="fa-solid fa-images text-astra-400"></i> <span id="playlist-photo-title">Foto Playlist</span>
                    </h3>
                    <button onclick="closePlaylistPhotoModal()" class="text-slate-400 hover:text-white text-lg"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="p-6 space-y-4 overflow-y-auto flex-grow">
                    <input type="hidden" id="playlist-photo-id" value="">

                    <!-- Upload baru -->
                    <div class="border-2 border-dashed border-slate-300 rounded-xl p-5 text-center hover:border-astra-500 transition-colors cursor-pointer" onclick="document.getElementById('pl-photo-file-input').click()">
                        <form id="pl-photo-form" enctype="multipart/form-data" class="space-y-3">
                            <input type="file" id="pl-photo-file-input" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="hidden" onchange="uploadPlaylistPhotos()">
                            <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-300 mb-2"></i>
                            <p class="text-sm text-slate-500 font-medium">Klik untuk upload foto ke playlist ini</p>
                            <p class="text-xs text-slate-400">Format: JPG, PNG, WEBP. Maks 5MB per gambar.</p>
                        </form>
                    </div>

                    <!-- Daftar foto -->
                    <div id="pl-photo-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        <p class="text-slate-400 text-sm text-center py-4 col-span-full">Belum ada foto.</p>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- PANEL PROFIL SAYA -->
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

    <!-- PANEL SERIAL NUMBER -->
    <div id="panel-serial" class="hidden">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="mb-5">
                <h3 class="font-extrabold text-slate-900 text-lg flex items-center gap-2">
                    <i class="fa-solid fa-barcode text-astra-700"></i> Cari Serial Number
                </h3>
                <p class="text-sm text-slate-500 mt-1">Cari nota pembelian dan penjualan berdasarkan nomor serial produk, kode item, atau nama produk.</p>
            </div>

            <div class="flex gap-3 mb-6">
                <div class="relative flex-grow">
                    <input type="text" id="serial-search-input" 
                        placeholder="Ketik nomor serial, kode item, atau nama produk..." 
                        onkeydown="if(event.key==='Enter') searchSerial()"
                        class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-lg px-4 py-3 pl-10 focus:outline-none focus:border-astra-500 text-sm">
                    <i class="fa-solid fa-barcode absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
                </div>
                <button onclick="searchSerial()" id="btn-search-serial"
                    class="bg-astra-700 hover:bg-astra-800 text-white px-6 py-3 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-magnifying-glass"></i> Cari
                </button>
            </div>

            <div id="serial-loading" class="hidden py-8 text-center text-slate-400">
                <i class="fa-solid fa-spinner animate-spin text-2xl mb-2"></i>
                <p class="text-sm">Mencari data...</p>
            </div>

            <div id="serial-empty" class="hidden py-8 text-center">
                <i class="fa-solid fa-barcode text-4xl text-slate-300 mb-3"></i>
                <p class="text-slate-500 text-sm">Masukkan nomor serial untuk memulai pencarian.</p>
            </div>

            <div id="serial-no-results" class="hidden py-8 text-center">
                <i class="fa-solid fa-circle-exclamation text-4xl text-amber-300 mb-3"></i>
                <p class="text-amber-600 text-sm font-semibold">Tidak ditemukan hasil untuk pencarian tersebut.</p>
            </div>

            <div id="serial-table-wrapper" class="hidden overflow-x-auto">
                <div class="mb-3 flex items-center justify-between">
                    <span id="serial-result-info" class="text-xs text-slate-400"></span>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">
                            <th class="pb-3 pr-3 whitespace-nowrap">Serial Number</th>
                            <th class="pb-3 pr-3 whitespace-nowrap">Kode Item</th>
                            <th class="pb-3 pr-3 whitespace-nowrap">Nama Produk</th>
                            <th class="pb-3 pr-3 whitespace-nowrap">Status</th>
                            <th class="pb-3 pr-3 whitespace-nowrap">Nota Pembelian</th>
                            <th class="pb-3 pr-3 whitespace-nowrap">Nota Penjualan</th>
                            <th class="pb-3 pr-2 whitespace-nowrap">Terakhir Update</th>
                        </tr>
                    </thead>
                    <tbody id="serial-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PANEL PUSH KE GIT -->
    <div id="panel-push" class="hidden">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 max-w-2xl">
            <h3 class="font-extrabold text-slate-900 text-lg mb-1 flex items-center gap-2">
                <i class="fa-solid fa-upload text-astra-700"></i> Push ke Git
            </h3>
            <p class="text-sm text-slate-500 mb-4">Push perubahan data toko (jam operasional, jadwal tutup, heading, dll) dan foto produk ke repository GitHub. Perubahan akan otomatis terdeploy setelah push.</p>

            <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800 flex items-start gap-3">
                <i class="fa-solid fa-circle-info text-lg mt-0.5"></i>
                <div>
                    <p class="font-semibold">Catatan:</p>
                    <ul class="list-disc ml-5 mt-1 text-amber-700 space-y-0.5">
                        <li>Hanya perubahan dari panel admin yang akan di-push (data/ + uploads/).</li>
                        <li>Perubahan dari IPOS (sync) tetap dijalankan oleh Task Scheduler lokal setiap jam.</li>
                        <li>Untuk push dari panel admin, buat GitHub token → simpan di <code class="bg-amber-100 px-1 rounded">backend/.env</code> sebagai <code class="bg-amber-100 px-1 rounded">GIT_TOKEN=token_anda</code></li>
                        <li>Atau jalankan <code class="bg-amber-100 px-1 rounded">backend/setup_push_task.bat</code> sbg Administrator sekali saja (task scheduler jalan sebagai user login).</li>
                    </ul>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" onclick="pushToGit()" id="btn-push-git"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Push Sekarang
                </button>
                <span id="push-feedback" class="text-sm font-semibold hidden"></span>
            </div>

            <div id="push-result" class="hidden mt-4 p-4 rounded-lg border text-sm"></div>
        </div>
    </div>

        </main>

<!-- MODAL KELOLA PRODUK -->
<div id="edit-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
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
            <div class="pt-2 flex justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-slate-300 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50">Batal</button>
                <button type="submit" id="btn-submit" class="px-5 py-2 bg-astra-700 hover:bg-astra-800 text-white rounded-lg text-xs font-bold flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- KONFIRMASI MODAL -->
<div id="confirm-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[110] hidden flex items-center justify-center p-4">
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
<div id="modal-admin" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
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
const CURRENT_ADMIN_ID = "<?php echo $current_admin['id']; ?>";

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
    const panels = ['katalog','jam','schedule','admin','ui','banner','profil','serial','push'];
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
    if (name === 'schedule') loadSchedules();
    if (name === 'banner') loadPlaylists();
    if (name === 'serial') document.getElementById('serial-search-input')?.focus();
}

function switchTab(tab){ showPanel(tab); }

// KATALOG
let allProducts=[], filteredProducts=[];
let adminFilters = {search:'',photoStatus:'all',sortBy:'name-asc',condition:'all'};

window.addEventListener('DOMContentLoaded', () => { fetchProducts(); if (typeof loadSchedules === 'function') loadSchedules(); // ensure initial tab
    showPanel('katalog'); });

function fetchProducts() {
    _savedScrollY = window.scrollY;
    document.getElementById('loading-spinner').style.display='flex';
    document.getElementById('table-container').classList.add('hidden');
    fetch('api_produk.php').then(r=>r.json()).then(data => {
        if(data.error) throw new Error(data.error);
        allProducts=data; applyAdminFilters();
        document.getElementById('loading-spinner').style.display='none';
        document.getElementById('table-container').classList.remove('hidden');
        requestAnimationFrame(() => window.scrollTo(0, _savedScrollY));
    }).catch(()=>{
        document.getElementById('loading-spinner').innerHTML='<p class="text-red-500 font-bold"><i class="fa-solid fa-triangle-exclamation"></i> Gagal terhubung ke database.</p>';
    });
}

function handleConditionFilterAdmin(v){adminFilters.condition=v;applyAdminFilters();}
function handleAdminSearch(v){adminFilters.search=v.toLowerCase();applyAdminFilters();}
function handlePhotoFilter(v){adminFilters.photoStatus=v;applyAdminFilters();}
function handleAdminSort(v){adminFilters.sortBy=v;applyAdminFilters();}

function applyAdminFilters() {
    filteredProducts = allProducts.filter(p => {
        const s=adminFilters.search;
        const matchSearch=(p.name||'').toLowerCase().includes(s)||(p.id||'').toLowerCase().includes(s);
        const hasPhoto=(p.image||'')&&!(p.image||'').includes('unsplash.com');
        let matchPhoto=true;
        if(adminFilters.photoStatus==='no-photo') matchPhoto=!hasPhoto;
        else if(adminFilters.photoStatus==='has-photo') matchPhoto=hasPhoto;
        const isBekas=(p.name||'').toUpperCase().includes('2ND');
        let matchCond=true;
        if(adminFilters.condition==='baru') matchCond=!isBekas;
        if(adminFilters.condition==='bekas') matchCond=isBekas;
        return matchSearch&&matchPhoto&&matchCond;
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
        tr.innerHTML=`
            <td class="px-5 py-3"><img src="${p.image}" alt="" class="w-12 h-12 object-cover rounded shadow-sm border border-slate-200"></td>
            <td class="px-5 py-3 font-mono text-xs text-slate-500">${p.id||'-'}</td>
            <td class="px-5 py-3 font-bold text-slate-800">${p.name||''}</td>
            <td class="px-5 py-3 text-xs"><span class="bg-slate-100 text-slate-600 px-2 py-1 rounded font-semibold">${p.category||'Lainnya'}</span></td>
            <td class="px-5 py-3 text-right font-bold text-astra-700">${formattedPrice}</td>
            <td class="px-5 py-3 text-center font-bold ${(p.stock||0)<5?'text-orange-500':'text-slate-700'}">${p.stock||0}</td>
            <td class="px-5 py-3 text-center">${photoBadge}</td>
            <td class="px-5 py-3 text-center">
                <button onclick="openEditModal('${p.id}')" class="bg-astra-600 hover:bg-astra-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1 mx-auto shadow-sm">
                    <i class="fa-solid fa-pen-to-square"></i> Kelola
                </button>
            </td>`;
        tbody.appendChild(tr);
    });
}

function openEditModal(id){
    const p = allProducts.find(x => x.id === id);
    if(!p) return;
    document.getElementById('modal-id').value=id;
    document.getElementById('modal-name').value=(p.name||'').replace(/&quot;/g,'"');
    document.getElementById('modal-desc').value=(p.description||'').replace(/&quot;/g,'"');
    document.getElementById('modal-foto').value='';
    
    renderSavedPhotos(id, p.images);
    document.getElementById('edit-modal').classList.remove('hidden');
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

function closeEditModal(){document.getElementById('edit-modal').classList.add('hidden');}

function submitForm(event){
    event.preventDefault();
    const btn=document.getElementById('btn-submit');
    btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner animate-spin"></i> Menyimpan...';

    const formData = new FormData();
    formData.append('id', document.getElementById('modal-id').value);
    formData.append('description', document.getElementById('modal-desc').value);

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

// HEADING
function saveHeading(){
    const btn=document.getElementById('btn-simpan-heading');
    const fb=document.getElementById('heading-feedback');
    const prefix=document.getElementById('heading-prefix-input').value.trim();
    const brand=document.getElementById('heading-brand-input').value.trim();
    if(!prefix||!brand){ showNotification('Prefix dan brand heading tidak boleh kosong.', 'error'); return; }
    btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner animate-spin"></i> Menyimpan...';
    fb.classList.add('hidden');
    const fd=new FormData();
    fd.append('action','save_heading');
    fd.append('prefix',prefix);
    fd.append('brand',brand);
    fetch('update_admin.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(data=>{
            showNotification(data.message, data.success ? 'success' : 'error');
        }).catch(()=>showNotification('Gagal. Cek koneksi.', 'error'))
        .finally(()=>{btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-floppy-disk"></i> Simpan Heading';});
}

// Live preview heading
function updateHeadingPreview(){
    const prefix=document.getElementById('heading-prefix-input').value.trim();
    const brand=document.getElementById('heading-brand-input').value.trim();
    const preview=document.getElementById('heading-preview');
    if(preview) preview.innerHTML=prefix+' <span class="text-transparent bg-clip-text bg-gradient-to-r from-astra-400 to-sky-300">'+escHtml(brand)+'</span>';
}

document.addEventListener('DOMContentLoaded', function(){
    const p=document.getElementById('heading-prefix-input');
    const b=document.getElementById('heading-brand-input');
    if(p) p.addEventListener('input', updateHeadingPreview);
    if(b) b.addEventListener('input', updateHeadingPreview);
});

// PRODUCT INFO
function saveProductInfo(){
    const btn=document.getElementById('btn-simpan-produk-info');
    const fb=document.getElementById('product-info-feedback');
    const text=document.getElementById('product-info-input').value.trim();
    if(!text){ showNotification('Teks info produk tidak boleh kosong.', 'error'); return; }
    btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner animate-spin"></i> Menyimpan...';
    fb.classList.add('hidden');
    const fd=new FormData();
    fd.append('action','save_product_info');
    fd.append('text',text);
    fetch('update_admin.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(data=>{
            showNotification(data.message, data.success ? 'success' : 'error');
        }).catch(()=>showNotification('Gagal. Cek koneksi.', 'error'))
        .finally(()=>{btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-floppy-disk"></i> Simpan';});
}

// TAGLINE
function saveTagline(){
    const btn=document.getElementById('btn-simpan-tagline');
    const fb=document.getElementById('tagline-feedback');
    const tagline=document.getElementById('tagline-input').value.trim();
    if(!tagline){ showNotification('Tagline tidak boleh kosong.', 'error'); return; }
    btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner animate-spin"></i> Menyimpan...';
    fb.classList.add('hidden');
    const fd=new FormData();
    fd.append('action','save_tagline');
    fd.append('tagline',tagline);
    fetch('update_admin.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(data=>{
            showNotification(data.message, data.success ? 'success' : 'error');
        }).catch(()=>showNotification('Gagal. Cek koneksi.', 'error'))
        .finally(()=>{btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-floppy-disk"></i> Simpan';});
}

// JAM OPERASIONAL
function toggleLibur(checkbox, day) {
    const bukaInput = document.getElementById('buka-' + day);
    const tutupInput = document.getElementById('tutup-' + day);
    if (checkbox.checked) {
        bukaInput.disabled = true;
        bukaInput.classList.add('opacity-40');
        tutupInput.disabled = true;
        tutupInput.classList.add('opacity-40');
    } else {
        bukaInput.disabled = false;
        bukaInput.classList.remove('opacity-40');
        tutupInput.disabled = false;
        tutupInput.classList.remove('opacity-40');
    }
}

function submitJam(){
    const btn=document.getElementById('btn-simpan-jam');
    const fb=document.getElementById('jam-feedback');
    btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner animate-spin"></i> Menyimpan...';
    fb.classList.add('hidden');
    fetch('update_jam.php',{method:'POST',body:new FormData(document.getElementById('form-jam'))})
        .then(r=>r.json()).then(data=>{
            fb.classList.remove('hidden');
            if(data.success){fb.className='text-sm font-semibold text-green-600';fb.innerHTML='<i class="fa-solid fa-check-circle mr-1"></i>'+data.message;}
            else{fb.className='text-sm font-semibold text-red-600';fb.innerHTML='<i class="fa-solid fa-triangle-exclamation mr-1"></i>'+data.message;}
            setTimeout(()=>fb.classList.add('hidden'),4000);
        }).catch(()=>{fb.classList.remove('hidden');fb.className='text-sm font-semibold text-red-600';fb.textContent='Gagal. Cek koneksi.';})
        .finally(()=>{btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-floppy-disk"></i> Simpan Jam Operasional';});
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
function closeModalAdmin(){document.getElementById('modal-admin').classList.add('hidden');}

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

// SCHEDULE FUNCTIONS
let _lastSchedules = [];
let _editingScheduleId = null;
function loadSchedules(){
    fetch('update_admin.php',{method:'POST',body:new URLSearchParams({action:'get_schedules'})})
    .then(r=>r.json()).then(data=>{
        if(!data.success) return;
        _lastSchedules = data.data || [];
        const list=document.getElementById('schedule-list');
        if(!list) return;
        list.innerHTML='';
        if(!data.data || data.data.length===0){ list.innerHTML='<div class="p-4 text-sm text-slate-500">Belum ada jadwal.</div>'; return; }
        data.data.forEach(s=>{
           const row=document.createElement('div');
           row.className='p-3 border rounded mb-2 flex justify-between items-center';
           const note = s.note?escHtml(s.note):'Tutup Sementara';
           row.innerHTML=`<div><div class="text-sm font-bold">${note}</div><div class="text-xs text-slate-500">${escHtml(s.start)} → ${escHtml(s.end)}</div></div><div class="flex gap-2"><button onclick="editSchedule('${s.id}')" class="text-xs bg-blue-50 text-blue-700 px-3 py-1 rounded">Edit</button><button onclick="deleteSchedule('${s.id}')" class="text-xs bg-red-50 text-red-700 px-3 py-1 rounded">Hapus</button></div>`;
           list.appendChild(row);
        });
    });
}

function editSchedule(id){
    const s = _lastSchedules.find(x=>x.id===id);
    if(!s) return showNotification('Jadwal tidak ditemukan. Muat ulang daftar.', 'error');
    _editingScheduleId = id;
    document.getElementById('sched-start-date').value = s.start.split(' ')[0];
    document.getElementById('sched-start-time').value = s.start.split(' ')[1] || '00:00';
    document.getElementById('sched-end-date').value = s.end.split(' ')[0];
    document.getElementById('sched-end-time').value = s.end.split(' ')[1] || '00:00';
    document.getElementById('sched-note').value = s.note || '';
    const btn = document.getElementById('btn-add-schedule');
    btn.textContent = 'Simpan Perubahan';
}

function submitSchedule(){
  const sd=document.getElementById('sched-start-date').value;
  const st=document.getElementById('sched-start-time').value;
  const ed=document.getElementById('sched-end-date').value;
  const et=document.getElementById('sched-end-time').value;
  const note=document.getElementById('sched-note').value;
  if(!sd||!st||!ed||!et){ showNotification('Isi tanggal dan waktu mulai serta selesai.', 'error'); return; }
  const fd=new FormData();
  const btn=document.getElementById('btn-add-schedule');
  if(_editingScheduleId){
    fd.append('action','edit_schedule'); fd.append('id',_editingScheduleId);
  } else {
    fd.append('action','add_schedule');
  }
  fd.append('start_date',sd); fd.append('start_time',st);
  fd.append('end_date',ed); fd.append('end_time',et);
  fd.append('note',note);
  btn.disabled=true; btn.textContent='Menyimpan...';
  fetch('update_admin.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
    showNotification(data.message, data.success ? 'success' : 'error');
    if(data.success){ document.getElementById('sched-start-date').value=''; document.getElementById('sched-start-time').value=''; document.getElementById('sched-end-date').value=''; document.getElementById('sched-end-time').value=''; document.getElementById('sched-note').value=''; _editingScheduleId=null; btn.textContent='Tambah Jadwal Tutup'; loadSchedules(); }
  }).catch(()=>{showNotification('Gagal. Cek koneksi.', 'error');}).finally(()=>{btn.disabled=false; if(!_editingScheduleId) btn.textContent='Tambah Jadwal Tutup';});
}

function deleteSchedule(id){
  showConfirmModal('Hapus jadwal ini?', function() {
    const fd=new FormData(); fd.append('action','delete_schedule'); fd.append('id',id);
    fetch('update_admin.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{ showNotification(data.message, data.success ? 'success' : 'error'); if(data.success) loadSchedules(); }).catch(()=>showNotification('Gagal. Cek koneksi.', 'error'));
  });
}

function setManualStatus(){
    let status = 'buka';
    const select = document.getElementById('manual-status');
    if(select && select.value) status = select.value;

    const btn = document.getElementById('btn-set-manual');
    if(btn) { btn.disabled = true; btn.textContent = 'Menyimpan...'; }

    const fd=new FormData(); fd.append('action','set_manual_status'); fd.append('status',status);
    fetch('update_admin.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
        showNotification(data.message, data.success ? 'success' : 'error');
    }).catch(()=>showNotification('Gagal. Cek koneksi.', 'error')).finally(()=>{ if(btn){btn.disabled=false;btn.textContent='Simpan Status Manual';} });
}

function pushToGit(){
    const btn = document.getElementById('btn-push-git');
    const feedback = document.getElementById('push-feedback');
    const result = document.getElementById('push-result');
    if(btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> Mempush...'; }
    feedback.className = 'text-sm font-semibold hidden';
    result.className = 'hidden';

    const fd = new FormData(); fd.append('action', 'push_to_git');
    fetch('update_admin.php', {method:'POST', body:fd}).then(r=>r.json()).then(data=>{
        result.className = 'mt-4 p-4 rounded-lg border text-sm ' + (data.success ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800');
        result.innerHTML = '<i class="fa-solid ' + (data.success ? 'fa-circle-check' : 'fa-circle-exclamation') + ' mr-1.5"></i> ' + escHtml(data.message);
        result.classList.remove('hidden');
        showNotification(data.message, data.success ? 'success' : 'error');
    }).catch(()=>{
        result.className = 'mt-4 p-4 rounded-lg border text-sm bg-red-50 border-red-200 text-red-800';
        result.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1.5"></i> Gagal terhubung ke server.';
        result.classList.remove('hidden');
        showNotification('Gagal. Cek koneksi.', 'error');
    }).finally(()=>{
        if(btn){ btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Push Sekarang'; }
    });
}

function escHtml(str){
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function escAttr(str){
    return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// SERIAL NUMBER SEARCH — juga tersedia di global scope

function triggerSync(){
    const btn = document.getElementById('sync-btn');
    const icon = document.getElementById('sync-icon');
    btn.disabled = true;
    icon.className = 'fa-solid fa-spinner animate-spin';
    document.getElementById('sync-modal').classList.remove('hidden');
    document.getElementById('sync-modal').classList.add('flex');
    document.getElementById('sync-output').textContent = 'Memulai sinkronisasi...';
    fetch('trigger_sync.php', {method:'POST'})
        .then(r => r.json())
        .then(data => {
            document.getElementById('sync-output').textContent = data.output || '(tidak ada output)';
            if(data.success){
                showNotification('Sinkronisasi berhasil! ' + (data.output.match(/(\d+)\s*products?/i) ? data.output.match(/(\d+)\s*products?/i)[1] + ' produk' : ''), 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showNotification('Sinkronisasi gagal (exit code: ' + data.exit_code + ')', 'error');
            }
        })
        .catch(() => {
            document.getElementById('sync-output').textContent = 'Gagal menghubungi server.';
            showNotification('Gagal terhubung ke server.', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            icon.className = 'fa-solid fa-rotate';
        });
}

function closeSyncModal(){
    document.getElementById('sync-modal').classList.add('hidden');
    document.getElementById('sync-modal').classList.remove('flex');
}

// ============================================================
// SERIAL NUMBER SEARCH
// ============================================================
function searchSerial() {
    const query = document.getElementById('serial-search-input').value.trim();
    if (!query) {
        showNotification('Masukkan nomor serial, kode item, atau nama produk.', 'error');
        return;
    }

    const loading = document.getElementById('serial-loading');
    const empty = document.getElementById('serial-empty');
    const noResults = document.getElementById('serial-no-results');
    const wrapper = document.getElementById('serial-table-wrapper');
    const tbody = document.getElementById('serial-body');
    const info = document.getElementById('serial-result-info');
    const btn = document.getElementById('btn-search-serial');

    loading.classList.remove('hidden');
    empty.classList.add('hidden');
    noResults.classList.add('hidden');
    wrapper.classList.add('hidden');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> Mencari...';

    const formData = new FormData();
    formData.append('action', 'search_serial');
    formData.append('query', query);

    fetch('update_admin.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            loading.classList.add('hidden');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Cari';

            if (!data.success) {
                showNotification(data.message || 'Gagal mencari data.', 'error');
                noResults.classList.remove('hidden');
                noResults.innerHTML = '<i class="fa-solid fa-circle-exclamation text-4xl text-red-300 mb-3"></i><p class="text-red-500 text-sm">' + escHtml(data.message) + '</p>';
                return;
            }

            if (!data.data || data.data.length === 0) {
                noResults.classList.remove('hidden');
                if (info) info.textContent = '';
                return;
            }

            wrapper.classList.remove('hidden');
            if (info) {
                info.textContent = 'Ditemukan ' + data.total + ' hasil untuk "' + escHtml(query) + '"';
            }

            if (tbody) {
                tbody.innerHTML = '';
                data.data.forEach(function(item) {
                    const tr = document.createElement('tr');
                    tr.className = 'border-b border-slate-100 hover:bg-slate-50 transition-colors';

                    // Status badge
                    let statusBadge = '';
                    if (item.stsada === 'Y') {
                        statusBadge = '<span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-lg bg-green-100 text-green-700 border border-green-200"><i class="fa-solid fa-circle-check text-[10px]"></i> Tersedia</span>';
                    } else if (item.stsada === 'T') {
                        statusBadge = '<span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-lg bg-red-100 text-red-700 border border-red-200"><i class="fa-solid fa-circle-xmark text-[10px]"></i> Terjual</span>';
                    } else {
                        statusBadge = '<span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-lg bg-slate-100 text-slate-600 border border-slate-200">-</span>';
                    }

                    // Purchase info
                    let beliHtml = '<span class="text-xs text-slate-400">-</span>';
                    if (item.notrans_beli) {
                        const tgl = item.tgl_beli ? formatDate(item.tgl_beli) : '-';
                        const sup = item.nama_supplier || '-';
                        beliHtml = '<div class="text-xs">' +
                            '<span class="font-semibold text-slate-700">' + escHtml(item.notrans_beli) + '</span><br>' +
                            '<span class="text-slate-500">' + tgl + '</span><br>' +
                            '<span class="text-slate-400">Dari: ' + escHtml(sup) + '</span>' +
                            '</div>';
                    }

                    // Sales info
                    let jualHtml = '<span class="text-xs text-slate-400">-</span>';
                    if (item.notrans_jual) {
                        const tgl = item.tgl_jual ? formatDate(item.tgl_jual) : '-';
                        const pel = item.nama_pelanggan || '-';
                        jualHtml = '<div class="text-xs">' +
                            '<span class="font-semibold text-slate-700">' + escHtml(item.notrans_jual) + '</span><br>' +
                            '<span class="text-slate-500">' + tgl + '</span><br>' +
                            '<span class="text-slate-400">Kepada: ' + escHtml(pel) + '</span>' +
                            '</div>';
                    }

                    const tglUpdate = item.dateupd ? formatDate(item.dateupd) : '-';

                    tr.innerHTML =
                        '<td class="py-3 pr-3"><span class="font-mono text-xs font-bold text-astra-700">' + escHtml(item.noserial) + '</span></td>' +
                        '<td class="py-3 pr-3"><span class="font-mono text-xs text-slate-500">' + escHtml(item.kodeitem || '-') + '</span></td>' +
                        '<td class="py-3 pr-3"><span class="text-sm font-semibold text-slate-800">' + escHtml(item.namaitem || '-') + '</span></td>' +
                        '<td class="py-3 pr-3 whitespace-nowrap">' + statusBadge + '</td>' +
                        '<td class="py-3 pr-3 max-w-[200px]">' + beliHtml + '</td>' +
                        '<td class="py-3 pr-3 max-w-[200px]">' + jualHtml + '</td>' +
                        '<td class="py-3 pr-2 text-xs text-slate-400 whitespace-nowrap">' + tglUpdate + '</td>';
                    tbody.appendChild(tr);
                });
            }
        })
        .catch(function(err) {
            console.error('Error searching serial:', err);
            loading.classList.add('hidden');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Cari';
            noResults.classList.remove('hidden');
            noResults.innerHTML = '<i class="fa-solid fa-circle-exclamation text-4xl text-red-300 mb-3"></i><p class="text-red-500 text-sm">Gagal memuat data.</p>';
        });
}

// ============================================================
// PLAYLIST BANNER MANAGEMENT
// ============================================================
let playlistEditId = null;
let draggedPlaylistId = null;

function loadPlaylists() {
    const container = document.getElementById('playlist-list');
    if (!container) return;
    container.innerHTML = '<p class="text-slate-400 text-sm text-center py-8">Memuat data playlist...</p>';
    fetch('api_banner.php')
        .then(r => r.json())
        .then(playlists => {
            if (!playlists || playlists.length === 0) {
                container.innerHTML = '<div class="text-center py-8"><i class="fa-solid fa-images text-4xl text-slate-300 mb-3"></i><p class="text-slate-400 text-sm">Belum ada playlist. Klik "Tambah Playlist" untuk membuat baru.</p></div>';
                return;
            }
            container.innerHTML = '<div class="flex items-center gap-2 text-xs text-slate-400 mb-2 px-2"><i class="fa-solid fa-arrows-up-down"></i> Urutkan dengan drag & drop</div>';
            playlists.forEach((pl, idx) => {
                const isActive = pl.active !== false;
                const photoCount = (pl.photos || []).length;
                const firstPhoto = photoCount > 0 && pl.photos[0].image ? 'uploads/banners/' + pl.photos[0].image : null;

                const div = document.createElement('div');
                div.className = 'flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-200 shadow-sm playlist-item';
                div.draggable = true;
                div.dataset.playlistId = pl.id;

                let previewHtml = '<div class="w-24 h-14 rounded-lg border border-slate-200 bg-slate-100 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-image text-slate-300 text-lg"></i></div>';
                if (firstPhoto) {
                    previewHtml = '<div class="w-24 h-14 rounded-lg border border-slate-200 bg-slate-100 flex-shrink-0 overflow-hidden relative"><img src="' + firstPhoto + '" alt="" class="w-full h-full object-cover"><span class="absolute bottom-0 right-0 bg-black/60 text-white text-[10px] px-1.5 py-0.5 rounded-tl-md font-bold">' + photoCount + '</span></div>';
                }

                div.innerHTML =
                    '<button type="button" class="cursor-grab text-slate-400 hover:text-slate-600 px-1" title="Seret untuk urutkan"><i class="fa-solid fa-grip-lines"></i></button>' +
                    previewHtml +
                    '<div class="flex-grow min-w-0">' +
                        '<p class="text-sm font-bold text-slate-800 truncate">' + escHtml(pl.name || '(tanpa nama)') + '</p>' +
                        '<p class="text-xs text-slate-400">' + photoCount + ' foto &middot; interval ' + (pl.interval || 5) + ' detik &middot; ' + (isActive ? '<span class="text-green-600 font-medium">Aktif</span>' : '<span class="text-slate-400">Nonaktif</span>') + '</p>' +
                    '</div>' +
                    '<div class="flex items-center gap-1 flex-shrink-0">' +
                        '<button type="button" onclick="openPlaylistPhotos(\'' + pl.id + '\', \'' + escHtml(pl.name || '') + '\')" class="text-xs text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-2.5 py-1.5 rounded-lg font-semibold transition-colors" title="Kelola Foto"><i class="fa-solid fa-images"></i></button>' +
                        '<button type="button" onclick="editPlaylist(\'' + pl.id + '\')" class="text-xs text-astra-600 hover:text-astra-800 bg-astra-50 hover:bg-astra-100 px-2.5 py-1.5 rounded-lg font-semibold transition-colors" title="Edit"><i class="fa-solid fa-pen"></i></button>' +
                        '<button type="button" onclick="deletePlaylist(\'' + pl.id + '\')" class="text-xs text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded-lg font-semibold transition-colors" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>' +
                    '</div>';

                div.addEventListener('dragstart', () => {
                    draggedPlaylistId = div.dataset.playlistId;
                    div.classList.add('opacity-50');
                });
                div.addEventListener('dragend', () => {
                    div.classList.remove('opacity-50');
                    draggedPlaylistId = null;
                });
                div.addEventListener('dragover', e => { e.preventDefault(); div.classList.add('border-astra-500'); });
                div.addEventListener('dragleave', () => div.classList.remove('border-astra-500'));
                div.addEventListener('drop', e => {
                    e.preventDefault();
                    div.classList.remove('border-astra-500');
                    if (!draggedPlaylistId || draggedPlaylistId === div.dataset.playlistId) return;
                    const ids = Array.from(document.querySelectorAll('.playlist-item'))
                        .map(el => el.dataset.playlistId);
                    const fd = new FormData();
                    fd.append('action', 'reorder_playlists');
                    fd.append('ids', JSON.stringify(ids));
                    fetch('update_banner.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) loadPlaylists();
                            else showNotification(data.message || 'Gagal mengurutkan.', 'error');
                        })
                        .catch(() => showNotification('Gagal terhubung.', 'error'));
                });
                container.appendChild(div);
            });
        })
        .catch(err => {
            console.error('Failed to load playlists:', err);
            container.innerHTML = '<p class="text-red-500 text-sm text-center py-8">Gagal memuat playlist.</p>';
        });
}

// --- CRUD Playlist ---
function openPlaylistModal(editId) {
    playlistEditId = editId || null;
    const modal = document.getElementById('playlist-modal');
    const titleText = document.getElementById('playlist-modal-title-text');
    const nameInput = document.getElementById('playlist-name-input');
    const intervalInput = document.getElementById('playlist-interval-input');
    const activeInput = document.getElementById('playlist-active-input');
    const idInput = document.getElementById('playlist-edit-id');
    const feedback = document.getElementById('playlist-modal-feedback');
    feedback.classList.add('hidden');

    const aspectInput = document.getElementById('playlist-aspect-input');

    if (editId) {
        titleText.textContent = 'Edit Playlist';
        fetch('api_banner.php')
            .then(r => r.json())
            .then(playlists => {
                const pl = playlists.find(x => x.id === editId);
                if (!pl) return;
                nameInput.value = pl.name || '';
                intervalInput.value = pl.interval || 5;
                activeInput.checked = pl.active !== false;
                idInput.value = editId;
                aspectInput.value = pl.aspect || '16/9';
            });
    } else {
        titleText.textContent = 'Tambah Playlist Baru';
        nameInput.value = '';
        intervalInput.value = 5;
        activeInput.checked = true;
        idInput.value = '';
        aspectInput.value = '16/9';
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closePlaylistModal() {
    document.getElementById('playlist-modal').classList.add('hidden');
    document.getElementById('playlist-modal').classList.remove('flex');
}

function submitPlaylist() {
    const btn = document.getElementById('btn-playlist-submit');
    const fb = document.getElementById('playlist-modal-feedback');
    fb.classList.add('hidden');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> Menyimpan...';

    const fd = new FormData();
    fd.append('action', 'save_playlist');
    fd.append('id', document.getElementById('playlist-edit-id').value);
    fd.append('name', document.getElementById('playlist-name-input').value.trim());
    fd.append('interval', document.getElementById('playlist-interval-input').value);
    fd.append('aspect', document.getElementById('playlist-aspect-input').value);
    fd.append('active', document.getElementById('playlist-active-input').checked ? '1' : '0');

    fetch('update_banner.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'Playlist berhasil disimpan.', 'success');
                closePlaylistModal();
                loadPlaylists();
            } else {
                fb.classList.remove('hidden');
                fb.className = 'text-sm font-semibold p-3 rounded-lg bg-red-50 text-red-700 border border-red-200';
                fb.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-1"></i>' + (data.message || 'Gagal menyimpan.');
            }
        })
        .catch(() => {
            fb.classList.remove('hidden');
            fb.className = 'text-sm font-semibold p-3 rounded-lg bg-red-50 text-red-700 border border-red-200';
            fb.textContent = 'Gagal. Cek koneksi.';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Playlist';
        });
}

function editPlaylist(id) {
    openPlaylistModal(id);
}

function deletePlaylist(id) {
    showConfirmModal('Hapus playlist ini beserta semua fotonya? Tindakan tidak dapat dibatalkan.', function() {
        const fd = new FormData();
        fd.append('action', 'delete_playlist');
        fd.append('id', id);
        fetch('update_banner.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Playlist berhasil dihapus.', 'success');
                    loadPlaylists();
                } else {
                    showNotification(data.message || 'Gagal menghapus playlist.', 'error');
                }
            })
            .catch(() => showNotification('Gagal terhubung.', 'error'));
    });
}

// --- Foto dalam Playlist ---
function openPlaylistPhotos(id, name) {
    document.getElementById('playlist-photo-id').value = id;
    document.getElementById('playlist-photo-title').textContent = 'Foto: ' + (name || 'Playlist');
    document.getElementById('pl-photo-file-input').value = '';
    loadPlaylistPhotos(id);
    document.getElementById('playlist-photo-modal').classList.remove('hidden');
    document.getElementById('playlist-photo-modal').classList.add('flex');
}

function closePlaylistPhotoModal() {
    document.getElementById('playlist-photo-modal').classList.add('hidden');
    document.getElementById('playlist-photo-modal').classList.remove('flex');
}

function loadPlaylistPhotos(id) {
    const grid = document.getElementById('pl-photo-grid');
    grid.innerHTML = '<p class="text-slate-400 text-sm text-center py-4 col-span-full"><i class="fa-solid fa-spinner animate-spin mr-2"></i> Memuat foto...</p>';
    fetch('api_banner.php')
        .then(r => r.json())
        .then(playlists => {
            const pl = playlists.find(x => x.id === id);
            if (!pl || !pl.photos || pl.photos.length === 0) {
                grid.innerHTML = '<p class="text-slate-400 text-sm text-center py-4 col-span-full">Belum ada foto. Upload foto di atas.</p>';
                return;
            }
            grid.innerHTML = '';
            grid.className = 'space-y-4';
            pl.photos.forEach((photo, idx) => {
                const imgUrl = 'uploads/banners/' + photo.image;
                const div = document.createElement('div');
                div.className = 'flex flex-col bg-white rounded-xl border border-slate-200 overflow-hidden';
                div.innerHTML =
                    '<div class="flex gap-4 p-3">' +
                        '<img src="' + imgUrl + '" alt="' + escHtml(photo.alt || '') + '" class="w-32 h-20 object-cover rounded-lg border border-slate-200 bg-slate-100 flex-shrink-0">' +
                        '<div class="flex-grow min-w-0 space-y-2">' +
                            '<div>' +
                                '<label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Link</label>' +
                                '<input type="text" class="pl-photo-link w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-1.5 text-xs focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500" value="' + escAttr(photo.link || '') + '" placeholder="https://...">' +
                            '</div>' +
                            '<div>' +
                                '<label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Alt Text</label>' +
                                '<input type="text" class="pl-photo-alt w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-1.5 text-xs focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500" value="' + escAttr(photo.alt || '') + '" placeholder="Deskripsi foto">' +
                            '</div>' +
                        '</div>' +
                        '<div class="flex flex-col gap-1.5 flex-shrink-0 justify-start">' +
                            '<button type="button" onclick="savePlaylistPhotoInfo(\'' + id + '\', ' + idx + ', this)" class="text-xs text-white bg-astra-700 hover:bg-astra-800 px-2.5 py-1.5 rounded-lg font-semibold transition-colors flex items-center gap-1"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>' +
                            '<button type="button" onclick="deletePlaylistPhoto(\'' + id + '\', \'' + photo.image + '\', ' + idx + ')" class="text-xs text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded-lg font-semibold transition-colors flex items-center gap-1"><i class="fa-solid fa-trash-can"></i></button>' +
                        '</div>' +
                    '</div>' +
                    (idx === 0 ? '<div class="bg-astra-700 text-white text-[10px] font-bold px-3 py-1">Foto Utama</div>' : '');
                grid.appendChild(div);
            });
        })
        .catch(() => {
            grid.innerHTML = '<p class="text-red-500 text-sm text-center py-4 col-span-full">Gagal memuat foto.</p>';
        });
}

function uploadPlaylistPhotos() {
    const input = document.getElementById('pl-photo-file-input');
    const files = input.files;
    if (!files || files.length === 0) return;

    const id = document.getElementById('playlist-photo-id').value;
    if (!id) {
        showNotification('ID playlist tidak ditemukan.', 'error');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'save_playlist');
    fd.append('id', id);
    for (let i = 0; i < files.length; i++) {
        fd.append('photos[]', files[i]);
    }

    const container = document.querySelector('#pl-photo-form');
    const origHtml = container.innerHTML;
    container.innerHTML = '<i class="fa-solid fa-spinner animate-spin text-xl text-astra-700"></i><p class="text-sm text-astra-700 font-medium">Mengupload...</p>';

    fetch('update_banner.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification((data.message || 'Foto berhasil diupload.') + ' (' + files.length + ' foto)', 'success');
                input.value = '';
                loadPlaylistPhotos(id);
                loadPlaylists();
            } else {
                showNotification(data.message || 'Gagal upload.', 'error');
            }
        })
        .catch(() => showNotification('Gagal terhubung.', 'error'))
        .finally(() => {
            container.innerHTML = origHtml;
        });
}

function savePlaylistPhotoInfo(playlistId, idx, btn) {
    const card = btn.closest('[class*="rounded-xl"]');
    const linkInput = card ? card.querySelector('.pl-photo-link') : null;
    const altInput = card ? card.querySelector('.pl-photo-alt') : null;
    if (!linkInput && !altInput) return;
    const fd = new FormData();
    fd.append('action', 'update_photo_info');
    fd.append('playlist_id', playlistId);
    fd.append('photo_index', idx);
    fd.append('link', linkInput ? linkInput.value : '');
    fd.append('alt', altInput ? altInput.value : '');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    fetch('update_banner.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification('Info foto berhasil disimpan.', 'success');
            } else {
                showNotification(data.message || 'Gagal menyimpan.', 'error');
            }
        })
        .catch(() => showNotification('Gagal terhubung.', 'error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan';
        });
}

function deletePlaylistPhoto(playlistId, image, idx) {
    showConfirmModal('Hapus foto ini dari playlist?' , function() {
        const fd = new FormData();
        fd.append('action', 'delete_playlist_photo');
        fd.append('playlist_id', playlistId);
        fd.append('photo_index', idx);
        fetch('update_banner.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification('Foto berhasil dihapus.', 'success');
                    loadPlaylistPhotos(playlistId);
                    loadPlaylists();
                } else {
                    showNotification(data.message || 'Gagal menghapus foto.', 'error');
                }
            })
            .catch(() => showNotification('Gagal terhubung.', 'error'));
    });
}
</script>
</body>
</html>