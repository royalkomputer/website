$file = "website/backend/admin.php"
$content = [System.IO.File]::ReadAllText($file)

# ============================================================
# 1. Update renderRevSummary - use d.deductions instead of d.bonus, add RUSAK
# ============================================================
$oldSummaryFunc = @'
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

$newSummaryFunc = @'
function renderRevSummary(d) {
    const fmt = v => 'Rp ' + Number(v).toLocaleString('id-ID');
    const canProfit = d.can_calc_profit === true;
    const ded = d.deductions || {};
    const hasDed = d.total_deductions_penjualan > 0;
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
    // Deduction cards per category
    if (hasDed) {
        const dedConfigs = { bonus: { icon: 'fa-gift', cat: 'BONUS' }, rusak: { icon: 'fa-broken', cat: 'RUSAK' } };
        Object.keys(ded).forEach(k => {
            const cfg = dedConfigs[k] || { icon: 'fa-tag', cat: k.toUpperCase() };
            const dd = ded[k];
            if (dd.total_transaksi > 0 || dd.total_penjualan > 0) {
                cards.push({ icon: cfg.icon, color: 'text-orange-600 bg-orange-50 border-orange-200', label: cfg.cat + ' (Deduksi)', value: fmt(dd.total_penjualan) + (dd.total_transaksi > 0 ? ' <span class="text-xs text-slate-400 font-normal">(' + dd.total_transaksi + ' tx)</span>' : '') });
                if (canProfit && dd.total_hpp > 0) {
                    cards.push({ icon: 'fa-box-open', color: 'text-rose-600 bg-rose-50 border-rose-200', label: 'Modal ' + cfg.cat, value: fmt(dd.total_hpp) });
                }
            }
        });
        // Bersih setelah semua deduksi
        const mgNd = d.margin_non_ded || 0;
        const mgNdClass = mgNd >= 20 ? 'text-green-600' : (mgNd >= 10 ? 'text-amber-600' : 'text-red-600');
        cards.push({ icon: 'fa-chart-pie', color: 'text-emerald-700 bg-emerald-50 border-emerald-200', label: 'Bersih (excl. Deduksi)', value: fmt(d.pendapatan_bersih_non_ded) + ' <span class="text-xs font-bold ml-1 ' + mgNdClass + '">(' + mgNd + '%)</span>' });
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
    Write-Host "✅ Updated renderRevSummary for RUSAK + generic deductions"
} else {
    Write-Host "❌ Could not find renderRevSummary function"
}

# ============================================================
# 2. Update loadRevenueData to use new field names
# ============================================================
$oldLoadChecks = @'
            if (d.harian && d.harian.length > 0) { renderRevDaily(d.harian); el('rev-daily-count').textContent = d.harian.length + ' hari'; el('rev-daily-section').classList.remove('hidden'); }
            if (d.transaksi_terbaru && d.transaksi_terbaru.length > 0) { renderRevTransactions(d.transaksi_terbaru); el('rev-trans-count').textContent = d.transaksi_terbaru.length + ' transaksi'; el('rev-trans-section').classList.remove('hidden'); }
'@

# This doesn't need changes since it uses d.harian and d.transaksi_terbaru which are still the same

# ============================================================
# 3. Update renderRevDaily - add RUSAK columns
# ============================================================
$oldDailyFunc = @'
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
        let bonusCols = '<td class="px-5 py-3 text-right text-xs text-slate-400">-</td><td class="px-5 py-3 text-right text-xs text-slate-400">-</td>';
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
        // BONUS columns
        const hasBonus = h.bonus_jumlah !== undefined && h.bonus_jumlah > 0;
        let bonusCols = '<td class="px-5 py-3 text-right text-xs text-slate-400">-</td><td class="px-5 py-3 text-right text-xs text-slate-400">-</td>';
        if (hasBonus) {
            bonusCols = '<td class="px-5 py-3 text-right text-xs text-orange-600 font-bold">' + h.bonus_jumlah + '</td>' +
                '<td class="px-5 py-3 text-right text-xs text-orange-600">' + fmt(h.bonus_total) + '</td>';
        }
        // RUSAK columns
        const hasRusak = h.rusak_jumlah !== undefined && h.rusak_jumlah > 0;
        let rusakCols = '<td class="px-5 py-3 text-right text-xs text-slate-400">-</td><td class="px-5 py-3 text-right text-xs text-slate-400">-</td>';
        if (hasRusak) {
            rusakCols = '<td class="px-5 py-3 text-right text-xs text-red-600 font-bold">' + h.rusak_jumlah + '</td>' +
                '<td class="px-5 py-3 text-right text-xs text-red-600">' + fmt(h.rusak_total) + '</td>';
        }
        return '<tr class="hover:bg-slate-50 transition-colors">' +
            '<td class="px-5 py-3 font-semibold text-slate-700">' + t.toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'}) + '</td>' +
            '<td class="px-5 py-3 text-right font-bold text-slate-600">' + h.jumlah + '</td>' +
            '<td class="px-5 py-3 text-right font-bold text-emerald-600">' + fmt(h.total) + '</td>' + extraCols + bonusCols + rusakCols + '</tr>';
    }).join('');
}
'@

if ($content.Contains($oldDailyFunc)) {
    $content = $content.Replace($oldDailyFunc, $newDailyFunc)
    Write-Host "✅ Updated renderRevDaily for RUSAK columns"
} else {
    Write-Host "❌ Could not find renderRevDaily function"
}

# ============================================================
# 4. Update daily table header - add RUSAK columns
# ============================================================
$oldHeader = '<th class="px-5 py-3 text-right text-orange-600">BONUS</th><th class="px-5 py-3 text-right text-orange-600">Nilai BONUS</th></tr>'
$newHeader = '<th class="px-5 py-3 text-right text-orange-600">BONUS</th><th class="px-5 py-3 text-right text-orange-600">Nilai BONUS</th><th class="px-5 py-3 text-right text-red-600">RUSAK</th><th class="px-5 py-3 text-right text-red-600">Nilai RUSAK</th></tr>'

if ($content.Contains($oldHeader)) {
    $content = $content.Replace($oldHeader, $newHeader)
    Write-Host "✅ Updated daily table header for RUSAK"
} else {
    Write-Host "❌ Could not find daily table header ending"
}

[System.IO.File]::WriteAllText($file, $content)
Write-Host "`n=== Script completed ==="
