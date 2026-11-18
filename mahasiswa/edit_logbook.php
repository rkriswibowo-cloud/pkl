<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'mahasiswa') {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';

$id = intval($_GET['id']);
$id_mahasiswa = $_SESSION['user']['id'];
$error = "";

// Ambil data logbook
$log = $conn->query("SELECT * FROM logbook WHERE id='$id' AND id_mahasiswa='$id_mahasiswa'")->fetch_assoc();

// Cek apakah sudah disetujui
if (!$log || $log['status_dospem_pkl'] != 'pending' || $log['status_dospem_lapangan'] != 'pending') {
    die("Logbook tidak dapat diedit.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $hari = htmlspecialchars($_POST['hari']);
    $tanggal = htmlspecialchars($_POST['tanggal']);
    $jam_datang = htmlspecialchars($_POST['jam_datang']);
    $jam_pulang = htmlspecialchars($_POST['jam_pulang']);
    $unit_penempatan = htmlspecialchars($_POST['unit_penempatan']);
    $kegiatan = htmlspecialchars($_POST['kegiatan']);

    // Validasi jam
    if (strtotime($jam_pulang) <= strtotime($jam_datang)) {
        $error = "Jam pulang harus lebih besar dari jam datang";
    } else {
        $stmt = $conn->prepare("UPDATE logbook SET hari=?, tanggal=?, jam_datang=?, jam_pulang=?, unit_penempatan=?, kegiatan=? WHERE id=? AND id_mahasiswa=?");
        $stmt->bind_param("ssssssss", $hari, $tanggal, $jam_datang, $jam_pulang, $unit_penempatan, $kegiatan, $id, $id_mahasiswa);
        
        if ($stmt->execute()) {
            header("Location: riwayat.php");
            exit();
        } else {
            $error = "Gagal memperbarui logbook.";
        }
    }
}
$page_title = 'Edit Logbook - Logbook PKL';
include '../includes/header.php';
?>


<div class="dashboard-panel dashboard-section">
    <h2 class="dashboard-title text-xl font-bold mb-4">Edit Logbook</h2>

    <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4"><?= $error ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="dashboard-fields dashboard-fields-three mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Hari</label>
                <input type="text" name="hari" value="<?= htmlspecialchars($log['hari']) ?>" required class="mt-1 block w-full border border-gray-300 rounded-md p-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Tanggal</label>
                <input type="date" name="tanggal" value="<?= htmlspecialchars($log['tanggal']) ?>" required class="mt-1 block w-full border border-gray-300 rounded-md p-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Unit Penempatan</label>
                <input type="text" name="unit_penempatan" value="<?= htmlspecialchars($log['unit_penempatan'] ?? '') ?>" required class="mt-1 block w-full border border-gray-300 rounded-md p-2">
            </div>
        </div>

        <div class="dashboard-fields mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Jam Datang</label>
                <input type="time" name="jam_datang" value="<?= htmlspecialchars($log['jam_datang'] ?? '08:00') ?>" required class="mt-1 block w-full border border-gray-300 rounded-md p-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Jam Pulang</label>
                <input type="time" name="jam_pulang" value="<?= htmlspecialchars($log['jam_pulang'] ?? '16:00') ?>" required class="mt-1 block w-full border border-gray-300 rounded-md p-2">
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Kegiatan</label>
            <textarea name="kegiatan" rows="4" required class="mt-1 block w-full border border-gray-300 rounded-md p-2"><?= htmlspecialchars($log['kegiatan']) ?></textarea>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Simpan Perubahan</button>
        <button type="button" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700" onclick="window.location.href='riwayat.php'">Batal</button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
