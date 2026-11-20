<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'superadmin'])) {
    header("Location: ../index.php"); exit();
}
require_once __DIR__ . '/../config/db.php';

$page_title   = 'Penempatan PKL - Admin';
$feedback     = '';
$feedback_type = 'success';

// ---- SIMPAN / UPDATE PENEMPATAN ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['simpan', 'edit'])) {
    $id_mhs    = (int)($_POST['id_mahasiswa'] ?? 0);
    $id_mitra  = (int)($_POST['id_mitra'] ?? 0);
    $nama_pbl  = $conn->real_escape_string(trim($_POST['nama_pembimbing_lapangan'] ?? ''));
    $jab_pbl   = $conn->real_escape_string(trim($_POST['jabatan_pembimbing'] ?? ''));
    $telp_pbl  = $conn->real_escape_string(trim($_POST['no_telp_pembimbing'] ?? ''));
    $tgl_mulai = $conn->real_escape_string(trim($_POST['tanggal_mulai'] ?? ''));
    $tgl_sel   = $conn->real_escape_string(trim($_POST['tanggal_selesai'] ?? ''));
    $ket       = $conn->real_escape_string(trim($_POST['keterangan'] ?? ''));

    if (!$id_mhs || !$id_mitra) {
        $feedback = 'Mahasiswa dan Mitra PKL wajib dipilih.';
        $feedback_type = 'error';
    } elseif ($tgl_mulai && $tgl_sel && $tgl_mulai > $tgl_sel) {
        $feedback = 'Tanggal mulai tidak boleh melebihi tanggal selesai.';
        $feedback_type = 'error';
    } else {
        $existing = $conn->query("SELECT id FROM penempatan_pkl WHERE id_mahasiswa=$id_mhs")->fetch_assoc();
        if ($existing) {
            $conn->query("UPDATE penempatan_pkl SET
                id_mitra=$id_mitra,
                nama_pembimbing_lapangan='$nama_pbl',
                jabatan_pembimbing='$jab_pbl',
                no_telp_pembimbing='$telp_pbl',
                tanggal_mulai=" . ($tgl_mulai ? "'$tgl_mulai'" : "NULL") . ",
                tanggal_selesai=" . ($tgl_sel ? "'$tgl_sel'" : "NULL") . ",
                keterangan='$ket'
                WHERE id_mahasiswa=$id_mhs");
            $feedback = 'Data penempatan berhasil diperbarui.';
        } else {
            $conn->query("INSERT INTO penempatan_pkl
                (id_mahasiswa, id_mitra, nama_pembimbing_lapangan, jabatan_pembimbing,
                 no_telp_pembimbing, tanggal_mulai, tanggal_selesai, keterangan)
                VALUES ($id_mhs, $id_mitra, '$nama_pbl', '$jab_pbl', '$telp_pbl',
                " . ($tgl_mulai ? "'$tgl_mulai'" : "NULL") . ",
                " . ($tgl_sel   ? "'$tgl_sel'" : "NULL") . ",
                '$ket')");
            $feedback = 'Penempatan PKL berhasil disimpan.';
        }
    }
}

// ---- HAPUS PENEMPATAN ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus') {
    $id = (int)$_POST['id_penempatan'];
    $conn->query("DELETE FROM penempatan_pkl WHERE id=$id");
    $feedback = 'Data penempatan berhasil dihapus.';
}

// ---- Ambil semua mahasiswa + data penempatan ----
$search_q    = trim($_GET['search'] ?? '');
$filter_mitra = (int)($_GET['mitra'] ?? 0);

$where = ["u.role = 'mahasiswa'"];
if ($search_q) $where[] = "(u.nama LIKE '%" . $conn->real_escape_string($search_q) . "%' OR m.nim LIKE '%" . $conn->real_escape_string($search_q) . "%')";
if ($filter_mitra) $where[] = "p.id_mitra = $filter_mitra";

