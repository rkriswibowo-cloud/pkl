<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mahasiswa') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$page_title   = 'Unggah Dokumen PKL - Mahasiswa';
$id_mahasiswa = (int)$_SESSION['user']['id'];
$feedback     = '';
$feedback_type = 'success';

// ---- Jenis dokumen ----
$jenis_map = [
    'laporan_akhir'       => 'Laporan Akhir PKL',
    'lembar_pengesahan'   => 'Lembar Pengesahan',
    'sertifikat_industri' => 'Sertifikat / Surat Keterangan Industri',
    'surat_tugas'         => 'Surat Tugas PKL',
];

// ---- Hapus dokumen ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hapus') {
    $id_dok = (int)$_POST['id_dokumen'];
    $row = $conn->query("SELECT file_path, status FROM dokumen_pkl WHERE id = $id_dok AND id_mahasiswa = $id_mahasiswa")->fetch_assoc();
    if ($row) {
        if ($row['status'] === 'disetujui') {
            $feedback = 'Dokumen yang sudah disetujui tidak dapat dihapus.';
            $feedback_type = 'error';
        } else {
            $full = __DIR__ . '/../uploads/dokumen/' . basename($row['file_path']);
            if (file_exists($full)) unlink($full);
            $conn->query("DELETE FROM dokumen_pkl WHERE id = $id_dok AND id_mahasiswa = $id_mahasiswa");
            $feedback = 'Dokumen berhasil dihapus.';
        }
    }
}

// ---- Upload dokumen ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $jenis  = $_POST['jenis_dokumen'] ?? '';
    $judul  = trim($_POST['judul'] ?? '');
    $file   = $_FILES['file_dokumen'] ?? null;

    if (!array_key_exists($jenis, $jenis_map)) {
        $feedback = 'Jenis dokumen tidak valid.';
        $feedback_type = 'error';
    } elseif (empty($judul)) {
        $feedback = 'Judul dokumen tidak boleh kosong.';
        $feedback_type = 'error';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $feedback = 'Gagal mengunggah file. Pastikan file dipilih dan ukurannya tidak melebihi 20MB.';
        $feedback_type = 'error';
    } else {
        $allowed_types = ['application/pdf','image/jpeg','image/png','image/jpg'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $ext_map = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/jpg'=>'jpg','image/png'=>'png'];
        $ext = $ext_map[$mime] ?? null;

        if (!in_array($mime, $allowed_types)) {
            $feedback = 'Format file tidak didukung. Hanya PDF, JPG, dan PNG diizinkan.';
            $feedback_type = 'error';
        } elseif ($file['size'] > 20 * 1024 * 1024) {
            $feedback = 'Ukuran file melebihi batas maksimal 20MB.';
            $feedback_type = 'error';
        } else {
            // Cek jika sudah ada dokumen jenis ini (replace / tambah)
            $existing = $conn->query("SELECT id, file_path FROM dokumen_pkl WHERE id_mahasiswa = $id_mahasiswa AND jenis_dokumen = '$jenis' AND status != 'disetujui' LIMIT 1")->fetch_assoc();

            $filename = 'dok_' . $id_mahasiswa . '_' . $jenis . '_' . time() . '.' . $ext;
            $dest     = __DIR__ . '/../uploads/dokumen/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $judul_esc = $conn->real_escape_string($judul);
                if ($existing) {
                    // hapus file lama
                    $old = __DIR__ . '/../uploads/dokumen/' . basename($existing['file_path']);
                    if (file_exists($old)) unlink($old);
                    $conn->query("UPDATE dokumen_pkl SET judul='$judul_esc', file_path='$filename', status='pending', catatan_revisi=NULL, uploaded_at=NOW() WHERE id={$existing['id']}");
                    $feedback = 'Dokumen berhasil diperbarui dan menunggu verifikasi.';
                } else {
                    $conn->query("INSERT INTO dokumen_pkl (id_mahasiswa, jenis_dokumen, judul, file_path) VALUES ($id_mahasiswa, '$jenis', '$judul_esc', '$filename')");
                    $feedback = 'Dokumen berhasil diunggah dan menunggu verifikasi.';
                }
            } else {
                $feedback = 'Gagal menyimpan file ke server. Hubungi administrator.';
                $feedback_type = 'error';
            }
        }
    }
}

