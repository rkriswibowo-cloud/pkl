<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = htmlspecialchars($_POST['nama']);
    $username = htmlspecialchars($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Validasi
    if (empty($nama) || empty($username) || empty($password) || empty($role)) {
        $error = "Semua kolom harus diisi.";
    } elseif ($conn->query("SELECT * FROM users WHERE username='$username'")->num_rows > 0) {
        $error = "Username sudah terdaftar.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nama, $username, $password, $role);
        if ($stmt->execute()) {
            header("Location: users.php");
            exit();
        } else {
            $error = "Gagal menambahkan pengguna.";
        }
    }
}
$page_title = 'Tambah Pengguna - Logbook PKL';
include '../includes/header.php';
?>


<div class="dashboard-panel dashboard-section">
    <h2 class="dashboard-title text-xl font-bold mb-4">Tambah Pengguna Baru</h2>

    <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 mb-4"><?= $error ?></div>
    <?php endif; ?>

    <form method="post" class="dashboard-form-grid">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
            <input type="text" name="nama" required class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring focus:ring-blue-200">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Username</label>
            <input type="text" name="username" required class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring focus:ring-blue-200">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" name="password" required class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring focus:ring-blue-200">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Role</label>
            <select name="role" required class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring focus:ring-blue-200">
                <option value="">-- Pilih Role --</option>
                <option value="admin">Admin</option>
                <option value="mahasiswa">Mahasiswa</option>
                <option value="dosen_pkl">Dosen Pembimbing PKL</option>
                <option value="dosen_lapangan">Dosen Pembimbing Lapangan</option>
            </select>
        </div>

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded w-full">
            Simpan Pengguna
        </button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>