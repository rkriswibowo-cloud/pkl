<?php
session_start();

// Pastikan hanya admin yang bisa akses halaman ini
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../config/db.php';

// Pagination & Filter
$tanggal_filter = $_GET['tanggal'] ?? '';
if (!is_string($tanggal_filter) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $tanggal_filter)) {
    $tanggal_filter = '';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$delete_token = $_SESSION['logbook_delete_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? null;
    if (!is_string($token) || !hash_equals($delete_token, $token)) {
        http_response_code(403);
        exit('Permintaan hapus tidak valid.');
    }

    $id = filter_var($_POST['delete_logbook_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        http_response_code(400);
        exit('Logbook tidak valid.');
    }

    $stmt = $conn->prepare('DELETE FROM logbook WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $_SESSION['logbook_delete_result'] = $stmt->affected_rows === 1 ? 'deleted' : 'missing';
    $stmt->close();

    $query_params = ['page' => $page];
    if ($tanggal_filter !== '') {
        $query_params['tanggal'] = $tanggal_filter;
    }
    header('Location: logbooks.php?' . http_build_query($query_params), true, 303);
    exit;
}

$delete_result = $_SESSION['logbook_delete_result'] ?? null;
unset($_SESSION['logbook_delete_result']);

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
$page = min($page, max(1, (int) $total_pages));
$start = ($page - 1) * $limit;
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

    <?php if ($delete_result === 'deleted'): ?>
        <p class="profile-feedback bg-green-50" role="status">Logbook berhasil dihapus.</p>
    <?php elseif ($delete_result === 'missing'): ?>
        <p class="profile-feedback bg-yellow-50" role="status">Logbook sudah tidak tersedia.</p>
    <?php endif; ?>

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
                        <th scope="col" class="logbooks-actions">Aksi</th>
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
                            <td class="logbooks-actions">
                                <form method="post" onsubmit="return confirm('Hapus logbook ini? Data yang dihapus tidak dapat dikembalikan.')">
                                    <input type="hidden" name="token" value="<?= $delete_token ?>">
                                    <button type="submit" name="delete_logbook_id" value="<?= (int) $log['id'] ?>"
                                            class="admin-button admin-button-danger">
                                        <i class="fas fa-trash-can" aria-hidden="true"></i> Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="admin-list-empty">
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
