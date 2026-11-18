<?php
session_start();

// Pastikan hanya admin yang bisa akses halaman ini
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';

// Pagination & Filter
$tanggal_filter = isset($_GET['tanggal']) ? $_GET['tanggal'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$start = ($page - 1) * $limit;

$where_clause = "";
if (!empty($tanggal_filter)) {
    $where_clause = " AND l.tanggal = '$tanggal_filter' ";
}

// Hitung total data untuk pagination
$total_result = $conn->query("
    SELECT COUNT(*) as total FROM logbook l
    JOIN users u ON l.id_mahasiswa = u.id
    WHERE 1=1 $where_clause
");
$total_row = $total_result->fetch_assoc();
$total_pages = ceil($total_row['total'] / $limit);
// Ambil data logbook dengan filter tanggal dan limit
$query = "
    SELECT l.*, u.nama 
    FROM logbook l
    JOIN users u ON l.id_mahasiswa = u.id
    WHERE 1=1 $where_clause
    ORDER BY l.tanggal DESC
    LIMIT $start, $limit
";
$result = $conn->query($query);
$page_title = 'Logbook Mahasiswa - Logbook PKL';
include '../includes/header.php';
?>


<div class="dashboard-page">
    <div class="admin-list-header">
        <div>
            <a href="dashboard.php" class="admin-list-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Dashboard
            </a>
            <h2 class="dashboard-title">Logbook Mahasiswa</h2>
        </div>
    </div>

    <div class="dashboard-panel admin-list-panel">
        <form method="get" class="admin-list-filter" aria-label="Filter logbook berdasarkan tanggal">
            <label class="logbooks-date-filter">
                <span>Tanggal kegiatan</span>
                <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal_filter) ?>">
            </label>
            <button type="submit" class="admin-button admin-button-primary">
                <i class="fas fa-filter" aria-hidden="true"></i> Terapkan
            </button>
            <?php if ($tanggal_filter !== ''): ?>
                <a href="logbooks.php" class="admin-button admin-button-quiet">Reset</a>
            <?php endif; ?>
        </form>

        <div class="dashboard-table-scroll" tabindex="0" role="region" aria-label="Tabel Logbook Mahasiswa">
            <table class="admin-list-table logbooks-table">
                <thead>
                    <tr>
                        <th scope="col">Mahasiswa</th>
                        <th scope="col">Hari</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Kegiatan</th>
                        <th scope="col">Status PKL</th>
                        <th scope="col">Status Lapangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="logbooks-student"><?= htmlspecialchars($log['nama']) ?></td>
                            <td><?= htmlspecialchars($log['hari']) ?></td>
                            <td class="logbooks-date"><time datetime="<?= htmlspecialchars($log['tanggal']) ?>"><?= htmlspecialchars($log['tanggal']) ?></time></td>
                            <td class="logbooks-activity"><?= substr(htmlspecialchars($log['kegiatan']), 0, 80) ?>...</td>
                            <?php foreach (['status_dospem_pkl', 'status_dospem_lapangan'] as $status_field): ?>
                                <?php $status = htmlspecialchars((string) $log[$status_field]); ?>
                                <td>
                                    <span class="dashboard-status" data-status="<?= $status ?>"><?= $status !== '' ? ucfirst($status) : '&ndash;' ?></span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" class="admin-list-empty">
                                <?= $tanggal_filter === '' ? 'Belum ada logbook.' : 'Tidak ada logbook pada tanggal ini.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        $pagination_filters = ['tanggal' => $tanggal_filter];
        include '../includes/pagination.php';
        ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
