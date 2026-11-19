<?php
session_start();
require_once __DIR__ . '/../includes/impersonation.php';
require_writable_session();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'dosen_pkl') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/assessment_helper.php';

$page_title = 'Penilaian Mahasiswa PKL - Dosen Pembimbing';
$feedback = '';
$id_dosen_pkl = (int)$_SESSION['user']['id'];

// Proses simpan / update penilaian kampus
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_nilai') {
    $id_mahasiswa = (int)$_POST['id_mahasiswa'];
    $laporan   = filter_var($_POST['nilai_laporan'], FILTER_VALIDATE_FLOAT);
    $materi    = filter_var($_POST['nilai_materi'], FILTER_VALIDATE_FLOAT);
    $bimbingan = filter_var($_POST['nilai_bimbingan'], FILTER_VALIDATE_FLOAT);
    $catatan   = trim($_POST['catatan_kampus'] ?? '');

    // Validasi apakah mahasiswa ini benar-benar dibimbing oleh dospem pkl ini
    $cek_bimbingan = $conn->prepare("SELECT id FROM bimbingan WHERE id_mahasiswa = ? AND id_dospem_pkl = ?");
    $cek_bimbingan->bind_param("ii", $id_mahasiswa, $id_dosen_pkl);
    $cek_bimbingan->execute();
    if ($cek_bimbingan->get_result()->num_rows === 0) {
        $feedback = "<script>Swal.fire({icon:'error', title:'Akses Ditolak', text:'Mahasiswa ini tidak berada di bawah bimbingan Anda.'});</script>";
    } elseif ($laporan === false || $laporan < 0 || $laporan > 100 ||
              $materi === false || $materi < 0 || $materi > 100 ||
              $bimbingan === false || $bimbingan < 0 || $bimbingan > 100) {
        $feedback = "<script>Swal.fire({icon:'warning', title:'Input Tidak Valid', text:'Semua nilai komponen harus berada dalam rentang 0 - 100.'});</script>";
    } else {
        // Cek apakah sudah ada entri di penilaian_pkl
        $cek_nilai = $conn->prepare("SELECT id FROM penilaian_pkl WHERE id_mahasiswa = ?");
        $cek_nilai->bind_param("i", $id_mahasiswa);
        $cek_nilai->execute();
        $res_cek = $cek_nilai->get_result();

        if ($res_cek->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE penilaian_pkl SET 
                nilai_laporan_kampus = ?, 
                nilai_materi_kampus = ?, 
                nilai_bimbingan_kampus = ?, 
                catatan_kampus = ?, 
                tgl_nilai_kampus = NOW() 
                WHERE id_mahasiswa = ?");
            $stmt->bind_param("dddsi", $laporan, $materi, $bimbingan, $catatan, $id_mahasiswa);
        } else {
            $stmt = $conn->prepare("INSERT INTO penilaian_pkl 
                (id_mahasiswa, nilai_laporan_kampus, nilai_materi_kampus, nilai_bimbingan_kampus, catatan_kampus, tgl_nilai_kampus) 
                VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iddds", $id_mahasiswa, $laporan, $materi, $bimbingan, $catatan);
        }

        if ($stmt->execute()) {
            sinkronkan_nilai_akhir($conn, $id_mahasiswa);
            $feedback = "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Penilaian bimbingan dan laporan PKL mahasiswa berhasil disimpan.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'penilaian.php';
                });
            </script>";
        } else {
            $feedback = "<script>Swal.fire({icon:'error', title:'Gagal', text:'Terjadi kesalahan saat menyimpan penilaian.'});</script>";
        }
    }
}

