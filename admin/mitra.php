<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'superadmin'])) {
    header("Location: ../index.php"); exit();
}
require_once __DIR__ . '/../config/db.php';

$page_title = 'Master Data Mitra PKL - Admin';
$feedback = ''; $feedback_type = 'success';

// ---- Helpers ----
function mitra_esc(mysqli $c, $v): string { return $c->real_escape_string(trim($v ?? '')); }

// ---- DELETE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus') {
    $id = (int)$_POST['id_mitra'];
    // Cek apakah masih dipakai di penempatan
    $used = $conn->query("SELECT COUNT(*) AS n FROM penempatan_pkl WHERE id_mitra=$id")->fetch_assoc()['n'];
    if ($used > 0) {
        $feedback = "Mitra tidak dapat dihapus karena masih digunakan oleh $used mahasiswa.";
        $feedback_type = 'error';
    } else {
        $conn->query("DELETE FROM mitra_pkl WHERE id=$id");
        $feedback = 'Data mitra berhasil dihapus.';
    }
}

// ---- TOGGLE STATUS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    $id  = (int)$_POST['id_mitra'];
    $cur = $conn->query("SELECT status FROM mitra_pkl WHERE id=$id")->fetch_assoc()['status'] ?? 'aktif';
    $new = ($cur === 'aktif') ? 'tidak_aktif' : 'aktif';
    $conn->query("UPDATE mitra_pkl SET status='$new' WHERE id=$id");
    $feedback = "Status mitra berhasil diubah menjadi " . ($new === 'aktif' ? 'Aktif' : 'Tidak Aktif') . ".";
}

// ---- SIMPAN (ADD / EDIT) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['tambah','edit'])) {
    $is_edit = ($_POST['action'] === 'edit');
    $id_edit = $is_edit ? (int)$_POST['id_mitra'] : 0;

    $nama   = mitra_esc($conn, $_POST['nama_perusahaan'] ?? '');
    $alamat = mitra_esc($conn, $_POST['alamat'] ?? '');
    $kota   = mitra_esc($conn, $_POST['kota'] ?? '');
    $prov   = mitra_esc($conn, $_POST['provinsi'] ?? '');
    $bidang = mitra_esc($conn, $_POST['bidang_usaha'] ?? '');
    $pic    = mitra_esc($conn, $_POST['nama_pic'] ?? '');
    $jabatan= mitra_esc($conn, $_POST['jabatan_pic'] ?? '');
    $telp   = mitra_esc($conn, $_POST['no_telepon'] ?? '');
    $email  = mitra_esc($conn, $_POST['email'] ?? '');
    $web    = mitra_esc($conn, $_POST['website'] ?? '');
    $status = ($_POST['status'] ?? 'aktif') === 'aktif' ? 'aktif' : 'tidak_aktif';

    if (empty($nama)) {
        $feedback = 'Nama perusahaan/instansi tidak boleh kosong.';
        $feedback_type = 'error';
    } else {
        if ($is_edit) {
            $conn->query("UPDATE mitra_pkl SET
                nama_perusahaan='$nama', alamat='$alamat', kota='$kota', provinsi='$prov',
                bidang_usaha='$bidang', nama_pic='$pic', jabatan_pic='$jabatan',
                no_telepon='$telp', email='$email', website='$web', status='$status'
                WHERE id=$id_edit");
            $feedback = 'Data mitra berhasil diperbarui.';
        } else {
            $conn->query("INSERT INTO mitra_pkl
                (nama_perusahaan,alamat,kota,provinsi,bidang_usaha,nama_pic,jabatan_pic,no_telepon,email,website,status)
                VALUES ('$nama','$alamat','$kota','$prov','$bidang','$pic','$jabatan','$telp','$email','$web','$status')");
            $feedback = 'Mitra PKL baru berhasil ditambahkan.';
        }
    }
}

// ---- Ambil data edit jika ada ----
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_data = $conn->query("SELECT * FROM mitra_pkl WHERE id=" . (int)$_GET['edit'])->fetch_assoc();
}

// ---- Filter & Query ----
$search_q      = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$where = ["1=1"];
if ($search_q) $where[] = "(nama_perusahaan LIKE '%" . $conn->real_escape_string($search_q) . "%' OR kota LIKE '%" . $conn->real_escape_string($search_q) . "%' OR bidang_usaha LIKE '%" . $conn->real_escape_string($search_q) . "%')";
if (in_array($filter_status, ['aktif','tidak_aktif'])) $where[] = "status='$filter_status'";

