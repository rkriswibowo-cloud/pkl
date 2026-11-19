<?php
session_start();

// Cek apakah user sudah login dan role adalah admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';

$page_title = 'Edit Logo & Favicon - Admin Logbook PKL';
$success_msg = '';
$error_msg = '';

$upload_dir = __DIR__ . '/../assets/uploads/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0775, true);
}

// Handle Reset ke Default
if (isset($_GET['reset'])) {
    $reset_key = trim($_GET['reset']);
    $default_maps = [
        'app_logo'           => 'assets/images/dashboard-logo-uam.png',
        'app_favicon'        => 'assets/images/dashboard-logo-uam.png',
        'app_logo_login'     => 'assets/images/uam1.png',
        'app_logo_secondary' => 'assets/images/dikti.png',
    ];

    if (array_key_exists($reset_key, $default_maps)) {
        update_setting($reset_key, $default_maps[$reset_key]);
        $success_msg = "Item berhasil dikembalikan ke pengaturan standar bawaan!";
    }
}

// Handle Simpan Pengaturan & Upload File
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $allowed_exts = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'ico'];
    $max_size = 3 * 1024 * 1024; // 3MB

    // Update Identitas Teks
    if (isset($_POST['app_name'])) {
        $name = trim($_POST['app_name']);
        if (!empty($name)) {
            update_setting('app_name', $name);
        }
    }
    if (isset($_POST['app_desc'])) {
        $desc = trim($_POST['app_desc']);
        update_setting('app_desc', $desc);
    }

    $files_to_process = [
        'app_logo'           => 'logo_header',
        'app_favicon'        => 'favicon',
        'app_logo_login'     => 'logo_login',
        'app_logo_secondary' => 'logo_sekunder'
    ];

    $uploaded_count = 0;

    foreach ($files_to_process as $setting_key => $prefix) {
        if (isset($_FILES[$setting_key]) && $_FILES[$setting_key]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$setting_key];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed_exts)) {
                $error_msg = "Format file untuk {$setting_key} tidak didukung. Harap gunakan format PNG, JPG, WEBP, SVG, atau ICO.";
                break;
            }

            if ($file['size'] > $max_size) {
                $error_msg = "Ukuran file untuk {$setting_key} melebihi batas maksimal 3MB.";
                break;
            }

            $filename = $prefix . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $target_path = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                update_setting($setting_key, 'assets/uploads/' . $filename);
                $uploaded_count++;
            } else {
                $error_msg = "Gagal mengunggah file {$setting_key}. Periksa izin tulis folder uploads.";
                break;
            }
        }
    }

    if (empty($error_msg)) {
        $success_msg = "Pengaturan logo dan favicon berhasil diperbarui!";
    }
}

// Ambil nilai terbaru
$app_logo           = get_setting('app_logo', 'assets/images/dashboard-logo-uam.png');
$app_favicon        = get_setting('app_favicon', 'assets/images/dashboard-logo-uam.png');
$app_logo_login     = get_setting('app_logo_login', 'assets/images/uam1.png');
$app_logo_secondary = get_setting('app_logo_secondary', 'assets/images/dikti.png');
$app_name           = get_setting('app_name', 'Logbook PKL');
$app_desc           = get_setting('app_desc', 'Sistem Monitoring Praktik Kerja Lapangan');

include '../includes/header.php';
?>

