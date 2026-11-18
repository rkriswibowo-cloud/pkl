<?php 
// Pastikan session_start() hanya dipanggil sekali
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect berdasarkan role jika belum login
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

// Tentukan base path berdasarkan lokasi file
$base_path = '';
if (strpos($_SERVER['PHP_SELF'], 'admin/') !== false) {
    $base_path = '../';
} elseif (strpos($_SERVER['PHP_SELF'], 'mahasiswa/') !== false || 
          strpos($_SERVER['PHP_SELF'], 'dosen_pkl/') !== false || 
          strpos($_SERVER['PHP_SELF'], 'dosen_lapangan/') !== false) {
    $base_path = '../';
}

// Role-specific dashboard URLs
$role_dashboard = [
    'admin' => $base_path . 'admin/dashboard.php',
    'mahasiswa' => $base_path . 'mahasiswa/dashboard.php',
    'dosen_pkl' => $base_path . 'dosen_pkl/dashboard.php',
    'dosen_lapangan' => $base_path . 'dosen_lapangan/dashboard.php'
];

$current_role = $_SESSION['user']['role'];
$dashboard_url = $role_dashboard[$current_role] ?? '../index.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : (ucfirst($current_role) . ' - Sistem Logbook PKL') ?></title>
    <!-- Favicon -->
    <link rel="icon" href="<?= $base_path ?>assets/images/dashboard-logo-uam.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .time {
            font-size: 0.875rem;
            line-height: 1.25rem;
        }
        .dropdown-menu {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }
        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
    </style>
    <script>
        try {
            if (localStorage.getItem('pkl-dashboard-theme') === 'light') {
                document.documentElement.dataset.pklTheme = 'light';
            }
            document.documentElement.classList.toggle('pkl-compact-sidebar',
                localStorage.getItem('pkl-dashboard-sidebar') === 'compact');
        } catch (error) {}
    </script>
    <link rel="stylesheet" href="<?= $base_path ?>assets/css/dashboard.css">
    <script defer src="<?= $base_path ?>assets/js/dashboard-navigation.js"></script>
</head>
<body class="min-h-screen pkl-dashboard">

