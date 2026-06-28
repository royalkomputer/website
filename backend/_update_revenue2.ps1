$file = "website/backend/admin.php"
$content = [System.IO.File]::ReadAllText($file)

# ============================================================
# 1. Update renderRevSummary - add HPP, Pendapatan Bersih, Margin
# ============================================================
$oldSummaryFunc = @'
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
'@

$newSummaryFunc = @'
function renderRevSummary(d) {
    const fmt = v => 'Rp ' + Number(v).toLocaleString('id-ID');
    const canProfit = d.can_calc_profit === true;
    const cards = [
        { icon: 'fa-money-bill-wave', color: 'text-emerald-600 bg-emerald-50 border-emerald-200', label: 'Total Penjualan', value: fmt(d.total_penjualan) },
        { icon: 'fa-receipt', color: 'text-blue-600 bg-blue-50 border-blue-200', label: 'Jumlah Transaksi', value: d.total_transaksi + ' transaksi' },
        { icon: 'fa-cube', color: 'text-sky-600 bg-sky-50 border-sky-200', label: 'Item Terjual', value: d.total_item_terjual + ' item' },
        { icon: 'fa-chart-simple', color: 'text-purple-600 bg-purple-50 border-purple-200', label: 'Rata-rata per Hari', value: fmt(d.rata_rata_per_hari) },
        { icon: 'fa-calculator', color: 'text-amber-600 bg-amber-50 border-amber-200', label: 'Rata-rata per Transaksi', value: fmt(d.rata_rata_per_transaksi) }
    ];
    if (canProfit) {
        const mgClass = d.margin_persen >= 20 ? 'text-green-600 bg-green-50 border-green-200' : (d.margin_persen >= 10 ? 'text-amber-600 bg-amber-50 border-amber-200' : 'text-red-600 bg-red-50 border-red-200');
        cards.push({ icon: 'fa-coins', color: 'text-slate-600 bg-slate-50 border-slate-200', label: 'Total Modal (HPP)', value: fmt(d.total_hpp) });
        cards.push({ icon: 'fa-sack-dollar', color: 'text-green-600 bg-green-50 border-green-200', label: 'Pendapatan Bersih', value: fmt(d.pendapatan_bersih) + ' <span class="text-xs font-bold ml-1 ' + mgClass.split(' ')[0] + '">(' + d.margin_persen + '%)</span>' });
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
'@

if ($content.Contains($oldSummaryFunc)) {
    $content = $content.Replace($oldSummaryFunc, $newSummaryFunc)
    Write-Host "✅ Updated renderRevSummary function"
} else {
    Write-Host "❌ Could not find renderRevSummary function (exact match failed)"
    # Show first 100 chars of the actual function for debugging
    $idx = $content.IndexOf("function renderRevSummary")
    if ($idx -ge 0) {
        Write-Host "Found at index $idx, first 300 chars:"
        Write-Host $content.Substring($idx, [Math]::Min(300, $content.Length - $idx))
    }
}

# ============================================================
# 2. Update renderRevDaily - add HPP, Bersih, Margin columns
# ============================================================
$oldDailyFunc = @'
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
'@

$newDailyFunc = @'
function renderRevDaily(harian) {
    const fmt = v => 'Rp ' + Number(v).toLocaleString('id-ID');
    document.getElementById('rev-daily-body').innerHTML = harian.map(h => {
        const t = new Date(h.tgl + 'T00:00:00');
        const hasProfit = h.pendapatan_bersih !== undefined;
        let extraCols = '';
        if (hasProfit && h.total_hpp !== undefined) {
            const marginH = h.total > 0 ? ((h.pendapatan_bersih / h.total) * 100).toFixed(1) : '0.0';
            const mgClass = marginH >= 20 ? 'text-green-600' : (marginH >= 10 ? 'text-amber-600' : 'text-red-600');
            extraCols = '<td class="px-5 py-3 text-right text-slate-600">' + fmt(h.total_hpp) + '</td>' +
                '<td class="px-5 py-3 text-right font-bold text-emerald-600">' + fmt(h.pendapatan_bersih) + '</td>' +
                '<td class="px-5 py-3 text-right font-bold ' + mgClass + '">' + marginH + '%</td>';
        }
        return '<tr class="hover:bg-slate-50 transition-colors">' +
            '<td class="px-5 py-3 font-semibold text-slate-700">' + t.toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'}) + '</td>' +
            '<td class="px-5 py-3 text-right font-bold text-slate-600">' + h.jumlah + '</td>' +
            '<td class="px-5 py-3 text-right font-bold text-emerald-600">' + fmt(h.total) + '</td>' + extraCols + '</tr>';
    }).join('');
}
'@

if ($content.Contains($oldDailyFunc)) {
    $content = $content.Replace($oldDailyFunc, $newDailyFunc)
    Write-Host "✅ Updated renderRevDaily function"
} else {
    Write-Host "❌ Could not find renderRevDaily function"
}

# ============================================================
# 3. Update the daily table header
# ============================================================
$oldDailyHeader1 = '<th class="px-5 py-3">Tanggal</th><th class="px-5 py-3 text-right">Jumlah Transaksi</th><th class="px-5 py-3 text-right">Total Penjualan</th>'
$newDailyHeader1 = '<th class="px-5 py-3">Tanggal</th><th class="px-5 py-3 text-right">Jumlah</th><th class="px-5 py-3 text-right">Penjualan</th><th class="px-5 py-3 text-right">Modal (HPP)</th><th class="px-5 py-3 text-right">Bersih</th><th class="px-5 py-3 text-right">Margin</th>'

if ($content.Contains($oldDailyHeader1)) {
    $content = $content.Replace($oldDailyHeader1, $newDailyHeader1)
    Write-Host "✅ Updated daily table header"
} else {
    Write-Host "❌ Could not find daily table header (variant 1)"
    # Try another variant
    if ($content.Contains('Jumlah Transaksi')) {
        Write-Host "Found 'Jumlah Transaksi' in file"
    }
}

# ============================================================
# 4. Update summary grid to support 6 columns
# ============================================================
$oldGrid = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" id="rev-summary"'
$newGrid = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6" id="rev-summary"'

if ($content.Contains($oldGrid)) {
    $content = $content.Replace($oldGrid, $newGrid)
    Write-Host "✅ Updated summary grid to support 6 columns"
} else {
    Write-Host "❌ Could not find summary grid with id attribute"
    # Search for any grid pattern
    $idx2 = $content.IndexOf("rev-summary")
    if ($idx2 -ge 0) {
        Write-Host "Found rev-summary at $idx2, context: " + $content.Substring([Math]::Max(0,$idx2-80), [Math]::Min(160, $content.Length - $idx2 + 80))
    }
}

[System.IO.File]::WriteAllText($file, $content)
Write-Host "`n=== Script completed ==="
