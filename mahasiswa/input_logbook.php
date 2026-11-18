<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'mahasiswa') {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $hari = htmlspecialchars($_POST['hari']);
    $tanggal = htmlspecialchars($_POST['tanggal']);
    $jam_datang = htmlspecialchars($_POST['jam_datang']);
    $jam_pulang = htmlspecialchars($_POST['jam_pulang']);
    $unit_penempatan = htmlspecialchars($_POST['unit_penempatan']);
    $kegiatan = htmlspecialchars($_POST['kegiatan']);
    $id_mahasiswa = $_SESSION['user']['id'];

    // Validasi jam
    if (strtotime($jam_pulang) <= strtotime($jam_datang)) {
        $error = "Jam pulang harus lebih besar dari jam datang";
    } else {
        $stmt = $conn->prepare("INSERT INTO logbook (id_mahasiswa, hari, tanggal, jam_datang, jam_pulang, unit_penempatan, kegiatan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $id_mahasiswa, $hari, $tanggal, $jam_datang, $jam_pulang, $unit_penempatan, $kegiatan);

        if ($stmt->execute()) {
            // Redirect ke halaman riwayat setelah sukses
            header("Location: riwayat.php");
            exit();
        } else {
            $error = "Gagal menyimpan logbook.";
        }
    }
}
$page_title = 'Input Kegiatan Harian - Logbook PKL';
include '../includes/header.php';
?>


<div class="dashboard-panel dashboard-section">
    <h2 class="dashboard-title text-xl font-bold mb-4">Input Kegiatan Harian</h2>

    <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4"><?= $error ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="dashboard-fields dashboard-fields-three mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Hari</label>
                <input type="text" name="hari" required 
                       placeholder="Contoh: Senin" 
                       class="mt-1 block w-full border border-gray-300 rounded-md p-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Tanggal</label>
                <input type="date" name="tanggal" required 
                       class="mt-1 block w-full border border-gray-300 rounded-md p-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Unit Penempatan</label>
                <input type="text" name="unit_penempatan" required 
                       placeholder="Contoh: Divisi IT" 
                       class="mt-1 block w-full border border-gray-300 rounded-md p-2">
            </div>
        </div>

        <div class="dashboard-fields mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Jam Datang</label>
                <input type="time" name="jam_datang" required 
                       class="mt-1 block w-full border border-gray-300 rounded-md p-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Jam Pulang</label>
                <input type="time" name="jam_pulang" required 
                       class="mt-1 block w-full border border-gray-300 rounded-md p-2">
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Kegiatan (Deskripsi pekerjaan/hasil)</label>
            <textarea name="kegiatan" rows="4" required 
                      placeholder="Deskripsikan kegiatan yang dilakukan hari ini..." 
                      class="mt-1 block w-full border border-gray-300 rounded-md p-2"></textarea>
        </div>

        <div class="dashboard-page-toolbar">
            <div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Ajukan</button>
                <button type="button" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700" 
                        onclick="window.location.href='dashboard.php'">Kembali</button>
            </div>
            <div class="text-sm text-gray-500">
                <p>Catatan: Logbook hanya bisa diedit selama status masih "pending"</p>
            </div>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
