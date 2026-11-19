<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mahasiswa') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/assessment_helper.php';

$page_title = 'Transkrip Nilai PKL - Mahasiswa';
$id_mahasiswa = (int)$_SESSION['user']['id'];

// Ambil data mahasiswa & dosen pembimbing
$query_info = "
    SELECT 
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
    WHERE u.id = ?
";
$stmt = $conn->prepare($query_info);
$stmt->bind_param("i", $id_mahasiswa);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

$has_lapangan = ($data['nilai_disiplin_lapangan'] !== null);
$rata_lapangan = $has_lapangan ? hitung_rata_rata_lapangan(
    (float)$data['nilai_disiplin_lapangan'],
    (float)$data['nilai_teknis_lapangan'],
    (float)$data['nilai_etika_lapangan'],
    (float)$data['nilai_kerjasama_lapangan']
) : null;

$has_kampus = ($data['nilai_laporan_kampus'] !== null);
$nilai_kampus = $has_kampus ? hitung_nilai_kampus(
    (float)$data['nilai_laporan_kampus'],
    (float)$data['nilai_materi_kampus'],
    (float)$data['nilai_bimbingan_kampus']
) : null;

include '../includes/header.php';
?>

<div class="min-w-0">
    <div class="dashboard-page w-full max-w-full">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                    <i class="fas fa-graduation-cap text-blue-600" aria-hidden="true"></i>
                    Transkrip Nilai PKL
                </h2>
                <p class="text-sm mt-1" style="color: var(--dashboard-muted);">
                    Rincian hasil evaluasi kinerja lapangan dan bimbingan akademik Praktik Kerja Lapangan.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    <i class="fas fa-print"></i> Cetak Transkrip
                </button>
                <a href="dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Kartu Banner Hasil Akhir -->
        <div class="dashboard-panel p-6 sm:p-8 rounded-3xl border mb-6 relative overflow-hidden" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-3 bg-blue-500/10 text-blue-600 border border-blue-500/20">
                        <i class="fas fa-award"></i> Hasil Penilaian Akhir
                    </span>
                    <h3 class="text-xl sm:text-2xl font-bold" style="color: var(--dashboard-text);">
                        <?= htmlspecialchars($data['nama']) ?>
                    </h3>
                    <p class="text-xs sm:text-sm mt-1" style="color: var(--dashboard-muted);">
                        NIM: <span class="font-semibold text-blue-500"><?= htmlspecialchars($data['nim'] ?: '-') ?></span> | Program Studi: <?= htmlspecialchars($data['prodi']) ?>
                    </p>
                </div>

                <div class="flex items-center gap-4 sm:gap-6 bg-slate-500/5 p-4 rounded-2xl border" style="border-color: var(--dashboard-border);">
                    <div>
                        <span class="text-xs font-semibold block" style="color: var(--dashboard-muted);">Nilai Akhir:</span>
                        <div class="text-3xl sm:text-4xl font-extrabold text-blue-600">
                            <?= $data['nilai_akhir'] !== null ? number_format($data['nilai_akhir'], 1) : '-' ?>
                        </div>
                    </div>
                    <div class="border-l pl-4 sm:pl-6" style="border-color: var(--dashboard-border);">
                        <span class="text-xs font-semibold block" style="color: var(--dashboard-muted);">Mutu:</span>
                        <div class="text-3xl sm:text-4xl font-extrabold text-purple-600">
                            <?= htmlspecialchars($data['nilai_huruf'] ?: '-') ?>
                        </div>
                    </div>
                    <div class="border-l pl-4 sm:pl-6" style="border-color: var(--dashboard-border);">
                        <span class="text-xs font-semibold block" style="color: var(--dashboard-muted);">Status:</span>
                        <?php if ($data['status_kelulusan'] === 'lulus'): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-green-500/10 text-green-500 border border-green-500/30 mt-1">
                                <i class="fas fa-check-circle"></i> LULUS
                            </span>
                        <?php elseif ($data['status_kelulusan'] === 'tidak_lulus'): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-red-500/10 text-red-500 border border-red-500/30 mt-1">
                                <i class="fas fa-times-circle"></i> TIDAK LULUS
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/30 mt-1">
                                <i class="fas fa-spinner fa-spin"></i> PROSES
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2 Kolom Rincian Nilai -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

            <!-- 1. Nilai Lapangan (60%) -->
            <div class="dashboard-panel p-6 rounded-2xl border flex flex-col justify-between" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
                <div>
                    <div class="flex items-center justify-between pb-3 mb-4 border-b" style="border-color: var(--dashboard-border);">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-500 flex items-center justify-center">
                                <i class="fas fa-building"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold" style="color: var(--dashboard-text);">Nilai Pembimbing Lapangan (60%)</h4>
                                <p class="text-[11px]" style="color: var(--dashboard-muted);">
                                    Dospem: <?= htmlspecialchars($data['nama_dospem_lapangan'] ?: 'Belum ditentukan') ?>
                                </p>
                            </div>
                        </div>
                        <?php if ($has_lapangan): ?>
                            <span class="text-lg font-bold text-amber-600 dark:text-amber-400">
                                <?= number_format($rata_lapangan, 1) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-xs px-2 py-0.5 rounded bg-amber-500/10 text-amber-500 border border-amber-500/20">Menunggu</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($has_lapangan): ?>
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div class="p-3 rounded-xl border text-center" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                                <span class="text-[11px] block font-medium" style="color: var(--dashboard-muted);">Kedisiplinan (25%)</span>
                                <span class="text-lg font-bold" style="color: var(--dashboard-text);"><?= $data['nilai_disiplin_lapangan'] ?></span>
                            </div>
                            <div class="p-3 rounded-xl border text-center" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                                <span class="text-[11px] block font-medium" style="color: var(--dashboard-muted);">Keterampilan Teknis (25%)</span>
                                <span class="text-lg font-bold" style="color: var(--dashboard-text);"><?= $data['nilai_teknis_lapangan'] ?></span>
                            </div>
                            <div class="p-3 rounded-xl border text-center" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                                <span class="text-[11px] block font-medium" style="color: var(--dashboard-muted);">Etika & Sikap (25%)</span>
                                <span class="text-lg font-bold" style="color: var(--dashboard-text);"><?= $data['nilai_etika_lapangan'] ?></span>
                            </div>
                            <div class="p-3 rounded-xl border text-center" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                                <span class="text-[11px] block font-medium" style="color: var(--dashboard-muted);">Kerjasama Tim (25%)</span>
                                <span class="text-lg font-bold" style="color: var(--dashboard-text);"><?= $data['nilai_kerjasama_lapangan'] ?></span>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl border text-xs" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                            <span class="font-bold block mb-1 text-amber-500">Catatan Pembimbing Lapangan:</span>
                            <p class="italic leading-relaxed" style="color: var(--dashboard-text);">
                                <?= nl2br(htmlspecialchars($data['catatan_lapangan'] ?: 'Tidak ada catatan khusus.')) ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="py-12 text-center text-xs" style="color: var(--dashboard-muted);">
                            <i class="fas fa-clock text-2xl mb-2 block text-amber-500"></i>
                            Pembimbing lapangan belum menginputkan nilai evaluasi PKL Anda.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. Nilai Kampus (40%) -->
            <div class="dashboard-panel p-6 rounded-2xl border flex flex-col justify-between" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
                <div>
                    <div class="flex items-center justify-between pb-3 mb-4 border-b" style="border-color: var(--dashboard-border);">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold" style="color: var(--dashboard-text);">Nilai Dosen Pembimbing (40%)</h4>
                                <p class="text-[11px]" style="color: var(--dashboard-muted);">
                                    Dospem: <?= htmlspecialchars($data['nama_dospem_pkl'] ?: 'Belum ditentukan') ?>
                                </p>
                            </div>
                        </div>
                        <?php if ($has_kampus): ?>
                            <span class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                <?= number_format($nilai_kampus, 1) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-xs px-2 py-0.5 rounded bg-amber-500/10 text-amber-500 border border-amber-500/20">Menunggu</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($has_kampus): ?>
                        <div class="grid grid-cols-3 gap-3 mb-4">
                            <div class="p-3 rounded-xl border text-center" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                                <span class="text-[11px] block font-medium" style="color: var(--dashboard-muted);">Laporan PKL (40%)</span>
                                <span class="text-lg font-bold" style="color: var(--dashboard-text);"><?= $data['nilai_laporan_kampus'] ?></span>
                            </div>
                            <div class="p-3 rounded-xl border text-center" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                                <span class="text-[11px] block font-medium" style="color: var(--dashboard-muted);">Materi (30%)</span>
                                <span class="text-lg font-bold" style="color: var(--dashboard-text);"><?= $data['nilai_materi_kampus'] ?></span>
                            </div>
                            <div class="p-3 rounded-xl border text-center" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                                <span class="text-[11px] block font-medium" style="color: var(--dashboard-muted);">Bimbingan (30%)</span>
                                <span class="text-lg font-bold" style="color: var(--dashboard-text);"><?= $data['nilai_bimbingan_kampus'] ?></span>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl border text-xs" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                            <span class="font-bold block mb-1 text-blue-500">Catatan Dosen Pembimbing:</span>
                            <p class="italic leading-relaxed" style="color: var(--dashboard-text);">
                                <?= nl2br(htmlspecialchars($data['catatan_kampus'] ?: 'Tidak ada catatan khusus.')) ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="py-12 text-center text-xs" style="color: var(--dashboard-muted);">
                            <i class="fas fa-clock text-2xl mb-2 block text-blue-500"></i>
                            Dosen pembimbing kampus belum menginputkan nilai bimbingan & laporan PKL Anda.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>