// Ambil daftar mahasiswa yang dibimbing oleh dospem PKL ini
$query_mhs = "
    SELECT 
        u.id AS id_mahasiswa,
        u.nama,
        u.username AS email,
        m.nim,
        COALESCE(m.prodi, u.prodi, '-') AS prodi,
        COUNT(l.id) AS total_logbook,
        SUM(CASE WHEN l.status_dospem_pkl = 'disetujui' THEN 1 ELSE 0 END) AS logbook_disetujui,
        p.nilai_disiplin_lapangan,
        p.nilai_teknis_lapangan,
        p.nilai_etika_lapangan,
        p.nilai_kerjasama_lapangan,
        p.nilai_laporan_kampus,
        p.nilai_materi_kampus,
        p.nilai_bimbingan_kampus,
        p.catatan_kampus,
        p.tgl_nilai_kampus,
        p.nilai_akhir,
        p.nilai_huruf,
        p.status_kelulusan
    FROM bimbingan b
    JOIN users u ON b.id_mahasiswa = u.id
    LEFT JOIN mahasiswa m ON u.id = m.id
    LEFT JOIN logbook l ON u.id = l.id_mahasiswa
    LEFT JOIN penilaian_pkl p ON u.id = p.id_mahasiswa
    WHERE b.id_dospem_pkl = ?
    GROUP BY u.id
    ORDER BY u.nama ASC
";
$stmt_list = $conn->prepare($query_mhs);
$stmt_list->bind_param("i", $id_dosen_pkl);
$stmt_list->execute();
$mahasiswa_list = $stmt_list->get_result();

include '../includes/header.php';
?>

<?= $feedback ?>

