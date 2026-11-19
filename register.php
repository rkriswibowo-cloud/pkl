<?php
include 'config/db.php';

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Cek username tidak duplikat
    $cek = $conn->query("SELECT * FROM users WHERE username='$username'");
    if ($cek->num_rows > 0) {
        $error = "Username sudah ada!";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nama, $username, $password, $role);
        if ($stmt->execute()) {
            $success = "Registrasi berhasil! Redirect ke halaman login...";
            header("refresh:2;url=index.php");
        } else {
            $error = "Gagal registrasi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Sistem Logbook PKL</title>

    <!-- Favicon -->
    <?php
    $app_favicon = function_exists('get_setting') ? get_setting('app_favicon', 'assets/images/logo.png') : 'assets/images/logo.png';
    $app_logo_login = function_exists('get_setting') ? get_setting('app_logo_login', 'assets/images/uam1.png') : 'assets/images/uam1.png';
    $app_logo_sec   = function_exists('get_setting') ? get_setting('app_logo_secondary', 'assets/images/dikti.png') : 'assets/images/dikti.png';
    ?>
    <link rel="icon" href="<?= htmlspecialchars($app_favicon) ?>?v=<?= file_exists(__DIR__ . '/' . $app_favicon) ? filemtime(__DIR__ . '/' . $app_favicon) : time() ?>" type="image/x-icon">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        body {
            background-image: url('assets/images/sistem.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        @media (max-width: 640px) {
            body {
                background-size: contain;
            }
            .logo-container {
                flex-direction: column;
                align-items: center;
            }
            .logo-container img {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-6 sm:p-8 space-y-6 transition-all duration-300 hover:shadow-xl">
        <!-- Logo Section -->
        <div class="flex justify-between mb-4 logo-container">
            <?php if (!empty($app_logo_login) && file_exists(__DIR__ . '/' . $app_logo_login)): ?>
                <img src="<?= htmlspecialchars($app_logo_login) ?>?v=<?= filemtime(__DIR__ . '/' . $app_logo_login) ?>" alt="Logo Utama" class="h-24 w-24 sm:h-32 sm:w-32 object-contain">
            <?php endif; ?>
            <?php if (!empty($app_logo_sec) && file_exists(__DIR__ . '/' . $app_logo_sec)): ?>
                <img src="<?= htmlspecialchars($app_logo_sec) ?>?v=<?= filemtime(__DIR__ . '/' . $app_logo_sec) ?>" alt="Logo Sekunder" class="h-24 w-24 sm:h-32 sm:w-32 object-contain">
            <?php endif; ?>
        </div>

        <h4 class="text-xl sm:text-2xl font-bold text-center mb-4">Register Pengguna</h4>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <?= $error ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <?= $success ?>
        </div>
        <?php endif; ?>

        <form method="post" class="space-y-5">
            <div>
                <label for="nama" class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                <input type="text" name="nama" id="nama" required
                    class="w-full px-3 py-2 sm:px-4 sm:py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 outline-none transition duration-200">
            </div>

            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username (Email)</label>
                <input type="text" name="username" id="username" required
                    class="w-full px-3 py-2 sm:px-4 sm:py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 outline-none transition duration-200">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" id="password" required
                    class="w-full px-3 py-2 sm:px-4 sm:py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 outline-none transition duration-200">
            </div>

            <div>
                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                <select name="role" id="role" required
                    class="w-full px-3 py-2 sm:px-4 sm:py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 outline-none transition duration-200">
                    <option value="">-- Pilih Role --</option>
                    <option value="admin">Admin</option>
                    <option value="mahasiswa">Mahasiswa</option>
                    <option value="dosen_pkl">Dosen Pembimbing PKL</option>
                    <option value="dosen_lapangan">Dosen Pembimbing Lapangan</option>
                </select>
            </div>

            <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                Daftar
            </button>
        </form>

        <p class="mt-3 text-center text-sm text-gray-600">
            Sudah punya akun?
            <a href="index.php"
                class="font-medium text-blue-600 hover:text-blue-800 hover:underline transition duration-200">
                Login di sini
            </a>
        </p>
    </div>
</body>
</html>