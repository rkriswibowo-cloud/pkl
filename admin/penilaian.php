<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/assessment_helper.php';

$page_title = 'Rekapitulasi Nilai PKL - Admin Logbook';

// Filter parameter
$search_query = trim($_GET['search'] ?? '');
$filter_prodi  = trim($_GET['prodi'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

// Ambil daftar prodi unik untuk filter
$prodi_options = $conn->query("
    SELECT DISTINCT COALESCE(NULLIF(m.prodi, ''), NULLIF(u.prodi, '')) AS prodi_name 
    FROM users u 
    LEFT JOIN mahasiswa m ON u.id = m.id 
    WHERE u.role = 'mahasiswa' AND (m.prodi IS NOT NULL OR u.prodi IS NOT NULL)
");

// Query Statistik
$stats_query = "
    SELECT 
        COUNT(u.id) AS total_mahasiswa,
        SUM(CASE WHEN p.nilai_akhir IS NOT NULL THEN 1 ELSE 0 END) AS dinilai_lengkap,
        SUM(CASE WHEN p.nilai_akhir IS NULL THEN 1 ELSE 0 END) AS belum_lengkap,
        SUM(CASE WHEN p.status_kelulusan = 'lulus' THEN 1 ELSE 0 END) AS lulus_count
    FROM users u
    LEFT JOIN penilaian_pkl p ON u.id = p.id_mahasiswa
    WHERE u.role = 'mahasiswa'
";
$stats = $conn->query($stats_query)->fetch_assoc();
$total_mhs       = (int)($stats['total_mahasiswa'] ?? 0);
$dinilai_lengkap = (int)($stats['dinilai_lengkap'] ?? 0);
$belum_lengkap   = (int)($stats['belum_lengkap'] ?? 0);
$lulus_count     = (int)($stats['lulus_count'] ?? 0);
$persen_lulus    = $total_mhs > 0 ? round(($lulus_count / $total_mhs) * 100, 1) : 0;

// Query Daftar Nilai dengan Filter
$sql = "
    SELECT 
        u.id AS id_mahasiswa,
        u.nama,
        u.username AS email,
        m.nim,
        COALESCE(NULLIF(m.prodi, ''), NULLIF(u.prodi, ''), '-') AS prodi,
        pkl.nama AS nama_dospem_pkl,
        lapangan.nama AS nama_dospem_lapangan,
        p.nilai_disiplin_lapangan,
        p.nilai_teknis_lapangan,
        p.nilai_etika_lapangan,
        p.nilai_kerjasama_lapangan,
        p.catatan_lapangan,
        p.tgl_nilai_lapangan,
        p.nilai_laporan_kampus,
        p.nilai_materi_kampus,
        p.nilai_bimbingan_kampus,
        p.catatan_kampus,
        p.tgl_nilai_kampus,
        p.nilai_akhir,
        p.nilai_huruf,
        COALESCE(p.status_kelulusan, 'belum_selesai') AS status_kelulusan
    FROM users u
    LEFT JOIN mahasiswa m ON u.id = m.id
    LEFT JOIN bimbingan b ON u.id = b.id_mahasiswa
    LEFT JOIN users pkl ON b.id_dospem_pkl = pkl.id
    LEFT JOIN users lapangan ON b.id_dospem_lapangan = lapangan.id
    LEFT JOIN penilaian_pkl p ON u.id = p.id_mahasiswa
    WHERE u.role = 'mahasiswa'
";

$params = [];
$types  = "";

if (!empty($search_query)) {
    $sql .= " AND (u.nama LIKE ? OR m.nim LIKE ? OR u.username LIKE ?)";
    $like = "%$search_query%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if (!empty($filter_prodi)) {
    $sql .= " AND (m.prodi = ? OR u.prodi = ?)";
    $params[] = $filter_prodi;
    $params[] = $filter_prodi;
    $types .= "ss";
}

if (!empty($filter_status)) {
    if ($filter_status === 'belum_selesai') {
        $sql .= " AND (p.status_kelulusan = 'belum_selesai' OR p.status_kelulusan IS NULL)";
    } else {
        $sql .= " AND p.status_kelulusan = ?";
        $params[] = $filter_status;
        $types .= "s";
    }
}

$sql .= " ORDER BY u.nama ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$daftar_nilai = $stmt->get_result();

include '../includes/header.php';
?>

<div class="min-w-0">
    <div class="dashboard-page w-full max-w-full">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                    <i class="fas fa-file-invoice text-blue-600" aria-hidden="true"></i>
                    Rekapitulasi Nilai PKL
                </h2>
                <p class="text-sm mt-1" style="color: var(--dashboard-muted);">
                    Rekap gabungan penilaian dari Pembimbing Lapangan (60%) dan Dosen Pembimbing Kampus (40%).
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    <i class="fas fa-print"></i> Cetak Rekap
                </button>
                <a href="dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- 4 Statistik Card -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="p-5 rounded-2xl border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider" style="color: var(--dashboard-muted);">Total Mahasiswa</span>
                    <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold" style="color: var(--dashboard-text);"><?= $total_mhs ?></div>
                <div class="text-[11px] mt-1" style="color: var(--dashboard-muted);">Peserta PKL aktif terdaftar</div>
            </div>

            <div class="p-5 rounded-2xl border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider" style="color: var(--dashboard-muted);">Sudah Dinilai</span>
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-500 flex items-center justify-center">
                        <i class="fas fa-circle-check"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= $dinilai_lengkap ?></div>
                <div class="text-[11px] mt-1" style="color: var(--dashboard-muted);">Penilaian lapangan & kampus lengkap</div>
            </div>

            <div class="p-5 rounded-2xl border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider" style="color: var(--dashboard-muted);">Menunggu Nilai</span>
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-500 flex items-center justify-center">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold text-amber-600 dark:text-amber-400"><?= $belum_lengkap ?></div>
                <div class="text-[11px] mt-1" style="color: var(--dashboard-muted);">Belum selesai dinilai pembimbing</div>
            </div>

            <div class="p-5 rounded-2xl border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider" style="color: var(--dashboard-muted);">Tingkat Kelulusan</span>
                    <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-500 flex items-center justify-center">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400"><?= $persen_lulus ?>%</div>
                <div class="text-[11px] mt-1" style="color: var(--dashboard-muted);"><?= $lulus_count ?> mahasiswa lulus</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="p-4 rounded-2xl border mb-6" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
            <form action="penilaian.php" method="GET" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                <div class="sm:col-span-5">
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Cari Mahasiswa</label>
                    <div class="relative">
                        <i class="fas fa-magnifying-glass absolute left-3 top-3 text-xs text-gray-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Nama, NIM, atau email..."
                               class="w-full pl-8 pr-3 py-2 rounded-lg text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Program Studi</label>
                    <select name="prodi" class="w-full px-3 py-2 rounded-lg text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                        <option value="">Semua Program Studi</option>
                        <?php while ($p = $prodi_options->fetch_assoc()): ?>
                            <?php if (!empty($p['prodi_name'])): ?>
                                <option value="<?= htmlspecialchars($p['prodi_name']) ?>" <?= $filter_prodi === $p['prodi_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['prodi_name']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Status</label>
                    <select name="status" class="w-full px-3 py-2 rounded-lg text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                        <option value="">Semua Status</option>
                        <option value="lulus" <?= $filter_status === 'lulus' ? 'selected' : '' ?>>Lulus</option>
                        <option value="tidak_lulus" <?= $filter_status === 'tidak_lulus' ? 'selected' : '' ?>>Tidak Lulus</option>
                        <option value="belum_selesai" <?= $filter_status === 'belum_selesai' ? 'selected' : '' ?>>Belum Selesai</option>
                    </select>
                </div>

                <div class="sm:col-span-2 flex items-center gap-2">
                    <button type="submit" class="w-full py-2 px-3 rounded-lg text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 shadow transition flex items-center justify-center gap-1.5">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <?php if (!empty($search_query) || !empty($filter_prodi) || !empty($filter_status)): ?>
                        <a href="penilaian.php" class="p-2 rounded-lg text-xs border hover:bg-slate-500/10 transition" style="border-color: var(--dashboard-border); color: var(--dashboard-muted);" title="Reset Filter">
                            <i class="fas fa-rotate-left"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Rekap Nilai -->
        <div class="dashboard-panel p-5 sm:p-6 rounded-2xl border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-xs uppercase tracking-wider font-semibold" style="border-color: var(--dashboard-border); color: var(--dashboard-muted);">
                            <th class="py-3 px-3">No</th>
                            <th class="py-3 px-3">Mahasiswa</th>
                            <th class="py-3 px-3">Prodi</th>
                            <th class="py-3 px-3">Pembimbing</th>
                            <th class="py-3 px-3 text-center">Nilai Lapangan</th>
                            <th class="py-3 px-3 text-center">Nilai Kampus</th>
                            <th class="py-3 px-3 text-center">Nilai Akhir</th>
                            <th class="py-3 px-3 text-center">Mutu</th>
                            <th class="py-3 px-3 text-center">Status</th>
                            <th class="py-3 px-3 text-center">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--dashboard-border);">
                        <?php if ($daftar_nilai->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $daftar_nilai->fetch_assoc()): ?>
                                <?php
                                $has_lapangan = ($row['nilai_disiplin_lapangan'] !== null);
                                $rata_lapangan = $has_lapangan ? hitung_rata_rata_lapangan(
                                    (float)$row['nilai_disiplin_lapangan'],
                                    (float)$row['nilai_teknis_lapangan'],
                                    (float)$row['nilai_etika_lapangan'],
                                    (float)$row['nilai_kerjasama_lapangan']
                                ) : null;

                                $has_kampus = ($row['nilai_laporan_kampus'] !== null);
                                $nilai_kampus = $has_kampus ? hitung_nilai_kampus(
                                    (float)$row['nilai_laporan_kampus'],
                                    (float)$row['nilai_materi_kampus'],
                                    (float)$row['nilai_bimbingan_kampus']
                                ) : null;
                                ?>
                                <tr class="hover:bg-slate-500/5 transition">
                                    <td class="py-3 px-3 font-medium text-xs" style="color: var(--dashboard-muted);"><?= $no++ ?></td>
                                    <td class="py-3 px-3">
                                        <div class="font-bold" style="color: var(--dashboard-text);"><?= htmlspecialchars($row['nama']) ?></div>
                                        <div class="text-xs" style="color: var(--dashboard-muted);">NIM: <?= htmlspecialchars($row['nim'] ?: '-') ?></div>
                                    </td>
                                    <td class="py-3 px-3 text-xs" style="color: var(--dashboard-text);"><?= htmlspecialchars($row['prodi']) ?></td>
                                    <td class="py-3 px-3 text-xs leading-relaxed" style="color: var(--dashboard-muted);">
                                        <div><span class="font-semibold text-blue-500">Kampus:</span> <?= htmlspecialchars($row['nama_dospem_pkl'] ?: '-') ?></div>
                                        <div><span class="font-semibold text-amber-500">Lapangan:</span> <?= htmlspecialchars($row['nama_dospem_lapangan'] ?: '-') ?></div>
                                    </td>
                                    <td class="py-3 px-3 text-center font-bold">
                                        <?php if ($has_lapangan): ?>
                                            <span class="text-slate-800 dark:text-slate-100"><?= number_format($rata_lapangan, 1) ?></span>
                                        <?php else: ?>
                                            <span class="text-xs text-amber-500 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center font-bold">
                                        <?php if ($has_kampus): ?>
                                            <span class="text-blue-600 dark:text-blue-400"><?= number_format($nilai_kampus, 1) ?></span>
                                        <?php else: ?>
                                            <span class="text-xs text-amber-500 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center font-bold text-base">
                                        <?php if ($row['nilai_akhir'] !== null): ?>
                                            <span class="text-emerald-500"><?= number_format($row['nilai_akhir'], 1) ?></span>
                                        <?php else: ?>
                                            <span class="text-xs font-normal" style="color: var(--dashboard-muted);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center font-extrabold text-sm">
                                        <?php if (!empty($row['nilai_huruf'])): ?>
                                            <span class="px-2 py-0.5 rounded bg-blue-500/10 text-blue-600 border border-blue-500/20"><?= htmlspecialchars($row['nilai_huruf']) ?></span>
                                        <?php else: ?>
                                            <span class="text-xs font-normal" style="color: var(--dashboard-muted);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <?php if ($row['status_kelulusan'] === 'lulus'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-500/10 text-green-500 border border-green-500/20">
                                                <i class="fas fa-check-circle"></i> Lulus
                                            </span>
                                        <?php elseif ($row['status_kelulusan'] === 'tidak_lulus'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-500/10 text-red-500 border border-red-500/20">
                                                <i class="fas fa-times-circle"></i> Tidak Lulus
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                                <i class="fas fa-clock"></i> Belum Lengkap
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <button type="button" 
                                                onclick="bukaDetailNilai(<?= htmlspecialchars(json_encode($row)) ?>)"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold border hover:bg-blue-600 hover:text-white transition"
                                                style="border-color: var(--dashboard-border); color: var(--dashboard-text);">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="py-8 text-center text-sm" style="color: var(--dashboard-muted);">
                                    <i class="fas fa-clipboard-question text-2xl mb-2 block"></i>
                                    Tidak ada data penilaian yang sesuai dengan filter pencarian.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal Detail Penilaian -->
<div id="modalDetail" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden">
    <div class="w-full max-w-2xl rounded-2xl shadow-2xl border overflow-hidden transition-all transform max-h-[90vh] flex flex-col" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
        <div class="p-5 border-b flex items-center justify-between" style="border-color: var(--dashboard-border);">
            <div>
                <h3 class="font-bold text-lg" style="color: var(--dashboard-text);">Rincian Transkrip Nilai PKL</h3>
                <p id="detailMahasiswa" class="text-xs" style="color: var(--dashboard-muted);"></p>
            </div>
            <button type="button" onclick="tutupDetailNilai()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-neutral-800 transition">
                <i class="fas fa-xmark text-lg"></i>
            </button>
        </div>

        <div class="p-5 overflow-y-auto space-y-5">
            <!-- Box Skor Akhir -->
            <div class="p-4 rounded-xl flex items-center justify-between bg-blue-500/10 border border-blue-500/20">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Nilai Akhir Gabungan</span>
                    <div id="detailNilaiAkhir" class="text-3xl font-extrabold text-blue-700 mt-0.5">-</div>
                </div>
                <div class="text-right">
                    <span class="text-xs font-semibold text-blue-600">Huruf Mutu & Status:</span>
                    <div class="flex items-center gap-2 mt-1 justify-end">
                        <span id="detailHuruf" class="text-xl font-bold px-3 py-0.5 rounded-lg bg-blue-600 text-white">-</span>
                        <span id="detailStatusBadge" class="text-xs font-bold px-3 py-1 rounded-full bg-slate-200">-</span>
                    </div>
                </div>
            </div>

            <!-- Bagian 1: Lapangan -->
            <div class="border rounded-xl p-4" style="border-color: var(--dashboard-border);">
                <div class="flex items-center justify-between mb-3 pb-2 border-b" style="border-color: var(--dashboard-border);">
                    <h4 class="text-sm font-bold flex items-center gap-2" style="color: var(--dashboard-text);">
                        <i class="fas fa-building text-amber-500"></i> Nilai Pembimbing Lapangan (Bobot 60%)
                    </h4>
                    <span id="detailDospemLapangan" class="text-xs font-medium" style="color: var(--dashboard-muted);"></span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center mb-3">
                    <div class="p-2.5 rounded-lg" style="background: var(--dashboard-raised);">
                        <span class="text-[11px] block text-muted">Disiplin (25%)</span>
                        <span id="val_disiplin" class="text-base font-bold text-slate-800 dark:text-slate-100">-</span>
                    </div>
                    <div class="p-2.5 rounded-lg" style="background: var(--dashboard-raised);">
                        <span class="text-[11px] block text-muted">Teknis (25%)</span>
                        <span id="val_teknis" class="text-base font-bold text-slate-800 dark:text-slate-100">-</span>
                    </div>
                    <div class="p-2.5 rounded-lg" style="background: var(--dashboard-raised);">
                        <span class="text-[11px] block text-muted">Etika (25%)</span>
                        <span id="val_etika" class="text-base font-bold text-slate-800 dark:text-slate-100">-</span>
                    </div>
                    <div class="p-2.5 rounded-lg" style="background: var(--dashboard-raised);">
                        <span class="text-[11px] block text-muted">Kerjasama (25%)</span>
                        <span id="val_kerjasama" class="text-base font-bold text-slate-800 dark:text-slate-100">-</span>
                    </div>
                </div>
                <div class="text-xs p-2.5 rounded-lg" style="background: var(--dashboard-raised); color: var(--dashboard-text);">
                    <span class="font-semibold block mb-0.5 text-muted">Catatan Pembimbing Lapangan:</span>
                    <span id="val_catatan_lapangan" class="italic">-</span>
                </div>
            </div>

            <!-- Bagian 2: Kampus -->
            <div class="border rounded-xl p-4" style="border-color: var(--dashboard-border);">
                <div class="flex items-center justify-between mb-3 pb-2 border-b" style="border-color: var(--dashboard-border);">
                    <h4 class="text-sm font-bold flex items-center gap-2" style="color: var(--dashboard-text);">
                        <i class="fas fa-graduation-cap text-blue-500"></i> Nilai Dosen Pembimbing Kampus (Bobot 40%)
                    </h4>
                    <span id="detailDospemPKL" class="text-xs font-medium" style="color: var(--dashboard-muted);"></span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-center mb-3">
                    <div class="p-2.5 rounded-lg" style="background: var(--dashboard-raised);">
                        <span class="text-[11px] block text-muted">Laporan Akhir (40%)</span>
                        <span id="val_laporan" class="text-base font-bold text-slate-800 dark:text-slate-100">-</span>
                    </div>
                    <div class="p-2.5 rounded-lg" style="background: var(--dashboard-raised);">
                        <span class="text-[11px] block text-muted">Materi/Presentasi (30%)</span>
                        <span id="val_materi" class="text-base font-bold text-slate-800 dark:text-slate-100">-</span>
                    </div>
                    <div class="p-2.5 rounded-lg" style="background: var(--dashboard-raised);">
                        <span class="text-[11px] block text-muted">Keaktifan Bimbingan (30%)</span>
                        <span id="val_bimbingan" class="text-base font-bold text-slate-800 dark:text-slate-100">-</span>
                    </div>
                </div>
                <div class="text-xs p-2.5 rounded-lg" style="background: var(--dashboard-raised); color: var(--dashboard-text);">
                    <span class="font-semibold block mb-0.5 text-muted">Catatan Dospem Kampus:</span>
                    <span id="val_catatan_kampus" class="italic">-</span>
                </div>
            </div>
        </div>

        <div class="p-4 border-t flex items-center justify-end" style="border-color: var(--dashboard-border);">
            <button type="button" onclick="tutupDetailNilai()" class="px-5 py-2 rounded-lg text-xs font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                Tutup
            </button>
        </div>
    </div>
</div>

<script>
function bukaDetailNilai(row) {
    document.getElementById('detailMahasiswa').innerText = row.nama + ' - NIM: ' + (row.nim || '-') + ' (' + row.prodi + ')';
    document.getElementById('detailDospemLapangan').innerText = 'Dospem: ' + (row.nama_dospem_lapangan || 'Belum di-set');
    document.getElementById('detailDospemPKL').innerText = 'Dospem: ' + (row.nama_dospem_pkl || 'Belum di-set');

    document.getElementById('detailNilaiAkhir').innerText = row.nilai_akhir !== null ? parseFloat(row.nilai_akhir).toFixed(1) : 'Belum Lengkap';
    document.getElementById('detailHuruf').innerText = row.nilai_huruf || '-';

    const statusBadge = document.getElementById('detailStatusBadge');
    if (row.status_kelulusan === 'lulus') {
        statusBadge.className = 'text-xs font-bold px-3 py-1 rounded-full bg-green-500/20 text-green-500 border border-green-500/30';
        statusBadge.innerText = 'Lulus';
    } else if (row.status_kelulusan === 'tidak_lulus') {
        statusBadge.className = 'text-xs font-bold px-3 py-1 rounded-full bg-red-500/20 text-red-500 border border-red-500/30';
        statusBadge.innerText = 'Tidak Lulus';
    } else {
        statusBadge.className = 'text-xs font-semibold px-3 py-1 rounded-full bg-amber-500/20 text-amber-500 border border-amber-500/30';
        statusBadge.innerText = 'Belum Selesai';
    }

    document.getElementById('val_disiplin').innerText = row.nilai_disiplin_lapangan !== null ? row.nilai_disiplin_lapangan : '-';
    document.getElementById('val_teknis').innerText = row.nilai_teknis_lapangan !== null ? row.nilai_teknis_lapangan : '-';
    document.getElementById('val_etika').innerText = row.nilai_etika_lapangan !== null ? row.nilai_etika_lapangan : '-';
    document.getElementById('val_kerjasama').innerText = row.nilai_kerjasama_lapangan !== null ? row.nilai_kerjasama_lapangan : '-';
    document.getElementById('val_catatan_lapangan').innerText = row.catatan_lapangan || 'Tidak ada catatan khusus.';

    document.getElementById('val_laporan').innerText = row.nilai_laporan_kampus !== null ? row.nilai_laporan_kampus : '-';
    document.getElementById('val_materi').innerText = row.nilai_materi_kampus !== null ? row.nilai_materi_kampus : '-';
    document.getElementById('val_bimbingan').innerText = row.nilai_bimbingan_kampus !== null ? row.nilai_bimbingan_kampus : '-';
    document.getElementById('val_catatan_kampus').innerText = row.catatan_kampus || 'Tidak ada catatan khusus.';

    document.getElementById('modalDetail').classList.remove('hidden');
}

function tutupDetailNilai() {
    document.getElementById('modalDetail').classList.add('hidden');
}
</script>

<?php include '../includes/footer.php'; ?>