<!-- Navbar -->
<nav class="sticky top-0 z-50" aria-label="Bilah atas">
    <div>
        <div class="flex justify-between items-center h-16">
            <!-- Logo & Brand -->
            <div class="flex items-center space-x-4">
                <button type="button" id="dashboardSidebarToggle" class="dashboard-sidebar-toggle"
                        aria-label="Menu navigasi" aria-controls="dashboardSidebar" aria-expanded="false">
                    <i class="fas fa-bars-staggered" aria-hidden="true"></i>
                </button>
                <!-- Logo -->
                <a href="<?= $dashboard_url ?>" class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center shadow">
                        <i class="fas fa-graduation-cap text-blue-600 text-lg" aria-hidden="true"></i>
                    </div>
                    <div class="hidden md:block">
                        <h1 class="text-lg font-bold text-white">Logbook PKL</h1>
                        <p class="text-xs text-blue-100">Sistem Monitoring Praktik Kerja Lapangan</p>
                    </div>
                </a>
                
            </div>

            <!-- Right Section: Time & User Menu -->
            <div class="flex items-center space-x-4">
                <button type="button" id="dashboardThemeToggle" class="dashboard-sidebar-toggle dashboard-theme-toggle"
                        aria-label="Mode gelap" aria-pressed="true" title="Ganti ke tema terang">
                    <i class="fas fa-sun" aria-hidden="true"></i>
                </button>
                <!-- Time Display (Desktop) -->
                <div class="hidden md:block text-right">
                    <i class="far fa-clock dashboard-clock-icon" aria-hidden="true"></i>
                    <div id="tanggal" class="text-sm font-medium text-blue-100"></div>
                    <div id="jam" class="text-xs text-white/90 time"></div>
                </div>

                <!-- User Menu -->
                <div class="relative">
                    <button id="userMenuButton" class="flex items-center space-x-3 focus:outline-none focus:ring-2 focus:ring-white/30 rounded-lg p-1">
                        <!-- Profile Image/Initial -->
                        <div class="w-10 h-10 rounded-full border-2 border-white overflow-hidden bg-gradient-to-r from-blue-400 to-purple-400 flex items-center justify-center shadow">
                            <?php if (isset($_SESSION['user']['profile_image']) && !empty($_SESSION['user']['profile_image'])): ?>
                                <img src="<?= $base_path ?>assets/uploads/profiles/<?= htmlspecialchars($_SESSION['user']['profile_image']) ?>" 
                                     alt="Profile" 
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="text-white font-bold text-lg">
                                    <?= strtoupper(substr($_SESSION['user']['nama'], 0, 1)) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- User Info (Desktop) -->
                        <div class="dashboard-account-label hidden md:block text-left">
                            <div class="text-sm font-medium text-white"><?= htmlspecialchars($_SESSION['user']['nama']) ?></div>
                            <div class="text-xs text-blue-100 capitalize"><?= $current_role ?></div>
                        </div>
                        
                        <i class="fas fa-chevron-down text-white text-xs hidden md:block"></i>
                    </button>

                    <!-- Dropdown Menu -->
                    <div id="userMenu" class="dropdown-menu absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl py-2 z-50 border border-gray-200">
                        <!-- Header -->
                        <div class="px-4 py-3 border-b">
                            <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($_SESSION['user']['nama']) ?></p>
                            <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($_SESSION['user']['username']) ?></p>
                            <p class="text-xs text-blue-600 font-medium capitalize mt-1"><?= $current_role ?></p>
                        </div>
                        
                        <!-- Menu Items -->
                        <a href="<?= $base_path ?>profile.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                            <i class="fas fa-user-circle mr-3 text-gray-400"></i>
                            <span>Profil Saya</span>
                        </a>
                        
                        <a href="<?= $base_path ?>profile.php?ubah_kata_sandi=1#ubah-kata-sandi" class="flex items-center px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                            <i class="fas fa-key mr-3 text-gray-400"></i>
                            <span>Ubah Password</span>
                        </a>
                        
                        <!-- Role-specific links -->
                        <?php if ($current_role == 'mahasiswa'): ?>
                            <a href="<?= $base_path ?>mahasiswa/riwayat.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
                                <i class="fas fa-history mr-3 text-gray-400"></i>
                                <span>Logbook Saya</span>
                            </a>
                        <?php endif; ?>
                        
                        <div class="border-t my-1"></div>
                        
                        <a href="<?= $base_path ?>logout.php" class="flex items-center px-4 py-3 text-red-600 hover:bg-red-50 transition">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            <span>Keluar</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </div>
</nav>

<!-- Script untuk Update Jam & Menu -->
<script>
    // Update clock
    function updateClock() {
        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };
        const now = new Date();
        
        // Format Indonesia
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        
        const hari = days[now.getDay()];
        const tanggal = now.getDate();
        const bulan = months[now.getMonth()];
        const tahun = now.getFullYear();
        
        const jam = now.getHours().toString().padStart(2, '0');
        const menit = now.getMinutes().toString().padStart(2, '0');
        const detik = now.getSeconds().toString().padStart(2, '0');
        
        const hariTanggal = `${hari}, ${tanggal} ${bulan} ${tahun}`;
        const waktu = `${jam}:${menit}:${detik}`;
        
        // Update desktop
        const tanggalEl = document.getElementById('tanggal');
        const jamEl = document.getElementById('jam');
        if (tanggalEl) tanggalEl.textContent = hariTanggal;
        if (jamEl) jamEl.textContent = waktu;
        
    }
    
    // User menu toggle
    const userMenuButton = document.getElementById('userMenuButton');
    const userMenu = document.getElementById('userMenu');
    
    if (userMenuButton && userMenu) {
        userMenuButton.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenu.classList.toggle('show');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!userMenu.contains(e.target) && !userMenuButton.contains(e.target)) {
                userMenu.classList.remove('show');
            }
        });
        
        // Close menu when pressing ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                userMenu.classList.remove('show');
            }
        });
    }
    
    // Initialize
    window.onload = () => {
        updateClock();
        setInterval(updateClock, 1000);
        
    };
</script>

<!-- Main Content Container -->
<div class="dashboard-layout" id="dashboardLayout">
<?php require __DIR__ . '/dashboard-sidebar.php'; ?>
<main id="dashboardMain">
