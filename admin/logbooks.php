<?php
session_start();

// Pastikan hanya admin yang bisa akses halaman ini
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'superadmin'])) {
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

// Ambil daftar prodi untuk modal ekspor
$prodi_export_options = $conn->query("
    SELECT DISTINCT COALESCE(NULLIF(m.prodi, ''), NULLIF(u.prodi, '')) AS prodi_name 
    FROM users u 
    LEFT JOIN mahasiswa m ON u.id = m.id 
    WHERE u.role = 'mahasiswa' AND (m.prodi IS NOT NULL OR u.prodi IS NOT NULL)
    ORDER BY prodi_name ASC
");

$page_title = 'Logbook Mahasiswa - Logbook PKL';
include '../includes/header.php';
?>


<div class="dashboard-page">
    <div class="admin-list-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <a href="dashboard.php" class="admin-list-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Dashboard
            </a>
            <h2 class="dashboard-title">Logbook Mahasiswa</h2>
        </div>
        <div>
            <button type="button" onclick="bukaModalEksporLogbook()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm" style="cursor: pointer;">
                <i class="fas fa-file-excel"></i> Ekspor Rekap Logbook
            </button>
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

<!-- Modal Ekspor Logbook -->
<div id="modalEksporLogbook" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden">
    <div class="w-full max-w-lg rounded-2xl shadow-2xl border overflow-hidden transition-all transform flex flex-col" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
        <div class="p-5 border-b flex items-center justify-between" style="border-color: var(--dashboard-border);">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                    <i class="fas fa-file-excel text-lg"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base" style="color: var(--dashboard-text);">Ekspor Rekapitulasi Logbook</h3>
                    <p class="text-xs" style="color: var(--dashboard-muted);">Pilih kriteria rentang tanggal dan filter data logbook</p>
                </div>
            </div>
            <button type="button" onclick="tutupModalEksporLogbook()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition">
                <i class="fas fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="export_logbook_excel.php" method="GET" target="_blank" class="p-5 flex flex-col gap-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Dari Tanggal</label>
                    <input type="date" name="start_date" class="form-input w-full text-xs py-2 px-3 rounded-lg border" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-input w-full text-xs py-2 px-3 rounded-lg border" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Program Studi</label>
                <select name="prodi" class="form-select w-full text-xs py-2 px-3 rounded-lg border" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    <option value="">-- Semua Program Studi --</option>
                    <?php if ($prodi_export_options && $prodi_export_options->num_rows > 0): ?>
                        <?php while ($p = $prodi_export_options->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($p['prodi_name']) ?>"><?= htmlspecialchars($p['prodi_name']) ?></option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Status Verifikasi</label>
                <select name="status" class="form-select w-full text-xs py-2 px-3 rounded-lg border" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    <option value="">-- Semua Status --</option>
                    <option value="disetujui">Lengkap Disetujui (Kampus & Lapangan)</option>
                    <option value="pending">Menunggu Verifikasi (Pending)</option>
                    <option value="ditolak">Terdapat Penolakan (Ditolak)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Format File Unduhan</label>
                <div class="grid grid-cols-2 gap-3 mt-1">
                    <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer hover:bg-emerald-500/5 transition" style="border-color: var(--dashboard-border);">
                        <input type="radio" name="format" value="excel" checked class="text-emerald-600 focus:ring-emerald-500">
                        <span class="text-xs font-semibold flex items-center gap-1.5" style="color: var(--dashboard-text);">
                            <i class="fas fa-file-excel text-emerald-600"></i> Excel (.xls)
                        </span>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer hover:bg-blue-500/5 transition" style="border-color: var(--dashboard-border);">
                        <input type="radio" name="format" value="csv" class="text-blue-600 focus:ring-blue-500">
                        <span class="text-xs font-semibold flex items-center gap-1.5" style="color: var(--dashboard-text);">
                            <i class="fas fa-file-csv text-blue-600"></i> CSV Spreadsheet
                        </span>
                    </label>
                </div>
            </div>

            <div class="pt-3 border-t flex items-center justify-end gap-2" style="border-color: var(--dashboard-border);">
                <button type="button" onclick="tutupModalEksporLogbook()" class="px-4 py-2 rounded-lg text-xs font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    Batal
                </button>
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm">
                    <i class="fas fa-download"></i> Unduh File
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalEksporLogbook() {
    document.getElementById('modalEksporLogbook').classList.remove('hidden');
}
function tutupModalEksporLogbook() {
    document.getElementById('modalEksporLogbook').classList.add('hidden');
}
</script>

<?php include '../includes/footer.php'; ?>
