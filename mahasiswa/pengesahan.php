<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mahasiswa') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/prodi_helper.php';

$page_title = 'Lembar Pengesahan PKL - Mahasiswa';
$id_mahasiswa = (int)$_SESSION['user']['id'];
$feedback = '';

// Ambil info prodi mahasiswa
$user_row = $conn->query("SELECT u.prodi, m.nim FROM users u LEFT JOIN mahasiswa m ON u.id = m.id WHERE u.id = '$id_mahasiswa'")->fetch_assoc();
$current_prodi = $user_row['prodi'] ?? '';
$kaprodi_info = get_kaprodi_by_prodi($conn, $current_prodi);
$list_prodi = get_all_prodi($conn, true);

// Ambil info bimbingan dosen
$bimbingan = $conn->query("
    SELECT 
        pkl.id AS id_pkl, pkl.nama AS nama_dospem_pkl,
        lapangan.id AS id_lap, lapangan.nama AS nama_dospem_lapangan
    FROM bimbingan b
    LEFT JOIN users pkl ON b.id_dospem_pkl = pkl.id
    LEFT JOIN users lapangan ON b.id_dospem_lapangan = lapangan.id
    WHERE b.id_mahasiswa = '$id_mahasiswa'
")->fetch_assoc();

// Handle Submit / Update Pengajuan Pengesahan
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajukan_pengesahan') {
    $judul      = trim($_POST['judul_laporan'] ?? '');
    $instansi   = trim($_POST['nama_instansi'] ?? '');
    $alamat     = trim($_POST['alamat_instansi'] ?? '');
    $prodi_post = trim($_POST['prodi'] ?? '');
    $tgl_mulai  = !empty($_POST['tgl_mulai']) ? $_POST['tgl_mulai'] : null;
    $tgl_selesai= !empty($_POST['tgl_selesai']) ? $_POST['tgl_selesai'] : null;

    if (!empty($prodi_post)) {
        $conn->query("UPDATE users SET prodi = '" . $conn->real_escape_string($prodi_post) . "' WHERE id = '$id_mahasiswa'");
        $conn->query("INSERT INTO mahasiswa (id, prodi) VALUES ('$id_mahasiswa', '" . $conn->real_escape_string($prodi_post) . "') ON DUPLICATE KEY UPDATE prodi = VALUES(prodi)");
        $_SESSION['user']['prodi'] = $prodi_post;
        $current_prodi = $prodi_post;
        $kaprodi_info = get_kaprodi_by_prodi($conn, $current_prodi);
    }

    if (empty($judul) || empty($instansi)) {
        $feedback = "<script>Swal.fire({icon:'warning', title:'Mohon Lengkapi', text:'Judul laporan dan nama instansi PKL wajib diisi.'});</script>";
    } else {
        $file_path = null;
        if (isset($_FILES['file_laporan']) && $_FILES['file_laporan']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file_laporan'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $feedback = "<script>Swal.fire({icon:'error', title:'Format Salah', text:'File laporan harus berformat PDF.'});</script>";
            } elseif ($file['size'] > 15 * 1024 * 1024) {
                $feedback = "<script>Swal.fire({icon:'error', title:'Ukuran Terlalu Besar', text:'Ukuran file maksimal 15MB.'});</script>";
            } else {
                $upload_dir = __DIR__ . '/../assets/uploads/laporan/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0775, true);
                }
                $filename = 'laporan_' . $id_mahasiswa . '_' . time() . '.pdf';
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                    $file_path = 'assets/uploads/laporan/' . $filename;
                }
            }
        }

        if (empty($feedback)) {
            // Cek apakah sudah ada entri
            $cek = $conn->query("SELECT id, file_laporan FROM pengesahan_pkl WHERE id_mahasiswa = '$id_mahasiswa'");
            if ($cek->num_rows > 0) {
                $existing = $cek->fetch_assoc();
                $final_file = $file_path ?: $existing['file_laporan'];
                $stmt = $conn->prepare("UPDATE pengesahan_pkl SET 
                    judul_laporan = ?, nama_instansi = ?, alamat_instansi = ?, 
                    tgl_mulai = ?, tgl_selesai = ?, file_laporan = ?,
                    status_final = 'menunggu_verifikasi'
                    WHERE id_mahasiswa = ?");
                $stmt->bind_param("ssssssi", $judul, $instansi, $alamat, $tgl_mulai, $tgl_selesai, $final_file, $id_mahasiswa);
            } else {
                $stmt = $conn->prepare("INSERT INTO pengesahan_pkl 
                    (id_mahasiswa, judul_laporan, nama_instansi, alamat_instansi, tgl_mulai, tgl_selesai, file_laporan, status_final)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'menunggu_verifikasi')");
                $stmt->bind_param("issssss", $id_mahasiswa, $judul, $instansi, $alamat, $tgl_mulai, $tgl_selesai, $file_path);
            }

            if ($stmt->execute()) {
                $feedback = "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil Diajukan!',
                        text: 'Data lembar pengesahan berhasil disimpan untuk diverifikasi.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'pengesahan.php';
                    });
                </script>";
            } else {
                $feedback = "<script>Swal.fire({icon:'error', title:'Gagal', text:'Terjadi kesalahan database.'});</script>";
            }
        }
    }
}

