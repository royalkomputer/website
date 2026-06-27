<?php
// Temporary patch script for admin.php
$file = __DIR__ . '/admin.php';
$content = file_get_contents($file);

// 1. Change tab button text
$content = str_replace(
    '<i class="fa-solid fa-image"></i> Kelola Banner',
    '<i class="fa-solid fa-images"></i> Playlist Banner',
    $content
);

// 2. Replace entire PANEL BANNER section with new playlist UI
$oldPanel = <<<'OLD'
    <!-- PANEL BANNER -->
    <div id="panel-banner" class="hidden">
        <div class="mb-5">
            <h3 class="font-extrabold text-slate-900 text-lg flex items-center gap-2">
                <i class="fa-solid fa-image text-astra-700"></i> Kelola Banner
            </h3>
            <p class="text-sm text-slate-500 mt-0.5">Atur banner yang tampil di halaman utama toko. Maksimal 5 banner.</p>
        </div>

        <!-- Banner List -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6 max-w-2xl">
            <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-3"><i class="fa-solid fa-list text-astra-700"></i> Daftar Banner</h4>
            <div id="banner-list" class="space-y-4">
                <p class="text-slate-400 text-sm text-center py-8">Memuat data banner...</p>
            </div>
        </div>

        <!-- Upload Form -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6 max-w-2xl">
            <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-3"><i class="fa-solid fa-plus-circle text-astra-700"></i> <span id="banner-form-title">Tambah Banner Baru</span></h4>
            <form id="banner-form" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="id" id="banner-id-input" value="">
                <input type="hidden" name="order" id="banner-order-input" value="">

                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Gambar Banner</label>
                    <input type="file" name="file" id="banner-file-input" accept="image/jpeg,image/png,image/webp"
                        class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2 text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-astra-700 file:text-white file:text-xs file:font-bold hover:file:bg-astra-800 file:cursor-pointer focus:outline-none focus:border-astra-500">
                    <p class="text-xs text-slate-400 mt-1">Format: JPG, PNG, WEBP. Ukuran maks: 5MB. Rasio 16:9 disarankan.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Teks Alternatif (alt)</label>
                        <input type="text" name="alt" id="banner-alt-input"
                            class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500"
                            placeholder="Contoh: Promo Akhir Tahun">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Link (opsional)</label>
                        <input type="text" name="link" id="banner-link-input"
                            class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500"
                            placeholder="https://...">
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="active" id="banner-active-input" value="1" checked
                        class="w-4 h-4 rounded border-slate-300 text-astra-700 focus:ring-astra-500">
                    <label for="banner-active-input" class="text-sm text-slate-700 font-medium">Aktif</label>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" id="btn-simpan-banner"
                        class="bg-astra-700 hover:bg-astra-800 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                        <i id="banner-btn-icon" class="fa-solid fa-floppy-disk"></i> <span id="banner-submit-text">Simpan Banner</span>
                    </button>
                    <button type="button" onclick="resetBannerForm()" id="btn-batal-banner"
                        class="hidden bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
                        <i class="fa-solid fa-xmark"></i> Batal
                    </button>
                    <span id="banner-feedback" class="text-sm font-semibold hidden"></span>
                </div>
            </form>
        </div>
    </div>
OLD;

