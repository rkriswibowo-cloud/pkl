<?php
session_start();
require_once __DIR__ . '/../includes/impersonation.php';
require_writable_session();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'dosen_pkl') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';

$page_title = 'Verifikasi Lembar Pengesahan - Dosen Pembimbing';
$id_dosen_pkl = (int)$_SESSION['user']['id'];
$feedback = '';

// Proses Verifikasi & TTD oleh Dospem PKL
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'proses_verifikasi') {
    $id_pengesahan = (int)$_POST['id_pengesahan'];
    $status        = in_array($_POST['status'], ['disetujui', 'ditolak']) ? $_POST['status'] : 'pending';
    $catatan       = trim($_POST['catatan_dospem_pkl'] ?? '');
    $ttd_data      = $_POST['signature'] ?? '';

    // Validasi kepemilikan bimbingan
    $cek = $conn->query("
        SELECT p.id, p.id_mahasiswa 
        FROM pengesahan_pkl p
        JOIN bimbingan b ON p.id_mahasiswa = b.id_mahasiswa
        WHERE p.id = '$id_pengesahan' AND b.id_dospem_pkl = '$id_dosen_pkl'
    ");

    if ($cek->num_rows === 0) {
        $feedback = "<script>Swal.fire({icon:'error', title:'Akses Ditolak', text:'Anda tidak memiliki hak verifikasi untuk mahasiswa ini.'});</script>";
    } else {
        $file_ttd = null;
        if ($status === 'disetujui' && !empty($ttd_data) && strpos($ttd_data, 'data:image/png;base64,') === 0) {
            $imageData = explode(',', $ttd_data)[1];
            $decodedImage = base64_decode($imageData);
            if ($decodedImage !== false) {
                $dir_ttd = __DIR__ . '/../signatures/';
                if (!is_dir($dir_ttd)) {
                    @mkdir($dir_ttd, 0775, true);
                }
                $filename = 'ttd_pkl_' . $id_pengesahan . '_' . time() . '.png';
                if (file_put_contents($dir_ttd . $filename, $decodedImage)) {
                    $file_ttd = 'signatures/' . $filename;
                }
            }
        }

        if ($file_ttd) {
            $stmt = $conn->prepare("UPDATE pengesahan_pkl SET 
                status_dospem_pkl = ?, catatan_dospem_pkl = ?, ttd_dospem_pkl = ?, tgl_approval_dospem_pkl = NOW()
                WHERE id = ?");
            $stmt->bind_param("sssi", $status, $catatan, $file_ttd, $id_pengesahan);
        } else {
            $stmt = $conn->prepare("UPDATE pengesahan_pkl SET 
                status_dospem_pkl = ?, catatan_dospem_pkl = ?, tgl_approval_dospem_pkl = NOW()
                WHERE id = ?");
            $stmt->bind_param("ssi", $status, $catatan, $id_pengesahan);
        }

        if ($stmt->execute()) {
            $feedback = "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Status verifikasi dan tanda tangan lembar pengesahan berhasil disimpan.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'verifikasi_berkas.php';
                });
            </script>";
        } else {
            $feedback = "<script>Swal.fire({icon:'error', title:'Gagal', text:'Terjadi kesalahan database.'});</script>";
        }
    }
}

// Ambil daftar pengajuan pengesahan mahasiswa bimbingan dospem ini
$query = "
    SELECT 
        p.*,
        u.nama AS nama_mahasiswa,
        m.nim,
        COALESCE(m.prodi, u.prodi, '-') AS prodi,
        lapangan.nama AS nama_dospem_lapangan
    FROM bimbingan b
    JOIN users u ON b.id_mahasiswa = u.id
    LEFT JOIN mahasiswa m ON u.id = m.id
    LEFT JOIN pengesahan_pkl p ON u.id = p.id_mahasiswa
    LEFT JOIN users lapangan ON b.id_dospem_lapangan = lapangan.id
    WHERE b.id_dospem_pkl = ?
    ORDER BY u.nama ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_dosen_pkl);
$stmt->execute();
$list_pengesahan = $stmt->get_result();

include '../includes/header.php';
?>

<?= $feedback ?>

