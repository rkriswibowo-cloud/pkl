<?php
session_start();
require_once __DIR__ . '/../includes/impersonation.php';
require_writable_session();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'dosen_lapangan') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/assessment_helper.php';

$page_title = 'Penilaian Mahasiswa PKL - Pembimbing Lapangan';
$feedback = '';
$id_dosen_lapangan = (int)$_SESSION['user']['id'];

// Proses simpan / update penilaian lapangan
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_nilai') {
    $id_mahasiswa = (int)$_POST['id_mahasiswa'];
    $disiplin   = filter_var($_POST['nilai_disiplin'], FILTER_VALIDATE_FLOAT);
    $teknis     = filter_var($_POST['nilai_teknis'], FILTER_VALIDATE_FLOAT);
    $etika      = filter_var($_POST['nilai_etika'], FILTER_VALIDATE_FLOAT);
    $kerjasama  = filter_var($_POST['nilai_kerjasama'], FILTER_VALIDATE_FLOAT);
    $catatan    = trim($_POST['catatan_lapangan'] ?? '');

    // Validasi apakah mahasiswa ini benar-benar dibimbing oleh dospem lapangan ini
    $cek_bimbingan = $conn->prepare("SELECT id FROM bimbingan WHERE id_mahasiswa = ? AND id_dospem_lapangan = ?");
    $cek_bimbingan->bind_param("ii", $id_mahasiswa, $id_dosen_lapangan);
    $cek_bimbingan->execute();
    if ($cek_bimbingan->get_result()->num_rows === 0) {
        $feedback = "<script>Swal.fire({icon:'error', title:'Akses Ditolak', text:'Mahasiswa ini tidak berada di bawah bimbingan Anda.'});</script>";
    } elseif ($disiplin === false || $disiplin < 0 || $disiplin > 100 ||
              $teknis === false || $teknis < 0 || $teknis > 100 ||
              $etika === false || $etika < 0 || $etika > 100 ||
              $kerjasama === false || $kerjasama < 0 || $kerjasama > 100) {
        $feedback = "<script>Swal.fire({icon:'warning', title:'Input Tidak Valid', text:'Semua nilai komponen harus berada dalam rentang 0 - 100.'});</script>";
    } else {
        // Cek apakah sudah ada entri di penilaian_pkl
        $cek_nilai = $conn->prepare("SELECT id FROM penilaian_pkl WHERE id_mahasiswa = ?");
        $cek_nilai->bind_param("i", $id_mahasiswa);
        $cek_nilai->execute();
        $res_cek = $cek_nilai->get_result();

        if ($res_cek->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE penilaian_pkl SET 
                nilai_disiplin_lapangan = ?, 
                nilai_teknis_lapangan = ?, 
                nilai_etika_lapangan = ?, 
                nilai_kerjasama_lapangan = ?, 
                catatan_lapangan = ?, 
                tgl_nilai_lapangan = NOW() 
                WHERE id_mahasiswa = ?");
            $stmt->bind_param("ddddsi", $disiplin, $teknis, $etika, $kerjasama, $catatan, $id_mahasiswa);
        } else {
            $stmt = $conn->prepare("INSERT INTO penilaian_pkl 
                (id_mahasiswa, nilai_disiplin_lapangan, nilai_teknis_lapangan, nilai_etika_lapangan, nilai_kerjasama_lapangan, catatan_lapangan, tgl_nilai_lapangan) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("idddds", $id_mahasiswa, $disiplin, $teknis, $etika, $kerjasama, $catatan);
        }

        if ($stmt->execute()) {
            sinkronkan_nilai_akhir($conn, $id_mahasiswa);
            $feedback = "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Penilaian kinerja lapangan mahasiswa berhasil disimpan.',
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

// Ambil daftar mahasiswa yang dibimbing oleh dospem lapangan ini
$query_mhs = "
    SELECT 
        u.id AS id_mahasiswa,
        u.nama,
        u.username AS email,
        m.nim,
        COALESCE(m.prodi, u.prodi, '-') AS prodi,
        COUNT(l.id) AS total_logbook,
        SUM(CASE WHEN l.status_dospem_lapangan = 'disetujui' THEN 1 ELSE 0 END) AS logbook_disetujui,
        p.nilai_disiplin_lapangan,
        p.nilai_teknis_lapangan,
        p.nilai_etika_lapangan,
        p.nilai_kerjasama_lapangan,
        p.catatan_lapangan,
        p.tgl_nilai_lapangan,
        p.nilai_akhir,
        p.nilai_huruf,
        p.status_kelulusan
    FROM bimbingan b
    JOIN users u ON b.id_mahasiswa = u.id
    LEFT JOIN mahasiswa m ON u.id = m.id
    LEFT JOIN logbook l ON u.id = l.id_mahasiswa
    LEFT JOIN penilaian_pkl p ON u.id = p.id_mahasiswa
    WHERE b.id_dospem_lapangan = ?
    GROUP BY u.id
    ORDER BY u.nama ASC
";
$stmt_list = $conn->prepare($query_mhs);
$stmt_list->bind_param("i", $id_dosen_lapangan);
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
                    <i class="fas fa-clipboard-check text-blue-600" aria-hidden="true"></i>
                    Penilaian Praktikan Lapangan
                </h2>
                <p class="text-sm mt-1" style="color: var(--dashboard-muted);">
                    Evaluasi kinerja, kedisiplinan, dan etika kerja mahasiswa bimbingan PKL di tempat magang / industri.
                </p>
            </div>
            <a href="dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>

        <!-- Info Card Bobot -->
        <div class="p-4 sm:p-5 rounded-2xl mb-6 border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="fas fa-circle-info text-lg"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold" style="color: var(--dashboard-text);">Panduan Penilaian Pembimbing Lapangan (Bobot 60% Nilai Akhir)</h4>
                    <p class="text-xs mt-1" style="color: var(--dashboard-muted);">
                        Nilai lapangan dihitung dari rata-rata 4 aspek: <strong>Kedisiplinan & Kehadiran</strong> (25%), <strong>Keterampilan Teknis & Kinerja</strong> (25%), <strong>Etika & Tanggung Jawab</strong> (25%), dan <strong>Kerjasama Tim & Komunikasi</strong> (25%). Rentang nilai adalah 0 sampai 100.
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
                            <th class="py-3 px-3 text-center">Rata-rata Nilai</th>
                            <th class="py-3 px-3 text-center">Status</th>
                            <th class="py-3 px-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--dashboard-border);">
                        <?php if ($mahasiswa_list->num_rows > 0): ?>
                            <?php $no = 1; while ($m = $mahasiswa_list->fetch_assoc()): ?>
                                <?php
                                $has_nilai = ($m['nilai_disiplin_lapangan'] !== null);
                                $rata_lapangan = $has_nilai ? hitung_rata_rata_lapangan(
                                    (float)$m['nilai_disiplin_lapangan'],
                                    (float)$m['nilai_teknis_lapangan'],
                                    (float)$m['nilai_etika_lapangan'],
                                    (float)$m['nilai_kerjasama_lapangan']
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
                                    <td class="py-3 px-3 text-center font-bold text-base">
                                        <?php if ($has_nilai): ?>
                                            <span class="text-emerald-500"><?= number_format($rata_lapangan, 1) ?></span>
                                        <?php else: ?>
                                            <span class="text-xs font-normal" style="color: var(--dashboard-muted);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <?php if ($has_nilai): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                                                <i class="fas fa-check-circle"></i> Sudah Dinilai
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                                <i class="fas fa-clock"></i> Belum Dinilai
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <button type="button" 
                                                onclick="bukaModalNilai(<?= htmlspecialchars(json_encode($m)) ?>)"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 transition shadow-sm">
                                            <i class="fas fa-pen-to-square"></i>
                                            <?= $has_nilai ? 'Edit Nilai' : 'Input Nilai' ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-sm" style="color: var(--dashboard-muted);">
                                    <i class="fas fa-user-slash text-2xl mb-2 block"></i>
                                    Belum ada mahasiswa yang ditugaskan di bawah bimbingan lapangan Anda.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal Form Penilaian -->
<div id="modalNilai" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden">
    <div class="w-full max-w-lg rounded-2xl shadow-2xl border overflow-hidden transition-all transform" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
        <div class="p-5 border-b flex items-center justify-between" style="border-color: var(--dashboard-border);">
            <div>
                <h3 class="font-bold text-lg" style="color: var(--dashboard-text);">Form Penilaian Lapangan</h3>
                <p id="modalSubTitle" class="text-xs" style="color: var(--dashboard-muted);"></p>
            </div>
            <button type="button" onclick="tutupModalNilai()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-neutral-800 transition">
                <i class="fas fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="penilaian.php" method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="simpan_nilai">
            <input type="hidden" id="modal_id_mahasiswa" name="id_mahasiswa" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- 1. Disiplin -->
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">
                        Kedisiplinan & Kehadiran (0-100)
                    </label>
                    <input type="number" step="0.1" min="0" max="100" id="input_disiplin" name="nilai_disiplin" required
                           class="w-full px-3 py-2 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold" 
                           style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);"
                           oninput="hitungRataRataLive()">
                </div>

                <!-- 2. Teknis -->
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">
                        Keterampilan & Kinerja (0-100)
                    </label>
                    <input type="number" step="0.1" min="0" max="100" id="input_teknis" name="nilai_teknis" required
                           class="w-full px-3 py-2 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold" 
                           style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);"
                           oninput="hitungRataRataLive()">
                </div>

                <!-- 3. Etika -->
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">
                        Etika & Tanggung Jawab (0-100)
                    </label>
                    <input type="number" step="0.1" min="0" max="100" id="input_etika" name="nilai_etika" required
                           class="w-full px-3 py-2 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold" 
                           style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);"
                           oninput="hitungRataRataLive()">
                </div>

                <!-- 4. Kerjasama -->
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">
                        Kerjasama & Komunikasi (0-100)
                    </label>
                    <input type="number" step="0.1" min="0" max="100" id="input_kerjasama" name="nilai_kerjasama" required
                           class="w-full px-3 py-2 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold" 
                           style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);"
                           oninput="hitungRataRataLive()">
                </div>
            </div>

            <!-- Preview Rata-rata Live -->
            <div class="p-3 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-between">
                <span class="text-xs font-semibold text-blue-600">Estimasi Rata-rata Lapangan:</span>
                <span id="previewRataLive" class="text-base font-bold text-blue-700">0.0</span>
            </div>

            <!-- Catatan / Evaluasi Lapangan -->
            <div>
                <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">
                    Catatan / Saran Evaluasi untuk Mahasiswa
                </label>
                <textarea id="input_catatan" name="catatan_lapangan" rows="3" placeholder="Tuliskan catatan apresiasi atau evaluasi kinerja..."
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
    
    document.getElementById('input_disiplin').value = data.nilai_disiplin_lapangan !== null ? data.nilai_disiplin_lapangan : '';
    document.getElementById('input_teknis').value = data.nilai_teknis_lapangan !== null ? data.nilai_teknis_lapangan : '';
    document.getElementById('input_etika').value = data.nilai_etika_lapangan !== null ? data.nilai_etika_lapangan : '';
    document.getElementById('input_kerjasama').value = data.nilai_kerjasama_lapangan !== null ? data.nilai_kerjasama_lapangan : '';
    document.getElementById('input_catatan').value = data.catatan_lapangan || '';

    hitungRataRataLive();
    document.getElementById('modalNilai').classList.remove('hidden');
}

function tutupModalNilai() {
    document.getElementById('modalNilai').classList.add('hidden');
}

function hitungRataRataLive() {
    const d = parseFloat(document.getElementById('input_disiplin').value) || 0;
    const t = parseFloat(document.getElementById('input_teknis').value) || 0;
    const e = parseFloat(document.getElementById('input_etika').value) || 0;
    const k = parseFloat(document.getElementById('input_kerjasama').value) || 0;
    const rata = ((d + t + e + k) / 4).toFixed(1);
    document.getElementById('previewRataLive').innerText = rata;
}
</script>

<?php include '../includes/footer.php'; ?>