// ---- Ambil semua dokumen milik mahasiswa ----
$dokumen_list = $conn->query("
    SELECT d.*, u.nama AS verifikator_nama
    FROM dokumen_pkl d
    LEFT JOIN users u ON d.verified_by = u.id
    WHERE d.id_mahasiswa = $id_mahasiswa
    ORDER BY d.uploaded_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Kelompokkan per jenis
$dokumen_by_jenis = [];
foreach ($dokumen_list as $d) {
    $dokumen_by_jenis[$d['jenis_dokumen']][] = $d;
}

include '../includes/header.php';
?>

<div class="min-w-0">
    <div class="dashboard-page w-full max-w-full">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                    <i class="fas fa-folder-open text-blue-600" aria-hidden="true"></i>
                    Dokumen &amp; Laporan PKL
                </h2>
                <p class="text-sm mt-1" style="color: var(--dashboard-muted);">
                    Unggah laporan akhir, sertifikat industri, dan dokumen pendukung PKL Anda.
                </p>
            </div>
        </div>

        <?php if ($feedback): ?>
            <div class="rounded-lg p-4 mb-5 flex items-start gap-3 <?= $feedback_type === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-700' ?>">
                <i class="fas <?= $feedback_type === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' ?> mt-0.5 shrink-0"></i>
                <span><?= htmlspecialchars($feedback) ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            <!-- Form Upload -->
            <div class="xl:col-span-1">
                <div class="dashboard-card p-5">
                    <h3 class="font-semibold text-lg mb-4 flex items-center gap-2">
                        <i class="fas fa-cloud-arrow-up text-blue-500"></i>
                        Unggah Dokumen Baru
                    </h3>
                    <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-4">
                        <input type="hidden" name="action" value="upload">

                        <div>
                            <label class="block text-sm font-medium mb-1" for="jenis_dokumen">
                                Jenis Dokumen <span class="text-red-500">*</span>
                            </label>
                            <select name="jenis_dokumen" id="jenis_dokumen" required
                                    class="form-select w-full" onchange="updateJudulHint(this.value)">
                                <option value="">— Pilih Jenis —</option>
                                <?php foreach ($jenis_map as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" for="judul">
                                Judul / Keterangan <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="judul" id="judul" required maxlength="255"
                                   placeholder="Contoh: Laporan PKL PT. Maju Jaya"
                                   class="form-input w-full">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1" for="file_dokumen">
                                File (PDF / JPG / PNG, maks. 20 MB) <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="file_dokumen" id="file_dokumen" required
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   class="form-input w-full text-sm py-2"
                                   onchange="previewFileInfo(this)">
                            <p id="file_info" class="text-xs mt-1" style="color:var(--dashboard-muted);"></p>
                        </div>

                        <div id="hint_box" class="hidden rounded-lg p-3 bg-blue-50 border border-blue-200 text-blue-700 text-xs">
                            <i class="fas fa-circle-info mr-1"></i>
                            <span id="hint_text"></span>
                        </div>

                        <button type="submit"
                                class="btn-primary w-full flex items-center justify-center gap-2 py-2.5">
                            <i class="fas fa-cloud-arrow-up"></i>
                            Unggah Dokumen
                        </button>
                    </form>
                </div>

                <!-- Panduan -->
                <div class="dashboard-card p-5 mt-4">
                    <h4 class="font-semibold mb-3 flex items-center gap-2 text-sm">
                        <i class="fas fa-circle-info text-amber-500"></i> Panduan Unggah
                    </h4>
                    <ul class="text-xs space-y-2" style="color:var(--dashboard-muted);">
                        <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-0.5"></i> Format: PDF (utama), JPG/PNG (sertifikat)</li>
                        <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-0.5"></i> Ukuran maks. 20 MB per file</li>
                        <li class="flex items-start gap-2"><i class="fas fa-check text-green-500 mt-0.5"></i> Dokumen yang belum disetujui dapat diganti</li>
                        <li class="flex items-start gap-2"><i class="fas fa-ban text-red-500 mt-0.5"></i> Dokumen yang sudah <strong>Disetujui</strong> tidak dapat dihapus</li>
                    </ul>
                </div>
            </div>

            <!-- Daftar Dokumen -->
            <div class="xl:col-span-2 flex flex-col gap-5">
                <?php
                $status_cfg = [
                    'pending'   => ['bg-yellow-100 text-yellow-800 border-yellow-200', 'fa-clock',        'Menunggu Verifikasi'],
                    'revisi'    => ['bg-red-100 text-red-800 border-red-200',           'fa-rotate-left',  'Perlu Revisi'],
                    'disetujui' => ['bg-green-100 text-green-800 border-green-200',     'fa-circle-check', 'Disetujui'],
                ];
                foreach ($jenis_map as $jenis_key => $jenis_label):
                    $docs = $dokumen_by_jenis[$jenis_key] ?? [];
                    $has_approved = count(array_filter($docs, fn($d) => $d['status'] === 'disetujui')) > 0;
                ?>
                <div class="dashboard-card p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold flex items-center gap-2">
                            <i class="fas <?= match($jenis_key) {
                                'laporan_akhir'       => 'fa-file-pdf text-red-500',
                                'lembar_pengesahan'   => 'fa-file-signature text-purple-500',
                                'sertifikat_industri' => 'fa-award text-amber-500',
                                default               => 'fa-file-alt text-blue-500'
                            } ?>"></i>
                            <?= htmlspecialchars($jenis_label) ?>
                        </h4>
                        <?php if ($has_approved): ?>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 border border-green-200">
                                <i class="fas fa-circle-check mr-1"></i>Lengkap
                            </span>
                        <?php else: ?>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 border border-gray-200">
                                Belum ada / belum disetujui
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($docs)): ?>
                        <p class="text-sm text-center py-4" style="color:var(--dashboard-muted);">
                            <i class="fas fa-inbox text-2xl mb-2 block opacity-30"></i>
                            Belum ada dokumen diunggah.
                        </p>
                    <?php else: ?>
                        <div class="flex flex-col gap-3">
                            <?php foreach ($docs as $doc):
                                [$badge_cls, $badge_icon, $badge_text] = $status_cfg[$doc['status']];
                                $is_image = in_array(pathinfo($doc['file_path'], PATHINFO_EXTENSION), ['jpg','jpeg','png']);
                                $file_url = '../uploads/dokumen/' . basename($doc['file_path']);
                            ?>
                            <div class="rounded-lg border p-3 flex flex-col sm:flex-row sm:items-center gap-3"
                                 style="background:var(--dashboard-bg-soft, #f8fafc); border-color:var(--dashboard-border);">
                                <!-- Icon -->
                                <div class="shrink-0 w-10 h-10 rounded-lg flex items-center justify-center
                                            <?= $is_image ? 'bg-amber-100 text-amber-600' : 'bg-red-100 text-red-600' ?>">
                                    <i class="fas <?= $is_image ? 'fa-image' : 'fa-file-pdf' ?> text-lg"></i>
                                </div>
                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-sm truncate"><?= htmlspecialchars($doc['judul']) ?></p>
                                    <p class="text-xs mt-0.5" style="color:var(--dashboard-muted);">
                                        Diunggah <?= date('d M Y H:i', strtotime($doc['uploaded_at'])) ?>
                                    </p>
                                    <?php if ($doc['catatan_revisi']): ?>
                                        <div class="mt-1 text-xs bg-red-50 border border-red-200 text-red-700 rounded px-2 py-1">
                                            <i class="fas fa-comment-dots mr-1"></i>
                                            <?= htmlspecialchars($doc['catatan_revisi']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <!-- Badge & Actions -->
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-xs px-2 py-0.5 rounded-full border <?= $badge_cls ?>">
                                        <i class="fas <?= $badge_icon ?> mr-1"></i><?= $badge_text ?>
                                    </span>
                                    <a href="<?= htmlspecialchars($file_url) ?>" target="_blank"
                                       class="text-blue-600 hover:text-blue-800 p-1.5 rounded hover:bg-blue-50 transition"
                                       title="Lihat / Unduh">
                                        <i class="fas fa-eye text-sm"></i>
                                    </a>
                                    <?php if ($doc['status'] !== 'disetujui'): ?>
                                    <form method="POST" class="inline"
                                          onsubmit="return confirm('Hapus dokumen ini?')">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id_dokumen" value="<?= $doc['id'] ?>">
                                        <button type="submit"
                                                class="text-red-500 hover:text-red-700 p-1.5 rounded hover:bg-red-50 transition"
                                                title="Hapus Dokumen">
                                            <i class="fas fa-trash-can text-sm"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div><!-- end col 2 -->
        </div><!-- end grid -->
    </div>
</div>

<script>
const hints = {
    laporan_akhir: 'Unggah draf atau laporan akhir PKL dalam format PDF. Laporan akan direview oleh Dosen Pembimbing.',
    lembar_pengesahan: 'Lembar pengesahan yang telah ditandatangani (scan/foto jelas). Format PDF atau gambar.',
    sertifikat_industri: 'Sertifikat kelulusan/selesai PKL dari perusahaan/instansi. Bisa berupa PDF atau foto.',
    surat_tugas: 'Surat tugas PKL dari kampus atau instansi terkait.',
};
function updateJudulHint(val) {
    const box = document.getElementById('hint_box');
    const txt = document.getElementById('hint_text');
    if (hints[val]) { txt.textContent = hints[val]; box.classList.remove('hidden'); }
    else box.classList.add('hidden');
}
function previewFileInfo(input) {
    const info = document.getElementById('file_info');
    if (input.files && input.files[0]) {
        const f = input.files[0];
        const mb = (f.size / 1024 / 1024).toFixed(2);
        info.textContent = `${f.name} (${mb} MB)`;
        info.style.color = f.size > 20*1024*1024 ? '#dc2626' : 'var(--dashboard-muted)';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
