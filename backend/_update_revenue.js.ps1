$file = "website/backend/admin.php"
$content = [System.IO.File]::ReadAllText($file)

# ============================================================
# 1. Update renderRevSummary to add HPP, Pendapatan Bersih, Margin cards
# ============================================================
$oldSummary = @'
function renderRevSummary(d) {
    const c = document.getElementById('rev-summary');
    c.classList.remove('hidden');
    const fmt = (v) => 'Rp ' + Number(v).toLocaleString('id-ID');
'@

$newSummary = @'
function renderRevSummary(d) {
    const c = document.getElementById('rev-summary');
    c.classList.remove('hidden');
    const fmt = (v) => 'Rp ' + Number(v).toLocaleString('id-ID');
    const canProfit = d.can_calc_profit === true;

    let profitCards = '';
    if (canProfit) {
        const marginClass = d.margin_persen >= 20 ? 'text-green-600' : (d.margin_persen >= 10 ? 'text-amber-600' : 'text-red-600');
        profitCards = `
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-coins text-slate-400"></i> Total Modal (HPP)</div>
                <div class="text-lg font-extrabold text-slate-600">${fmt(d.total_hpp)}</div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-sack-dollar text-emerald-500"></i> Pendapatan Bersih</div>
                <div class="text-lg font-extrabold text-emerald-600">${fmt(d.pendapatan_bersih)}</div>
                <div class="text-xs text-slate-400 mt-1">Margin: <span class="${marginClass} font-bold">${d.margin_persen}%</span></div>
            </div>
        `;
    }
'@

if ($content.Contains("function renderRevSummary(d) {")) {
    $content = $content.Replace($oldSummary, $newSummary)
    Write-Host "✅ Updated renderRevSummary function"
} else {
    Write-Host "❌ Could not find renderRevSummary function"
}

# ============================================================
# 2. Update the summary HTML template that generates the cards
# ============================================================
$oldCards = @'
    c.innerHTML = `
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-cart-shopping text-astra-400"></i> Total Penjualan</div>
            <div class="text-lg font-extrabold text-astra-700">${fmt(d.total_penjualan)}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-receipt text-slate-400"></i> Jumlah Transaksi</div>
            <div class="text-lg font-extrabold text-slate-800">${d.total_transaksi}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-boxes text-slate-400"></i> Item Terjual</div>
            <div class="text-lg font-extrabold text-slate-800">${d.total_item_terjual}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-chart-line text-slate-400"></i> Rata-rata / Hari</div>
            <div class="text-lg font-extrabold text-slate-800">${fmt(d.rata_rata_per_hari)}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-chart-bar text-slate-400"></i> Rata-rata / Transaksi</div>
            <div class="text-lg font-extrabold text-slate-800">${fmt(d.rata_rata_per_transaksi)}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-calendar-days text-slate-400"></i> Hari dalam Rentang</div>
            <div class="text-lg font-extrabold text-slate-800">${d.hari_rentang} hari</div>
        </div>
    `;
'@

$newCards = @'
    c.innerHTML = `
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-cart-shopping text-astra-400"></i> Total Penjualan</div>
            <div class="text-lg font-extrabold text-astra-700">${fmt(d.total_penjualan)}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-receipt text-slate-400"></i> Jumlah Transaksi</div>
            <div class="text-lg font-extrabold text-slate-800">${d.total_transaksi}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-boxes text-slate-400"></i> Item Terjual</div>
            <div class="text-lg font-extrabold text-slate-800">${d.total_item_terjual}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-chart-line text-slate-400"></i> Rata-rata / Hari</div>
            <div class="text-lg font-extrabold text-slate-800">${fmt(d.rata_rata_per_hari)}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-chart-bar text-slate-400"></i> Rata-rata / Transaksi</div>
            <div class="text-lg font-extrabold text-slate-800">${fmt(d.rata_rata_per_transaksi)}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-2 text-slate-400 text-xs font-bold uppercase tracking-wider mb-2"><i class="fa-solid fa-calendar-days text-slate-400"></i> Hari dalam Rentang</div>
            <div class="text-lg font-extrabold text-slate-800">${d.hari_rentang} hari</div>
        </div>
        ${profitCards}
    `;
'@

if ($content.Contains($oldCards)) {
    $content = $content.Replace($oldCards, $newCards)
    Write-Host "✅ Updated summary cards HTML"
} else {
    Write-Host "❌ Could not find summary cards HTML template"
}

# ============================================================
# 3. Update renderRevDaily to add Pendapatan Bersih column
# ============================================================
$oldDailyHead = @'
                        <tr><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3 text-right">Jumlah Transaksi</th><th class="px-5 py-3 text-right">Total Penjualan</th></tr>
'@

$newDailyHead = @'
                        <tr><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3 text-right">Jumlah</th><th class="px-5 py-3 text-right">Penjualan</th><th class="px-5 py-3 text-right">Modal (HPP)</th><th class="px-5 py-3 text-right">Bersih</th><th class="px-5 py-3 text-right">Margin</th></tr>
'@

if ($content.Contains($oldDailyHead)) {
    $content = $content.Replace($oldDailyHead, $newDailyHead)
    Write-Host "✅ Updated daily table header"
} else {
    Write-Host "❌ Could not find daily table header"
}

# ============================================================
# 4. Update renderRevDaily function body
# ============================================================
$oldRenderDaily = @'
    harian.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td class="px-5 py-3 font-medium text-slate-700">${r.tgl}</td><td class="px-5 py-3 text-right">${r.jumlah}</td><td class="px-5 py-3 text-right font-bold text-astra-700">${fmt(r.total)}</td>`;
        b.appendChild(tr);
    });
'@

$newRenderDaily = @'
    harian.forEach(r => {
        const tr = document.createElement('tr');
        const hasProfit = r.pendapatan_bersih !== undefined;
        let extraCols = '';
        if (hasProfit && r.total_hpp !== undefined) {
            const marginH = r.total > 0 ? ((r.pendapatan_bersih / r.total) * 100).toFixed(1) : '0.0';
            const mgClass = marginH >= 20 ? 'text-green-600' : (marginH >= 10 ? 'text-amber-600' : 'text-red-600');
            extraCols = `
                <td class="px-5 py-3 text-right text-slate-600">${fmt(r.total_hpp)}</td>
                <td class="px-5 py-3 text-right font-bold text-emerald-600">${fmt(r.pendapatan_bersih)}</td>
                <td class="px-5 py-3 text-right font-bold ${mgClass}">${marginH}%</td>`;
        }
        tr.innerHTML = `<td class="px-5 py-3 font-medium text-slate-700">${r.tgl}</td><td class="px-5 py-3 text-right">${r.jumlah}</td><td class="px-5 py-3 text-right font-bold text-astra-700">${fmt(r.total)}</td>${extraCols}`;
        b.appendChild(tr);
    });
'@

if ($content.Contains($oldRenderDaily)) {
    $content = $content.Replace($oldRenderDaily, $newRenderDaily)
    Write-Host "✅ Updated daily table render body"
} else {
    Write-Host "❌ Could not find daily table render body"
}

# ============================================================
# 5. Update grid to allow 6 columns when profit is available
# ============================================================
$oldGrid1 = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">'
$newGrid1 = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" id="rev-summary"'

# Only update if we find the old pattern - this part might already be correct
# Actually, we need this pattern to make the grid dynamic
# Let's update the grid to accommodate 6 cards

$oldGridClass = 'lg:grid-cols-4'
$newGridClass = 'lg:grid-cols-3 xl:grid-cols-6'

# The summary grid is in the HTML template, not JS. Let's find and update it.
$oldSummaryGrid = '<div id="rev-summary" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">'

if ($content.Contains($oldSummaryGrid)) {
    $content = $content.Replace($oldSummaryGrid, '<div id="rev-summary" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">')
    Write-Host "✅ Updated summary grid to support 6 columns"
} else {
    Write-Host "❌ Could not find summary grid HTML"
}

[System.IO.File]::WriteAllText($file, $content)
Write-Host "`n=== Script completed ==="
