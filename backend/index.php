<?php
require_once __DIR__ . '/config.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Royal Admin Panel - Royal Komputer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Plus Jakarta Sans','sans-serif']},colors:{astra:{50:'#f0f7ff',700:'#0254A3',950:'#07162c'}}}}}</script>
</head>
<body class="bg-slate-100 min-h-screen font-sans flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8 text-center">
            <div class="w-28 h-28 bg-astra-950 rounded-full flex items-center justify-center mx-auto mb-5 shadow-lg p-3">
                <img src="logo/logo.webp" alt="Royal Komputer" class="max-w-full max-h-full object-contain">
            </div>
            <h1 class="text-2xl font-extrabold text-slate-800">Royal Admin Panel</h1>
            <p class="text-slate-500 text-sm mt-1 mb-8">Kelola produk, jam operasional, dan data toko</p>
            <a href="login.php"
                class="inline-flex items-center gap-2 bg-astra-950 hover:bg-astra-700 text-white font-bold py-3 px-8 rounded-lg transition-colors shadow-md text-sm">
                <i class="fa-solid fa-right-to-bracket"></i> Login Admin
            </a>
            <div class="mt-8 pt-6 border-t border-slate-100">
                <a href="/royalkomputer/frontend/" class="text-sm text-slate-400 hover:text-astra-700 transition-colors">
                    <i class="fa-solid fa-store mr-1"></i> Ke Toko
                </a>
            </div>
        </div>
        <p class="text-xs text-slate-400 text-center mt-6">&copy; <?= date('Y') ?> Royal Komputer Kediri</p>
    </div>
</body>
</html>
