<?php
require_once 'config.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin.php");
    exit;
}

// Auto-inisialisasi admins.json jika belum ada
loadAdmins();

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $admin = findAdminByUsername($username);

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']        = $admin['id'];
        $_SESSION['admin_username']  = $admin['username'];
        $_SESSION['admin_role']      = $admin['role'];
        header("Location: admin.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Royal Komputer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Plus Jakarta Sans','sans-serif']},colors:{astra:{50:'#f0f7ff',700:'#0254A3',950:'#07162c'}}}}}</script>
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen font-sans">
    <div class="bg-white p-8 rounded-2xl shadow-xl max-w-sm w-full border border-slate-200">
        <div class="text-center mb-7">
            <div class="w-24 h-24 bg-astra-950 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg p-3">
                <img src="logo/logo.webp" alt="Logo Royal Komputer" class="max-w-full max-h-full object-contain">
            </div>
            <h1 class="text-2xl font-extrabold text-slate-800">Royal Admin Panel</h1>
            <p class="text-sm text-slate-500 mt-1">Silakan login untuk melanjutkan</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-700 p-3 rounded-lg text-sm mb-5 border border-red-200 flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation flex-shrink-0"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Username</label>
                <div class="relative">
                    <input type="text" name="username" required autocomplete="username"
                        class="w-full px-4 py-2.5 pl-10 border border-slate-300 rounded-lg focus:outline-none focus:border-astra-700 focus:ring-1 focus:ring-astra-700 text-sm transition-all"
                        placeholder="Masukkan username">
                    <i class="fa-solid fa-user absolute left-3.5 top-3 text-slate-400 text-sm"></i>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Password</label>
                <div class="relative">
                    <input type="password" name="password" required autocomplete="current-password"
                        class="w-full px-4 py-2.5 pl-10 border border-slate-300 rounded-lg focus:outline-none focus:border-astra-700 focus:ring-1 focus:ring-astra-700 text-sm transition-all"
                        placeholder="Masukkan password">
                    <i class="fa-solid fa-lock absolute left-3.5 top-3 text-slate-400 text-sm"></i>
                </div>
            </div>
            <button type="submit"
                class="w-full bg-astra-950 hover:bg-astra-700 text-white font-bold py-2.5 px-4 rounded-lg transition-colors shadow-md mt-2 flex items-center justify-center gap-2 text-sm">
                <i class="fa-solid fa-right-to-bracket"></i> Masuk Dashboard
            </button>
        </form>
        <div class="mt-5 text-center">
            <a href="index.php" class="text-xs text-slate-400 hover:text-astra-700 transition-colors">
                <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Toko
            </a>
        </div>
    </div>
</body>
</html>