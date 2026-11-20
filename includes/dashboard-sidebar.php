<?php
$current_page = basename($_SERVER['PHP_SELF']);
$active_menu_page = match ($current_page) {
    'edit_user.php', 'tambah_user.php', 'reset_password.php' => 'users.php',
    'edit_logbook.php' => 'riwayat.php',
    default => $current_page,
};
$dashboard_groups = [
    'Utama' => [[$dashboard_url, 'Dashboard', 'fa-table-columns']],
];

switch ($current_role) {
    case 'superadmin':
    case 'admin':
        $dashboard_groups['Pengelolaan PKL'] = [
            [$base_path . 'admin/users.php', 'Pengguna', 'fa-users'],
            [$base_path . 'admin/master_prodi.php', 'Master Prodi (OBE)', 'fa-graduation-cap'],
            [$base_path . 'admin/bimbingan.php', 'Bimbingan', 'fa-chalkboard-user'],
            [$base_path . 'admin/dosen_pembimbing.php', 'Dosen Pembimbing', 'fa-id-badge'],
            [$base_path . 'admin/logbooks.php', 'Logbook', 'fa-book'],
            [$base_path . 'admin/dokumen.php', 'Arsip Dokumen PKL', 'fa-folder-open'],
            [$base_path . 'admin/penilaian.php', 'Rekap Nilai PKL', 'fa-award'],
            [$base_path . 'admin/verifikasi_pengesahan.php', 'Pengesahan (Kaprodi)', 'fa-stamp'],
        ];
        break;
    case 'mahasiswa':
        $dashboard_groups['Kegiatan PKL'] = [
            [$base_path . 'mahasiswa/input_logbook.php', 'Input Logbook', 'fa-plus-circle'],
            [$base_path . 'mahasiswa/riwayat.php', 'Riwayat Logbook', 'fa-clock-rotate-left'],
        ];
        $dashboard_groups['Laporan & Nilai'] = [
            [$base_path . 'mahasiswa/dokumen.php', 'Unggah Dokumen PKL', 'fa-folder-arrow-up'],
            [$base_path . 'mahasiswa/nilai.php', 'Transkrip Nilai PKL', 'fa-award'],
            [$base_path . 'mahasiswa/pengesahan.php', 'Lembar Pengesahan', 'fa-file-signature'],
        ];
        break;
    case 'dosen_pkl':
        $dashboard_groups['Bimbingan PKL'] = [
            [($current_page === 'dashboard.php' ? '' : $dashboard_url) . '#persetujuan-logbook', 'Persetujuan Logbook', 'fa-circle-check'],
            [$base_path . 'dosen_pkl/dokumen.php', 'Review Dokumen / Laporan', 'fa-folder-open'],
            [$base_path . 'dosen_pkl/penilaian.php', 'Penilaian Bimbingan', 'fa-award'],
            [$base_path . 'dosen_pkl/verifikasi_berkas.php', 'Verifikasi Pengesahan', 'fa-file-circle-check'],
        ];
        break;
    case 'dosen_lapangan':
        $dashboard_groups['Bimbingan PKL'] = [
            [($current_page === 'dashboard.php' ? '' : $dashboard_url) . '#persetujuan-logbook', 'Persetujuan Logbook', 'fa-circle-check'],
            [$base_path . 'dosen_lapangan/penilaian.php', 'Penilaian Lapangan', 'fa-clipboard-check'],
            [$base_path . 'dosen_lapangan/verifikasi_berkas.php', 'Verifikasi Pengesahan', 'fa-file-signature'],
        ];
        break;
}

$dashboard_groups['Akun'] = [
    [$base_path . 'profile.php', 'Profil', 'fa-user-circle'],
];

if ($current_role === 'admin' || $current_role === 'superadmin') {
    $dashboard_groups['Pengaturan'] = [
        [$base_path . 'admin/edit_logo.php', 'Edit Logo & Favicon', 'fa-palette'],
    ];
}
?>
<button type="button" id="dashboardSidebarBackdrop" class="dashboard-sidebar-backdrop"
        aria-label="Tutup menu navigasi" tabindex="-1" hidden></button>
<aside class="dashboard-sidebar" id="dashboardSidebar" aria-label="Menu Logbook PKL">
    <div class="dashboard-sidebar-heading">
        <strong>Navigasi</strong>
        <button type="button" id="dashboardSidebarClose" class="dashboard-sidebar-toggle"
                aria-label="Tutup menu navigasi">
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
    <label class="dashboard-sidebar-search">
        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" id="dashboardMenuSearch" placeholder="Cari menu... (Search)"
               aria-label="Cari menu" autocomplete="off">
        <kbd aria-hidden="true">/</kbd>
    </label>
    <button type="button" id="dashboardGroupToggle" class="dashboard-group-toggle">
        <i class="fas fa-arrows-up-down" aria-hidden="true"></i>
        <span>Ciutkan Semua</span>
    </button>
    <nav aria-label="Navigasi dashboard">
        <?php foreach ($dashboard_groups as $label => $links): ?>
            <div class="dashboard-sidebar-group">
                <?php if ($label === 'Utama'): ?>
                    <p><i class="fas fa-compass" aria-hidden="true"></i> Menu Utama</p>
                <?php else: ?>
                    <?php
                    $group_slug = match ($label) {
                        'Akun'          => 'account',
                        'Pengaturan'    => 'settings',
                        'Laporan & Nilai' => 'laporan-nilai',
                        'Kegiatan PKL'  => 'kegiatan-pkl',
                        'Bimbingan PKL' => 'bimbingan-pkl',
                        'Pengelolaan PKL' => 'pengelolaan-pkl',
                        default         => 'pkl-' . md5($label),
                    };
                    $group_icon = match ($label) {
                        'Akun'          => 'fa-user',
                        'Pengaturan'    => 'fa-sliders',
                        'Laporan & Nilai' => 'fa-file-lines',
                        default         => 'fa-layer-group',
                    };
                    ?>
                    <button type="button" class="dashboard-group-heading" aria-expanded="true"
                            aria-controls="dashboard-group-<?= $group_slug ?>">
                        <i class="fas <?= $group_icon ?>" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($label) ?></span>
                        <span class="dashboard-group-count" aria-hidden="true"><?= count($links) ?></span>
                        <i class="fas fa-chevron-down dashboard-group-chevron" aria-hidden="true"></i>
                    </button>
                <?php endif; ?>
                <div class="dashboard-group-links" id="dashboard-group-<?= $label === 'Utama' ? 'main' : $group_slug ?>">
                    <?php foreach ($links as [$href, $label_link, $icon]): ?>
                        <?php $menu_page = basename($href); ?>
                        <a href="<?= htmlspecialchars($href) ?>" title="<?= htmlspecialchars($label_link) ?>"
                            <?php if ($menu_page === $current_page): ?> aria-current="page"
                            <?php elseif ($menu_page === $active_menu_page): ?> class="dashboard-parent-link"<?php endif; ?>>
                            <i class="fas <?= htmlspecialchars($icon) ?>" aria-hidden="true"></i>
                            <span><?= htmlspecialchars($label_link) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <p id="dashboardMenuEmpty" role="status" hidden>Menu tidak ditemukan.</p>
    </nav>
    <button type="button" id="dashboardSidebarCollapse" class="dashboard-sidebar-collapse">
        <i class="fas fa-angles-left" aria-hidden="true"></i> <span>Ciutkan Sidebar</span>
    </button>
</aside>