<div class="min-w-0">
    <div class="dashboard-page w-full max-w-full">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                    <i class="fas fa-file-circle-check text-blue-600" aria-hidden="true"></i>
                    Verifikasi Lembar Pengesahan PKL
                </h2>
                <p class="text-sm mt-1" style="color: var(--dashboard-muted);">
                    Tinjau judul laporan akhir, isi berkas, dan berikan tanda tangan persetujuan sebagai Dosen Pembimbing PKL.
                </p>
            </div>
            <a href="dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>

        <!-- Tabel Daftar Verifikasi -->
        <div class="dashboard-panel p-5 sm:p-6 rounded-2xl border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-xs uppercase tracking-wider font-semibold" style="border-color: var(--dashboard-border); color: var(--dashboard-muted);">
                            <th class="py-3 px-3">No</th>
                            <th class="py-3 px-3">Mahasiswa</th>
                            <th class="py-3 px-3">Judul Laporan & Tempat PKL</th>
                            <th class="py-3 px-3 text-center">Status Dospem PKL</th>
                            <th class="py-3 px-3 text-center">Status Lapangan</th>
                            <th class="py-3 px-3 text-center">Status Kaprodi</th>
                            <th class="py-3 px-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--dashboard-border);">
                        <?php if ($list_pengesahan->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $list_pengesahan->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-500/5 transition">
                                    <td class="py-3 px-3 font-medium text-xs" style="color: var(--dashboard-muted);"><?= $no++ ?></td>
                                    <td class="py-3 px-3">
                                        <div class="font-bold" style="color: var(--dashboard-text);"><?= htmlspecialchars($row['nama_mahasiswa']) ?></div>
                                        <div class="text-xs" style="color: var(--dashboard-muted);">NIM: <?= htmlspecialchars($row['nim'] ?: '-') ?></div>
                                        <div class="text-xs" style="color: var(--dashboard-muted);"><?= htmlspecialchars($row['prodi']) ?></div>
                                    </td>
                                    <td class="py-3 px-3 max-w-xs">
                                        <?php if (!empty($row['judul_laporan'])): ?>
                                            <div class="font-semibold text-xs leading-snug line-clamp-2" style="color: var(--dashboard-text);" title="<?= htmlspecialchars($row['judul_laporan']) ?>">
                                                <?= htmlspecialchars($row['judul_laporan']) ?>
                                            </div>
                                            <div class="text-[11px] text-blue-500 mt-1">
                                                <i class="fas fa-building text-[10px]"></i> <?= htmlspecialchars($row['nama_instansi']) ?>
                                            </div>
                                            <?php if (!empty($row['file_laporan'])): ?>
                                                <a href="<?= $base_path . htmlspecialchars($row['file_laporan']) ?>" target="_blank" class="text-[11px] text-emerald-600 hover:underline inline-flex items-center gap-1 mt-0.5">
                                                    <i class="fas fa-file-pdf"></i> Lihat Draf Laporan
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-amber-500 italic">Belum mengisi judul laporan</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Status Dospem PKL -->
                                    <td class="py-3 px-3 text-center">
                                        <?php if (($row['status_dospem_pkl'] ?? '') === 'disetujui'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-500/10 text-green-500 border border-green-500/20">
                                                <i class="fas fa-check-circle"></i> Disetujui
                                            </span>
                                        <?php elseif (($row['status_dospem_pkl'] ?? '') === 'ditolak'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-500/10 text-red-500 border border-red-500/20">
                                                <i class="fas fa-circle-xmark"></i> Revisi
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Status Lapangan -->
                                    <td class="py-3 px-3 text-center">
                                        <?php if (($row['status_dospem_lapangan'] ?? '') === 'disetujui'): ?>
                                            <span class="text-xs text-emerald-500 font-semibold"><i class="fas fa-check"></i> Disetujui</span>
                                        <?php else: ?>
                                            <span class="text-xs text-muted"><i class="fas fa-clock"></i> Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Status Kaprodi -->
                                    <td class="py-3 px-3 text-center">
                                        <?php if (($row['status_kaprodi'] ?? '') === 'disetujui'): ?>
                                            <span class="text-xs text-emerald-500 font-semibold"><i class="fas fa-check"></i> Disahkan</span>
                                        <?php else: ?>
                                            <span class="text-xs text-muted"><i class="fas fa-clock"></i> Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Aksi -->
                                    <td class="py-3 px-3 text-center">
                                        <?php if (!empty($row['id'])): ?>
                                            <div class="flex items-center justify-center gap-1.5">
                                                <button type="button" 
                                                        onclick="bukaModalVerifikasi(<?= htmlspecialchars(json_encode($row)) ?>)"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition">
                                                    <i class="fas fa-pen-nib"></i> Verifikasi
                                                </button>
                                                <a href="../mahasiswa/cetak_pengesahan.php?id_mahasiswa=<?= $row['id_mahasiswa'] ?>" target="_blank" class="p-1.5 rounded-lg border text-xs hover:bg-slate-500/10 transition" title="Lihat Lembar Pengesahan" style="border-color: var(--dashboard-border); color: var(--dashboard-text);">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-sm" style="color: var(--dashboard-muted);">
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

<!-- Modal Verifikasi & TTD Digital -->
<div id="modalVerifikasi" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden">
    <div class="w-full max-w-lg rounded-2xl shadow-2xl border overflow-hidden transition-all transform max-h-[90vh] flex flex-col" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
        <div class="p-5 border-b flex items-center justify-between" style="border-color: var(--dashboard-border);">
            <div>
                <h3 class="font-bold text-lg" style="color: var(--dashboard-text);">Verifikasi Lembar Pengesahan</h3>
                <p id="modalMahasiswaInfo" class="text-xs" style="color: var(--dashboard-muted);"></p>
            </div>
            <button type="button" onclick="tutupModalVerifikasi()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-neutral-800 transition">
                <i class="fas fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="verifikasi_berkas.php" method="POST" class="p-5 space-y-4 overflow-y-auto">
            <input type="hidden" name="action" value="proses_verifikasi">
            <input type="hidden" id="modal_id_pengesahan" name="id_pengesahan" value="">
            <input type="hidden" id="signature_data" name="signature" value="">

            <div>
                <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Judul Laporan PKL</label>
                <div id="modalJudulLaporan" class="p-2.5 rounded-lg text-xs border font-medium leading-relaxed" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);"></div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Keputusan Verifikasi</label>
                    <select name="status" id="modalStatus" required class="w-full px-3 py-2 rounded-lg text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500 font-bold" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                        <option value="disetujui">✔ Setujui & Tanda Tangani</option>
                        <option value="ditolak">✕ Minta Revisi Laporan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Catatan Revisi / Masukan</label>
                    <input type="text" name="catatan_dospem_pkl" id="modalCatatan" placeholder="Opsional jika ada catatan..." class="w-full px-3 py-2 rounded-lg text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                </div>
            </div>

            <!-- Tanda Tangan Canvas -->
            <div id="signatureArea">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold" style="color: var(--dashboard-text);">Goreskan Tanda Tangan Digital Anda</label>
                    <button type="button" onclick="clearSignature()" class="text-xs text-red-500 hover:underline">Hapus / Ulangi</button>
                </div>
                <div class="border-2 border-dashed rounded-xl bg-white overflow-hidden" style="border-color: var(--dashboard-border);">
                    <canvas id="signatureCanvas" width="460" height="150" class="w-full h-36 cursor-crosshair block touch-none"></canvas>
                </div>
                <span class="text-[11px] block mt-1 text-muted">Gunakan mouse atau sentuhan layar untuk menandatangani.</span>
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t" style="border-color: var(--dashboard-border);">
                <button type="button" onclick="tutupModalVerifikasi()" class="px-4 py-2 rounded-lg text-xs font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    Batal
                </button>
                <button type="submit" onclick="return prepareSubmit()" class="px-5 py-2 rounded-lg text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 shadow transition">
                    <i class="fas fa-check mr-1.5"></i> Simpan Verifikasi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let canvas, ctx, isDrawing = false;

window.addEventListener('DOMContentLoaded', () => {
    canvas = document.getElementById('signatureCanvas');
    if (canvas) {
        ctx = canvas.getContext('2d');
        ctx.strokeStyle = '#1e3a8a';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';

        function getPos(e) {
            const rect = canvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: (clientX - rect.left) * (canvas.width / rect.width),
                y: (clientY - rect.top) * (canvas.height / rect.height)
            };
        }

        canvas.addEventListener('mousedown', (e) => {
            isDrawing = true;
            const pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
        });
        canvas.addEventListener('mousemove', (e) => {
            if (!isDrawing) return;
            const pos = getPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
        });
        window.addEventListener('mouseup', () => { isDrawing = false; });

        // Touch events
        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            isDrawing = true;
            const pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
        });
        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            if (!isDrawing) return;
            const pos = getPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
        });
        canvas.addEventListener('touchend', () => { isDrawing = false; });
    }
});

function clearSignature() {
    if (ctx && canvas) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
}

function bukaModalVerifikasi(row) {
    document.getElementById('modal_id_pengesahan').value = row.id;
    document.getElementById('modalMahasiswaInfo').innerText = row.nama_mahasiswa + ' (' + (row.nim || '-') + ')';
    document.getElementById('modalJudulLaporan').innerText = row.judul_laporan || '-';
    document.getElementById('modalCatatan').value = row.catatan_dospem_pkl || '';
    if (row.status_dospem_pkl) {
        document.getElementById('modalStatus').value = row.status_dospem_pkl;
    }
    clearSignature();
    document.getElementById('modalVerifikasi').classList.remove('hidden');
}

function tutupModalVerifikasi() {
    document.getElementById('modalVerifikasi').classList.add('hidden');
}

function prepareSubmit() {
    const status = document.getElementById('modalStatus').value;
    if (status === 'disetujui') {
        const dataUrl = canvas.toDataURL('image/png');
        document.getElementById('signature_data').value = dataUrl;
    }
    return true;
}
</script>

<?php include '../includes/footer.php'; ?>