$newPanel = <<<'NEW'
    <!-- PANEL BANNER: PLAYLIST -->
    <div id="panel-banner" class="hidden">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-5">
            <div>
                <h3 class="font-extrabold text-slate-900 text-lg flex items-center gap-2">
                    <i class="fa-solid fa-images text-astra-700"></i> Kelola Playlist Banner
                </h3>
                <p class="text-sm text-slate-500 mt-0.5">Buat playlist banner. Masing-masing playlist bisa berisi beberapa foto yang akan auto-slide. Playlist ditampilkan berurutan ke bawah di halaman utama.</p>
            </div>
            <button onclick="openPlaylistModal()" 
                class="bg-astra-700 hover:bg-astra-800 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2 flex-shrink-0">
                <i class="fa-solid fa-plus-circle"></i> Tambah Playlist
            </button>
        </div>

        <!-- Playlist List -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6">
            <h4 class="font-bold text-slate-800 flex items-center gap-2 mb-4"><i class="fa-solid fa-list text-astra-700"></i> Daftar Playlist</h4>
            <div id="playlist-list" class="space-y-4">
                <p class="text-slate-400 text-sm text-center py-8">Memuat data playlist...</p>
            </div>
        </div>
    </div>

    <!-- MODAL PLAYLIST -->
    <div id="modal-playlist" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl border border-slate-200 w-full max-w-2xl shadow-2xl flex flex-col overflow-hidden max-h-[90vh]">
            <div class="bg-astra-950 text-white p-4 flex items-center justify-between flex-shrink-0">
                <h3 id="modal-playlist-title" class="font-bold text-base flex items-center gap-2">
                    <i class="fa-solid fa-images text-astra-400"></i> <span id="modal-playlist-title-text">Tambah Playlist Baru</span>
                </h3>
                <button onclick="closePlaylistModal()" class="text-slate-400 hover:text-white text-lg"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="p-6 space-y-4 overflow-y-auto flex-grow">
                <input type="hidden" id="pl-id-input" value="">
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Nama Playlist</label>
                    <input type="text" id="pl-name-input" placeholder="Contoh: Promo Akhir Tahun"
                        class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Interval Slide (detik)</label>
                        <input type="number" id="pl-interval-input" value="5" min="2" max="30"
                            class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500">
                    </div>
                    <div class="flex items-end pb-2.5">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="pl-active-input" value="1" checked
                                class="w-4 h-4 rounded border-slate-300 text-astra-700 focus:ring-astra-500">
                            <span class="text-sm font-medium text-slate-700">Aktif</span>
                        </label>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-4">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Foto-foto Playlist</label>
                    <div id="pl-photos-container" class="flex gap-3 overflow-x-auto pb-3 snap-x min-h-[100px]">
                        <p class="text-sm text-slate-400 w-full text-center py-8">Belum ada foto. Upload foto di bawah.</p>
                    </div>

                    <div class="mt-3 p-4 bg-slate-50 rounded-xl border border-slate-200 border-dashed">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Tambah Foto Baru</label>
                        <input type="file" id="pl-photo-upload" multiple accept="image/jpeg,image/png,image/webp"
                            class="w-full bg-white border border-slate-300 text-slate-800 rounded-lg p-2 text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-astra-700 file:text-white file:text-xs file:font-bold hover:file:bg-astra-800 file:cursor-pointer focus:outline-none focus:border-astra-500">
                        <p class="text-xs text-slate-400 mt-1">Format: JPG, PNG, WEBP. Bisa pilih banyak sekaligus.</p>
                    </div>
                </div>

                <div id="pl-feedback" class="hidden text-sm font-semibold p-3 rounded-lg"></div>

                <div class="pt-2 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" onclick="closePlaylistModal()" class="px-4 py-2 border border-slate-300 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50">Batal</button>
                    <button type="button" onclick="savePlaylist()" id="btn-save-playlist"
                        class="px-5 py-2 bg-astra-700 hover:bg-astra-800 text-white rounded-lg text-xs font-bold flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan Playlist
                    </button>
                </div>
            </div>
        </div>
    </div>
NEW;

$content = str_replace($oldPanel, $newPanel, $content);

// 3. Replace the BANNER MANAGEMENT JS section
$oldJs = <<<'JS'
// ============================================================
// BANNER MANAGEMENT
// ============================================================
let bannerEditId = null;