$mahasiswa_list = $conn->query("
    SELECT
        u.id, u.nama, u.username, u.prodi,
        m.nim, COALESCE(NULLIF(m.prodi,''), NULLIF(u.prodi,''), '-') AS prodi_display,
        p.id AS id_penempatan,
        p.id_mitra, mp.nama_perusahaan, mp.kota, mp.bidang_usaha,
        p.nama_pembimbing_lapangan, p.jabatan_pembimbing, p.no_telp_pembimbing,
        p.tanggal_mulai, p.tanggal_selesai, p.keterangan
    FROM users u
    LEFT JOIN mahasiswa m ON u.id = m.id
    LEFT JOIN penempatan_pkl p ON u.id = p.id_mahasiswa
    LEFT JOIN mitra_pkl mp ON p.id_mitra = mp.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.nama ASC
")->fetch_all(MYSQLI_ASSOC);

// Mitra aktif untuk dropdown
$mitra_options = $conn->query("SELECT id, nama_perusahaan, kota FROM mitra_pkl WHERE status='aktif' ORDER BY nama_perusahaan ASC")->fetch_all(MYSQLI_ASSOC);
$all_mitra     = $conn->query("SELECT id, nama_perusahaan, kota FROM mitra_pkl ORDER BY nama_perusahaan ASC")->fetch_all(MYSQLI_ASSOC);

// Statistik
$st = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM users WHERE role='mahasiswa') AS total_mhs,
        (SELECT COUNT(*) FROM penempatan_pkl) AS sudah_ditempatkan
")->fetch_assoc();
$belum = (int)$st['total_mhs'] - (int)$st['sudah_ditempatkan'];

include '../includes/header.php';
?>

