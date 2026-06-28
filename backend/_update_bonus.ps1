$file = "website/backend/admin.php"
$content = [System.IO.File]::ReadAllText($file)

# ============================================================
# 1. Update renderRevSummary to show BONUS cards
# ============================================================
$oldSummaryFunc = @'
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

$newSummaryFunc = @'
function renderRevSummary(d) {
    const fmt = v => 'Rp ' + Number(v).toLocaleString('id-ID');
    const canProfit = d.can_calc_profit === true;
    const b = d.bonus || {};
    const hasBonus = b.total_transaksi > 0 || b.total_penjualan > 0;
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
    if (hasBonus) {
        const bonusVal = '<span class="text-red-500 line-through text-sm mr-1">' + fmt(b.total_penjualan) + '</span> <span class="text-green-600 font-bold">- ' + fmt(b.total_penjualan) + '</span>';
        cards.push({ icon: 'fa-gift', color: 'text-orange-600 bg-orange-50 border-orange-200', label: 'BONUS (Deduksi)', value: fmt(b.total_penjualan) });
        if (canProfit && b.total_hpp > 0) {
            cards.push({ icon: 'fa-box-open', color: 'text-rose-600 bg-rose-50 border-rose-200', label: 'Modal BONUS', value: fmt(b.total_hpp) });
        }
        const mgNb = d.margin_non_bonus || 0;
        const mgNbClass = mgNb >= 20 ? 'text-green-600' : (mgNb >= 10 ? 'text-amber-600' : 'text-red-600');
        cards.push({ icon: 'fa-chart-pie', color: 'text-emerald-700 bg-emerald-50 border-emerald-200', label: 'Bersih (excl. BONUS)', value: fmt(d.pendapatan_bersih_non_bonus) + ' <span class="text-xs font-bold ml-1 ' + mgNbClass + '">(' + mgNb + '%)</span>' });
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
    Write-Host "❌ Could not find renderRevSummary function"
}

# ============================================================
# 2. Update renderRevDaily to show BONUS columns
# ============================================================
$oldDailyFunc = @'
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

$newDailyFunc = @'
function renderRevDaily(harian) {
    const fmt = v => 'Rp ' + Number(v).toLocaleString('id-ID');
    document.getElementById('rev-daily-body').innerHTML = harian.map(h => {
        const t = new Date(h.tgl + 'T00:00:00');
        const hasProfit = h.pendapatan_bersih !== undefined;
        const hasBonus = h.bonus_jumlah !== undefined && h.bonus_jumlah > 0;
        let extraCols = '';
        if (hasProfit && h.total_hpp !== undefined) {
            const marginH = h.total > 0 ? ((h.pendapatan_bersih / h.total) * 100).toFixed(1) : '0.0';
            const mgClass = marginH >= 20 ? 'text-green-600' : (marginH >= 10 ? 'text-amber-600' : 'text-red-600');
            extraCols = '<td class="px-5 py-3 text-right text-slate-600">' + fmt(h.total_hpp) + '</td>' +
                '<td class="px-5 py-3 text-right font-bold text-emerald-600">' + fmt(h.pendapatan_bersih) + '</td>' +
                '<td class="px-5 py-3 text-right font-bold ' + mgClass + '">' + marginH + '%</td>';
        }
        let bonusCols = '';
        if (hasBonus) {
            bonusCols = '<td class="px-5 py-3 text-right text-xs text-orange-600 font-bold">' + h.bonus_jumlah + '</td>' +
                '<td class="px-5 py-3 text-right text-xs text-orange-600">' + fmt(h.bonus_total) + '</td>';
        }
        return '<tr class="hover:bg-slate-50 transition-colors">' +
            '<td class="px-5 py-3 font-semibold text-slate-700">' + t.toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'}) + '</td>' +
            '<td class="px-5 py-3 text-right font-bold text-slate-600">' + h.jumlah + '</td>' +
            '<td class="px-5 py-3 text-right font-bold text-emerald-600">' + fmt(h.total) + '</td>' + extraCols + bonusCols + '</tr>';
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
# 3. Update the daily table header to add BONUS columns
# ============================================================
$oldHeader = '<tr><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3 text-right">Jumlah</th><th class="px-5 py-3 text-right">Penjualan</th><th class="px-5 py-3 text-right">Modal (HPP)</th><th class="px-5 py-3 text-right">Bersih</th><th class="px-5 py-3 text-right">Margin</th></tr>'
$newHeader = '<tr><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3 text-right">Jumlah</th><th class="px-5 py-3 text-right">Penjualan</th><th class="px-5 py-3 text-right">Modal (HPP)</th><th class="px-5 py-3 text-right">Bersih</th><th class="px-5 py-3 text-right">Margin</th><th class="px-5 py-3 text-right text-orange-600">BONUS</th><th class="px-5 py-3 text-right text-orange-600">Nilai BONUS</th></tr>'

if ($content.Contains($oldHeader)) {
    $content = $content.Replace($oldHeader, $newHeader)
    Write-Host "✅ Updated daily table header"
} else {
    Write-Host "❌ Could not find daily table header"
}

# ============================================================
# 4. Update the description text to mention BONUS deduction
# ============================================================
$oldDesc = 'Lihat total penjualan dan laba berdasarkan rentang tanggal.'
$newDesc = 'Lihat total penjualan, laba, dan deduksi BONUS berdasarkan rentang tanggal. Transaksi ke pelanggan BONUS otomatis dipisahkan.'

if ($content.Contains($oldDesc)) {
    $content = $content.Replace($oldDesc, $newDesc)
    Write-Host "✅ Updated description text"
} else {
    Write-Host "❌ Could not find description text"
}

[System.IO.File]::WriteAllText($file, $content)
Write-Host "`n=== Script completed ==="