function loadBanners() {
    const container = document.getElementById('banner-list');
    if (!container) return;
    container.innerHTML = '<p class="text-slate-400 text-sm text-center py-8">Memuat data banner...</p>';
    fetch('api_banner.php')
        .then(r => r.json())
        .then(banners => {
            if (!banners || banners.length === 0) {
                container.innerHTML = '<p class="text-slate-400 text-sm text-center py-8">Belum ada banner. Tambah banner baru di form di bawah.</p>';
                return;
            }
            container.innerHTML = '<div class="flex items-center gap-2 text-xs text-slate-400 mb-2 px-2"><i class="fa-solid fa-arrows-up-down"></i> Urutkan dengan drag & drop</div>';
            banners.forEach((b, i) => {
                const isActive = b.active !== false;
                const imgUrl = 'uploads/banners/' + b.image;
                const div = document.createElement('div');
                div.className = 'flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 banner-item';
                div.draggable = true;
                div.dataset.bannerId = b.id;
                div.innerHTML =
                    '<button type="button" class="cursor-grab text-slate-400 hover:text-slate-600 px-1" title="Seret untuk urutkan"><i class="fa-solid fa-grip-lines"></i></button>' +
                    '<img src="' + imgUrl + '" alt="' + escHtml(b.alt || '') + '" class="w-24 h-14 object-cover rounded-lg border border-slate-200 bg-slate-100 flex-shrink-0">' +
                    '<div class="flex-grow min-w-0">' +
                        '<p class="text-sm font-semibold text-slate-800 truncate">' + escHtml(b.alt || '(tanpa teks)') + '</p>' +
                        '<p class="text-xs text-slate-400">' + (b.link ? escHtml(b.link) : 'Tidak ada link') + ' &middot; ' + (isActive ? '<span class="text-green-600 font-medium">Aktif</span>' : '<span class="text-slate-400">Nonaktif</span>') + '</p>' +
                    '</div>' +
                    '<div class="flex items-center gap-1 flex-shrink-0">' +
                        '<button type="button" onclick="editBanner(\'' + b.id + '\')" class="text-xs text-astra-600 hover:text-astra-800 bg-astra-50 hover:bg-astra-100 px-2.5 py-1.5 rounded-lg font-semibold transition-colors"><i class="fa-solid fa-pen"></i></button>' +
                        '<button type="button" onclick="deleteBanner(\'' + b.id + '\')" class="text-xs text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded-lg font-semibold transition-colors"><i class="fa-solid fa-trash-can"></i></button>' +
                    '</div>';

                // Drag events for reorder
                div.addEventListener('dragstart', () => div.classList.add('opacity-50'));
                div.addEventListener('dragend', () => div.classList.remove('opacity-50'));
                div.addEventListener('dragover', e => { e.preventDefault(); div.classList.add('border-astra-500'); });
                div.addEventListener('dragleave', () => div.classList.remove('border-astra-500'));
                div.addEventListener('drop', e => {
                    e.preventDefault();
                    div.classList.remove('border-astra-500');
                    const items = [...container.querySelectorAll('.banner-item')];
                    const from = items.indexOf(container.querySelector('.opacity-50'));
                    const to = items.indexOf(div);
                    if (from !== -1 && to !== -1 && from !== to) {
                        const ref = to > from ? div.nextSibling : div;
                        container.insertBefore(items[from], ref);
                        saveBannerOrder();
                    }
                });
                container.appendChild(div);
            });
        })
        .catch(() => {
            container.innerHTML = '<p class="text-red-500 text-sm text-center py-8">Gagal memuat banner.</p>';
        });
}

function saveBannerOrder() {
    const items = [...document.querySelectorAll('.banner-item')];
    const ids = items.map(el => el.dataset.bannerId);
    const formData = new FormData();
    formData.append('action', 'reorder');
    formData.append('ids', JSON.stringify(ids));
    fetch('update_banner.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (!data.success) console.error('Reorder failed:', data.message);
        })
        .catch(err => console.error('Reorder error:', err));
}

function editBanner(id) {
    const items = document.querySelectorAll('.banner-item');
    for (const item of items) {
        if (item.dataset.bannerId === id) {
            const img = item.querySelector('img');
            const text = item.querySelector('.text-sm.font-semibold');
            const info = item.querySelector('.text-xs.text-slate-400');
            bannerEditId = id;
            document.getElementById('banner-form-title').textContent = 'Edit Banner';
            document.getElementById('banner-submit-text').textContent = 'Update Banner';
            document.getElementById('banner-id-input').value = id;
            document.getElementById('banner-alt-input').value = text ? text.textContent : '';
            document.getElementById('banner-link-input').value = info && !info.textContent.includes('Tidak ada link') ? info.textContent.split(' · ')[0] : '';
            document.getElementById('banner-active-input').checked = !info || !info.textContent.includes('Nonaktif');
            document.getElementById('btn-batal-banner').classList.remove('hidden');
            document.getElementById('banner-file-input').required = false;
            document.getElementById('btn-batal-banner').scrollIntoView({ behavior: 'smooth', block: 'center' });
            break;
        }
    }
}