$mitra_list = $conn->query("
    SELECT m.*, (SELECT COUNT(*) FROM penempatan_pkl p WHERE p.id_mitra=m.id) AS jml_mahasiswa
    FROM mitra_pkl m
    WHERE " . implode(' AND ', $where) . "
    ORDER BY m.nama_perusahaan ASC
")->fetch_all(MYSQLI_ASSOC);

// Statistik
$stats = $conn->query("SELECT COUNT(*) AS total, SUM(status='aktif') AS aktif, SUM(status='tidak_aktif') AS nonaktif FROM mitra_pkl")->fetch_assoc();

include '../includes/header.php';
?>

<div class="min-w-0">
<div class="dashboard-page w-full max-w-full">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                <i class="fas fa-building text-blue-600"></i> Master Data Mitra PKL
            </h2>
            <p class="text-sm mt-1" style="color:var(--dashboard-muted);">
                Kelola data instansi/perusahaan mitra tempat mahasiswa melakukan PKL.
            </p>
        </div>
        <button onclick="showModal('modal-tambah')"
                class="btn-primary flex items-center gap-2 px-4 py-2.5 shrink-0">
            <i class="fas fa-plus"></i> Tambah Mitra
        </button>
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
            ['Total Mitra', $stats['total'], 'fa-building', 'text-blue-600','bg-blue-50'],
            ['Aktif', $stats['aktif'], 'fa-circle-check','text-green-600','bg-green-50'],
            ['Tidak Aktif', $stats['nonaktif'],'fa-circle-xmark','text-red-600','bg-red-50'],
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
                <label class="block text-xs font-medium mb-1">Cari Nama / Kota / Bidang</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input type="text" name="search" value="<?=htmlspecialchars($search_q)?>"
                           placeholder="Ketik untuk mencari..." class="form-input w-full pl-8 text-sm py-2">
                </div>
            </div>
            <div class="min-w-36">
                <label class="block text-xs font-medium mb-1">Status</label>
                <select name="status" class="form-select w-full text-sm py-2">
                    <option value="">Semua Status</option>
                    <option value="aktif" <?=$filter_status==='aktif'?'selected':''?>>Aktif</option>
                    <option value="tidak_aktif" <?=$filter_status==='tidak_aktif'?'selected':''?>>Tidak Aktif</option>
                </select>
            </div>
            <button type="submit" class="btn-primary text-sm py-2 px-4 flex items-center gap-1.5">
                <i class="fas fa-filter"></i> Filter
            </button>
            <?php if ($search_q || $filter_status): ?>
                <a href="mitra.php" class="text-sm py-2 px-4 rounded-lg border hover:bg-gray-100 transition flex items-center gap-1.5">
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
                        <th class="text-left px-4 py-3 font-semibold">Nama Instansi / Perusahaan</th>
                        <th class="text-left px-4 py-3 font-semibold">Bidang Usaha</th>
                        <th class="text-left px-4 py-3 font-semibold">Kota</th>
                        <th class="text-left px-4 py-3 font-semibold">PIC / Kontak</th>
                        <th class="text-left px-4 py-3 font-semibold">Mahasiswa</th>
                        <th class="text-left px-4 py-3 font-semibold">Status</th>
                        <th class="text-left px-4 py-3 font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mitra_list)): ?>
                        <tr><td colspan="8" class="text-center py-12">
                            <i class="fas fa-building text-4xl mb-3 block opacity-20"></i>
                            <p class="font-medium">Belum ada data mitra.</p>
                            <button onclick="showModal('modal-tambah')" class="btn-primary text-sm mt-3 px-4 py-2">
                                <i class="fas fa-plus mr-1"></i> Tambah Mitra Pertama
                            </button>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($mitra_list as $i => $m): ?>
                        <tr class="border-b hover:bg-gray-50 transition" style="border-color:var(--dashboard-border);">
                            <td class="px-4 py-3 text-xs" style="color:var(--dashboard-muted);"><?=$i+1?></td>
                            <td class="px-4 py-3">
                                <p class="font-semibold"><?=htmlspecialchars($m['nama_perusahaan'])?></p>
                                <?php if ($m['website']): ?>
                                    <a href="<?=htmlspecialchars($m['website'])?>" target="_blank"
                                       class="text-xs text-blue-500 hover:underline"><?=htmlspecialchars($m['website'])?></a>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm"><?=htmlspecialchars($m['bidang_usaha'] ?: '-')?></td>
                            <td class="px-4 py-3 text-sm">
                                <?=htmlspecialchars($m['kota'] ?: '-')?>
                                <?php if ($m['provinsi']): ?>
                                    <br><span class="text-xs" style="color:var(--dashboard-muted);"><?=htmlspecialchars($m['provinsi'])?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <?php if ($m['nama_pic']): ?>
                                    <p><?=htmlspecialchars($m['nama_pic'])?></p>
                                    <?php if ($m['jabatan_pic']): ?>
                                        <p class="text-xs" style="color:var(--dashboard-muted);"><?=htmlspecialchars($m['jabatan_pic'])?></p>
                                    <?php endif; ?>
                                    <?php if ($m['no_telepon']): ?>
                                        <p class="text-xs mt-0.5 text-blue-600">
                                            <i class="fas fa-phone mr-1"></i><?=htmlspecialchars($m['no_telepon'])?>
                                        </p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--dashboard-muted);">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-bold text-blue-600"><?=(int)$m['jml_mahasiswa']?></span>
                                <p class="text-xs" style="color:var(--dashboard-muted);">mahasiswa</p>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($m['status'] === 'aktif'): ?>
                                    <span class="text-xs px-2 py-0.5 rounded-full border bg-green-100 text-green-700 border-green-200">
                                        <i class="fas fa-circle-check mr-1"></i>Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs px-2 py-0.5 rounded-full border bg-gray-100 text-gray-500 border-gray-200">
                                        <i class="fas fa-circle-xmark mr-1"></i>Tidak Aktif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <!-- Edit -->
                                    <button onclick="openEditModal(<?=htmlspecialchars(json_encode($m))?>)"
                                            class="p-1.5 rounded text-blue-500 hover:bg-blue-50 transition" title="Edit">
                                        <i class="fas fa-pen text-sm"></i>
                                    </button>
                                    <!-- Toggle Status -->
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id_mitra" value="<?=$m['id']?>">
                                        <button type="submit"
                                                class="p-1.5 rounded <?=$m['status']==='aktif'?'text-amber-500 hover:bg-amber-50':'text-green-500 hover:bg-green-50'?> transition"
                                                title="<?=$m['status']==='aktif'?'Nonaktifkan':'Aktifkan'?>">
                                            <i class="fas <?=$m['status']==='aktif'?'fa-toggle-off':'fa-toggle-on'?> text-sm"></i>
                                        </button>
                                    </form>
                                    <!-- Hapus -->
                                    <form method="POST" class="inline"
                                          onsubmit="return confirm('Hapus mitra \'<?=addslashes($m['nama_perusahaan'])?>\'?')">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id_mitra" value="<?=$m['id']?>">
                                        <button type="submit"
                                                class="p-1.5 rounded text-red-500 hover:bg-red-50 transition" title="Hapus">
                                            <i class="fas fa-trash-can text-sm"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($mitra_list)): ?>
            <div class="px-4 py-3 text-xs" style="color:var(--dashboard-muted); border-top:1px solid var(--dashboard-border);">
                Menampilkan <?=count($mitra_list)?> mitra
            </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- ===== MODAL TAMBAH ===== -->