<div class="min-w-0">
<div class="dashboard-page w-full max-w-full">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                <i class="fas fa-map-pin text-green-600"></i> Penempatan PKL Mahasiswa
            </h2>
            <p class="text-sm mt-1" style="color:var(--dashboard-muted);">
                Tetapkan tempat PKL, periode, dan pembimbing lapangan untuk setiap mahasiswa.
            </p>
        </div>
    </div>

    <?php if ($feedback): ?>
        <div class="rounded-lg p-4 mb-5 flex items-start gap-3 <?= $feedback_type==='error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-700' ?>">
            <i class="fas <?= $feedback_type==='error' ? 'fa-circle-xmark' : 'fa-circle-check' ?> mt-0.5 shrink-0"></i>
            <span><?= htmlspecialchars($feedback) ?></span>
        </div>
    <?php endif; ?>

    <!-- Statistik -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <?php foreach ([
            ['Total Mahasiswa', $st['total_mhs'], 'fa-users', 'text-blue-600','bg-blue-50'],
            ['Sudah Ditempatkan', $st['sudah_ditempatkan'], 'fa-circle-check','text-green-600','bg-green-50'],
            ['Belum Ditempatkan', $belum, 'fa-clock','text-amber-600','bg-amber-50'],
        ] as [$label,$val,$icon,$tc,$bg]): ?>
        <div class="dashboard-card p-4 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl <?=$bg?> <?=$tc?> flex items-center justify-center shrink-0">
                <i class="fas <?=$icon?> text-lg"></i>
            </div>
            <div>
                <p class="text-2xl font-bold <?=$tc?>"><?=(int)$val?></p>
                <p class="text-xs" style="color:var(--dashboard-muted);"><?=$label?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter -->
    <div class="dashboard-card p-4 mb-5">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <label class="block text-xs font-medium mb-1">Cari Mahasiswa / NIM</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" name="search" value="<?=htmlspecialchars($search_q)?>"
                           class="form-input w-full pl-8 text-sm py-2" placeholder="Nama atau NIM...">
                </div>
            </div>
            <div class="min-w-52">
                <label class="block text-xs font-medium mb-1">Filter Mitra</label>
                <select name="mitra" class="form-select w-full text-sm py-2">
                    <option value="0">Semua Mitra</option>
                    <?php foreach ($all_mitra as $mtr): ?>
                        <option value="<?=$mtr['id']?>" <?=$filter_mitra==$mtr['id']?'selected':''?>>
                            <?=htmlspecialchars($mtr['nama_perusahaan'])?><?=$mtr['kota']?" - {$mtr['kota']}":''?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary text-sm py-2 px-4 flex items-center gap-1.5">
                <i class="fas fa-filter"></i> Filter
            </button>
            <?php if ($search_q || $filter_mitra): ?>
                <a href="penempatan.php" class="text-sm py-2 px-4 rounded-lg border hover:bg-gray-100 transition flex items-center gap-1.5">
                    <i class="fas fa-xmark"></i> Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabel -->
    <div class="dashboard-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background:var(--dashboard-bg-soft); border-bottom:1px solid var(--dashboard-border);">
                        <th class="text-left px-4 py-3 font-semibold">#</th>
                        <th class="text-left px-4 py-3 font-semibold">Mahasiswa</th>
                        <th class="text-left px-4 py-3 font-semibold">Tempat PKL</th>
                        <th class="text-left px-4 py-3 font-semibold">Pembimbing Lapangan</th>
                        <th class="text-left px-4 py-3 font-semibold">Periode</th>
                        <th class="text-left px-4 py-3 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mahasiswa_list)): ?>
                        <tr><td colspan="6" class="text-center py-12">
                            <i class="fas fa-users text-4xl mb-3 block opacity-20"></i>
                            <p class="font-medium">Tidak ada data mahasiswa.</p>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($mahasiswa_list as $i => $mhs): ?>
                        <tr class="border-b hover:bg-gray-50 transition" style="border-color:var(--dashboard-border);">
                            <td class="px-4 py-3 text-xs" style="color:var(--dashboard-muted);"><?=$i+1?></td>
                            <td class="px-4 py-3">
                                <p class="font-semibold"><?=htmlspecialchars($mhs['nama'])?></p>
                                <p class="text-xs" style="color:var(--dashboard-muted);">
                                    <?=htmlspecialchars($mhs['nim'] ?? $mhs['username'])?>
                                    &bull; <?=htmlspecialchars($mhs['prodi_display'])?>
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($mhs['nama_perusahaan']): ?>
                                    <p class="font-medium"><?=htmlspecialchars($mhs['nama_perusahaan'])?></p>
                                    <p class="text-xs" style="color:var(--dashboard-muted);">
                                        <?=htmlspecialchars($mhs['kota'] ?: '')?>
                                        <?php if ($mhs['bidang_usaha']): ?>
                                            &bull; <?=htmlspecialchars($mhs['bidang_usaha'])?>
                                        <?php endif; ?>
                                    </p>
                                <?php else: ?>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 border border-amber-200">
                                        <i class="fas fa-clock mr-1"></i>Belum Ditempatkan
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <?php if ($mhs['nama_pembimbing_lapangan']): ?>
                                    <p><?=htmlspecialchars($mhs['nama_pembimbing_lapangan'])?></p>
                                    <p class="text-xs" style="color:var(--dashboard-muted);"><?=htmlspecialchars($mhs['jabatan_pembimbing'] ?: '')?></p>
                                    <?php if ($mhs['no_telp_pembimbing']): ?>
                                        <p class="text-xs text-blue-600 mt-0.5">
                                            <i class="fas fa-phone mr-1"></i><?=htmlspecialchars($mhs['no_telp_pembimbing'])?>
                                        </p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--dashboard-muted);">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <?php if ($mhs['tanggal_mulai'] || $mhs['tanggal_selesai']): ?>
                                    <p class="text-green-700"><i class="fas fa-play mr-1"></i><?=($mhs['tanggal_mulai'] ? date('d M Y', strtotime($mhs['tanggal_mulai'])) : '-')?></p>
                                    <p class="text-red-600 mt-0.5"><i class="fas fa-stop mr-1"></i><?=($mhs['tanggal_selesai'] ? date('d M Y', strtotime($mhs['tanggal_selesai'])) : '-')?></p>
                                <?php else: ?>
                                    <span style="color:var(--dashboard-muted);">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <button onclick="openPenempatanModal(<?=htmlspecialchars(json_encode($mhs))?>)"
                                            class="p-1.5 rounded <?=$mhs['id_penempatan'] ? 'text-blue-500 hover:bg-blue-50' : 'text-green-500 hover:bg-green-50'?> transition"
                                            title="<?=$mhs['id_penempatan'] ? 'Edit Penempatan' : 'Tambah Penempatan'?>">
                                        <i class="fas <?=$mhs['id_penempatan'] ? 'fa-pen' : 'fa-plus'?> text-sm"></i>
                                    </button>
                                    <?php if ($mhs['id_penempatan']): ?>
                                    <form method="POST" class="inline"
                                          onsubmit="return confirm('Hapus data penempatan mahasiswa ini?')">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id_penempatan" value="<?=$mhs['id_penempatan']?>">
                                        <button type="submit" class="p-1.5 rounded text-red-500 hover:bg-red-50 transition" title="Hapus">
                                            <i class="fas fa-trash-can text-sm"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($mahasiswa_list)): ?>
            <div class="px-4 py-3 text-xs" style="color:var(--dashboard-muted); border-top:1px solid var(--dashboard-border);">
                Menampilkan <?=count($mahasiswa_list)?> mahasiswa
            </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- ===== MODAL PENEMPATAN ===== -->
