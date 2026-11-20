<?php
session_start();

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'superadmin'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$page_title = 'Arsip Dokumen PKL - Admin';

$jenis_map = [
    'laporan_akhir'       => 'Laporan Akhir PKL',
    'lembar_pengesahan'   => 'Lembar Pengesahan',
    'sertifikat_industri' => 'Sertifikat / Surat Keterangan Industri',
    'surat_tugas'         => 'Surat Tugas PKL',
];

$status_cfg = [
    'pending'   => ['bg-yellow-100 text-yellow-800 border-yellow-200', 'fa-clock',        'Menunggu'],
    'revisi'    => ['bg-red-100 text-red-800 border-red-200',           'fa-rotate-left',  'Perlu Revisi'],
    'disetujui' => ['bg-green-100 text-green-800 border-green-200',     'fa-circle-check', 'Disetujui'],
];

// ---- Filter ----
$search_q      = trim($_GET['search'] ?? '');
$filter_jenis  = trim($_GET['jenis'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_prodi  = trim($_GET['prodi'] ?? '');

// ---- Statistik ----
$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) AS pending_cnt,
        SUM(CASE WHEN status='revisi'    THEN 1 ELSE 0 END) AS revisi_cnt,
        SUM(CASE WHEN status='disetujui' THEN 1 ELSE 0 END) AS approved_cnt
    FROM dokumen_pkl
")->fetch_assoc();

// ---- Daftar prodi unik ----
$prodi_options = $conn->query("
    SELECT DISTINCT COALESCE(NULLIF(m.prodi,''), NULLIF(u.prodi,'')) AS prodi_name
    FROM users u
    LEFT JOIN mahasiswa m ON u.id = m.id
    WHERE u.role = 'mahasiswa' AND (m.prodi IS NOT NULL OR u.prodi IS NOT NULL)
    ORDER BY prodi_name ASC
")->fetch_all(MYSQLI_ASSOC);

// ---- Query Dokumen dengan filter ----
$where = ["1=1"];
$params = [];
$types  = '';

if ($search_q) {
    $where[] = "(u.nama LIKE ? OR m.nim LIKE ? OR d.judul LIKE ?)";
    $like = "%$search_q%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}
if ($filter_jenis && array_key_exists($filter_jenis, $jenis_map)) {
    $where[] = "d.jenis_dokumen = ?";
    $params[] = $filter_jenis;
    $types .= 's';
}
if ($filter_status && in_array($filter_status, ['pending','revisi','disetujui'])) {
    $where[] = "d.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if ($filter_prodi) {
    $where[] = "(m.prodi = ? OR u.prodi = ?)";
    $params[] = $filter_prodi;
    $params[] = $filter_prodi;
    $types .= 'ss';
}

$sql = "
    SELECT
        d.*,
        u.nama AS nama_mahasiswa, u.username AS email_mahasiswa,
        m.nim,
        COALESCE(NULLIF(m.prodi,''), NULLIF(u.prodi,''), '-') AS prodi,
        v.nama AS verifikator_nama
    FROM dokumen_pkl d
    JOIN users u ON d.id_mahasiswa = u.id
    LEFT JOIN mahasiswa m ON u.id = m.id
    LEFT JOIN users v ON d.verified_by = v.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY d.uploaded_at DESC
";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$dokumen_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="min-w-0">
<div class="dashboard-page w-full max-w-full">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                <i class="fas fa-archive text-blue-600" aria-hidden="true"></i>
                Arsip Dokumen PKL
            </h2>
            <p class="text-sm mt-1" style="color: var(--dashboard-muted);">
                Pantau status unggahan dokumen seluruh mahasiswa PKL.
            </p>
        </div>
    </div>

    <!-- Statistik Kartu -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <?php
        $stat_cards = [
            ['Total Dokumen',   $stats['total'],        'fa-folder',       'text-blue-600',   'bg-blue-50'],
            ['Menunggu',        $stats['pending_cnt'],  'fa-clock',        'text-yellow-600', 'bg-yellow-50'],
            ['Perlu Revisi',    $stats['revisi_cnt'],   'fa-rotate-left',  'text-red-600',    'bg-red-50'],
            ['Disetujui',       $stats['approved_cnt'], 'fa-circle-check', 'text-green-600',  'bg-green-50'],
        ];
        foreach ($stat_cards as [$label, $val, $icon, $tc, $bg]):
        ?>
        <div class="dashboard-card p-4 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl <?= $bg ?> <?= $tc ?> flex items-center justify-center shrink-0">
                <i class="fas <?= $icon ?> text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold <?= $tc ?>"><?= number_format((int)$val) ?></p>
                <p class="text-xs" style="color:var(--dashboard-muted);"><?= $label ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter Bar -->
    <div class="dashboard-card p-4 mb-5">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-medium mb-1">Cari Nama / NIM / Judul</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search_q) ?>"
                           placeholder="Ketik untuk mencari..."
                           class="form-input w-full pl-8 text-sm py-2">
                </div>
            </div>
            <div class="min-w-40">
                <label class="block text-xs font-medium mb-1">Jenis Dokumen</label>
                <select name="jenis" class="form-select w-full text-sm py-2">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($jenis_map as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $filter_jenis === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="min-w-36">
                <label class="block text-xs font-medium mb-1">Status</label>
                <select name="status" class="form-select w-full text-sm py-2">
                    <option value="">Semua Status</option>
                    <option value="pending"   <?= $filter_status === 'pending'   ? 'selected' : '' ?>>Menunggu</option>
                    <option value="revisi"    <?= $filter_status === 'revisi'    ? 'selected' : '' ?>>Perlu Revisi</option>
                    <option value="disetujui" <?= $filter_status === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                </select>
            </div>
            <div class="min-w-40">
                <label class="block text-xs font-medium mb-1">Program Studi</label>
                <select name="prodi" class="form-select w-full text-sm py-2">
                    <option value="">Semua Prodi</option>
                    <?php foreach ($prodi_options as $p): ?>
                        <option value="<?= htmlspecialchars($p['prodi_name']) ?>"
                            <?= $filter_prodi === $p['prodi_name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['prodi_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit"
                    class="btn-primary text-sm py-2 px-4 flex items-center gap-1.5">
                <i class="fas fa-filter"></i> Filter
            </button>
            <?php if ($search_q || $filter_jenis || $filter_status || $filter_prodi): ?>
                <a href="dokumen.php"
                   class="text-sm py-2 px-4 rounded-lg border hover:bg-gray-100 transition flex items-center gap-1.5">
                    <i class="fas fa-xmark"></i> Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabel Dokumen -->
    <div class="dashboard-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background:var(--dashboard-bg-soft); border-bottom:1px solid var(--dashboard-border);">
                        <th class="text-left px-4 py-3 font-semibold">#</th>
                        <th class="text-left px-4 py-3 font-semibold">Mahasiswa</th>
                        <th class="text-left px-4 py-3 font-semibold">Jenis</th>
                        <th class="text-left px-4 py-3 font-semibold">Judul Dokumen</th>
                        <th class="text-left px-4 py-3 font-semibold">Status</th>
                        <th class="text-left px-4 py-3 font-semibold">Diunggah</th>
                        <th class="text-left px-4 py-3 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dokumen_list)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-12">
                                <i class="fas fa-inbox text-4xl mb-3 block opacity-20"></i>
                                <p class="font-medium">Tidak ada dokumen ditemukan.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dokumen_list as $i => $doc):
                            [$badge_cls, $badge_icon, $badge_text] = $status_cfg[$doc['status']];
                            $ext = pathinfo($doc['file_path'], PATHINFO_EXTENSION);
                            $is_image = in_array(strtolower($ext), ['jpg','jpeg','png']);
                            $file_url = '../uploads/dokumen/' . basename($doc['file_path']);
                        ?>
                        <tr class="border-b hover:bg-gray-50 transition" style="border-color:var(--dashboard-border);">
                            <td class="px-4 py-3 text-xs" style="color:var(--dashboard-muted);"><?= $i + 1 ?></td>
                            <td class="px-4 py-3">
                                <p class="font-medium"><?= htmlspecialchars($doc['nama_mahasiswa']) ?></p>
                                <p class="text-xs" style="color:var(--dashboard-muted);">
                                    <?= htmlspecialchars($doc['nim'] ?? $doc['email_mahasiswa']) ?>
                                    &bull; <?= htmlspecialchars($doc['prodi']) ?>
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 border border-blue-200 whitespace-nowrap">
                                    <?= htmlspecialchars($jenis_map[$doc['jenis_dokumen']] ?? $doc['jenis_dokumen']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <p class="truncate font-medium"><?= htmlspecialchars($doc['judul']) ?></p>
                                <?php if ($doc['catatan_revisi']): ?>
                                    <p class="text-xs text-red-600 truncate mt-0.5">
                                        <i class="fas fa-comment-dots mr-1"></i>
                                        <?= htmlspecialchars($doc['catatan_revisi']) ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full border whitespace-nowrap <?= $badge_cls ?>">
                                    <i class="fas <?= $badge_icon ?> mr-1"></i><?= $badge_text ?>
                                </span>
                                <?php if ($doc['verifikator_nama']): ?>
                                    <p class="text-xs mt-1" style="color:var(--dashboard-muted);">oleh <?= htmlspecialchars($doc['verifikator_nama']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap" style="color:var(--dashboard-muted);">
                                <?= date('d M Y', strtotime($doc['uploaded_at'])) ?>
                                <br><?= date('H:i', strtotime($doc['uploaded_at'])) ?>
                            </td>
                            <td class="px-4 py-3">
                                <a href="<?= htmlspecialchars($file_url) ?>" target="_blank"
                                   class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 px-2 py-1.5 rounded hover:bg-blue-50 border border-blue-200 transition">
                                    <i class="fas <?= $is_image ? 'fa-image' : 'fa-file-pdf' ?>"></i>
                                    <?= strtoupper($ext) ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($dokumen_list)): ?>
            <div class="px-4 py-3 text-xs" style="color:var(--dashboard-muted); border-top:1px solid var(--dashboard-border);">
                Menampilkan <?= count($dokumen_list) ?> dokumen
                <?= ($search_q || $filter_jenis || $filter_status || $filter_prodi) ? '(difilter)' : '' ?>
            </div>
        <?php endif; ?>
    </div>

</div>
</div>

<?php include '../includes/footer.php'; ?>

