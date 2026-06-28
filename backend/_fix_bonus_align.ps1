$file = "website/backend/admin.php"
$content = [System.IO.File]::ReadAllText($file)

# Fix: always render bonus columns to maintain table alignment
$oldCode = @'
        let bonusCols = '';
        if (hasBonus) {
            bonusCols = '<td class="px-5 py-3 text-right text-xs text-orange-600 font-bold">' + h.bonus_jumlah + '</td>' +
                '<td class="px-5 py-3 text-right text-xs text-orange-600">' + fmt(h.bonus_total) + '</td>';
        }
'@

$newCode = @'
        let bonusCols = '<td class="px-5 py-3 text-right text-xs text-slate-400">-</td><td class="px-5 py-3 text-right text-xs text-slate-400">-</td>';
        if (hasBonus) {
            bonusCols = '<td class="px-5 py-3 text-right text-xs text-orange-600 font-bold">' + h.bonus_jumlah + '</td>' +
                '<td class="px-5 py-3 text-right text-xs text-orange-600">' + fmt(h.bonus_total) + '</td>';
        }
'@

if ($content.Contains($oldCode)) {
    $content = $content.Replace($oldCode, $newCode)
    Write-Host "✅ Fixed bonus column alignment"
} else {
    Write-Host "❌ Could not find bonus column code"
}

[System.IO.File]::WriteAllText($file, $content)
Write-Host "=== Done ==="