<div class="min-w-0">
    <div class="dashboard-page w-full max-w-full">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                    <i class="fas fa-award text-blue-600" aria-hidden="true"></i>
                    Penilaian Bimbingan & Laporan PKL
                </h2>
                <p class="text-sm mt-1" style="color: var(--dashboard-muted);">
                    Evaluasi penulisan laporan akhir, pemahaman materi, dan keaktifan proses bimbingan mahasiswa.
                </p>
            </div>
            <a href="dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>

        <!-- Info Card Bobot -->
        <div class="p-4 sm:p-5 rounded-2xl mb-6 border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="fas fa-scale-balanced text-lg"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold" style="color: var(--dashboard-text);">Panduan Penilaian Dosen Pembimbing (Bobot 40% Nilai Akhir)</h4>
                    <p class="text-xs mt-1" style="color: var(--dashboard-muted);">
                        Nilai kampus dihitung dari bobot: <strong>Kualitas Laporan Akhir PKL</strong> (40%), <strong>Penguasaan Materi / Presentasi</strong> (30%), dan <strong>Keaktifan Konsultasi Bimbingan</strong> (30%).
                        Nilai akhir akumulasi: <strong>60% Nilai Lapangan + 40% Nilai Dospem Kampus</strong>.
                    </p>
                </div>
            </div>
        </div>

        <!-- Daftar Mahasiswa -->
        <div class="dashboard-panel p-5 sm:p-6 rounded-2xl border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-xs uppercase tracking-wider font-semibold" style="border-color: var(--dashboard-border); color: var(--dashboard-muted);">
                            <th class="py-3 px-3">No</th>
                            <th class="py-3 px-3">Mahasiswa</th>
                            <th class="py-3 px-3">Prodi</th>
                            <th class="py-3 px-3 text-center">Logbook</th>
                            <th class="py-3 px-3 text-center">Nilai Lapangan (60%)</th>
                            <th class="py-3 px-3 text-center">Nilai Kampus (40%)</th>
                            <th class="py-3 px-3 text-center">Nilai Akhir</th>
                            <th class="py-3 px-3 text-center">Status</th>
                            <th class="py-3 px-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--dashboard-border);">
                        <?php if ($mahasiswa_list->num_rows > 0): ?>
                            <?php $no = 1; while ($m = $mahasiswa_list->fetch_assoc()): ?>
                                <?php
                                $has_lapangan = ($m['nilai_disiplin_lapangan'] !== null);
                                $rata_lapangan = $has_lapangan ? hitung_rata_rata_lapangan(
                                    (float)$m['nilai_disiplin_lapangan'],
                                    (float)$m['nilai_teknis_lapangan'],
                                    (float)$m['nilai_etika_lapangan'],
                                    (float)$m['nilai_kerjasama_lapangan']
                                ) : null;

                                $has_kampus = ($m['nilai_laporan_kampus'] !== null);
                                $nilai_kampus = $has_kampus ? hitung_nilai_kampus(
                                    (float)$m['nilai_laporan_kampus'],
                                    (float)$m['nilai_materi_kampus'],
                                    (float)$m['nilai_bimbingan_kampus']
                                ) : null;
                                ?>
                                <tr class="hover:bg-slate-500/5 transition">
                                    <td class="py-3 px-3 font-medium text-xs" style="color: var(--dashboard-muted);"><?= $no++ ?></td>
                                    <td class="py-3 px-3">
                                        <div class="font-bold" style="color: var(--dashboard-text);"><?= htmlspecialchars($m['nama']) ?></div>
                                        <div class="text-xs" style="color: var(--dashboard-muted);">NIM: <?= htmlspecialchars($m['nim'] ?: '-') ?></div>
                                    </td>
                                    <td class="py-3 px-3 text-xs" style="color: var(--dashboard-text);"><?= htmlspecialchars($m['prodi']) ?></td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-500/10 text-blue-500 border border-blue-500/20">
                                            <?= $m['logbook_disetujui'] ?> / <?= $m['total_logbook'] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <?php if ($has_lapangan): ?>
                                            <span class="font-bold text-slate-700 dark:text-slate-200"><?= number_format($rata_lapangan, 1) ?></span>
                                        <?php else: ?>
                                            <span class="text-xs text-amber-500 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">Menunggu</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <?php if ($has_kampus): ?>
                                            <span class="font-bold text-blue-600 dark:text-blue-400"><?= number_format($nilai_kampus, 1) ?></span>
                                        <?php else: ?>
                                            <span class="text-xs font-normal" style="color: var(--dashboard-muted);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center font-bold">
                                        <?php if ($m['nilai_akhir'] !== null): ?>
                                            <div class="text-base text-emerald-500"><?= number_format($m['nilai_akhir'], 1) ?></div>
                                            <div class="text-xs text-emerald-600 font-extrabold">(<?= $m['nilai_huruf'] ?>)</div>
                                        <?php else: ?>
                                            <span class="text-xs font-normal" style="color: var(--dashboard-muted);">Belum Lengkap</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <?php if ($m['status_kelulusan'] === 'lulus'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-green-500/10 text-green-500 border border-green-500/20">
                                                <i class="fas fa-check-circle"></i> Lulus
                                            </span>
                                        <?php elseif ($m['status_kelulusan'] === 'tidak_lulus'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-red-500/10 text-red-500 border border-red-500/20">
                                                <i class="fas fa-times-circle"></i> Tidak Lulus
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                                <i class="fas fa-spinner fa-spin"></i> Proses
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <button type="button" 
                                                onclick="bukaModalNilai(<?= htmlspecialchars(json_encode($m)) ?>)"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 transition shadow-sm">
                                            <i class="fas fa-pen-to-square"></i>
                                            <?= $has_kampus ? 'Edit Nilai' : 'Input Nilai' ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="py-8 text-center text-sm" style="color: var(--dashboard-muted);">
                                    <i class="fas fa-user-slash text-2xl mb-2 block"></i>
                                    Belum ada mahasiswa yang ditugaskan di bawah bimbingan PKL Anda.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal Form Penilaian Kampus -->
<div id="modalNilai" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden">
    <div class="w-full max-w-lg rounded-2xl shadow-2xl border overflow-hidden transition-all transform" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
        <div class="p-5 border-b flex items-center justify-between" style="border-color: var(--dashboard-border);">
            <div>
                <h3 class="font-bold text-lg" style="color: var(--dashboard-text);">Form Penilaian Dosen Pembimbing</h3>
                <p id="modalSubTitle" class="text-xs" style="color: var(--dashboard-muted);"></p>
            </div>
            <button type="button" onclick="tutupModalNilai()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-neutral-800 transition">
                <i class="fas fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="penilaian.php" method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="simpan_nilai">
            <input type="hidden" id="modal_id_mahasiswa" name="id_mahasiswa" value="">

            <div class="space-y-3">
                <!-- 1. Laporan -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-semibold" style="color: var(--dashboard-text);">
                            Kualitas Laporan Akhir PKL (Bobot 40%)
                        </label>
                        <span class="text-[11px] text-blue-500 font-bold">Skala 0 - 100</span>
                    </div>
                    <input type="number" step="0.1" min="0" max="100" id="input_laporan" name="nilai_laporan" required
                           class="w-full px-3 py-2 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold" 
                           style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);"
                           oninput="hitungNilaiKampusLive()">
                </div>

                <!-- 2. Materi / Presentasi -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-semibold" style="color: var(--dashboard-text);">
                            Penguasaan Materi / Presentasi (Bobot 30%)
                        </label>
                        <span class="text-[11px] text-blue-500 font-bold">Skala 0 - 100</span>
                    </div>
                    <input type="number" step="0.1" min="0" max="100" id="input_materi" name="nilai_materi" required
                           class="w-full px-3 py-2 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold" 
                           style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);"
                           oninput="hitungNilaiKampusLive()">
                </div>

                <!-- 3. Keaktifan Bimbingan -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-semibold" style="color: var(--dashboard-text);">
                            Keaktifan & Kedisiplinan Bimbingan (Bobot 30%)
                        </label>
                        <span class="text-[11px] text-blue-500 font-bold">Skala 0 - 100</span>
                    </div>
                    <input type="number" step="0.1" min="0" max="100" id="input_bimbingan" name="nilai_bimbingan" required
                           class="w-full px-3 py-2 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold" 
                           style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);"
                           oninput="hitungNilaiKampusLive()">
                </div>
            </div>

            <!-- Preview Nilai Kampus Live -->
            <div class="p-3 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-between">
                <span class="text-xs font-semibold text-purple-600">Estimasi Nilai Kampus Terbobot:</span>
                <span id="previewKampusLive" class="text-base font-bold text-purple-700">0.0</span>
            </div>

            <!-- Catatan / Masukan Dospem -->
            <div>
                <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">
                    Catatan Pembimbing / Rekomendasi
                </label>
                <textarea id="input_catatan" name="catatan_kampus" rows="3" placeholder="Tuliskan catatan evaluasi bimbingan atau revisi laporan..."
                          class="w-full px-3 py-2 rounded-lg text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500" 
                          style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);"></textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3 border-t" style="border-color: var(--dashboard-border);">
                <button type="button" onclick="tutupModalNilai()" class="px-4 py-2 rounded-lg text-xs font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2 rounded-lg text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 shadow transition">
                    <i class="fas fa-save mr-1.5"></i> Simpan Penilaian
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalNilai(data) {
    document.getElementById('modal_id_mahasiswa').value = data.id_mahasiswa;
    document.getElementById('modalSubTitle').innerText = data.nama + ' (NIM: ' + (data.nim || '-') + ')';
    
    document.getElementById('input_laporan').value = data.nilai_laporan_kampus !== null ? data.nilai_laporan_kampus : '';
    document.getElementById('input_materi').value = data.nilai_materi_kampus !== null ? data.nilai_materi_kampus : '';
    document.getElementById('input_bimbingan').value = data.nilai_bimbingan_kampus !== null ? data.nilai_bimbingan_kampus : '';
    document.getElementById('input_catatan').value = data.catatan_kampus || '';

    hitungNilaiKampusLive();
    document.getElementById('modalNilai').classList.remove('hidden');
}

function tutupModalNilai() {
    document.getElementById('modalNilai').classList.add('hidden');
}

function hitungNilaiKampusLive() {
    const l = parseFloat(document.getElementById('input_laporan').value) || 0;
    const m = parseFloat(document.getElementById('input_materi').value) || 0;
    const b = parseFloat(document.getElementById('input_bimbingan').value) || 0;
    const total = ((l * 0.40) + (m * 0.30) + (b * 0.30)).toFixed(1);
    document.getElementById('previewKampusLive').innerText = total;
}
</script>

<?php include '../includes/footer.php'; ?>