<div id="modal-penempatan" class="fixed inset-0 z-50 hidden items-center justify-center p-4"
     style="background:rgba(0,0,0,0.5);" onclick="if(event.target===this)hideModal('modal-penempatan')">
    <div class="dashboard-card w-full max-w-xl max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-lg flex items-center gap-2" id="modal-penempatan-title">
                <i class="fas fa-map-pin text-green-500"></i>
                <span>Penempatan PKL</span>
            </h3>
            <button onclick="hideModal('modal-penempatan')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-xmark text-xl"></i>
            </button>
        </div>

        <!-- Info Mahasiswa -->
        <div id="modal-mhs-info" class="mb-4 p-3 rounded-lg bg-blue-50 border border-blue-200 text-sm text-blue-800"></div>

        <form method="POST" id="form-penempatan">
            <input type="hidden" name="action" value="simpan">
            <input type="hidden" name="id_mahasiswa" id="pmp_id_mahasiswa">

            <div class="flex flex-col gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Mitra / Tempat PKL <span class="text-red-500">*</span></label>
                    <select name="id_mitra" id="pmp_id_mitra" required class="form-select w-full">
                        <option value="">— Pilih Mitra PKL —</option>
                        <?php foreach ($mitra_options as $mtr): ?>
                            <option value="<?=$mtr['id']?>">
                                <?=htmlspecialchars($mtr['nama_perusahaan'])?><?=$mtr['kota'] ? " — {$mtr['kota']}" : ''?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs mt-1" style="color:var(--dashboard-muted);">
                        Hanya mitra berstatus <strong>Aktif</strong> yang ditampilkan.
                        <a href="mitra.php" class="text-blue-500 hover:underline">Kelola Mitra</a>
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Tanggal Mulai</label>
                        <input type="date" name="tanggal_mulai" id="pmp_tgl_mulai" class="form-input w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Tanggal Selesai</label>
                        <input type="date" name="tanggal_selesai" id="pmp_tgl_sel" class="form-input w-full">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Nama Pembimbing Lapangan</label>
                    <input type="text" name="nama_pembimbing_lapangan" id="pmp_nama_pbl"
                           maxlength="100" class="form-input w-full"
                           placeholder="Nama PIC / mentor di perusahaan">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Jabatan Pembimbing</label>
                        <input type="text" name="jabatan_pembimbing" id="pmp_jab_pbl"
                               maxlength="100" class="form-input w-full" placeholder="Supervisor, Manager...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">No. Telepon Pembimbing</label>
                        <input type="text" name="no_telp_pembimbing" id="pmp_telp_pbl"
                               maxlength="20" class="form-input w-full" placeholder="08xx-xxxx-xxxx">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Keterangan</label>
                    <textarea name="keterangan" id="pmp_ket" rows="2"
                              class="form-input w-full text-sm" placeholder="Catatan tambahan..."></textarea>
                </div>
            </div>

            <div class="flex gap-3 mt-5 pt-4 border-t" style="border-color:var(--dashboard-border);">
                <button type="submit" class="btn-primary flex items-center gap-2 px-5 py-2.5">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <button type="button" onclick="hideModal('modal-penempatan')"
                        class="px-5 py-2.5 rounded-lg border hover:bg-gray-100 transition">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function showModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
function hideModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }

function openPenempatanModal(d) {
    document.getElementById('pmp_id_mahasiswa').value = d.id;
    document.getElementById('pmp_id_mitra').value     = d.id_mitra ?? '';
    document.getElementById('pmp_tgl_mulai').value    = d.tanggal_mulai ?? '';
    document.getElementById('pmp_tgl_sel').value      = d.tanggal_selesai ?? '';
    document.getElementById('pmp_nama_pbl').value     = d.nama_pembimbing_lapangan ?? '';
    document.getElementById('pmp_jab_pbl').value      = d.jabatan_pembimbing ?? '';
    document.getElementById('pmp_telp_pbl').value     = d.no_telp_pembimbing ?? '';
    document.getElementById('pmp_ket').value           = d.keterangan ?? '';
    document.getElementById('modal-mhs-info').innerHTML =
        `<i class="fas fa-user-graduate mr-2"></i><strong>${d.nama}</strong> &bull; NIM: ${d.nim ?? d.username} &bull; ${d.prodi_display}`;
    document.querySelector('#modal-penempatan-title span').textContent =
        d.id_penempatan ? 'Edit Penempatan PKL' : 'Tambah Penempatan PKL';
    showModal('modal-penempatan');
}
</script>

<?php include '../includes/footer.php'; ?>