// Ambil data pengesahan saat ini
$pengesahan = $conn->query("SELECT * FROM pengesahan_pkl WHERE id_mahasiswa = '$id_mahasiswa'")->fetch_assoc();

include '../includes/header.php';
?>

<?= $feedback ?>

<div class="min-w-0">
    <div class="dashboard-page w-full max-w-full">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                    <i class="fas fa-file-signature text-blue-600" aria-hidden="true"></i>
                    Lembar Pengesahan Laporan PKL
                </h2>
                <p class="text-sm mt-1" style="color: var(--dashboard-muted);">
                    Pengajuan verifikasi berkas dan tanda tangan lembar pengesahan oleh 3 pihak: <strong>Dospem PKL</strong>, <strong>Pembimbing Lapangan</strong>, dan <strong>Kaprodi (Mengetahui)</strong>.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($pengesahan): ?>
                    <a href="cetak_pengesahan.php" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow transition">
                        <i class="fas fa-print"></i> Cetak Lembar Pengesahan
                    </a>
                <?php endif; ?>
                <a href="dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- 3 Kartu Status Verifikasi Tanda Tangan -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
            <!-- 1. Dosen Pembimbing PKL -->
            <div class="p-5 rounded-2xl border flex flex-col justify-between" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-blue-500/10 text-blue-500 border border-blue-500/20">
                            <i class="fas fa-graduation-cap"></i> Menyetujui
                        </span>
                        <?php if (($pengesahan['status_dospem_pkl'] ?? '') === 'disetujui'): ?>
                            <span class="text-xs font-bold text-emerald-500 flex items-center gap-1">
                                <i class="fas fa-circle-check"></i> Disetujui
                            </span>
                        <?php elseif (($pengesahan['status_dospem_pkl'] ?? '') === 'ditolak'): ?>
                            <span class="text-xs font-bold text-red-500 flex items-center gap-1">
                                <i class="fas fa-circle-xmark"></i> Perlu Revisi
                            </span>
                        <?php else: ?>
                            <span class="text-xs font-semibold text-amber-500 flex items-center gap-1">
                                <i class="fas fa-clock"></i> Menunggu
                            </span>
                        <?php endif; ?>
                    </div>
                    <h4 class="text-sm font-bold" style="color: var(--dashboard-text);">Dosen Pembimbing PKL (Kampus)</h4>
                    <p class="text-xs mt-1" style="color: var(--dashboard-muted);">
                        <?= htmlspecialchars($bimbingan['nama_dospem_pkl'] ?? 'Belum ditentukan') ?>
                    </p>
                </div>
                <div class="pt-4 mt-3 border-t text-xs" style="border-color: var(--dashboard-border);">
                    <?php if (!empty($pengesahan['ttd_dospem_pkl'])): ?>
                        <div class="h-12 flex items-center">
                            <img src="<?= $base_path . htmlspecialchars($pengesahan['ttd_dospem_pkl']) ?>" alt="TTD Dospem PKL" class="max-h-full max-w-[120px] object-contain">
                        </div>
                        <span class="text-[10px] text-emerald-600 block mt-1 font-semibold">Telah ditandatangani secara digital</span>
                    <?php else: ?>
                        <span class="text-[11px] text-amber-600 font-medium">Menunggu tanda tangan digital dospem</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. Pembimbing Lapangan -->
            <div class="p-5 rounded-2xl border flex flex-col justify-between" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-amber-500/10 text-amber-500 border border-amber-500/20">
                            <i class="fas fa-building"></i> Menyetujui
                        </span>
                        <?php if (($pengesahan['status_dospem_lapangan'] ?? '') === 'disetujui'): ?>
                            <span class="text-xs font-bold text-emerald-500 flex items-center gap-1">
                                <i class="fas fa-circle-check"></i> Disetujui
                            </span>
                        <?php elseif (($pengesahan['status_dospem_lapangan'] ?? '') === 'ditolak'): ?>
                            <span class="text-xs font-bold text-red-500 flex items-center gap-1">
                                <i class="fas fa-circle-xmark"></i> Perlu Revisi
                            </span>
                        <?php else: ?>
                            <span class="text-xs font-semibold text-amber-500 flex items-center gap-1">
                                <i class="fas fa-clock"></i> Menunggu
                            </span>
                        <?php endif; ?>
                    </div>
                    <h4 class="text-sm font-bold" style="color: var(--dashboard-text);">Pembimbing Lapangan (Industri)</h4>
                    <p class="text-xs mt-1" style="color: var(--dashboard-muted);">
                        <?= htmlspecialchars($bimbingan['nama_dospem_lapangan'] ?? 'Belum ditentukan') ?>
                    </p>
                </div>
                <div class="pt-4 mt-3 border-t text-xs" style="border-color: var(--dashboard-border);">
                    <?php if (!empty($pengesahan['ttd_dospem_lapangan'])): ?>
                        <div class="h-12 flex items-center">
                            <img src="<?= $base_path . htmlspecialchars($pengesahan['ttd_dospem_lapangan']) ?>" alt="TTD Pembimbing Lapangan" class="max-h-full max-w-[120px] object-contain">
                        </div>
                        <span class="text-[10px] text-emerald-600 block mt-1 font-semibold">Telah ditandatangani secara digital</span>
                    <?php else: ?>
                        <span class="text-[11px] text-amber-600 font-medium">Menunggu tanda tangan digital pembimbing</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3. Ketua Program Studi (Kaprodi) -->
            <div class="p-5 rounded-2xl border flex flex-col justify-between" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-purple-500/10 text-purple-500 border border-purple-500/20">
                            <i class="fas fa-stamp"></i> Mengetahui
                        </span>
                        <?php if (($pengesahan['status_kaprodi'] ?? '') === 'disetujui'): ?>
                            <span class="text-xs font-bold text-emerald-500 flex items-center gap-1">
                                <i class="fas fa-circle-check"></i> Disahkan
                            </span>
                        <?php elseif (($pengesahan['status_kaprodi'] ?? '') === 'ditolak'): ?>
                            <span class="text-xs font-bold text-red-500 flex items-center gap-1">
                                <i class="fas fa-circle-xmark"></i> Ditolak
                            </span>
                        <?php else: ?>
                            <span class="text-xs font-semibold text-amber-500 flex items-center gap-1">
                                <i class="fas fa-clock"></i> Menunggu
                            </span>
                        <?php endif; ?>
                    </div>
                    <h4 class="text-sm font-bold" style="color: var(--dashboard-text);">
                        Kaprodi <?= htmlspecialchars($kaprodi_info['prodi_nama']) ?>
                    </h4>
                    <p class="text-xs mt-1" style="color: var(--dashboard-muted);">
                        <?= htmlspecialchars(($pengesahan['nama_kaprodi'] ?? '') ?: $kaprodi_info['nama_kaprodi']) ?>
                    </p>
                    <div class="text-[11px]" style="color: var(--dashboard-muted);">
                        NIDN: <?= htmlspecialchars(($pengesahan['nip_kaprodi'] ?? '') ?: $kaprodi_info['nidn_kaprodi']) ?>
                    </div>
                </div>
                <div class="pt-4 mt-3 border-t text-xs" style="border-color: var(--dashboard-border);">
                    <?php 
                    $kaprodi_ttd_show = !empty($pengesahan['ttd_kaprodi']) ? $pengesahan['ttd_kaprodi'] : $kaprodi_info['ttd_kaprodi'];
                    if (!empty($kaprodi_ttd_show)): 
                    ?>
                        <div class="h-12 flex items-center">
                            <img src="<?= $base_path . htmlspecialchars($kaprodi_ttd_show) ?>" alt="TTD Kaprodi" class="max-h-full max-w-[120px] object-contain">
                        </div>
                        <span class="text-[10px] text-emerald-600 block mt-1 font-semibold">Tanda tangan digital Kaprodi siap</span>
                    <?php else: ?>
                        <span class="text-[11px] text-amber-600 font-medium">Mengetahui & pengesahan akhir oleh Kaprodi</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Form Input & Detail Laporan PKL -->
        <div class="dashboard-panel p-6 sm:p-8 rounded-2xl border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
            <div class="flex items-center gap-3 mb-6 pb-4 border-b" style="border-color: var(--dashboard-border);">
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center">
                    <i class="fas fa-pen-fancy text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold" style="color: var(--dashboard-text);">Formulir Lembar Pengesahan Laporan PKL</h3>
                    <p class="text-xs" style="color: var(--dashboard-muted);">Lengkapi data judul laporan dan instansi tempat pelaksanaan PKL untuk dicantumkan pada lembar pengesahan resmi.</p>
                </div>
            </div>

            <form action="pengesahan.php" method="POST" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="action" value="ajukan_pengesahan">

                <!-- Pilihan Program Studi (Master OBE) -->
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--dashboard-text);">
                        Program Studi <span class="text-red-500">*</span>
                    </label>
                    <select name="prodi" required class="w-full px-4 py-2.5 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                        <option value="">-- Pilih Program Studi --</option>
                        <?php foreach ($list_prodi as $pr): ?>
                            <option value="<?= htmlspecialchars($pr['nama']) ?>" <?= ($current_prodi === $pr['nama'] || $current_prodi === $pr['kode']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pr['jenjang'] . ' ' . $pr['nama'] . ' (' . $pr['kode'] . ') - Kaprodi: ' . $pr['nama_kaprodi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-[11px] block mt-1" style="color: var(--dashboard-muted);">
                        Ketua Program Studi pada lembar pengesahan akan disesuaikan otomatis dengan prodi yang Anda pilih.
                    </span>
                </div>

                <!-- Judul Laporan -->
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--dashboard-text);">
                        Judul Laporan Praktik Kerja Lapangan <span class="text-red-500">*</span>
                    </label>
                    <textarea name="judul_laporan" rows="2" required placeholder="Contoh: Rancang Bangun Sistem Monitoring Logbook PKL Berbasis Web pada Dinas Komunikasi dan Informatika"
                              class="w-full px-4 py-2.5 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium"
                              style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);"><?= htmlspecialchars($pengesahan['judul_laporan'] ?? '') ?></textarea>
                </div>

                <!-- Instansi & Alamat -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: var(--dashboard-text);">
                            Nama Perusahaan / Instansi Tempat PKL <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nama_instansi" required value="<?= htmlspecialchars($pengesahan['nama_instansi'] ?? '') ?>" placeholder="Contoh: PT. Telekomunikasi Indonesia / Diskominfo"
                               class="w-full px-4 py-2.5 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: var(--dashboard-text);">
                            Alamat / Kota Lokasi PKL
                        </label>
                        <input type="text" name="alamat_instansi" value="<?= htmlspecialchars($pengesahan['alamat_instansi'] ?? '') ?>" placeholder="Contoh: Jl. Pahlawan No. 10, Surabaya"
                               class="w-full px-4 py-2.5 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    </div>
                </div>

                <!-- Periode Pelaksanaan -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: var(--dashboard-text);">
                            Tanggal Mulai PKL
                        </label>
                        <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($pengesahan['tgl_mulai'] ?? '') ?>"
                               class="w-full px-4 py-2.5 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color: var(--dashboard-text);">
                            Tanggal Selesai PKL
                        </label>
                        <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($pengesahan['tgl_selesai'] ?? '') ?>"
                               class="w-full px-4 py-2.5 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500"
                               style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    </div>
                </div>

                <!-- Unggah File Laporan PDF (Opsional) -->
                <div>
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--dashboard-text);">
                        Unggah Draf Laporan Akhir (PDF, Opsional)
                    </label>
                    <input type="file" name="file_laporan" accept=".pdf"
                           class="block w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer">
                    <?php if (!empty($pengesahan['file_laporan'])): ?>
                        <div class="mt-2 text-xs flex items-center gap-2">
                            <span class="text-emerald-500"><i class="fas fa-file-pdf"></i> File terunggah:</span>
                            <a href="<?= $base_path . htmlspecialchars($pengesahan['file_laporan']) ?>" target="_blank" class="text-blue-500 underline font-semibold">
                                Unduh Draf Laporan Saat Ini
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tombol Aksi Simpan -->
                <div class="pt-4 flex items-center justify-end gap-3 border-t" style="border-color: var(--dashboard-border);">
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 shadow transition duration-200">
                        <i class="fas fa-floppy-disk"></i> Simpan & Ajukan Pengesahan
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