function resetBannerForm() {
    bannerEditId = null;
    document.getElementById('banner-form-title').textContent = 'Tambah Banner Baru';
    document.getElementById('banner-submit-text').textContent = 'Simpan Banner';
    document.getElementById('banner-id-input').value = '';
    document.getElementById('banner-order-input').value = '';
    document.getElementById('banner-alt-input').value = '';
    document.getElementById('banner-link-input').value = '';
    document.getElementById('banner-active-input').checked = true;
    document.getElementById('banner-file-input').required = true;
    document.getElementById('banner-file-input').value = '';
    document.getElementById('btn-batal-banner').classList.add('hidden');
    document.getElementById('banner-feedback').classList.add('hidden');
}

function deleteBanner(id) {
    showConfirmModal('Hapus banner ini?', function() {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        fetch('update_banner.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showFeedback('banner-feedback', 'Banner berhasil dihapus.', 'green');
                    if (bannerEditId === id) resetBannerForm();
                    loadBanners();
                } else {
                    showFeedback('banner-feedback', data.message, 'red');
                }
            })
            .catch(() => showFeedback('banner-feedback', 'Gagal menghapus banner.', 'red'));
    });
}

function showFeedback(id, msg, color) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.className = 'text-sm font-semibold ' + (color === 'red' ? 'text-red-600' : 'text-green-600');
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 4000);
}

document.addEventListener('DOMContentLoaded', function() {
    const bannerForm = document.getElementById('banner-form');
    if (bannerForm) {
        bannerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            setBannerLoading(true);
            const safetyTimer = setTimeout(function() { setBannerLoading(false); }, 20000);

            const formData = new FormData(this);
            fetch('update_banner.php', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    clearTimeout(safetyTimer);
                    setBannerLoading(false);
                    if (data.success) {
                        showFeedback('banner-feedback', 'Banner berhasil disimpan.', 'green');
                        resetBannerForm();
                        loadBanners();
                    } else {
                        showFeedback('banner-feedback', data.message, 'red');
                    }
                })
                .catch(function() {
                    clearTimeout(safetyTimer);
                    setBannerLoading(false);
                    showFeedback('banner-feedback', 'Gagal menyimpan banner.', 'red');
                });
        });
    }

    function setBannerLoading(loading) {
        var btn = document.getElementById('btn-simpan-banner');
        var icon = document.getElementById('banner-btn-icon');
        var text = document.getElementById('banner-submit-text');
        if (!btn || !icon || !text) return;
        if (loading) {
            btn.disabled = true;
            icon.className = 'fa-solid fa-spinner fa-spin';
            text.textContent = 'Menyimpan...';
        } else {
            btn.disabled = false;
            icon.className = 'fa-solid fa-floppy-disk';
            text.textContent = bannerEditId ? 'Update Banner' : 'Simpan Banner';
        }
    }
});
JS;

$newJs = <<<'JS'
// ============================================================
// PLAYLIST BANNER MANAGEMENT
// ============================================================

