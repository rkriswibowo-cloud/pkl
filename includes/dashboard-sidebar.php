<?php
$current_page = basename($_SERVER['PHP_SELF']);
$active_menu_page = match ($current_page) {
    'edit_user.php', 'tambah_user.php' => 'users.php',
    'edit_logbook.php' => 'riwayat.php',
    default => $current_page,
};
$dashboard_groups = [
    'Utama' => [[$dashboard_url, 'Dashboard', 'fa-table-columns']],
];

switch ($current_role) {
    case 'admin':
        $dashboard_groups['Pengelolaan PKL'] = [
            [$base_path . 'admin/users.php', 'Pengguna', 'fa-users'],
            [$base_path . 'admin/bimbingan.php', 'Bimbingan', 'fa-chalkboard-user'],
            [$base_path . 'admin/dosen_pembimbing.php', 'Dosen Pembimbing', 'fa-id-badge'],
            [$base_path . 'admin/logbooks.php', 'Logbook', 'fa-book'],
        ];
        break;
    case 'mahasiswa':
        $dashboard_groups['Kegiatan PKL'] = [
            [$base_path . 'mahasiswa/input_logbook.php', 'Input Logbook', 'fa-plus-circle'],
            [$base_path . 'mahasiswa/riwayat.php', 'Riwayat Logbook', 'fa-clock-rotate-left'],
        ];
        break;
    case 'dosen_pkl':
    case 'dosen_lapangan':
        $dashboard_groups['Bimbingan PKL'] = [
            [($current_page === 'dashboard.php' ? '' : $dashboard_url) . '#persetujuan-logbook', 'Persetujuan Logbook', 'fa-circle-check'],
        ];
        break;
}

$dashboard_groups['Akun'] = [
    [$base_path . 'profile.php', 'Profil', 'fa-user-circle'],
];
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
                    <button type="button" class="dashboard-group-heading" aria-expanded="true"
                            aria-controls="dashboard-group-<?= $label === 'Akun' ? 'account' : 'pkl' ?>">
                        <i class="fas <?= $label === 'Akun' ? 'fa-user' : 'fa-layer-group' ?>" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($label) ?></span>
                        <span class="dashboard-group-count" aria-hidden="true"><?= count($links) ?></span>
                        <i class="fas fa-chevron-down dashboard-group-chevron" aria-hidden="true"></i>
                    </button>
                <?php endif; ?>
                <div class="dashboard-group-links" id="dashboard-group-<?= $label === 'Utama' ? 'main' : ($label === 'Akun' ? 'account' : 'pkl') ?>">
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
