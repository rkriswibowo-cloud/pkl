<?php
session_start();
require_once __DIR__ . '/../includes/impersonation.php';
require_writable_session();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'dosen_pkl') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$page_title   = 'Review Dokumen PKL - Dosen Pembimbing';
$id_dosen_pkl = (int)$_SESSION['user']['id'];
$feedback     = '';
$feedback_type = 'success';

$jenis_map = [
    'laporan_akhir'       => 'Laporan Akhir PKL',
    'lembar_pengesahan'   => 'Lembar Pengesahan',
    'sertifikat_industri' => 'Sertifikat / Surat Keterangan Industri',
    'surat_tugas'         => 'Surat Tugas PKL',
];

// ---- Proses approve / revisi ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action   = $_POST['action'];
    $id_dok   = (int)$_POST['id_dokumen'];
    $catatan  = trim($_POST['catatan'] ?? '');

    // Pastikan dokumen milik mahasiswa bimbingan dosen ini
    $check = $conn->query("
        SELECT d.id FROM dokumen_pkl d
        JOIN bimbingan b ON d.id_mahasiswa = b.id_mahasiswa
        WHERE d.id = $id_dok AND b.id_dospem_pkl = $id_dosen_pkl
        LIMIT 1
    ")->fetch_assoc();

    if (!$check) {
        $feedback = 'Aksi tidak diizinkan.';
        $feedback_type = 'error';
    } elseif ($action === 'approve') {
        $conn->query("UPDATE dokumen_pkl SET status='disetujui', catatan_revisi=NULL, verified_at=NOW(), verified_by=$id_dosen_pkl WHERE id=$id_dok");
        $feedback = 'Dokumen berhasil disetujui.';
    } elseif ($action === 'revisi') {
        if (empty($catatan)) {
            $feedback = 'Catatan revisi wajib diisi.';
            $feedback_type = 'error';
        } else {
            $catatan_esc = $conn->real_escape_string($catatan);
            $conn->query("UPDATE dokumen_pkl SET status='revisi', catatan_revisi='$catatan_esc', verified_by=$id_dosen_pkl WHERE id=$id_dok");
            $feedback = 'Catatan revisi berhasil dikirim ke mahasiswa.';
        }
    }
}