function loadBanners() {
    const container = document.getElementById('playlist-list');
    if (!container) return;
    container.innerHTML = '<p class="text-slate-400 text-sm text-center py-8">Memuat data playlist...</p>';
    fetch('api_banner.php')
        .then(r => r.json())
        .then(playlists => {
            if (!playlists || playlists.length === 0) {
                container.innerHTML = '<div class="text-center py-8"><i class="fa-solid fa-images text-4xl text-slate-300 mb-3"></i><p class="text-slate-400 text-sm">Belum ada playlist. Klik "Tambah Playlist" untuk membuat playlist baru.</p></div>';
                return;
            }
            container.innerHTML = '';
            playlists.forEach((pl, idx) => {
                const isActive = pl.active !== false;
                const photoCount = (pl.photos || []).length;
                const firstPhoto = photoCount > 0 ? pl.photos[0].image : null;
                const imgHtml = firstPhoto
                    ? '<img src="uploads/banners/' + escHtml(firstPhoto) + '" class="w-28 h-16 object-cover rounded-lg border border-slate-200 bg-slate-100 flex-shrink-0">'
                    : '<div class="w-28 h-16 rounded-lg border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-300 text-xs flex-shrink-0"><i class="fa-solid fa-image"></i></div>';

                const div = document.createElement('div');
                div.className = 'flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200';
                div.innerHTML = 
                    '<div class="flex-shrink-0 w-7 text-center text-slate-400 font-bold text-sm">' + (idx + 1) + '</div>' +
                    imgHtml +
                    '<div class="flex-grow min-w-0">' +
                        '<div class="flex items-center gap-2 mb-1">' +
                            '<span class="text-sm font-bold text-slate-800 truncate">' + escHtml(pl.name || 'Playlist') + '</span>' +
                            '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full ' + (isActive ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-slate-100 text-slate-500 border border-slate-200') + '">' + (isActive ? 'Aktif' : 'Nonaktif') + '</span>' +
                        '</div>' +
                        '<p class="text-xs text-slate-500">' +
                            '<span class="font-semibold">' + photoCount + '</span> foto' +
                            (pl.interval ? ' &middot; Interval ' + (pl.interval / 1000) + ' detik' : '') +
                            (photoCount > 1 ? ' &middot; <span class="text-astra-600 font-medium">Auto-slide</span>' : '') +
                        '</p>' +
                    '</div>' +
                    '<div class="flex items-center gap-1 flex-shrink-0">' +
                        '<button type="button" onclick="openPlaylistModal(\'' + pl.id + '\')" class="text-xs text-astra-600 hover:text-astra-800 bg-astra-50 hover:bg-astra-100 px-2.5 py-1.5 rounded-lg font-semibold transition-colors"><i class="fa-solid fa-pen"></i></button>' +
                        '<button type="button" onclick="deletePlaylist(\'' + pl.id + '\')" class="text-xs text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded-lg font-semibold transition-colors"><i class="fa-solid fa-trash-can"></i></button>' +
                    '</div>';
                container.appendChild(div);
            });
        })
        .catch(() => {
            container.innerHTML = '<p class="text-red-500 text-sm text-center py-8">Gagal memuat playlist.</p>';
        });
}

// ── Playlist Modal ──
let _currentPlaylistId = null;
let _plPendingPhotos = [];

function openPlaylistModal(id) {
    const isEdit = !!id;
    _currentPlaylistId = id || null;
    _plPendingPhotos = [];

    document.getElementById('modal-playlist-title-text').textContent = isEdit ? 'Edit Playlist' : 'Tambah Playlist Baru';
    document.getElementById('pl-id-input').value = id || '';
    document.getElementById('pl-feedback').classList.add('hidden');
    document.getElementById('pl-photo-upload').value = '';

    if (isEdit) {
        fetch('api_banner.php')
            .then(r => r.json())
            .then(playlists => {
                const pl = playlists.find(p => p.id === id);
                if (!pl) { showNotification('Playlist tidak ditemukan.', 'error'); return; }
                document.getElementById('pl-name-input').value = pl.name || '';
                document.getElementById('pl-interval-input').value = (pl.interval || 5000) / 1000;
                document.getElementById('pl-active-input').checked = pl.active !== false;
                renderPlaylistPhotos(pl.photos || []);
            });
    } else {
        document.getElementById('pl-name-input').value = '';
        document.getElementById('pl-interval-input').value = 5;
        document.getElementById('pl-active-input').checked = true;
        renderPlaylistPhotos([]);
    }

    document.getElementById('modal-playlist').classList.remove('hidden');
}

function closePlaylistModal() {
    document.getElementById('modal-playlist').classList.add('hidden');
}

function renderPlaylistPhotos(photos) {
    const container = document.getElementById('pl-photos-container');
    container.innerHTML = '';

    if (photos.length === 0 && _plPendingPhotos.length === 0) {
        container.innerHTML = '<p class="text-sm text-slate-400 w-full text-center py-8">Belum ada foto. Upload foto di bawah.</p>';
        return;
    }

    photos.forEach((photo, idx) => {
        const div = document.createElement('div');
        div.className = 'flex-shrink-0 relative w-28 h-20 rounded-lg border border-slate-200 overflow-hidden group bg-slate-50 snap-center';
        div.innerHTML = 
            '<img src="uploads/banners/' + escHtml(photo.image) + '" class="w-full h-full object-cover">' +
            '<div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-1">' +
                '<button type="button" onclick="deletePlaylistPhoto(\'' + _currentPlaylistId + '\', ' + idx + ')" class="self-end text-white hover:text-red-400 text-xs"><i class="fa-solid fa-trash-can"></i></button>' +
                '<div class="flex justify-between">' +
                    '<button type="button" onclick="reorderPlaylistPhoto(' + idx + ', -1)" class="text-white hover:text-astra-400 text-xs ' + (idx === 0 ? 'invisible' : '') + '"><i class="fa-solid fa-chevron-left"></i></button>' +
                    '<span class="text-white text-[10px] font-bold px-1 bg-black/40 rounded">' + (idx + 1) + '</span>' +
                    '<button type="button" onclick="reorderPlaylistPhoto(' + idx + ', 1)" class="text-white hover:text-astra-400 text-xs ' + (idx === photos.length - 1 ? 'invisible' : '') + '"><i class="fa-solid fa-chevron-right"></i></button>' +
                '</div>' +
            '</div>';
        container.appendChild(div);
    });

    _plPendingPhotos.forEach((item, idx) => {
        const div = document.createElement('div');
        div.className = 'flex-shrink-0 relative w-28 h-20 rounded-lg border-2 border-astra-300 border-dashed overflow-hidden group bg-slate-50 snap-center';
        div.innerHTML = 
            '<img src="' + item.url + '" class="w-full h-full object-cover">' +
            '<div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-1">' +
                '<button type="button" onclick="removePendingPhoto(' + idx + ')" class="self-end text-white hover:text-red-400 text-xs"><i class="fa-solid fa-xmark"></i></button>' +
                '<div class="text-center">' +
                    '<span class="text-white text-[10px] font-bold px-1 bg-green-600/80 rounded">Baru</span>' +
                '</div>' +
            '</div>';
        container.appendChild(div);
    });
}

let _plFileCounter = 0;
document.addEventListener('DOMContentLoaded', function() {
    const upload = document.getElementById('pl-photo-upload');
    if (upload) {
        upload.addEventListener('change', function(e) {
            const files = Array.from(e.target.files || []);
            if (!files.length) return;
            files.forEach(file => {
                _plFileCounter++;
                _plPendingPhotos.push({ file, url: URL.createObjectURL(file), tempId: 'new_' + _plFileCounter });
            });
            const plId = _currentPlaylistId;
            if (plId) {
                fetch('api_banner.php')
                    .then(r => r.json())
                    .then(playlists => {
                        const pl = playlists.find(p => p.id === plId);
                        if (pl) renderPlaylistPhotos(pl.photos || []);
                    });
            } else {
                renderPlaylistPhotos([]);
            }
            e.target.value = '';
        });
    }
});

function removePendingPhoto(idx) {
    _plPendingPhotos.splice(idx, 1);
    const plId = _currentPlaylistId;
    if (plId) {
        fetch('api_banner.php')
            .then(r => r.json())
            .then(playlists => {
                const pl = playlists.find(p => p.id === plId);
                if (pl) renderPlaylistPhotos(pl.photos || []);
            });
    } else {
        renderPlaylistPhotos([]);
    }
}

function reorderPlaylistPhoto(idx, dir) {
    if (!_currentPlaylistId) return;
    fetch('api_banner.php')
        .then(r => r.json())
        .then(playlists => {
            const pl = playlists.find(p => p.id === _currentPlaylistId);
            if (!pl || !pl.photos) return;
            const photos = [...pl.photos];
            const newIdx = idx + dir;
            if (newIdx < 0 || newIdx >= photos.length) return;
            [photos[idx], photos[newIdx]] = [photos[newIdx], photos[idx]];
            
            const fd = new FormData();
            fd.append('action', 'reorder_playlist_photos');
            fd.append('playlist_id', _currentPlaylistId);
            fd.append('order', JSON.stringify(photos.map((_, i) => i)));
            fetch('update_banner.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderPlaylistPhotos(photos);
                        loadBanners();
                    }
                });
        });
}

