<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';

$id = intval($_GET['id']);
$user = $conn->query("SELECT * FROM users WHERE id='$id'")->fetch_assoc();

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = htmlspecialchars($_POST['nama']);
    $username = htmlspecialchars($_POST['username']);
    $role = $_POST['role'];

    if (empty($nama) || empty($username) || empty($role)) {
        $error = "Semua kolom harus diisi.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET nama=?, username=?, role=? WHERE id=?");
        $stmt->bind_param("ssss", $nama, $username, $role, $id);
        if ($stmt->execute()) {
            header("Location: users.php");
            exit();
        } else {
            $error = "Gagal memperbarui pengguna.";
        }
    }
}
$page_title = 'Edit Pengguna - Logbook PKL';
include '../includes/header.php';
?>


<div class="dashboard-panel dashboard-section">
    <h2 class="dashboard-title text-xl font-bold mb-4">Edit Pengguna</h2>

    <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 mb-4"><?= $error ?></div>
    <?php endif; ?>

    <form method="post" class="dashboard-form-grid">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
            <input type="text" name="nama" value="<?= htmlspecialchars($user['nama']) ?>" required class="w-full px-4 py-2 border border-gray-300 rounded">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required class="w-full px-4 py-2 border border-gray-300 rounded">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Role</label>
            <select name="role" required class="w-full px-4 py-2 border border-gray-300 rounded">
                <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="mahasiswa" <?= $user['role'] == 'mahasiswa' ? 'selected' : '' ?>>Mahasiswa</option>
                <option value="dosen_pkl" <?= $user['role'] == 'dosen_pkl' ? 'selected' : '' ?>>Dosen Pembimbing PKL</option>
                <option value="dosen_lapangan" <?= $user['role'] == 'dosen_lapangan' ? 'selected' : '' ?>>Dosen Pembimbing Lapangan</option>
            </select>
        </div>

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded w-full">Simpan Perubahan</button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>