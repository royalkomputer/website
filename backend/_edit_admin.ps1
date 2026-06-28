# PowerShell script to add Penghasilan tab to admin.php - using regex for flexibility
$file = "website/backend/admin.php"
$content = Get-Content $file -Raw

# 1. Add tab button after Push ke Git button (using regex)
$oldTabPattern = "(<button onclick=\""switchTab\('push'\)\""[^>]*>[\s\S]*?Push ke Git\s*</button>)(\s*</div>)"
$replacement = "`$1
        <button onclick=\""switchTab('penghasilan')\"" id=\""tab-penghasilan\"" class=\""tab-btn flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100\"">
            <i class=\""fa-solid fa-money-bill-trend-up\""></i> Penghasilan
        </button>`$2"

if ($content -match $oldTabPattern) {
    $content = $content -replace $oldTabPattern, $replacement
    Write-Host "Tab button added successfully"
} else {
    Write-Host "ERROR: Could not find push tab button section"
    exit 1
}

# 2. Update panels array
$oldPanels = "const panels = ['katalog','jam','schedule','admin','ui','banner','profil','serial','push'];"
$newPanels = "const panels = ['katalog','jam','schedule','admin','ui','banner','profil','serial','push','penghasilan'];"

if ($content.Contains($oldPanels)) {
    $content = $content.Replace($oldPanels, $newPanels)
    Write-Host "Panels array updated successfully"
} else {
    Write-Host "ERROR: Could not find panels array"
    exit 1
}

# 3. Add penghasilan panel HTML before </main>
$panelPenghasilan = @'
    <!-- PANEL PENGHASILAN -->
    <div id="panel-penghasilan" class="hidden">
        <div class="mb-5">
            <h3 class="font-extrabold text-slate-900 text-lg flex items-center gap-2">
                <i class="fa-solid fa-money-bill-trend-up text-astra-700"></i> Laporan Penghasilan
            </h3>
            <p class="text-sm text-slate-500 mt-0.5">Lihat total penjualan dan laba berdasarkan rentang tanggal.</p>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Tanggal Mulai</label>
                    <input type="date" id="rev-tgl-mulai" class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Tanggal Selesai</label>
                    <input type="date" id="rev-tgl-selesai" class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-lg p-2.5 text-sm focus:outline-none focus:border-astra-500 focus:ring-1 focus:ring-astra-500">
                </div>
                <div class="flex gap-2">
                    <button onclick="loadRevenueData()" id="btn-rev-load" class="bg-astra-700 hover:bg-astra-800 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition-all shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-magnifying-glass"></i> Tampilkan
                    </button>
                    <button onclick="setRevRange('today')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2.5 rounded-lg text-xs font-bold transition-colors border border-slate-200">Hari ini</button>
                    <button onclick="setRevRange('week')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2.5 rounded-lg text-xs font-bold transition-colors border border-slate-200">7 Hari</button>
                    <button onclick="setRevRange('month')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2.5 rounded-lg text-xs font-bold transition-colors border border-slate-200">Bulan ini</button>
                    <button onclick="setRevRange('year')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2.5 rounded-lg text-xs font-bold transition-colors border border-slate-200">Tahun ini</button>
                </div>
            </div>
        </div>
        <div id="rev-loading" class="hidden py-12 flex flex-col items-center justify-center gap-3">
            <i class="fa-solid fa-circle-notch text-3xl text-astra-700 animate-spin"></i>
            <p class="text-slate-500 text-sm font-medium">Memuat data penghasilan...</p>
        </div>
        <div id="rev-summary" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6"></div>
        <div id="rev-daily-section" class="hidden bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h4 class="font-bold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-calendar-day text-astra-700"></i> Penjualan Harian</h4>
                <span id="rev-daily-count" class="text-xs text-slate-400"></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                        <tr><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3 text-right">Jumlah Transaksi</th><th class="px-5 py-3 text-right">Total Penjualan</th></tr>
                    </thead>
                    <tbody id="rev-daily-body" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>
        <div id="rev-trans-section" class="hidden bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h4 class="font-bold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-receipt text-astra-700"></i> Transaksi Terbaru</h4>
                <span id="rev-trans-count" class="text-xs text-slate-400"></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                        <tr><th class="px-5 py-3">Nota</th><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Pelanggan</th><th class="px-5 py-3 text-right">Total</th></tr>
                    </thead>
                    <tbody id="rev-trans-body" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>
        <div id="rev-empty" class="hidden py-12 text-center">
            <i class="fa-solid fa-chart-line text-4xl text-slate-300 mb-3"></i>
            <p class="text-slate-500 text-sm font-medium">Tidak ada data penjualan untuk rentang tanggal ini.</p>
        </div>
        <div id="rev-error" class="hidden py-12 text-center">
            <i class="fa-solid fa-triangle-exclamation text-4xl text-red-300 mb-3"></i>
            <p id="rev-error-text" class="text-red-500 text-sm font-medium"></p>
        </div>
    </div>

'@

$closingMain = "`r`n        </main>"
$content = $content.Replace($closingMain, "`r`n" + $panelPenghasilan + $closingMain)
Write-Host "Panel HTML added successfully"

# 4. Add JavaScript functions before </script>
$revFunctions = @'

// ============================================================
// PENGHASILAN / REVENUE
// ============================================================
function setRevRange(range) {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    const tglStr = y + '-' + m + '-' + d;
    let mulai = tglStr;
    if (range === 'week') {
        const wa = new Date(today); wa.setDate(wa.getDate() - 6);
        mulai = wa.getFullYear()+'-'+String(wa.getMonth()+1).padStart(2,'0')+'-'+String(wa.getDate()).padStart(2,'0');
    } else if (range === 'month') { mulai = y + '-' + m + '-01'; }
    else if (range === 'year') { mulai = y + '-01-01'; }
    document.getElementById('rev-tgl-mulai').value = mulai;
    document.getElementById('rev-tgl-selesai').value = tglStr;
    loadRevenueData();
}

function loadRevenueData() {
    const mulai = document.getElementById('rev-tgl-mulai').value;
    const selesai = document.getElementById('rev-tgl-selesai').value;
    if (!mulai || !selesai) { showNotification('Pilih tanggal mulai dan selesai.', 'error'); return; }
    const el = id => document.getElementById(id);
    el('rev-loading').classList.remove('hidden');
    el('rev-summary').classList.add('hidden');
    el('rev-daily-section').classList.add('hidden');
    el('rev-trans-section').classList.add('hidden');
    el('rev-empty').classList.add('hidden');
    el('rev-error').classList.add('hidden');
    const btn = el('btn-rev-load'); btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> Memuat...';
    const fd = new FormData(); fd.append('tgl_mulai', mulai); fd.append('tgl_selesai', selesai);
    fetch('api_penghasilan.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(data => {
            el('rev-loading').classList.add('hidden');
            btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Tampilkan';
            if (!data.success) { el('rev-error').classList.remove('hidden'); el('rev-error-text').textContent = data.message; return; }
            const d = data.data;
            if (d.total_transaksi === 0) { el('rev-empty').classList.remove('hidden'); return; }
            renderRevSummary(d); el('rev-summary').classList.remove('hidden');
            if (d.harian && d.harian.length > 0) { renderRevDaily(d.harian); el('rev-daily-count').textContent = d.harian.length + ' hari'; el('rev-daily-section').classList.remove('hidden'); }
            if (d.transaksi_terbaru && d.transaksi_terbaru.length > 0) { renderRevTransactions(d.transaksi_terbaru); el('rev-trans-count').textContent = d.transaksi_terbaru.length + ' transaksi'; el('rev-trans-section').classList.remove('hidden'); }
        }).catch(() => {
            el('rev-loading').classList.add('hidden');
            btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Tampilkan';
            el('rev-error').classList.remove('hidden'); el('rev-error-text').textContent = 'Gagal terhubung ke server.';
        });
}

function renderRevSummary(d) {
    const fmt = v => 'Rp ' + Number(v).toLocaleString('id-ID');
    const cards = [
        { icon: 'fa-money-bill-wave', color: 'text-emerald-600 bg-emerald-50 border-emerald-200', label: 'Total Penjualan', value: fmt(d.total_penjualan) },
        { icon: 'fa-receipt', color: 'text-blue-600 bg-blue-50 border-blue-200', label: 'Jumlah Transaksi', value: d.total_transaksi + ' transaksi' },
        { icon: 'fa-chart-simple', color: 'text-purple-600 bg-purple-50 border-purple-200', label: 'Rata-rata per Hari', value: fmt(d.rata_rata_per_hari) },
        { icon: 'fa-calculator', color: 'text-amber-600 bg-amber-50 border-amber-200', label: 'Rata-rata per Transaksi', value: fmt(d.rata_rata_per_transaksi) }
    ];
    if (d.profit) {
        cards.push({ icon: 'fa-coins', color: 'text-green-600 bg-green-50 border-green-200', label: 'Laba Kotor', value: fmt(d.profit.total_laba_kotor) });
        cards.push({ icon: 'fa-cube', color: 'text-sky-600 bg-sky-50 border-sky-200', label: 'Item Terjual', value: d.profit.total_item_terjual + ' item' });
        cards.push({ icon: 'fa-cart-shopping', color: 'text-slate-600 bg-slate-50 border-slate-200', label: 'Total Modal', value: fmt(d.profit.total_modal) });
    }
    document.getElementById('rev-summary').innerHTML = cards.map(c =>
        '<div class="bg-white rounded-xl border ' + c.color.split(' ')[2] + ' shadow-sm p-5">' +
            '<div class="flex items-center gap-3 mb-3">' +
                '<div class="w-10 h-10 rounded-lg ' + c.color.split(' ').slice(1,3).join(' ') + ' flex items-center justify-center"><i class="fa-solid ' + c.icon + ' ' + c.color.split(' ')[0] + '"></i></div>' +
                '<span class="text-xs font-bold text-slate-400 uppercase tracking-wider">' + c.label + '</span>' +
            '</div>' +
            '<div class="text-xl font-extrabold text-slate-900">' + c.value + '</div>' +
        '</div>'
    ).join('');
}

function renderRevDaily(harian) {
    const fmt = v => 'Rp ' + Number(v).toLocaleString('id-ID');
    document.getElementById('rev-daily-body').innerHTML = harian.map(h => {
        const t = new Date(h.tgl + 'T00:00:00');
        return '<tr class="hover:bg-slate-50 transition-colors">' +
            '<td class="px-5 py-3 font-semibold text-slate-700">' + t.toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'}) + '</td>' +
            '<td class="px-5 py-3 text-right font-bold text-slate-600">' + h.jumlah + '</td>' +
            '<td class="px-5 py-3 text-right font-bold text-emerald-600">' + fmt(h.total) + '</td></tr>';
    }).join('');
}

function renderRevTransactions(transaksi) {
    const fmt = v => 'Rp ' + Number(v).toLocaleString('id-ID');
    document.getElementById('rev-trans-body').innerHTML = transaksi.map(t => {
        const d = new Date(t.tgl + 'T00:00:00');
        return '<tr class="hover:bg-slate-50 transition-colors">' +
            '<td class="px-5 py-3 font-mono text-xs font-bold text-astra-700">' + escHtml(t.notransaksi) + '</td>' +
            '<td class="px-5 py-3 text-sm text-slate-600">' + d.toLocaleDateString('id-ID',{year:'numeric',month:'short',day:'numeric'}) + '</td>' +
            '<td class="px-5 py-3 text-sm text-slate-600">' + escHtml(t.pelanggan) + '</td>' +
            '<td class="px-5 py-3 text-right font-bold text-emerald-600">' + fmt(t.totalakhir) + '</td></tr>';
    }).join('');
}
'@

$scriptEnd = "`r`n</script>"
$content = $content.Replace($scriptEnd, "`r`n" + $revFunctions + $scriptEnd)
Write-Host "JavaScript functions added successfully"

# Write
Set-Content $file $content -NoNewline
Write-Host "All edits completed successfully!"
