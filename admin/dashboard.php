<?php
session_start();

// Cek apakah user sudah login dan role adalah admin
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'superadmin'])) {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';
$page_title = 'Dashboard Admin';
include '../includes/header.php';
?>

<!-- Dashboard Container -->
<div class="min-w-0">
    <div class="dashboard-page">

        <!-- Header -->
        <h2 class="dashboard-title text-3xl font-bold text-gray-800 mb-6"><i class="fas fa-graduation-cap" aria-hidden="true"></i>Dashboard Admin</h2>

        <!-- Stats Cards -->
        <div class="grid dashboard-kpis grid-cols-1 md:grid-cols-4 gap-6 mb-8">

            <!-- Mahasiswa Card -->
            <?php
            $mhs = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='mahasiswa'")->fetch_assoc()['total'];
            ?>
            <div class="dashboard-card p-6 dashboard-kpi">
                <h5 class="text-xl font-semibold text-blue-600">Mahasiswa</h5>
                <p class="text-4xl font-bold text-gray-800 mt-2"><?= $mhs ?></p>
                <i class="fas fa-user-graduate dashboard-kpi-icon" aria-hidden="true"></i>
            </div>

            <!-- Dosen Pembimbing PKL -->
            <?php
            $dospem_pkl = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='dosen_pkl'")->fetch_assoc()['total'];
            ?>
            <div class="dashboard-card p-6 dashboard-kpi dashboard-kpi-secondary">
                <h5 class="text-xl font-semibold text-green-600">Dosen PKL</h5>
                <p class="text-4xl font-bold text-gray-800 mt-2"><?= $dospem_pkl ?></p>
                <i class="fas fa-chalkboard-user dashboard-kpi-icon" aria-hidden="true"></i>
            </div>

            <!-- Dosen Pembimbing Lapangan -->
            <?php
            $dospem_lapangan = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='dosen_lapangan'")->fetch_assoc()['total'];
            ?>
            <div class="dashboard-card p-6 dashboard-kpi dashboard-kpi-warning">
                <h5 class="text-xl font-semibold text-purple-600">Dosen Lapangan</h5>
                <p class="text-4xl font-bold text-gray-800 mt-2"><?= $dospem_lapangan ?></p>
                <i class="fas fa-id-badge dashboard-kpi-icon" aria-hidden="true"></i>
            </div>

            <!-- Tombol Setting Bimbingan -->
            <a href="bimbingan.php" class="dashboard-card-bimbingan flex flex-col justify-center items-center py-6 px-4 rounded-xl shadow transition duration-200">
                <div class="bimbingan-icon-box">
                    <i class="fas fa-user-tie text-2xl"></i>
                </div>
                <h3>Setting Bimbingan</h3>
                <p>Atur siapa membimbing siapa</p>
            </a>

        </div>

        <!-- Informasi Tambahan -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

            <!-- Mahasiswa Belum Punya Dosen PKL -->
            <?php
            $mhs_no_pkl = $conn->query("
                SELECT COUNT(*) AS total FROM users u
                LEFT JOIN bimbingan b ON u.id = b.id_mahasiswa AND b.id_dospem_pkl IS NOT NULL
                WHERE u.role = 'mahasiswa' AND (b.id_dospem_pkl IS NULL OR b.id_dospem_pkl = 0)
            ")->fetch_assoc()['total'];
            ?>
            <div class="dashboard-card dashboard-warning p-4">
                <h5 class="text-base font-semibold text-gray-800">Mahasiswa Belum Ada Dosen PKL</h5>
                <p class="text-2xl font-bold text-yellow-600 mt-1"><?= $mhs_no_pkl ?></p>
            </div>

            <!-- Mahasiswa Belum Punya Dosen Lapangan -->
            <?php
            $mhs_no_lapangan = $conn->query("
                SELECT COUNT(*) AS total FROM users u
                LEFT JOIN bimbingan b ON u.id = b.id_mahasiswa AND b.id_dospem_lapangan IS NOT NULL
                WHERE u.role = 'mahasiswa' AND (b.id_dospem_lapangan IS NULL OR b.id_dospem_lapangan = 0)
            ")->fetch_assoc()['total'];
            ?>
            <div class="dashboard-card dashboard-warning p-4">
                <h5 class="text-base font-semibold text-gray-800">Belum Ada Dosen Lapangan</h5>
                <p class="text-2xl font-bold text-yellow-600 mt-1"><?= $mhs_no_lapangan ?></p>
            </div>

            <!-- Logbook Pending -->
            <?php
            $log_pending = $conn->query("SELECT COUNT(*) AS total FROM logbook WHERE status_dospem_pkl = 'pending' OR status_dospem_lapangan = 'pending'")->fetch_assoc()['total'];
            ?>
            <div class="dashboard-card dashboard-danger p-4">
                <h5 class="text-base font-semibold text-gray-800">Logbook Belum Disetujui</h5>
                <p class="text-2xl font-bold text-red-600 mt-1"><?= $log_pending ?></p>
            </div>

        </div>

        <!-- Recent Logbooks Table -->
        <div class="dashboard-panel overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Logbook Terbaru</h3>
            </div>
            <div class="dashboard-table-scroll" tabindex="0" role="region" aria-label="Tabel logbook terbaru">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hari</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kegiatan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status PKL</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status Lapangan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        // Pagination setup
                        $limit = 5;
                        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
                        $start = ($page - 1) * $limit;

                        // Hitung total logbook
                        $total_result = $conn->query("SELECT COUNT(*) as total FROM logbook");
                        $total_row = $total_result->fetch_assoc();
                        $total_pages = ceil($total_row['total'] / $limit);

                        // Ambil data logbook dengan JOIN nama mahasiswa
                        $result = $conn->query("
                            SELECT l.*, u.nama 
                            FROM logbook l
                            JOIN users u ON l.id_mahasiswa = u.id
                            ORDER BY l.tanggal DESC
                            LIMIT $start, $limit
                        ");

                        while ($log = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($log['nama']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($log['hari']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($log['tanggal']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= substr(htmlspecialchars($log['kegiatan']), 0, 50) ?>...</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="dashboard-status" data-status="<?= htmlspecialchars((string) $log['status_dospem_pkl']) ?>">
                                    <?= ucfirst(htmlspecialchars($log['status_dospem_pkl']) ?? '-') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="dashboard-status" data-status="<?= htmlspecialchars((string) $log['status_dospem_lapangan']) ?>">
                                    <?= ucfirst(htmlspecialchars($log['status_dospem_lapangan']) ?? '-') ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php $pagination_filters = []; include '../includes/pagination.php'; ?>
        </div>

        <!-- Action Buttons -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <a href="users.php" class="block w-full text-center py-3 px-4 dashboard-action font-semibold rounded-lg shadow transition duration-200">
                Kelola Pengguna
            </a>
            <a href="logbooks.php" class="block w-full text-center py-3 px-4 dashboard-action font-semibold rounded-lg shadow transition duration-200">
                Lihat Semua Logbook
            </a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>