function deletePlaylistPhoto(playlistId, idx) {
    showConfirmModal('Hapus foto ini dari playlist?', function() {
        const fd = new FormData();
        fd.append('action', 'delete_playlist_photo');
        fd.append('playlist_id', playlistId);
        fd.append('photo_index', idx);
        fetch('update_banner.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification('Foto berhasil dihapus.', 'success');
                    fetch('api_banner.php')
                        .then(r => r.json())
                        .then(playlists => {
                            const pl = playlists.find(p => p.id === playlistId);
                            if (pl) renderPlaylistPhotos(pl.photos || []);
                            loadBanners();
                        });
                } else {
                    showNotification(data.message, 'error');
                }
            });
    });
}

function savePlaylist() {
    const id = document.getElementById('pl-id-input').value.trim();
    const name = document.getElementById('pl-name-input').value.trim();
    const interval = parseInt(document.getElementById('pl-interval-input').value) * 1000 || 5000;
    const active = document.getElementById('pl-active-input').checked;

    if (!name) {
        showNotification('Nama playlist tidak boleh kosong.', 'error');
        return;
    }

    const btn = document.getElementById('btn-save-playlist');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> Menyimpan...';

    const fd = new FormData();
    fd.append('action', 'save_playlist');
    fd.append('id', id);
    fd.append('name', name);
    fd.append('interval', interval);
    fd.append('active', active ? '1' : '0');

    _plPendingPhotos.forEach(item => {
        fd.append('photos[]', item.file);
    });

    fetch('update_banner.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification('Playlist berhasil disimpan.', 'success');
                _plPendingPhotos = [];
                closePlaylistModal();
                loadBanners();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(() => showNotification('Gagal menyimpan playlist.', 'error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Playlist';
        });
}

function deletePlaylist(id) {
    showConfirmModal('Hapus playlist ini beserta semua fotonya?', function() {
        const fd = new FormData();
        fd.append('action', 'delete_playlist');
        fd.append('id', id);
        fetch('update_banner.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification('Playlist berhasil dihapus.', 'success');
                    loadBanners();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(() => showNotification('Gagal menghapus playlist.', 'error'));
    });
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
JS;

$content = str_replace($oldJs, $newJs, $content);

if (strpos($content, 'PANEL BANNER') !== false && strpos($content, 'PLAYLIST') !== false) {
    echo "Replacement 1 (HTML panel) applied\n";
}
if (strpos($content, 'PLAILIST BANNER MANAGEMENT') !== false || strpos($content, '// PLAYLIST BANNER MANAGEMENT') !== false) {
    echo "Replacement 2 (JS section) applied\n";
}

$result = file_put_contents($file, $content);
if ($result !== false) {
    echo "File saved successfully ($result bytes written)\n";
} else {
    echo "FAILED to save file\n";
}

// Check for old banner ID references that should be removed
$oldBannerRefs = ['banner-list', 'banner-form', 'banner-id-input', 'banner-file-input', 'banner-alt-input', 'banner-link-input', 'banner-active-input', 'banner-form-title', 'btn-simpan-banner', 'btn-batal-banner', 'banner-feedback', 'banner-submit-text', 'banner-btn-icon', 'bannerEditId', 'resetBannerForm', 'editBanner', 'deleteBanner', 'saveBannerOrder', 'showFeedback', 'banner-item'];
$found = [];
foreach ($oldBannerRefs as $ref) {
    if (strpos($content, $ref) !== false) {
        $found[] = $ref;
    }
}
if (!empty($found)) {
    echo "WARNING: Still found old banner references: " . implode(', ', $found) . "\n";
} else {
    echo "All old banner references cleaned up successfully.\n";
}
