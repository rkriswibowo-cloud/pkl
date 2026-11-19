<?php
session_start();

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'superadmin'])) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/prodi_helper.php';

$page_title = 'Master Program Studi & Kaprodi - Admin PKL';
$feedback = '';

// Handle Sinkronisasi Data dari Proyek OBE
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_obe') {
    $res = sync_prodi_from_obe($conn);
    if ($res['status']) {
        $feedback = "<script>
            Swal.fire({
                icon: 'success',
                title: 'Sinkronisasi Berhasil!',
                text: '{$res['message']}',
                timer: 2000,
                showConfirmButton: false
            }).then(() => { window.location.href = 'master_prodi.php'; });
        </script>";
    } else {
        $feedback = "<script>Swal.fire({icon: 'error', title: 'Gagal Sinkronisasi', text: '{$res['message']}'});</script>";
    }
}

// Handle Update Profil Kaprodi & TTD
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_prodi') {
    $id_prodi      = (int)$_POST['id_prodi'];
    $nama_kaprodi  = trim($_POST['nama_kaprodi'] ?? '');
    $nidn_kaprodi  = trim($_POST['nidn_kaprodi'] ?? '');
    $fakultas      = trim($_POST['fakultas'] ?? '');
    $status        = in_array($_POST['status'], ['ACTIVE', 'INACTIVE']) ? $_POST['status'] : 'ACTIVE';
    $ttd_data      = $_POST['signature'] ?? '';

    $file_ttd = null;
    if (!empty($ttd_data) && strpos($ttd_data, 'data:image/png;base64,') === 0) {
        $imageData = explode(',', $ttd_data)[1];
        $decodedImage = base64_decode($imageData);
        if ($decodedImage !== false) {
            $dir_ttd = __DIR__ . '/../signatures/kaprodi/';
            if (!is_dir($dir_ttd)) {
                @mkdir($dir_ttd, 0775, true);
            }
            $filename = 'ttd_kaprodi_prodi_' . $id_prodi . '_' . time() . '.png';
            if (file_put_contents($dir_ttd . $filename, $decodedImage)) {
                $file_ttd = 'signatures/kaprodi/' . $filename;
            }
        }
    }

    if ($file_ttd) {
        $stmt = $conn->prepare("UPDATE program_studi SET nama_kaprodi = ?, nidn_kaprodi = ?, fakultas = ?, status = ?, ttd_kaprodi = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $nama_kaprodi, $nidn_kaprodi, $fakultas, $status, $file_ttd, $id_prodi);
    } else {
        $stmt = $conn->prepare("UPDATE program_studi SET nama_kaprodi = ?, nidn_kaprodi = ?, fakultas = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $nama_kaprodi, $nidn_kaprodi, $fakultas, $status, $id_prodi);
    }

    if ($stmt->execute()) {
        $feedback = "<script>
            Swal.fire({
                icon: 'success',
                title: 'Data Disimpan!',
                text: 'Profil Kaprodi berhasil diperbarui.',
                timer: 1800,
                showConfirmButton: false
            }).then(() => { window.location.href = 'master_prodi.php'; });
        </script>";
    } else {
        $feedback = "<script>Swal.fire({icon: 'error', title: 'Gagal', text: 'Gagal memperbarui data: {$conn->error}'});</script>";
    }
}

$list_prodi = get_all_prodi($conn, false);

include '../includes/header.php';
?>

<?= $feedback ?>

<div class="min-w-0">
    <div class="dashboard-page w-full max-w-full">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                    <i class="fas fa-graduation-cap text-blue-600" aria-hidden="true"></i>
                    Master Program Studi & Kaprodi
                </h2>
                <p class="text-sm mt-1" style="color: var(--dashboard-muted);">
                    Data master program studi terintegrasi dari proyek OBE untuk penandatanganan lembar pengesahan PKL.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <form action="master_prodi.php" method="POST" onsubmit="return confirm('Tarik dan sinkronkan data Program Studi & Kaprodi terbaru dari proyek OBE?')">
                    <input type="hidden" name="action" value="sync_obe">
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 shadow transition">
                        <i class="fas fa-rotate"></i> Sinkronkan dari Proyek OBE
                    </button>
                </form>
                <a href="dosen_pembimbing.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    <i class="fas fa-chalkboard-user text-blue-600"></i> Master Dosen
                </a>
                <a href="dashboard.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Banner Info Integrasi OBE -->
        <div class="p-4 sm:p-5 rounded-2xl mb-6 border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="fas fa-database text-lg"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold" style="color: var(--dashboard-text);">Integrasi Master Data OBE</h4>
                    <p class="text-xs mt-1" style="color: var(--dashboard-muted);">
                        Sistem PKL mengambil data Program Studi, Fakultas, dan Nama Pejabat Kaprodi langsung dari master database proyek OBE (<code class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-neutral-800 text-blue-600 font-mono text-[11px]">obe_cpl.program_studi</code>). 
                        Anda dapat memperbarui nama Kaprodi atau mengunggah tanda tangan digital masing-masing Kaprodi untuk lembar pengesahan.
                    </p>
                </div>
            </div>
        </div>

        <!-- Tabel Master Program Studi -->
        <div class="dashboard-panel p-5 sm:p-6 rounded-2xl border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-xs uppercase tracking-wider font-semibold" style="border-color: var(--dashboard-border); color: var(--dashboard-muted);">
                            <th class="py-3 px-3">No</th>
                            <th class="py-3 px-3">Kode</th>
                            <th class="py-3 px-3">Program Studi</th>
                            <th class="py-3 px-3">Jenjang / Fakultas</th>
                            <th class="py-3 px-3">Ketua Program Studi (Kaprodi)</th>
                            <th class="py-3 px-3 text-center">TTD Digital</th>
                            <th class="py-3 px-3 text-center">Status</th>
                            <th class="py-3 px-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--dashboard-border);">
                        <?php if (!empty($list_prodi)): ?>
                            <?php $no = 1; foreach ($list_prodi as $prodi): ?>
                                <tr class="hover:bg-slate-500/5 transition">
                                    <td class="py-3 px-3 font-medium text-xs" style="color: var(--dashboard-muted);"><?= $no++ ?></td>
                                    <td class="py-3 px-3 font-bold font-mono text-xs text-blue-600"><?= htmlspecialchars($prodi['kode']) ?></td>
                                    <td class="py-3 px-3">
                                        <div class="font-bold" style="color: var(--dashboard-text);"><?= htmlspecialchars($prodi['nama']) ?></div>
                                    </td>
                                    <td class="py-3 px-3 text-xs" style="color: var(--dashboard-muted);">
                                        <span class="font-semibold text-purple-600"><?= htmlspecialchars($prodi['jenjang']) ?></span> - <?= htmlspecialchars($prodi['fakultas']) ?>
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="font-bold text-xs" style="color: var(--dashboard-text);"><?= htmlspecialchars($prodi['nama_kaprodi'] ?: 'Belum diatur') ?></div>
                                        <div class="text-[11px]" style="color: var(--dashboard-muted);">NIDN: <?= htmlspecialchars($prodi['nidn_kaprodi'] ?: '-') ?></div>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <?php if (!empty($prodi['ttd_kaprodi'])): ?>
                                            <div class="h-8 flex items-center justify-center">
                                                <img src="../<?= htmlspecialchars($prodi['ttd_kaprodi']) ?>" alt="TTD Kaprodi" class="max-h-full max-w-[80px] object-contain">
                                            </div>
                                        <?php else: ?>
                                            <span class="text-[11px] text-amber-500 italic">Belum ada TTD</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <?php if ($prodi['status'] === 'ACTIVE'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-500/10 text-green-500 border border-green-500/20">
                                                Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-500/10 text-red-500 border border-red-500/20">
                                                Non-aktif
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <button type="button" 
                                                onclick="bukaModalEdit(<?= htmlspecialchars(json_encode($prodi)) ?>)"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition">
                                            <i class="fas fa-edit"></i> Edit Kaprodi
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="py-8 text-center text-sm" style="color: var(--dashboard-muted);">
                                    Belum ada data program studi. Klik tombol <strong>Sinkronkan dari Proyek OBE</strong> di atas untuk menarik data otomatis.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Modal Edit Kaprodi & TTD -->
<div id="modalEdit" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden">
    <div class="w-full max-w-lg rounded-2xl shadow-2xl border overflow-hidden transition-all transform max-h-[90vh] flex flex-col" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
        <div class="p-5 border-b flex items-center justify-between" style="border-color: var(--dashboard-border);">
            <div>
                <h3 class="font-bold text-lg" style="color: var(--dashboard-text);">Edit Pejabat Kaprodi</h3>
                <p id="modalProdiTitle" class="text-xs text-blue-500 font-semibold"></p>
            </div>
            <button type="button" onclick="tutupModalEdit()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-neutral-800 transition">
                <i class="fas fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="master_prodi.php" method="POST" class="p-5 space-y-4 overflow-y-auto">
            <input type="hidden" name="action" value="update_prodi">
            <input type="hidden" id="modal_id_prodi" name="id_prodi" value="">
            <input type="hidden" id="signature_data" name="signature" value="">

            <div>
                <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Nama Lengkap & Gelar Kaprodi</label>
                <input type="text" name="nama_kaprodi" id="modal_nama_kaprodi" required class="w-full px-3 py-2 rounded-lg text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">NIDN / NIP Kaprodi</label>
                    <input type="text" name="nidn_kaprodi" id="modal_nidn_kaprodi" class="w-full px-3 py-2 rounded-lg text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Status Program Studi</label>
                    <select name="status" id="modal_status" class="w-full px-3 py-2 rounded-lg text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                        <option value="ACTIVE">ACTIVE (Aktif)</option>
                        <option value="INACTIVE">INACTIVE (Non-aktif)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color: var(--dashboard-text);">Nama Fakultas</label>
                <input type="text" name="fakultas" id="modal_fakultas" class="w-full px-3 py-2 rounded-lg text-xs border focus:outline-none focus:ring-2 focus:ring-blue-500" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
            </div>

            <!-- Tanda Tangan Digital Kaprodi -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold" style="color: var(--dashboard-text);">Tanda Tangan Digital Kaprodi</label>
                    <button type="button" onclick="clearSignature()" class="text-xs text-red-500 hover:underline">Hapus / Reset Goresan</button>
                </div>
                <div class="border-2 border-dashed rounded-xl bg-white overflow-hidden" style="border-color: var(--dashboard-border);">
                    <canvas id="signatureCanvas" width="460" height="140" class="w-full h-32 cursor-crosshair block touch-none"></canvas>
                </div>
                <span class="text-[11px] block mt-1 text-muted">Goreskan tanda tangan digital untuk dicantumkan otomatis pada setiap lembar pengesahan prodi ini.</span>
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t" style="border-color: var(--dashboard-border);">
                <button type="button" onclick="tutupModalEdit()" class="px-4 py-2 rounded-lg text-xs font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    Batal
                </button>
                <button type="submit" onclick="return prepareSubmit()" class="px-5 py-2 rounded-lg text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 shadow transition">
                    <i class="fas fa-save mr-1.5"></i> Simpan Data Kaprodi
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
        ctx.strokeStyle = '#1e40af';
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

        // Touch
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

function bukaModalEdit(prodi) {
    document.getElementById('modal_id_prodi').value = prodi.id;
    document.getElementById('modalProdiTitle').innerText = prodi.jenjang + ' ' + prodi.nama + ' (' + prodi.kode + ')';
    document.getElementById('modal_nama_kaprodi').value = prodi.nama_kaprodi || '';
    document.getElementById('modal_nidn_kaprodi').value = prodi.nidn_kaprodi || '';
    document.getElementById('modal_fakultas').value = prodi.fakultas || '';
    document.getElementById('modal_status').value = prodi.status || 'ACTIVE';
    clearSignature();
    document.getElementById('modalEdit').classList.remove('hidden');
}

function tutupModalEdit() {
    document.getElementById('modalEdit').classList.add('hidden');
}

function prepareSubmit() {
    // Cek apakah ada goresan di canvas
    const blank = document.createElement('canvas');
    blank.width = canvas.width;
    blank.height = canvas.height;
    if (canvas.toDataURL() !== blank.toDataURL()) {
        document.getElementById('signature_data').value = canvas.toDataURL('image/png');
    }
    return true;
}
</script>

<?php include '../includes/footer.php'; ?>

