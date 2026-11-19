<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/prodi_helper.php';

$page_title = 'Pengesahan & Verifikasi Kaprodi - Admin';
$feedback = '';

// Handle Pengesahan Akhir oleh Kaprodi / Admin
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sahkan_kaprodi') {
    $id_pengesahan = (int)$_POST['id_pengesahan'];
    $status        = in_array($_POST['status_kaprodi'], ['disetujui', 'ditolak']) ? $_POST['status_kaprodi'] : 'pending';
    $catatan       = trim($_POST['catatan_kaprodi'] ?? '');
    $ttd_data      = $_POST['signature'] ?? '';

    // Cari data prodi mahasiswa terkait untuk mencocokkan Kaprodi
    $q_mhs = $conn->query("
        SELECT u.prodi 
        FROM pengesahan_pkl p 
        JOIN users u ON p.id_mahasiswa = u.id 
        WHERE p.id = '$id_pengesahan'
    ")->fetch_assoc();
    $prodi_mhs = $q_mhs['prodi'] ?? '';
    $kap_info  = get_kaprodi_by_prodi($conn, $prodi_mhs);

    $kaprodi_nama = $kap_info['nama_kaprodi'];
    $kaprodi_nip  = $kap_info['nidn_kaprodi'];

    $file_ttd = null;
    if ($status === 'disetujui' && !empty($ttd_data) && strpos($ttd_data, 'data:image/png;base64,') === 0) {
        $imageData = explode(',', $ttd_data)[1];
        $decodedImage = base64_decode($imageData);
        if ($decodedImage !== false) {
            $dir_ttd = __DIR__ . '/../signatures/';
            if (!is_dir($dir_ttd)) {
                @mkdir($dir_ttd, 0775, true);
            }
            $filename = 'ttd_kaprodi_' . $id_pengesahan . '_' . time() . '.png';
            if (file_put_contents($dir_ttd . $filename, $decodedImage)) {
                $file_ttd = 'signatures/' . $filename;
            }
        }
    }

    // Jika tidak ada goresan baru tapi di master prodi sudah ada TTD, pakai TTD master prodi
    if (empty($file_ttd) && !empty($kap_info['ttd_kaprodi'])) {
        $file_ttd = $kap_info['ttd_kaprodi'];
    }

    if ($file_ttd) {
        $stmt = $conn->prepare("UPDATE pengesahan_pkl SET 
            status_kaprodi = ?, catatan_kaprodi = ?, nama_kaprodi = ?, nip_kaprodi = ?, ttd_kaprodi = ?, tgl_approval_kaprodi = NOW()
            WHERE id = ?");
        $stmt->bind_param("sssssi", $status, $catatan, $kaprodi_nama, $kaprodi_nip, $file_ttd, $id_pengesahan);
    } else {
        $stmt = $conn->prepare("UPDATE pengesahan_pkl SET 
            status_kaprodi = ?, catatan_kaprodi = ?, nama_kaprodi = ?, nip_kaprodi = ?, tgl_approval_kaprodi = NOW()
            WHERE id = ?");
        $stmt->bind_param("ssssi", $status, $catatan, $kaprodi_nama, $kaprodi_nip, $id_pengesahan);
    }

    if ($stmt->execute()) {
        $feedback = "<script>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Pengesahan akhir Kaprodi ' + '" . addslashes($prodi_mhs) . "' + ' berhasil disimpan.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => { window.location.href = 'verifikasi_pengesahan.php'; });
        </script>";
    } else {
        $feedback = "<script>Swal.fire({icon:'error', title:'Gagal', text:'Terjadi kesalahan database.'});</script>";
    }
}

// Ambil daftar seluruh pengesahan PKL
$query = "
    SELECT 
        p.*,
        u.nama AS nama_mahasiswa,
        m.nim,
        COALESCE(m.prodi, u.prodi, '-') AS prodi,
        pkl.nama AS dospem_pkl_nama,
        lapangan.nama AS dospem_lapangan_nama
    FROM users u
    LEFT JOIN mahasiswa m ON u.id = m.id
    LEFT JOIN pengesahan_pkl p ON u.id = p.id_mahasiswa
    LEFT JOIN bimbingan b ON u.id = b.id_mahasiswa
    LEFT JOIN users pkl ON b.id_dospem_pkl = pkl.id
    LEFT JOIN users lapangan ON b.id_dospem_lapangan = lapangan.id
    WHERE u.role = 'mahasiswa'
    ORDER BY u.nama ASC
";
$list_pengesahan = $conn->query($query);

include '../includes/header.php';
?>

<?= $feedback ?>

<div class="min-w-0">
    <div class="dashboard-page w-full max-w-full">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                    <i class="fas fa-stamp text-purple-600" aria-hidden="true"></i>
                    Verifikasi Lembar Pengesahan (Kaprodi)
                </h2>
                <p class="text-sm mt-1" style="color: var(--dashboard-muted);">
                    Pengesahan akhir "Mengetahui" oleh Ketua Program Studi setelah disetujui oleh Dospem 1 dan Pembimbing Lapangan.
                </p>
            </div>
            <a href="dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>

        <!-- Panel Master Prodi & Kaprodi Multi-Jurusan -->
        <div class="dashboard-panel p-5 sm:p-6 rounded-2xl border mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center font-bold text-lg">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold" style="color: var(--dashboard-text);">Master Program Studi & Kaprodi (OBE)</h3>
                    <p class="text-xs" style="color: var(--dashboard-muted);">Pengesahan Kaprodi akan otomatis menyesuaikan Program Studi masing-masing mahasiswa (SI, RPL, Bisnis Digital, Kewirausahaan).</p>
                </div>
            </div>
            <a href="master_prodi.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold text-white bg-purple-600 hover:bg-purple-700 shadow transition whitespace-nowrap">
                <i class="fas fa-sliders"></i> Kelola Master Kaprodi
            </a>
        </div>

        <!-- Tabel Daftar Pengesahan Seluruh Mahasiswa -->
        <div class="dashboard-panel p-5 sm:p-6 rounded-2xl border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-xs uppercase tracking-wider font-semibold" style="border-color: var(--dashboard-border); color: var(--dashboard-muted);">
                            <th class="py-3 px-3">No</th>
                            <th class="py-3 px-3">Mahasiswa</th>
                            <th class="py-3 px-3">Judul Laporan PKL</th>
                            <th class="py-3 px-3 text-center">Dospem 1 (Kampus)</th>
                            <th class="py-3 px-3 text-center">Dospem Lapangan</th>
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
                                            <div class="text-[11px] text-purple-500 mt-1">
                                                <i class="fas fa-building text-[10px]"></i> <?= htmlspecialchars($row['nama_instansi']) ?>
                                            </div>
                                            <?php if (!empty($row['file_laporan'])): ?>
                                                <a href="<?= $base_path . htmlspecialchars($row['file_laporan']) ?>" target="_blank" class="text-[11px] text-blue-600 hover:underline inline-flex items-center gap-1 mt-0.5">
                                                    <i class="fas fa-file-pdf"></i> Draf Laporan
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-amber-500 italic">Belum mengajukan lembar pengesahan</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Status Dospem 1 -->
                                    <td class="py-3 px-3 text-center">
                                        <?php if (($row['status_dospem_pkl'] ?? '') === 'disetujui'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-500/10 text-green-500 border border-green-500/20">
                                                <i class="fas fa-check-circle"></i> Disetujui
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
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-500/10 text-green-500 border border-green-500/20">
                                                <i class="fas fa-check-circle"></i> Disetujui
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Status Kaprodi -->
                                    <td class="py-3 px-3 text-center">
                                        <?php if (($row['status_kaprodi'] ?? '') === 'disetujui'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-500/10 text-purple-600 border border-purple-500/20">
                                                <i class="fas fa-stamp"></i> Disahkan
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                                <i class="fas fa-clock"></i> Menunggu
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Aksi -->
                                    <td class="py-3 px-3 text-center">
                                        <?php if (!empty($row['id'])): ?>
                                            <div class="flex items-center justify-center gap-1.5">
                                                <button type="button" 
                                                        onclick="bukaModalKaprodi(<?= htmlspecialchars(json_encode($row)) ?>)"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-purple-600 hover:bg-purple-700 shadow-sm transition">
                                                    <i class="fas fa-stamp"></i> Sahkan
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
                                    <i class="fas fa-users-slash text-2xl mb-2 block"></i>
                                    Belum ada mahasiswa terdaftar.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal Pengesahan Akhir Kaprodi -->
<div id="modalKaprodi" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden">
    <div class="w-full max-w-lg rounded-2xl shadow-2xl border overflow-hidden transition-all transform max-h-[90vh] flex flex-col" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
        <div class="p-5 border-b flex items-center justify-between" style="border-color: var(--dashboard-border);">
            <div>
                <h3 class="font-bold text-lg" style="color: var(--dashboard-text);">Pengesahan Akhir Kaprodi</h3>
                <p id="modalMahasiswaInfo" class="text-xs" style="color: var(--dashboard-muted);"></p>
            </div>
            <button type="button" onclick="tutupModalKaprodi()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-neutral-800 transition">
                <i class="fas fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="verifikasi_pengesahan.php" method="POST" class="p-5 space-y-4 overflow-y-auto">
            <input type="hidden" name="action" value="sahkan_kaprodi">
            <input type="hidden" id="modal_id_pengesahan" name="id_pengesahan" value="">
            <input type="hidden" id="signature_data" name="signature" value="">

            <div>
                <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Judul Laporan PKL</label>
                <div id="modalJudulLaporan" class="p-2.5 rounded-lg text-xs border font-medium leading-relaxed" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);"></div>
            </div>

            <!-- Status Pembimbing 1 & Lapangan -->
            <div class="p-3 rounded-xl border flex items-center justify-around text-xs" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                <div>
                    <span class="block text-[11px] text-muted">Dospem 1 (Kampus):</span>
                    <span id="badgeDospemPKL" class="font-bold">-</span>
                </div>
                <div class="border-l h-8" style="border-color: var(--dashboard-border);"></div>
                <div>
                    <span class="block text-[11px] text-muted">Pembimbing Lapangan:</span>
                    <span id="badgeDospemLap" class="font-bold">-</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Keputusan Pengesahan</label>
                    <select name="status_kaprodi" id="modalStatusKaprodi" required class="w-full px-3 py-2 rounded-lg text-xs border focus:outline-none focus:ring-2 focus:ring-purple-500 font-bold" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                        <option value="disetujui">✔ Sahkan ("Mengetahui")</option>
                        <option value="ditolak">✕ Tolak / Perlu Revisi</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Catatan Kaprodi</label>
                    <input type="text" name="catatan_kaprodi" id="modalCatatanKaprodi" placeholder="Opsional jika ada catatan..." class="w-full px-3 py-2 rounded-lg text-xs border focus:outline-none focus:ring-2 focus:ring-purple-500" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                </div>
            </div>

            <!-- Tanda Tangan Canvas -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold" style="color: var(--dashboard-text);">Tanda Tangan Digital Ketua Program Studi</label>
                    <button type="button" onclick="clearSignature()" class="text-xs text-red-500 hover:underline">Hapus / Ulangi</button>
                </div>
                <div class="border-2 border-dashed rounded-xl bg-white overflow-hidden" style="border-color: var(--dashboard-border);">
                    <canvas id="signatureCanvas" width="460" height="150" class="w-full h-36 cursor-crosshair block touch-none"></canvas>
                </div>
                <span class="text-[11px] block mt-1 text-muted">Tanda tangan digital Kaprodi untuk lembar pengesahan resmi.</span>
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t" style="border-color: var(--dashboard-border);">
                <button type="button" onclick="tutupModalKaprodi()" class="px-4 py-2 rounded-lg text-xs font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    Batal
                </button>
                <button type="submit" onclick="return prepareSubmit()" class="px-5 py-2 rounded-lg text-xs font-bold text-white bg-purple-600 hover:bg-purple-700 shadow transition">
                    <i class="fas fa-stamp mr-1.5"></i> Sahkan Lembar Pengesahan
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
        ctx.strokeStyle = '#6b21a8';
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

function bukaModalKaprodi(row) {
    document.getElementById('modal_id_pengesahan').value = row.id;
    document.getElementById('modalMahasiswaInfo').innerText = row.nama_mahasiswa + ' (' + (row.nim || '-') + ')';
    document.getElementById('modalJudulLaporan').innerText = row.judul_laporan || '-';
    document.getElementById('modalCatatanKaprodi').value = row.catatan_kaprodi || '';

    const pklEl = document.getElementById('badgeDospemPKL');
    if (row.status_dospem_pkl === 'disetujui') {
        pklEl.className = 'text-emerald-500 font-bold';
        pklEl.innerText = 'Disetujui';
    } else {
        pklEl.className = 'text-amber-500 font-bold';
        pklEl.innerText = 'Pending';
    }

    const lapEl = document.getElementById('badgeDospemLap');
    if (row.status_dospem_lapangan === 'disetujui') {
        lapEl.className = 'text-emerald-500 font-bold';
        lapEl.innerText = 'Disetujui';
    } else {
        lapEl.className = 'text-amber-500 font-bold';
        lapEl.innerText = 'Pending';
    }

    if (row.status_kaprodi) {
        document.getElementById('modalStatusKaprodi').value = row.status_kaprodi;
    }
    clearSignature();
    document.getElementById('modalKaprodi').classList.remove('hidden');
}

function tutupModalKaprodi() {
    document.getElementById('modalKaprodi').classList.add('hidden');
}

function prepareSubmit() {
    const status = document.getElementById('modalStatusKaprodi').value;
    if (status === 'disetujui') {
        const dataUrl = canvas.toDataURL('image/png');
        document.getElementById('signature_data').value = dataUrl;
    }
    return true;
}
</script>

<?php include '../includes/footer.php'; ?>

