<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'mahasiswa') {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';
$page_title = 'Dashboard Mahasiswa - Logbook PKL';
include '../includes/header.php';

$id_mahasiswa = $_SESSION['user']['id'];

// Ambil jumlah logbook
$total_logbook = $conn->query("SELECT COUNT(*) as total FROM logbook WHERE id_mahasiswa='$id_mahasiswa'")->fetch_assoc()['total'];

// Ambil nama dosen pembimbing (PKL & Lapangan)
$bimbingan = $conn->query("
    SELECT 
        pkl.nama AS dospem_pkl, 
        lapangan.nama AS dospem_lapangan
    FROM bimbingan b
    LEFT JOIN users pkl ON b.id_dospem_pkl = pkl.id
    LEFT JOIN users lapangan ON b.id_dospem_lapangan = lapangan.id
    WHERE b.id_mahasiswa = '$id_mahasiswa'
")->fetch_assoc();

$dospem_pkl = $bimbingan['dospem_pkl'] ?: "Belum ditentukan";
$dospem_lapangan = $bimbingan['dospem_lapangan'] ?: "Belum ditentukan";
?>

    <div>
        <h2 class="dashboard-title text-lg sm:text-2xl font-bold text-gray-800 mb-6"><i class="fas fa-graduation-cap" aria-hidden="true"></i>Dashboard Mahasiswa</h2>

        <!-- Stats Cards -->
        <div class="grid dashboard-kpis grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-8">
            <div class="dashboard-card p-4 sm:p-6 dashboard-kpi">
                <h5 class="text-base sm:text-lg font-semibold text-blue-600">Total Logbook</h5>
                <p class="text-2xl sm:text-3xl font-bold text-gray-800 mt-2"><?= $total_logbook ?></p>
                <i class="fas fa-book dashboard-kpi-icon" aria-hidden="true"></i>
            </div>

            <div class="dashboard-card p-4 sm:p-6 dashboard-kpi dashboard-kpi-secondary">
                <h5 class="text-base sm:text-lg font-semibold text-green-600">Dosen Pembimbing PKL</h5>
                <p class="text-lg sm:text-xl font-medium text-gray-800 mt-2 truncate"><?= htmlspecialchars($dospem_pkl) ?></p>
                <i class="fas fa-chalkboard-user dashboard-kpi-icon" aria-hidden="true"></i>
            </div>

            <div class="dashboard-card p-4 sm:p-6 dashboard-kpi dashboard-kpi-warning">
                <h5 class="text-base sm:text-lg font-semibold text-purple-600">Dosen Pembimbing Lapangan</h5>
                <p class="text-lg sm:text-xl font-medium text-gray-800 mt-2 truncate"><?= htmlspecialchars($dospem_lapangan) ?></p>
                <i class="fas fa-id-badge dashboard-kpi-icon" aria-hidden="true"></i>
            </div>
        </div>

        <!-- Menu Fitur -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <a href="input_logbook.php" class="block dashboard-action text-center py-4 sm:py-6 px-4 rounded-lg shadow transition duration-200">
                <h3 class="font-semibold text-base sm:text-lg">Input Kegiatan Harian</h3>
            </a>

            <a href="riwayat.php" class="block dashboard-action text-center py-4 sm:py-6 px-4 rounded-lg shadow transition duration-200">
                <h3 class="font-semibold text-base sm:text-lg">Riwayat Logbook</h3>
            </a>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