// ---- Ambil semua mahasiswa bimbingan ----
$mahasiswa_list = $conn->query("
    SELECT u.id, u.nama, u.username, m.nim,
           COALESCE(NULLIF(m.prodi,''), NULLIF(u.prodi,''), '-') AS prodi
    FROM bimbingan b
    JOIN users u ON b.id_mahasiswa = u.id
    LEFT JOIN mahasiswa m ON u.id = m.id
    WHERE b.id_dospem_pkl = $id_dosen_pkl
    ORDER BY u.nama ASC
")->fetch_all(MYSQLI_ASSOC);

// ---- Filter mahasiswa yang dipilih ----
$selected_mhs = isset($_GET['mhs']) ? (int)$_GET['mhs'] : ($mahasiswa_list[0]['id'] ?? 0);

// ---- Ambil dokumen mahasiswa terpilih ----
$dokumen_list = [];
if ($selected_mhs) {
    $dokumen_list = $conn->query("
        SELECT d.*, u.nama AS verifikator_nama
        FROM dokumen_pkl d
        LEFT JOIN users u ON d.verified_by = u.id
        WHERE d.id_mahasiswa = $selected_mhs
        ORDER BY d.uploaded_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

$status_cfg = [
    'pending'   => ['bg-yellow-100 text-yellow-800 border-yellow-200', 'fa-clock',        'Menunggu Verifikasi'],
    'revisi'    => ['bg-red-100 text-red-800 border-red-200',           'fa-rotate-left',  'Perlu Revisi'],
    'disetujui' => ['bg-green-100 text-green-800 border-green-200',     'fa-circle-check', 'Disetujui'],
];

include '../includes/header.php';
?>

<div class="min-w-0">
    <div class="dashboard-page w-full max-w-full">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                    <i class="fas fa-folder-open text-indigo-600" aria-hidden="true"></i>
                    Review Dokumen PKL
                </h2>
                <p class="text-sm mt-1" style="color: var(--dashboard-muted);">
                    Periksa, setujui, atau minta revisi dokumen laporan akhir mahasiswa bimbingan Anda.
                </p>
            </div>
        </div>

        <?php if ($feedback): ?>
            <div class="rounded-lg p-4 mb-5 flex items-start gap-3 <?= $feedback_type === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-700' ?>">
                <i class="fas <?= $feedback_type === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' ?> mt-0.5 shrink-0"></i>
                <span><?= htmlspecialchars($feedback) ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($mahasiswa_list)): ?>
            <div class="dashboard-card p-10 text-center">
                <i class="fas fa-users-slash text-5xl mb-3" style="color:var(--dashboard-muted);"></i>
                <p class="font-medium text-lg">Belum ada mahasiswa bimbingan.</p>
                <p class="text-sm mt-1" style="color:var(--dashboard-muted);">Hubungi admin untuk menetapkan mahasiswa ke Anda.</p>
            </div>
        <?php else: ?>
        <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">

            <!-- Daftar Mahasiswa -->
            <div class="xl:col-span-1">
                <div class="dashboard-card p-4">
                    <h3 class="font-semibold text-sm mb-3 flex items-center gap-2">
                        <i class="fas fa-users text-indigo-500"></i> Mahasiswa Bimbingan
                    </h3>
                    <div class="flex flex-col gap-1">
                        <?php foreach ($mahasiswa_list as $mhs):
                            // Hitung dokumen pending milik mhs ini
                            $cnt = $conn->query("SELECT COUNT(*) AS n FROM dokumen_pkl WHERE id_mahasiswa={$mhs['id']} AND status='pending'")->fetch_assoc()['n'];
                            $is_active = $mhs['id'] == $selected_mhs;
                        ?>
                        <a href="?mhs=<?= $mhs['id'] ?>"
                           class="flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm transition <?= $is_active ? 'bg-indigo-600 text-white' : 'hover:bg-gray-100' ?>">
                            <i class="fas fa-user-graduate shrink-0"></i>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate"><?= htmlspecialchars($mhs['nama']) ?></p>
                                <p class="text-xs opacity-70"><?= htmlspecialchars($mhs['nim'] ?? $mhs['username']) ?></p>
                            </div>
                            <?php if ($cnt > 0): ?>
                                <span class="shrink-0 text-xs font-bold px-1.5 py-0.5 rounded-full <?= $is_active ? 'bg-white text-indigo-700' : 'bg-yellow-500 text-white' ?>"><?= $cnt ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Panel Dokumen -->
            <div class="xl:col-span-3">
                <?php
                // Info mahasiswa terpilih
                $sel_info = array_values(array_filter($mahasiswa_list, fn($m) => $m['id'] == $selected_mhs))[0] ?? null;
                if ($sel_info):
                ?>
                <div class="dashboard-card p-4 mb-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?= htmlspecialchars($sel_info['nama']) ?></p>
                        <p class="text-xs" style="color:var(--dashboard-muted);">
                            NIM: <?= htmlspecialchars($sel_info['nim'] ?? '-') ?> &nbsp;|&nbsp;
                            <?= htmlspecialchars($sel_info['prodi']) ?>
                        </p>
                    </div>
                </div>

                <?php if (empty($dokumen_list)): ?>
                    <div class="dashboard-card p-10 text-center">
                        <i class="fas fa-inbox text-5xl mb-3 opacity-20"></i>
                        <p class="font-medium">Mahasiswa ini belum mengunggah dokumen.</p>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col gap-4">
                        <?php foreach ($dokumen_list as $doc):
                            [$badge_cls, $badge_icon, $badge_text] = $status_cfg[$doc['status']];
                            $ext = pathinfo($doc['file_path'], PATHINFO_EXTENSION);
                            $is_image = in_array(strtolower($ext), ['jpg','jpeg','png']);
                            $file_url = '../uploads/dokumen/' . basename($doc['file_path']);
                        ?>
                        <div class="dashboard-card p-4">
                            <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                                <!-- Preview icon / thumbnail -->
                                <div class="shrink-0">
                                    <?php if ($is_image): ?>
                                        <a href="<?= htmlspecialchars($file_url) ?>" target="_blank">
                                            <img src="<?= htmlspecialchars($file_url) ?>" alt="preview"
                                                 class="w-20 h-20 object-cover rounded-lg border">
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= htmlspecialchars($file_url) ?>" target="_blank"
                                           class="w-20 h-20 rounded-lg border bg-red-50 flex flex-col items-center justify-center gap-1 hover:bg-red-100 transition">
                                            <i class="fas fa-file-pdf text-2xl text-red-500"></i>
                                            <span class="text-xs text-red-600 font-medium">PDF</span>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-start gap-2 mb-1">
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 border border-blue-200">
                                            <?= htmlspecialchars($jenis_map[$doc['jenis_dokumen']] ?? $doc['jenis_dokumen']) ?>
                                        </span>
                                        <span class="text-xs px-2 py-0.5 rounded-full border <?= $badge_cls ?>">
                                            <i class="fas <?= $badge_icon ?> mr-1"></i><?= $badge_text ?>
                                        </span>
                                    </div>
                                    <p class="font-semibold truncate"><?= htmlspecialchars($doc['judul']) ?></p>
                                    <p class="text-xs mt-0.5" style="color:var(--dashboard-muted);">
                                        Diunggah <?= date('d M Y H:i', strtotime($doc['uploaded_at'])) ?>
                                        <?php if ($doc['verified_at']): ?>
                                            &nbsp;&bull;&nbsp; Diverifikasi <?= date('d M Y', strtotime($doc['verified_at'])) ?>
                                            <?php if ($doc['verifikator_nama']): ?>
                                                oleh <?= htmlspecialchars($doc['verifikator_nama']) ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($doc['catatan_revisi']): ?>
                                        <div class="mt-2 text-xs bg-red-50 border border-red-200 text-red-700 rounded px-2 py-1.5">
                                            <i class="fas fa-comment-dots mr-1"></i>
                                            Catatan: <?= htmlspecialchars($doc['catatan_revisi']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Actions -->
                                <?php if ($doc['status'] !== 'disetujui'): ?>
                                <div class="flex flex-col gap-2 shrink-0 w-full sm:w-auto">
                                    <!-- Approve -->
                                    <form method="POST"
                                          onsubmit="return confirm('Setujui dokumen ini?')">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="id_dokumen" value="<?= $doc['id'] ?>">
                                        <button type="submit"
                                                class="w-full sm:w-auto btn-primary text-xs py-2 px-3 flex items-center gap-1.5">
                                            <i class="fas fa-circle-check"></i> Setujui
                                        </button>
                                    </form>
                                    <!-- Revisi -->
                                    <button type="button" onclick="toggleRevisiForm(<?= $doc['id'] ?>)"
                                            class="w-full sm:w-auto text-xs py-2 px-3 rounded-lg border border-amber-500 text-amber-600 hover:bg-amber-50 flex items-center gap-1.5 transition">
                                        <i class="fas fa-rotate-left"></i> Minta Revisi
                                    </button>
                                </div>
                                <?php else: ?>
                                <div class="shrink-0">
                                    <span class="inline-flex items-center gap-1.5 text-xs text-green-700 bg-green-50 border border-green-200 px-3 py-2 rounded-lg">
                                        <i class="fas fa-circle-check"></i> Sudah Disetujui
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Form Revisi (hidden) -->
                            <?php if ($doc['status'] !== 'disetujui'): ?>
                            <div id="revisi_form_<?= $doc['id'] ?>" class="hidden mt-3 pt-3 border-t" style="border-color:var(--dashboard-border);">
                                <form method="POST">
                                    <input type="hidden" name="action" value="revisi">
                                    <input type="hidden" name="id_dokumen" value="<?= $doc['id'] ?>">
                                    <label class="block text-xs font-medium mb-1">Catatan Revisi untuk Mahasiswa</label>
                                    <textarea name="catatan" rows="3" required
                                              placeholder="Tuliskan catatan revisi yang jelas agar mahasiswa dapat memperbaiki dokumennya..."
                                              class="form-input w-full text-sm"><?= htmlspecialchars($doc['catatan_revisi'] ?? '') ?></textarea>
                                    <div class="flex gap-2 mt-2">
                                        <button type="submit"
                                                class="text-xs py-1.5 px-3 rounded-lg bg-amber-500 text-white hover:bg-amber-600 flex items-center gap-1 transition">
                                            <i class="fas fa-paper-plane"></i> Kirim Catatan
                                        </button>
                                        <button type="button" onclick="toggleRevisiForm(<?= $doc['id'] ?>)"
                                                class="text-xs py-1.5 px-3 rounded-lg border hover:bg-gray-100 transition">
                                            Batal
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleRevisiForm(id) {
    const el = document.getElementById('revisi_form_' + id);
    if (el) el.classList.toggle('hidden');
}
</script>

<?php include '../includes/footer.php'; ?>

