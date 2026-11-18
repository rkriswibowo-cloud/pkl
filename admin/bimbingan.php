<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';

$bimbingan_feedback = '';

// Proses submit form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_mahasiswa = intval($_POST['mahasiswa']);
    $id_dospem_pkl = intval($_POST['dospem_pkl']);
    $id_dospem_lapangan = intval($_POST['dospem_lapangan']);

    // Cek apakah sudah ada entri untuk mahasiswa ini
    $cek = $conn->query("SELECT * FROM bimbingan WHERE id_mahasiswa='$id_mahasiswa'");

    if ($cek->num_rows > 0) {
        // Update jika sudah ada
        $stmt = $conn->prepare("UPDATE bimbingan SET id_dospem_pkl=?, id_dospem_lapangan=? WHERE id_mahasiswa=?");
        $stmt->bind_param("iii", $id_dospem_pkl, $id_dospem_lapangan, $id_mahasiswa);
    } else {
        // Insert baru jika belum ada
        $stmt = $conn->prepare("INSERT INTO bimbingan (id_mahasiswa, id_dospem_pkl, id_dospem_lapangan) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $id_mahasiswa, $id_dospem_pkl, $id_dospem_lapangan);
    }

    if ($stmt->execute()) {
        $bimbingan_feedback = "<script>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Bimbingan berhasil diset.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'bimbingan.php';
            });
        </script>";
    } else {
        $bimbingan_feedback = "<script>
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Gagal menyimpan data bimbingan.'
            });
        </script>";
    }
}

// Ambil semua mahasiswa dan dosen
$mahasiswa_list = $conn->query("SELECT * FROM users WHERE role='mahasiswa'");
$dosen_pkl_list = $conn->query("SELECT * FROM users WHERE role='dosen_pkl'");
$dosen_lapangan_list = $conn->query("SELECT * FROM users WHERE role='dosen_lapangan'");
$bimbingan_list = $conn->query("
    SELECT b.id_mahasiswa, u.nama AS mahasiswa, pkl.nama AS dospem_pkl, lapangan.nama AS dospem_lapangan
    FROM bimbingan b
    JOIN users u ON b.id_mahasiswa = u.id
    LEFT JOIN users pkl ON b.id_dospem_pkl = pkl.id
    LEFT JOIN users lapangan ON b.id_dospem_lapangan = lapangan.id
");
$page_title = 'Pengaturan Bimbingan Mahasiswa - Logbook PKL';
include '../includes/header.php';
?>

<?php if ($bimbingan_feedback !== ''): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?= $bimbingan_feedback ?>
<?php endif; ?>


<div class="dashboard-page">
    <div class="admin-list-header">
        <div>
            <a href="dashboard.php" class="admin-list-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Dashboard
            </a>
            <h2 class="dashboard-title">Setting Bimbingan Mahasiswa</h2>
        </div>
    </div>

    <!-- Informasi Dosen Pembimbing -->
    <div class="mb-6 flex justify-between items-center">
        <a href="dosen_pembimbing.php" class="admin-button">
            <i class="fas fa-id-badge" aria-hidden="true"></i> Informasi Dosen Pembimbing
        </a>
    </div>

    <!-- Form Setting Bimbingan -->
    <div class="dashboard-panel dashboard-section mb-8">
        <form method="post" class="dashboard-form-grid dashboard-form-assignments">
            <div>
                <label class="block text-sm font-medium text-gray-700">Pilih Mahasiswa</label>
                <select name="mahasiswa" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                    <option value="">-- Pilih Mahasiswa --</option>
                    <?php while ($mhs = $mahasiswa_list->fetch_assoc()): ?>
                        <option value="<?= $mhs['id'] ?>"><?= htmlspecialchars($mhs['nama']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Dosen Pembimbing PKL</label>
                <select name="dospem_pkl" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                    <option value="">-- Pilih Dosen PKL --</option>
                    <?php while ($dosen = $dosen_pkl_list->fetch_assoc()): ?>
                        <option value="<?= $dosen['id'] ?>"><?= htmlspecialchars($dosen['nama']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Dosen Pembimbing Lapangan</label>
                <select name="dospem_lapangan" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                    <option value="">-- Pilih Dosen Lapangan --</option>
                    <?php while ($dosen = $dosen_lapangan_list->fetch_assoc()): ?>
                        <option value="<?= $dosen['id'] ?>"><?= htmlspecialchars($dosen['nama']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="dashboard-page-toolbar dashboard-form-actions">
                <button type="submit" class="admin-button admin-button-primary">
                    <i class="fas fa-check" aria-hidden="true"></i> Simpan Pengaturan
                </button>
                <button type="reset" class="admin-button admin-button-quiet">
                    <i class="fas fa-rotate-left" aria-hidden="true"></i> Reset Pilihan
                </button>
            </div>
        </form>
    </div>

    <!-- Daftar Bimbingan -->
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Daftar Bimbingan Saat Ini</h3>
    <div class="dashboard-panel dashboard-table-scroll" tabindex="0" role="region" aria-label="Tabel Pengaturan Bimbingan Mahasiswa">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mahasiswa</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dosen PKL</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dosen Lapangan</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($row = $bimbingan_list->fetch_assoc()): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($row['mahasiswa']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['dospem_pkl']) ?: '–' ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['dospem_lapangan']) ?: '–' ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

<?php include '../includes/footer.php'; ?>
