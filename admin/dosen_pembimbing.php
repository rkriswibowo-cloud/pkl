<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/prodi_helper.php';

$feedback = '';

// Handle Sinkronisasi Dosen dari OBE
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_dosen_obe') {
    $res = sync_dosen_from_obe($conn);
    if ($res['status']) {
        $feedback = "<script>
            Swal.fire({
                icon: 'success',
                title: 'Sinkronisasi Berhasil!',
                text: '{$res['message']}',
                timer: 2200,
                showConfirmButton: false
            }).then(() => { window.location.href = 'dosen_pembimbing.php'; });
        </script>";
    } else {
        $feedback = "<script>Swal.fire({icon: 'error', title: 'Gagal Sinkronisasi', text: '{$res['message']}'});</script>";
    }
}

// Ambil filter prodi
$filter_prodi = trim($_GET['prodi'] ?? '');

// Ambil list prodi untuk opsi filter
$prodi_options = $conn->query("
    SELECT DISTINCT p.nama 
    FROM program_studi p 
    WHERE p.status = 'ACTIVE' 
    ORDER BY p.nama ASC
");
$all_prodi_names = [];
if ($prodi_options) {
    while ($p_row = $prodi_options->fetch_assoc()) {
        $all_prodi_names[] = $p_row['nama'];
    }
}

// Query Dosen PKL (Dosen Kampus)
$sql_pkl = "
    SELECT 
        u.id, 
        u.nama, 
        u.username, 
        u.prodi, 
        d.nip, 
        d.bidang, 
        COUNT(b.id_mahasiswa) as jumlah_mahasiswa
    FROM users u
    LEFT JOIN dosen d ON u.id = d.id
    LEFT JOIN bimbingan b ON u.id = b.id_dospem_pkl
    WHERE u.role = 'dosen_pkl'
";
if (!empty($filter_prodi)) {
    $safe_filter = $conn->real_escape_string($filter_prodi);
    $sql_pkl .= " AND (u.prodi = '{$safe_filter}' OR d.bidang = '{$safe_filter}')";
}
$sql_pkl .= " GROUP BY u.id ORDER BY u.prodi ASC, u.nama ASC";
$dosen_pkl_list = $conn->query($sql_pkl);

// Query Dosen Lapangan (Pembimbing Lapangan)
$dosen_lapangan_list = $conn->query("
    SELECT 
        u.id, 
        u.nama, 
        u.username, 
        u.phone, 
        COUNT(b.id_mahasiswa) as jumlah_mahasiswa
    FROM users u
    LEFT JOIN bimbingan b ON u.id = b.id_dospem_lapangan
    WHERE u.role = 'dosen_lapangan'
    GROUP BY u.id
    ORDER BY u.nama ASC
");

// Total hitungan statistik
$total_pkl_dosen = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'dosen_pkl'")->fetch_assoc()['total'] ?? 0;
$total_lapangan = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'dosen_lapangan'")->fetch_assoc()['total'] ?? 0;
$total_bimbingan = $conn->query("SELECT COUNT(*) as total FROM bimbingan")->fetch_assoc()['total'] ?? 0;

$page_title = 'Master Dosen Pembimbing - Logbook PKL';
include __DIR__ . '/../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?= $feedback ?>

<div class="dashboard-page w-full max-w-full">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="dashboard.php" class="text-xs font-semibold px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 dark:bg-neutral-800 dark:hover:bg-neutral-700 transition" style="color: var(--dashboard-muted);">
                    <i class="fas fa-arrow-left mr-1"></i> Dashboard
                </a>
            </div>
            <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                <i class="fas fa-chalkboard-user text-blue-600" aria-hidden="true"></i>
                Master Dosen Pembimbing
            </h2>
            <p class="text-sm mt-1" style="color: var(--dashboard-muted);">
                Data Dosen Pembimbing Kampus (Dosen PKL) tersinkronisasi dari Master Dosen Proyek OBE.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <form action="dosen_pembimbing.php" method="POST" onsubmit="return confirm('Tarik dan sinkronkan master dosen terbaru dari database proyek OBE?')">
                <input type="hidden" name="action" value="sync_dosen_obe">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow transition">
                    <i class="fas fa-rotate" aria-hidden="true"></i> Sinkronkan Dosen dari Proyek OBE
                </button>
            </form>
            <a href="bimbingan.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow transition">
                <i class="fas fa-sliders" aria-hidden="true"></i> Pengaturan Bimbingan
            </a>
            <a href="master_prodi.php" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                <i class="fas fa-graduation-cap" aria-hidden="true"></i> Master Prodi
            </a>
        </div>
    </div>

    <!-- Statistik Ringkas -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="dashboard-panel p-4 rounded-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center text-xl font-bold flex-shrink-0">
                <i class="fas fa-user-tie"></i>
            </div>
            <div>
                <span class="text-xs font-medium uppercase tracking-wider" style="color: var(--dashboard-muted);">Dosen Pembimbing PKL</span>
                <h4 class="text-2xl font-bold" style="color: var(--dashboard-text);"><?= (int)$total_pkl_dosen ?> <span class="text-xs font-normal" style="color: var(--dashboard-muted);">Dosen OBE</span></h4>
            </div>
        </div>

        <div class="dashboard-panel p-4 rounded-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center text-xl font-bold flex-shrink-0">
                <i class="fas fa-id-badge"></i>
            </div>
            <div>
                <span class="text-xs font-medium uppercase tracking-wider" style="color: var(--dashboard-muted);">Pembimbing Lapangan</span>
                <h4 class="text-2xl font-bold" style="color: var(--dashboard-text);"><?= (int)$total_lapangan ?> <span class="text-xs font-normal" style="color: var(--dashboard-muted);">Mentor</span></h4>
            </div>
        </div>

        <div class="dashboard-panel p-4 rounded-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center text-xl font-bold flex-shrink-0">
                <i class="fas fa-users-rectangle"></i>
            </div>
            <div>
                <span class="text-xs font-medium uppercase tracking-wider" style="color: var(--dashboard-muted);">Total Mahasiswa Dibimbing</span>
                <h4 class="text-2xl font-bold" style="color: var(--dashboard-text);"><?= (int)$total_bimbingan ?> <span class="text-xs font-normal" style="color: var(--dashboard-muted);">Plotting</span></h4>
            </div>
        </div>
    </div>

    <!-- Integrasi Info Banner -->
    <div class="p-4 sm:p-5 rounded-2xl mb-6 border" style="background: var(--dashboard-surface); border-color: var(--dashboard-border);">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                <i class="fas fa-network-wired text-lg"></i>
            </div>
            <div>
                <h4 class="text-sm font-bold" style="color: var(--dashboard-text);">Integrasi Master Dosen Proyek OBE</h4>
                <p class="text-xs mt-1 leading-relaxed" style="color: var(--dashboard-muted);">
                    Data dosen pembimbing kampus diambil dari database master OBE (<code class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-neutral-800 text-blue-600 font-mono text-[11px]">obe_cpl.users</code> dengan role Dosen & Kaprodi). 
                    Dosen dapat login ke sistem PKL menggunakan <strong>Username</strong> atau <strong>NIDN</strong> dengan kata sandi yang sama dari akun OBE mereka tanpa memerlukan registrasi ulang.
                </p>
            </div>
        </div>
    </div>

    <!-- Section 1: Dosen Pembimbing PKL (Kampus) -->
    <div class="dashboard-panel rounded-2xl mb-8 overflow-hidden">
        <div class="p-5 border-b flex flex-col md:flex-row md:items-center justify-between gap-4" style="border-color: var(--dashboard-border);">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold text-lg">
                    <i class="fas fa-chalkboard-user"></i>
                </span>
                <div>
                    <h3 class="font-bold text-lg" style="color: var(--dashboard-text);">Dosen Pembimbing PKL (Kampus)</h3>
                    <p class="text-xs" style="color: var(--dashboard-muted);">Dosen pembimbing akademik dari masing-masing Program Studi</p>
                </div>
            </div>

            <!-- Filter Program Studi -->
            <div class="flex items-center gap-2">
                <label for="filterProdi" class="text-xs font-medium" style="color: var(--dashboard-muted);">Filter Prodi:</label>
                <select id="filterProdi" class="text-xs px-3 py-1.5 rounded-lg border font-medium focus:ring-2 focus:ring-blue-500 focus:outline-none" 
                        style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);"
                        onchange="window.location.href = 'dosen_pembimbing.php' + (this.value ? '?prodi=' + encodeURIComponent(this.value) : '')">
                    <option value="">Semua Program Studi (<?= (int)$total_pkl_dosen ?>)</option>
                    <?php foreach ($all_prodi_names as $p_name): ?>
                        <option value="<?= htmlspecialchars($p_name) ?>" <?= $filter_prodi === $p_name ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p_name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="dashboard-table-scroll" tabindex="0">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b text-xs uppercase tracking-wider font-semibold" style="border-color: var(--dashboard-border); color: var(--dashboard-muted); background: var(--dashboard-surface-subtle, rgba(0,0,0,0.02));">
                        <th class="px-5 py-3 w-12 text-center">No</th>
                        <th class="px-5 py-3">Nama Lengkap Dosen</th>
                        <th class="px-5 py-3">NIDN / NIP</th>
                        <th class="px-5 py-3">Program Studi</th>
                        <th class="px-5 py-3">Username Login</th>
                        <th class="px-5 py-3 text-center">Mahasiswa Bimbingan</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="divide-color: var(--dashboard-border);">
                    <?php if ($dosen_pkl_list && $dosen_pkl_list->num_rows > 0): ?>
                        <?php $no = 1; while ($dosen = $dosen_pkl_list->fetch_assoc()): ?>
                            <tr class="hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                <td class="px-5 py-3.5 text-center font-medium text-xs" style="color: var(--dashboard-muted);"><?= $no++ ?></td>
                                <td class="px-5 py-3.5 font-semibold" style="color: var(--dashboard-text);">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                            <?= mb_substr(preg_replace('/[^a-zA-Z]/', '', $dosen['nama']), 0, 1) ?: 'D' ?>
                                        </div>
                                        <span><?= htmlspecialchars($dosen['nama']) ?></span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-xs" style="color: var(--dashboard-text);">
                                    <?= htmlspecialchars($dosen['nip'] ?: '-') ?>
                                </td>
                                <td class="px-5 py-3.5">
                                    <?php if (!empty($dosen['prodi'])): ?>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                            <i class="fas fa-graduation-cap mr-1 text-[10px]"></i> <?= htmlspecialchars($dosen['prodi']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs italic" style="color: var(--dashboard-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-xs" style="color: var(--dashboard-muted);">
                                    <?= htmlspecialchars($dosen['username']) ?>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-bold <?= (int)$dosen['jumlah_mahasiswa'] > 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-neutral-800 dark:text-neutral-400' ?>">
                                        <?= (int)$dosen['jumlah_mahasiswa'] ?> Mahasiswa
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-sm" style="color: var(--dashboard-muted);">
                                <i class="fas fa-circle-info text-2xl mb-2 opacity-50 block"></i>
                                Belum ada data dosen pembimbing untuk filter ini. Silakan klik tombol <strong>Sinkronkan Dosen dari Proyek OBE</strong>.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 2: Pembimbing Lapangan -->
    <div class="dashboard-panel rounded-2xl mb-8 overflow-hidden">
        <div class="p-5 border-b flex items-center justify-between" style="border-color: var(--dashboard-border);">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-lg">
                    <i class="fas fa-id-badge"></i>
                </span>
                <div>
                    <h3 class="font-bold text-lg" style="color: var(--dashboard-text);">Pembimbing Lapangan (Instansi / Industri)</h3>
                    <p class="text-xs" style="color: var(--dashboard-muted);">Mentor lapangan tempat mahasiswa melaksanakan Praktik Kerja Lapangan</p>
                </div>
            </div>
        </div>

        <div class="dashboard-table-scroll" tabindex="0">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b text-xs uppercase tracking-wider font-semibold" style="border-color: var(--dashboard-border); color: var(--dashboard-muted); background: var(--dashboard-surface-subtle, rgba(0,0,0,0.02));">
                        <th class="px-5 py-3 w-12 text-center">No</th>
                        <th class="px-5 py-3">Nama Pembimbing Lapangan</th>
                        <th class="px-5 py-3">Username / Akun</th>
                        <th class="px-5 py-3">Kontak / No. Telepon</th>
                        <th class="px-5 py-3 text-center">Mahasiswa Bimbingan</th>
                    </tr>
                </thead>
                <tbody class="divide-y" style="divide-color: var(--dashboard-border);">
                    <?php if ($dosen_lapangan_list && $dosen_lapangan_list->num_rows > 0): ?>
                        <?php $no = 1; while ($lapangan = $dosen_lapangan_list->fetch_assoc()): ?>
                            <tr class="hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                <td class="px-5 py-3.5 text-center font-medium text-xs" style="color: var(--dashboard-muted);"><?= $no++ ?></td>
                                <td class="px-5 py-3.5 font-semibold" style="color: var(--dashboard-text);">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                            <?= mb_substr(preg_replace('/[^a-zA-Z]/', '', $lapangan['nama']), 0, 1) ?: 'L' ?>
                                        </div>
                                        <span><?= htmlspecialchars($lapangan['nama']) ?></span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-xs" style="color: var(--dashboard-muted);">
                                    <?= htmlspecialchars($lapangan['username']) ?>
                                </td>
                                <td class="px-5 py-3.5 text-xs" style="color: var(--dashboard-text);">
                                    <?= htmlspecialchars($lapangan['phone'] ?: '-') ?>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-bold <?= (int)$lapangan['jumlah_mahasiswa'] > 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-neutral-800 dark:text-neutral-400' ?>">
                                        <?= (int)$lapangan['jumlah_mahasiswa'] ?> Mahasiswa
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-sm" style="color: var(--dashboard-muted);">
                                <i class="fas fa-circle-info text-2xl mb-2 opacity-50 block"></i>
                                Belum ada dosen pembimbing lapangan terdaftar.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