<div id="modal-tambah" class="fixed inset-0 z-50 hidden items-center justify-center p-4"
     style="background:rgba(0,0,0,0.5);" onclick="if(event.target===this)hideModal('modal-tambah')">
    <div class="dashboard-card w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <i class="fas fa-plus-circle text-blue-500"></i> Tambah Mitra PKL
            </h3>
            <button onclick="hideModal('modal-tambah')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-xmark text-xl"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="tambah">
            <?php echo mitra_form_fields($conn); ?>
            <div class="flex gap-3 mt-5 pt-4 border-t" style="border-color:var(--dashboard-border);">
                <button type="submit" class="btn-primary flex items-center gap-2 px-5 py-2.5">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <button type="button" onclick="hideModal('modal-tambah')"
                        class="px-5 py-2.5 rounded-lg border hover:bg-gray-100 transition">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== MODAL EDIT ===== -->
<div id="modal-edit" class="fixed inset-0 z-50 hidden items-center justify-center p-4"
     style="background:rgba(0,0,0,0.5);" onclick="if(event.target===this)hideModal('modal-edit')">
    <div class="dashboard-card w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <i class="fas fa-pen text-blue-500"></i> Edit Mitra PKL
            </h3>
            <button onclick="hideModal('modal-edit')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-xmark text-xl"></i>
            </button>
        </div>
        <form method="POST" id="form-edit">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_mitra" id="edit_id_mitra">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Nama Perusahaan / Instansi <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_perusahaan" id="edit_nama" required maxlength="255" class="form-input w-full">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Alamat Lengkap</label>
                    <textarea name="alamat" id="edit_alamat" rows="2" class="form-input w-full text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Kota / Kabupaten</label>
                    <input type="text" name="kota" id="edit_kota" maxlength="100" class="form-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Provinsi</label>
                    <input type="text" name="provinsi" id="edit_provinsi" maxlength="100" class="form-input w-full">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Bidang Usaha / Sektor</label>
                    <input type="text" name="bidang_usaha" id="edit_bidang_usaha" maxlength="150" class="form-input w-full"
                           placeholder="Contoh: Teknologi Informasi, Manufaktur, Perbankan...">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Nama PIC / HRD</label>
                    <input type="text" name="nama_pic" id="edit_nama_pic" maxlength="100" class="form-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Jabatan PIC</label>
                    <input type="text" name="jabatan_pic" id="edit_jabatan_pic" maxlength="100" class="form-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">No. Telepon / WhatsApp</label>
                    <input type="text" name="no_telepon" id="edit_no_telepon" maxlength="20" class="form-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" name="email" id="edit_email" maxlength="150" class="form-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Website</label>
                    <input type="url" name="website" id="edit_website" maxlength="255" class="form-input w-full"
                           placeholder="https://...">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Status Kerjasama</label>
                    <select name="status" id="edit_status" class="form-select w-full">
                        <option value="aktif">Aktif</option>
                        <option value="tidak_aktif">Tidak Aktif</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3 mt-5 pt-4 border-t" style="border-color:var(--dashboard-border);">
                <button type="submit" class="btn-primary flex items-center gap-2 px-5 py-2.5">
                    <i class="fas fa-save"></i> Perbarui
                </button>
                <button type="button" onclick="hideModal('modal-edit')"
                        class="px-5 py-2.5 rounded-lg border hover:bg-gray-100 transition">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function showModal(id) { document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('flex'); }
function hideModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).classList.remove('flex'); }

function openEditModal(data) {
    document.getElementById('edit_id_mitra').value  = data.id;
    document.getElementById('edit_nama').value       = data.nama_perusahaan ?? '';
    document.getElementById('edit_alamat').value     = data.alamat ?? '';
    document.getElementById('edit_kota').value       = data.kota ?? '';
    document.getElementById('edit_provinsi').value   = data.provinsi ?? '';
    document.getElementById('edit_bidang_usaha').value = data.bidang_usaha ?? '';
    document.getElementById('edit_nama_pic').value   = data.nama_pic ?? '';
    document.getElementById('edit_jabatan_pic').value= data.jabatan_pic ?? '';
    document.getElementById('edit_no_telepon').value = data.no_telepon ?? '';
    document.getElementById('edit_email').value      = data.email ?? '';
    document.getElementById('edit_website').value    = data.website ?? '';
    document.getElementById('edit_status').value     = data.status ?? 'aktif';
    showModal('modal-edit');
}

<?php if ($feedback && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
// Auto-scroll to feedback on form submit
window.scrollTo({top:0, behavior:'smooth'});
<?php endif; ?>
</script>

<?php
// Helper function untuk form fields modal tambah
function mitra_form_fields($conn): string {
    return '
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Nama Perusahaan / Instansi <span class="text-red-500">*</span></label>
            <input type="text" name="nama_perusahaan" required maxlength="255"
                   placeholder="Contoh: PT. Teknologi Maju Indonesia" class="form-input w-full">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Alamat Lengkap</label>
            <textarea name="alamat" rows="2" class="form-input w-full text-sm"
                      placeholder="Jl. Contoh No.1, Kecamatan, Kabupaten..."></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Kota / Kabupaten</label>
            <input type="text" name="kota" maxlength="100" class="form-input w-full" placeholder="Surabaya">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Provinsi</label>
            <input type="text" name="provinsi" maxlength="100" class="form-input w-full" placeholder="Jawa Timur">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium mb-1">Bidang Usaha / Sektor</label>
            <input type="text" name="bidang_usaha" maxlength="150" class="form-input w-full"
                   placeholder="Teknologi Informasi, Manufaktur, Perbankan...">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Nama PIC / HRD</label>
            <input type="text" name="nama_pic" maxlength="100" class="form-input w-full">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Jabatan PIC</label>
            <input type="text" name="jabatan_pic" maxlength="100" class="form-input w-full"
                   placeholder="HR Manager, Kepala Personalia...">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">No. Telepon / WhatsApp</label>
            <input type="text" name="no_telepon" maxlength="20" class="form-input w-full" placeholder="08xx-xxxx-xxxx">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" maxlength="150" class="form-input w-full">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Website</label>
            <input type="url" name="website" maxlength="255" class="form-input w-full" placeholder="https://...">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Status Kerjasama</label>
            <select name="status" class="form-select w-full">
                <option value="aktif">Aktif</option>
                <option value="tidak_aktif">Tidak Aktif</option>
            </select>
        </div>
    </div>';
}

include '../includes/footer.php';
?>
