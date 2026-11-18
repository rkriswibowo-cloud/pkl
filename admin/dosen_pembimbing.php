<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';

// Ambil data dosen PKL dan jumlah mahasiswa bimbingannya
$dosen_pkl_list = $conn->query("
    SELECT u.id, u.nama, COUNT(b.id_mahasiswa) as jumlah_mahasiswa
    FROM users u
    LEFT JOIN bimbingan b ON u.id = b.id_dospem_pkl
    WHERE u.role = 'dosen_pkl'
    GROUP BY u.id
");

// Ambil data dosen lapangan dan jumlah mahasiswa bimbingannya
$dosen_lapangan_list = $conn->query("
    SELECT u.id, u.nama, COUNT(b.id_mahasiswa) as jumlah_mahasiswa
    FROM users u
    LEFT JOIN bimbingan b ON u.id = b.id_dospem_lapangan
    WHERE u.role = 'dosen_lapangan'
    GROUP BY u.id
");
$page_title = 'Informasi Dosen Pembimbing - Logbook PKL';
include '../includes/header.php';
?>


<div class="dashboard-page">
    <div class="advisors-header">
        <div>
            <a href="dashboard.php" class="admin-button admin-button-quiet advisors-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Dashboard
            </a>
            <h2 class="dashboard-title">Informasi Dosen Pembimbing</h2>
        </div>
        <a href="bimbingan.php" class="admin-button admin-button-primary">
            <i class="fas fa-sliders" aria-hidden="true"></i> Pengaturan Bimbingan
        </a>
    </div>

    <?php $advisor_groups = [
        ['pkl', 'Pembimbing PKL', 'fa-chalkboard-user', $dosen_pkl_list],
        ['lapangan', 'Pembimbing Lapangan', 'fa-id-badge', $dosen_lapangan_list],
    ]; ?>
    <div class="advisors-grid">
        <?php foreach ($advisor_groups as [$key, $title, $icon, $advisors]): ?>
            <section class="dashboard-panel advisors-panel" aria-labelledby="advisor-<?= $key ?>">
                <header class="advisors-panel-header">
                    <span class="advisors-icon"><i class="fas <?= $icon ?>" aria-hidden="true"></i></span>
                    <h3 id="advisor-<?= $key ?>"><?= $title ?></h3>
                </header>
                <?php if ($advisors->num_rows > 0): ?>
                    <table class="advisors-table" aria-labelledby="advisor-<?= $key ?>">
                        <thead>
                            <tr>
                                <th scope="col">Nama Dosen</th>
                                <th scope="col">Mahasiswa Dibimbing</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($dosen = $advisors->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($dosen['nama']) ?></td>
                                    <td><span class="advisors-count"><?= (int) $dosen['jumlah_mahasiswa'] ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="advisors-empty">Belum ada dosen pembimbing terdaftar.</p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