<div class="min-w-0">
    <div class="dashboard-page w-full max-w-full">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="dashboard-title text-2xl sm:text-3xl font-bold flex items-center gap-3">
                    <i class="fas fa-palette text-blue-600" aria-hidden="true"></i>
                    Edit Logo & Favicon
                </h2>
                <p class="text-sm mt-1" style="color: var(--dashboard-muted);">
                    Sesuaikan logo navbar, ikon favicon tab browser, logo halaman login, dan identitas sistem.
                </p>
            </div>
            <a href="dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>

        <!-- Alert Notifikasi -->
        <?php if (!empty($success_msg)): ?>
            <div class="mb-6 p-4 rounded-xl flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-500">
                <i class="fas fa-circle-check text-xl flex-shrink-0"></i>
                <div class="text-sm font-medium"><?= htmlspecialchars($success_msg) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="mb-6 p-4 rounded-xl flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-500">
                <i class="fas fa-circle-exclamation text-xl flex-shrink-0"></i>
                <div class="text-sm font-medium"><?= htmlspecialchars($error_msg) ?></div>
            </div>
        <?php endif; ?>

        <!-- Form Pengaturan -->
        <form action="edit_logo.php" method="POST" enctype="multipart/form-data">
            
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">

                <!-- 1. Logo Navbar / Dashboard -->
                <div class="dashboard-panel p-6 rounded-2xl flex flex-col justify-between" style="background: var(--dashboard-surface); border: 1px solid var(--dashboard-border);">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-blue-500/10 text-blue-500 border border-blue-500/20">
                                <i class="fas fa-image"></i> Header Navbar
                            </span>
                            <a href="?reset=app_logo" onclick="return confirm('Kembalikan logo header ke default?')" class="text-xs text-red-400 hover:text-red-500 transition" title="Reset ke default">
                                <i class="fas fa-rotate-left"></i> Reset Default
                            </a>
                        </div>

                        <h3 class="text-base font-bold mb-1" style="color: var(--dashboard-text);">Logo Dashboard (Navbar)</h3>
                        <p class="text-xs mb-4" style="color: var(--dashboard-muted);">Ditampilkan di sudut kiri bilah navigasi atas di seluruh halaman sistem.</p>

                        <!-- Preview Area -->
                        <div class="flex items-center justify-center p-6 rounded-xl border border-dashed mb-4" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                            <div class="w-16 h-16 rounded-xl bg-white flex items-center justify-center p-2 shadow overflow-hidden">
                                <img id="preview_app_logo" src="<?= $base_path . htmlspecialchars($app_logo) ?>?v=<?= time() ?>" alt="Preview Logo" class="max-w-full max-h-full object-contain">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-2" style="color: var(--dashboard-text);">Pilih File Baru</label>
                        <input type="file" name="app_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="block w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer" onchange="previewImage(this, 'preview_app_logo')">
                        <span class="text-[11px] block mt-1.5" style="color: var(--dashboard-muted);">Format: PNG, JPG, SVG, WEBP. Maks 3MB.</span>
                    </div>
                </div>

                <!-- 2. Favicon Browser -->
                <div class="dashboard-panel p-6 rounded-2xl flex flex-col justify-between" style="background: var(--dashboard-surface); border: 1px solid var(--dashboard-border);">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-purple-500/10 text-purple-500 border border-purple-500/20">
                                <i class="fas fa-globe"></i> Tab Browser
                            </span>
                            <a href="?reset=app_favicon" onclick="return confirm('Kembalikan favicon ke default?')" class="text-xs text-red-400 hover:text-red-500 transition" title="Reset ke default">
                                <i class="fas fa-rotate-left"></i> Reset Default
                            </a>
                        </div>

                        <h3 class="text-base font-bold mb-1" style="color: var(--dashboard-text);">Favicon Browser Tab</h3>
                        <p class="text-xs mb-4" style="color: var(--dashboard-muted);">Ikon kecil di samping judul tab web browser pada semua halaman.</p>

                        <!-- Preview Area -->
                        <div class="flex items-center justify-center p-6 rounded-xl border border-dashed mb-4" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                            <div class="w-16 h-16 rounded-xl bg-white flex items-center justify-center p-2 shadow overflow-hidden">
                                <img id="preview_app_favicon" src="<?= $base_path . htmlspecialchars($app_favicon) ?>?v=<?= time() ?>" alt="Preview Favicon" class="max-w-full max-h-full object-contain">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-2" style="color: var(--dashboard-text);">Pilih File Baru</label>
                        <input type="file" name="app_favicon" accept="image/png,image/x-icon,image/svg+xml,image/webp,image/jpeg" class="block w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-purple-600 file:text-white hover:file:bg-purple-700 cursor-pointer" onchange="previewImage(this, 'preview_app_favicon')">
                        <span class="text-[11px] block mt-1.5" style="color: var(--dashboard-muted);">Format: PNG, ICO, SVG, WEBP. Maks 3MB.</span>
                    </div>
                </div>

                <!-- 3. Logo Halaman Login (Utama) -->
                <div class="dashboard-panel p-6 rounded-2xl flex flex-col justify-between" style="background: var(--dashboard-surface); border: 1px solid var(--dashboard-border);">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                                <i class="fas fa-right-to-bracket"></i> Login & Register
                            </span>
                            <a href="?reset=app_logo_login" onclick="return confirm('Kembalikan logo login ke default?')" class="text-xs text-red-400 hover:text-red-500 transition" title="Reset ke default">
                                <i class="fas fa-rotate-left"></i> Reset Default
                            </a>
                        </div>

                        <h3 class="text-base font-bold mb-1" style="color: var(--dashboard-text);">Logo Login Utama (Kampus/Instansi)</h3>
                        <p class="text-xs mb-4" style="color: var(--dashboard-muted);">Logo instansi utama yang tampil di bagian atas formulir login dan pendaftaran.</p>

                        <!-- Preview Area -->
                        <div class="flex items-center justify-center p-6 rounded-xl border border-dashed mb-4" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                            <div class="w-28 h-20 bg-white rounded-xl flex items-center justify-center p-2 shadow overflow-hidden">
                                <img id="preview_app_logo_login" src="<?= $base_path . htmlspecialchars($app_logo_login) ?>?v=<?= time() ?>" alt="Preview Logo Login" class="max-w-full max-h-full object-contain">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-2" style="color: var(--dashboard-text);">Pilih File Baru</label>
                        <input type="file" name="app_logo_login" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="block w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer" onchange="previewImage(this, 'preview_app_logo_login')">
                        <span class="text-[11px] block mt-1.5" style="color: var(--dashboard-muted);">Format: PNG, JPG, SVG, WEBP. Maks 3MB.</span>
                    </div>
                </div>

                <!-- 4. Logo Sekunder (Kemendikbud / Dikti) -->
                <div class="dashboard-panel p-6 rounded-2xl flex flex-col justify-between" style="background: var(--dashboard-surface); border: 1px solid var(--dashboard-border);">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-amber-500/10 text-amber-500 border border-amber-500/20">
                                <i class="fas fa-building-columns"></i> Logo Mitra / Dikti
                            </span>
                            <a href="?reset=app_logo_secondary" onclick="return confirm('Kembalikan logo sekunder ke default?')" class="text-xs text-red-400 hover:text-red-500 transition" title="Reset ke default">
                                <i class="fas fa-rotate-left"></i> Reset Default
                            </a>
                        </div>

                        <h3 class="text-base font-bold mb-1" style="color: var(--dashboard-text);">Logo Sekunder (Dikti/Mitra)</h3>
                        <p class="text-xs mb-4" style="color: var(--dashboard-muted);">Logo pendamping di sebelah logo instansi pada halaman login & pendaftaran.</p>

                        <!-- Preview Area -->
                        <div class="flex items-center justify-center p-6 rounded-xl border border-dashed mb-4" style="background: var(--dashboard-raised); border-color: var(--dashboard-border);">
                            <div class="w-28 h-20 bg-white rounded-xl flex items-center justify-center p-2 shadow overflow-hidden">
                                <img id="preview_app_logo_secondary" src="<?= $base_path . htmlspecialchars($app_logo_secondary) ?>?v=<?= time() ?>" alt="Preview Logo Sekunder" class="max-w-full max-h-full object-contain">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-2" style="color: var(--dashboard-text);">Pilih File Baru</label>
                        <input type="file" name="app_logo_secondary" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="block w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-600 file:text-white hover:file:bg-amber-700 cursor-pointer" onchange="previewImage(this, 'preview_app_logo_secondary')">
                        <span class="text-[11px] block mt-1.5" style="color: var(--dashboard-muted);">Format: PNG, JPG, SVG, WEBP. Maks 3MB.</span>
                    </div>
                </div>

            </div>

            <!-- 5. Pengaturan Identitas Teks -->
            <div class="dashboard-panel p-6 rounded-2xl mb-8" style="background: var(--dashboard-surface); border: 1px solid var(--dashboard-border);">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center">
                        <i class="fas fa-font"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold" style="color: var(--dashboard-text);">Teks Identitas Sistem</h3>
                        <p class="text-xs" style="color: var(--dashboard-muted);">Judul dan deskripsi yang tampil di samping logo pada header navigasi.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="appName" class="block text-xs font-semibold mb-1.5" style="color: var(--dashboard-text);">Nama Aplikasi</label>
                        <input type="text" id="appName" name="app_name" value="<?= htmlspecialchars($app_name) ?>" required class="w-full px-4 py-2.5 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    </div>
                    <div>
                        <label for="appDesc" class="block text-xs font-semibold mb-1.5" style="color: var(--dashboard-text);">Deskripsi / Subjudul</label>
                        <input type="text" id="appDesc" name="app_desc" value="<?= htmlspecialchars($app_desc) ?>" class="w-full px-4 py-2.5 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-blue-500" style="background: var(--dashboard-raised); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    </div>
                </div>
            </div>

            <!-- Tombol Aksi Simpan -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="dashboard.php" class="px-5 py-2.5 rounded-xl text-sm font-semibold border transition" style="background: var(--dashboard-surface); border-color: var(--dashboard-border); color: var(--dashboard-text);">
                    Batal
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-md transition duration-200">
                    <i class="fas fa-floppy-disk"></i> Simpan Semua Perubahan
                </button>
            </div>

        </form>

    </div>
</div>

<script>
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById(previewId);
            if (img) {
                img.src = e.target.result;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include '../includes/footer.php'; ?>
