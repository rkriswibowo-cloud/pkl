<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
$error = '';
$success = '';

// Ambil data user dengan NULL check
$user_query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();

if ($user_result->num_rows === 0) {
    // User tidak ditemukan, logout
    session_destroy();
    header("Location: index.php");
    exit();
}

$user = $user_result->fetch_assoc();

// Set default values untuk menghindari warning
$user = array_merge([
    'nama' => '',
    'username' => '',
    'email' => '',
    'phone' => '',
    'prodi' => '',
    'profile_image' => '',
    'email_verified' => false,
    'created_at' => date('Y-m-d H:i:s')
], $user);

// Ambil data tambahan berdasarkan role
$additional_data = [
    'nim' => '',
    'jurusan' => '',
    'prodi' => '',
    'nip' => '',
    'bidang' => ''
];

if ($role == 'mahasiswa') {
    $mahasiswa_query = $conn->prepare("SELECT * FROM mahasiswa WHERE id = ?");
    $mahasiswa_query->bind_param("i", $user_id);
    $mahasiswa_query->execute();
    $mahasiswa_result = $mahasiswa_query->get_result();
    
    if ($mahasiswa_result->num_rows > 0) {
        $mahasiswa_data = $mahasiswa_result->fetch_assoc();
        $additional_data = array_merge($additional_data, [
            'nim' => $mahasiswa_data['nim'] ?? '',
            'jurusan' => $mahasiswa_data['jurusan'] ?? '',
            'prodi' => $mahasiswa_data['prodi'] ?? ''
        ]);
    }
} elseif ($role == 'dosen_pkl' || $role == 'dosen_lapangan') {
    $dosen_query = $conn->prepare("SELECT * FROM dosen WHERE id = ?");
    $dosen_query->bind_param("i", $user_id);
    $dosen_query->execute();
    $dosen_result = $dosen_query->get_result();
    
    if ($dosen_result->num_rows > 0) {
        $dosen_data = $dosen_result->fetch_assoc();
        $additional_data = array_merge($additional_data, [
            'nip' => $dosen_data['nip'] ?? '',
            'bidang' => $dosen_data['bidang'] ?? ''
        ]);
    }
}

// Handle upload foto profil
if (isset($_POST['upload_foto']) && isset($_FILES['profile_image'])) {
    // [kode upload foto tetap sama]
}

// Handle form submission untuk update profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['upload_foto'])) {
    // Ambil data dari form dengan null coalescing operator
    $nama = htmlspecialchars($_POST['nama'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $phone = htmlspecialchars($_POST['phone'] ?? '');
    
    // Validasi input
    if (empty($nama) || empty($email)) {
        $error = "Nama dan email harus diisi!";
    } else {
        // Data spesifik role
        if ($role == 'mahasiswa') {
            $nim = htmlspecialchars($_POST['nim'] ?? '');
            $prodi = htmlspecialchars($_POST['prodi'] ?? '');
            $jurusan = htmlspecialchars($_POST['jurusan'] ?? '');
            
            // Update tabel users
            $stmt = $conn->prepare("UPDATE users SET nama = ?, username = ?, phone = ?, prodi = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $nama, $email, $phone, $prodi, $user_id);
            
            if ($stmt->execute()) {
                // Update tabel mahasiswa
                // PERBAIKAN: Cek apakah data sudah ada di tabel mahasiswa
                $check_mahasiswa = $conn->prepare("SELECT id FROM mahasiswa WHERE id = ?");
                $check_mahasiswa->bind_param("i", $user_id);
                $check_mahasiswa->execute();
                $check_result = $check_mahasiswa->get_result();
                $check_mahasiswa->close();
                
                if ($check_result->num_rows > 0) {
                    // Update existing data
                    $stmt2 = $conn->prepare("UPDATE mahasiswa SET nim = ?, jurusan = ?, prodi = ? WHERE id = ?");
                    $stmt2->bind_param("sssi", $nim, $jurusan, $prodi, $user_id);
                } else {
                    // Insert new data
                    $stmt2 = $conn->prepare("INSERT INTO mahasiswa (id, nim, jurusan, prodi) VALUES (?, ?, ?, ?)");
                    $stmt2->bind_param("isss", $user_id, $nim, $jurusan, $prodi);
                }
                
                if ($stmt2->execute()) {
                    $stmt2->close();
                }
                
                // Update session
                $_SESSION['user']['nama'] = $nama;
                $_SESSION['user']['username'] = $email;
                $_SESSION['user']['prodi'] = $prodi;
                
                // Update data lokal
                $user['nama'] = $nama;
                $user['username'] = $email;
                $user['phone'] = $phone;
                $user['prodi'] = $prodi;
                $additional_data['nim'] = $nim;
                $additional_data['jurusan'] = $jurusan;
                $additional_data['prodi'] = $prodi;
                
                $success = "Profil berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui profil: " . $conn->error;
            }
            $stmt->close();
            
        } elseif ($role == 'dosen_pkl' || $role == 'dosen_lapangan') {
            // PERBAIKAN: Ambil data dosen dari form
            $nip = htmlspecialchars($_POST['nip'] ?? '');
            $bidang = htmlspecialchars($_POST['bidang'] ?? '');
            
            // Update tabel users
            $stmt = $conn->prepare("UPDATE users SET nama = ?, username = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("sssi", $nama, $email, $phone, $user_id);
            
            if ($stmt->execute()) {
                // PERBAIKAN: Cek apakah data sudah ada di tabel dosen
                $check_dosen = $conn->prepare("SELECT id FROM dosen WHERE id = ?");
                $check_dosen->bind_param("i", $user_id);
                $check_dosen->execute();
                $check_result = $check_dosen->get_result();
                $check_dosen->close();
                
                if ($check_result->num_rows > 0) {
                    // Update existing data
                    $stmt2 = $conn->prepare("UPDATE dosen SET nip = ?, bidang = ? WHERE id = ?");
                    $stmt2->bind_param("ssi", $nip, $bidang, $user_id);
                } else {
                    // Insert new data
                    $stmt2 = $conn->prepare("INSERT INTO dosen (id, nip, bidang) VALUES (?, ?, ?)");
                    $stmt2->bind_param("iss", $user_id, $nip, $bidang);
                }
                
                if ($stmt2->execute()) {
                    $stmt2->close();
                }
                
                // Update session
                $_SESSION['user']['nama'] = $nama;
                $_SESSION['user']['username'] = $email;
                
                // Update data lokal
                $user['nama'] = $nama;
                $user['username'] = $email;
                $user['phone'] = $phone;
                $additional_data['nip'] = $nip;
                $additional_data['bidang'] = $bidang;
                
                $success = "Profil berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui profil: " . $conn->error;
            }
            $stmt->close();
            
        } else {
            // Untuk role admin (tidak ada tabel khusus)
            $stmt = $conn->prepare("UPDATE users SET nama = ?, username = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("sssi", $nama, $email, $phone, $user_id);
            
            if ($stmt->execute()) {
                // Update session
                $_SESSION['user']['nama'] = $nama;
                $_SESSION['user']['username'] = $email;
                
                // Update data lokal
                $user['nama'] = $nama;
                $user['username'] = $email;
                $user['phone'] = $phone;
                
                $success = "Profil berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui profil: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
$page_title = 'Profil Pengguna - Logbook PKL';
include 'includes/header.php';
?>

    
    <div class="dashboard-page">
        <div>
            
            <!-- Header Profil -->
            <div class="profile-bg dashboard-section mb-6">
                <div class="flex flex-col md:flex-row items-center">
                    <!-- Foto Profil -->
                    <div class="relative mb-6 md:mb-0 md:mr-8">
                        <div class="profile-image-container">
                            <div class="w-32 h-32 rounded-full border-4 border-white overflow-hidden bg-white">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img src="assets/uploads/profiles/<?= htmlspecialchars($user['profile_image']) ?>" 
                                         alt="Foto Profil" 
                                         class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-r from-blue-400 to-purple-500">
                                        <span class="text-4xl font-bold text-white">
                                            <?= strtoupper(substr($user['nama'], 0, 1) ?: 'U') ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="profile-overlay">
                                <label class="cursor-pointer">
                                    <i class="fas fa-camera text-white text-2xl"></i>
                                </label>
                            </div>
                        </div>
                        <form method="POST" enctype="multipart/form-data" class="mt-4">
                            <label class="cursor-pointer bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition inline-flex items-center">
                                <i class="fas fa-camera mr-2"></i>Ubah Foto
                                <input type="file" name="profile_image" class="hidden" onchange="this.form.submit()" accept="image/*">
                                <input type="hidden" name="upload_foto" value="1">
                            </label>
                            <p class="text-white/70 text-xs mt-1">Maks. 2MB (JPG, PNG, GIF)</p>
                        </form>
                    </div>
                    
                    <!-- Info Profil -->
                    <div class="flex-1 min-w-0 text-center md:text-left dashboard-profile-identity">
                        <h1 class="text-3xl font-bold mb-2"><?= htmlspecialchars($user['nama']) ?: 'Pengguna' ?></h1>
                        <div class="flex flex-wrap items-center justify-center md:justify-start gap-4 mb-4">
                            <span class="px-4 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-user-tag mr-2"></i>
                                <?= ucfirst(str_replace('_', ' ', $role)) ?>
                            </span>
                            <?php if ($role == 'mahasiswa' && !empty($additional_data['nim'])): ?>
                                <span class="px-4 py-1 bg-white/20 rounded-full text-sm">
                                    <i class="fas fa-id-card mr-2"></i>
                                    NIM: <?= htmlspecialchars($additional_data['nim']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (($role == 'dosen_pkl' || $role == 'dosen_lapangan') && !empty($additional_data['nip'])): ?>
                                <span class="px-4 py-1 bg-white/20 rounded-full text-sm">
                                    <i class="fas fa-id-card mr-2"></i>
                                    NIP: <?= htmlspecialchars($additional_data['nip']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-white/80">
                            <i class="fas fa-envelope mr-2"></i>
                            <?= htmlspecialchars($user['username']) ?>
                        </p>
                        <?php if (!empty($user['phone'])): ?>
                            <p class="text-white/80 mt-1">
                                <i class="fas fa-phone mr-2"></i>
                                <?= htmlspecialchars($user['phone']) ?>
                            </p>
                        <?php endif; ?>
                        <?php if (($role == 'dosen_pkl' || $role == 'dosen_lapangan') && !empty($additional_data['bidang'])): ?>
                            <p class="text-white/80 mt-1">
                                <i class="fas fa-book mr-2"></i>
                                Bidang: <?= htmlspecialchars($additional_data['bidang']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Notifikasi -->
            <?php if ($success): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= $success ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <!-- Konten Utama -->
            <div class="dashboard-profile-grid">
                <!-- Form Edit Profil -->
                <div class="lg:col-span-2">
                    <div class="dashboard-panel dashboard-section">
                        <h2 class="dashboard-title text-xl font-bold text-gray-800 mb-6 pb-3 border-b">
                            <i class="fas fa-user-edit mr-2 text-blue-600"></i>
                            Edit Informasi Profil
                        </h2>
                        
                        <form method="POST">
                            <div class="dashboard-fields">
                                <!-- Nama -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-user mr-1"></i> Nama Lengkap *
                                    </label>
                                    <input type="text" name="nama" 
                                           value="<?= htmlspecialchars($user['nama']) ?>" 
                                           required
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                </div>
                                
                                <!-- Email -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-envelope mr-1"></i> Email *
                                    </label>
                                    <input type="email" name="email" 
                                           value="<?= htmlspecialchars($user['username']) ?>" 
                                           required
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                </div>
                                
                                <!-- Telepon -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-phone mr-1"></i> Nomor Telepon
                                    </label>
                                    <input type="tel" name="phone" 
                                           value="<?= htmlspecialchars($user['phone']) ?>" 
                                           placeholder="08xxxxxxxxxx"
                                           pattern="[0-9]{10,13}"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                    <p class="text-xs text-gray-500 mt-1">Contoh: 081234567890</p>
                                </div>
                                
                                <!-- Untuk Mahasiswa -->
                                <?php if ($role == 'mahasiswa'): ?>
                                    <!-- NIM -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-id-card mr-1"></i> NIM *
                                        </label>
                                        <input type="text" name="nim" 
                                               value="<?= htmlspecialchars($additional_data['nim']) ?>" 
                                               required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                    </div>
                                    
                                    <!-- Prodi -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-graduation-cap mr-1"></i> Program Studi *
                                        </label>
                                        <select name="prodi" 
                                                required
                                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                            <option value="">Pilih Prodi</option>
                                            <option value="Teknik Informatika" <?= ($user['prodi'] == 'Teknik Informatika') ? 'selected' : '' ?>>Teknik Informatika</option>
                                            <option value="Sistem Informasi" <?= ($user['prodi'] == 'Sistem Informasi') ? 'selected' : '' ?>>Sistem Informasi</option>
                                            <option value="Teknik Komputer" <?= ($user['prodi'] == 'Teknik Komputer') ? 'selected' : '' ?>>Teknik Komputer</option>
                                            <option value="Manajemen Informatika" <?= ($user['prodi'] == 'Manajemen Informatika') ? 'selected' : '' ?>>Manajemen Informatika</option>
                                            <option value="Akuntansi" <?= ($user['prodi'] == 'Akuntansi') ? 'selected' : '' ?>>Akuntansi</option>
                                            <option value="Manajemen" <?= ($user['prodi'] == 'Manajemen') ? 'selected' : '' ?>>Manajemen</option>
                                            <option value="Hukum" <?= ($user['prodi'] == 'Hukum') ? 'selected' : '' ?>>Hukum</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Jurusan -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-building mr-1"></i> Jurusan/Fakultas
                                        </label>
                                        <input type="text" name="jurusan" 
                                               value="<?= htmlspecialchars($additional_data['jurusan']) ?>" 
                                               placeholder="Contoh: Fakultas Teknik"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Untuk Dosen -->
                                <?php if ($role == 'dosen_pkl' || $role == 'dosen_lapangan'): ?>
                                    <!-- NIP -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-id-badge mr-1"></i> NIP
                                        </label>
                                        <input type="text" name="nip" 
                                               value="<?= htmlspecialchars($additional_data['nip']) ?>" 
                                               placeholder="Contoh: 197001012000031001"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                    </div>
                                    
                                    <!-- Bidang -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-book mr-1"></i> Bidang Keahlian
                                        </label>
                                        <input type="text" name="bidang" 
                                               value="<?= htmlspecialchars($additional_data['bidang']) ?>" 
                                               placeholder="Contoh: Pemrograman Web, Basis Data"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Area Tombol -->
                            <div class="mt-8 pt-6 border-t">
                                <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
                                    <div class="text-sm text-gray-500 order-2 sm:order-1 mt-4 sm:mt-0">
                                        <i class="fas fa-info-circle mr-1"></i> * Wajib diisi
                                    </div>
                                    
                                    <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto order-1 sm:order-2">
                                        <button type="submit" 
                                                class="btn-primary w-full sm:w-auto">
                                            <i class="fas fa-save mr-2"></i> Simpan Perubahan
                                        </button>
                                        
                                        <a href="<?= ($role == 'admin') ? 'admin/dashboard.php' : (($role == 'mahasiswa') ? 'mahasiswa/dashboard.php' : (($role == 'dosen_pkl') ? 'dosen_pkl/dashboard.php' : 'dosen_lapangan/dashboard.php')) ?>" 
                                           class="btn-secondary w-full sm:w-auto text-center">
                                            <i class="fas fa-times mr-2"></i> Batal
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Sidebar Info -->
                <div class="space-y-6">
                    <!-- Info Akun -->
                    <div class="dashboard-panel dashboard-section">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">
                            <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                            Informasi Akun
                        </h3>
                        <div class="space-y-3">
                            <div class="dashboard-page-toolbar py-2 border-b">
                                <span class="text-gray-600">Role</span>
                                <span class="font-medium">
                                    <?= ucfirst(str_replace('_', ' ', $role)) ?>
                                </span>
                            </div>
                            <div class="dashboard-page-toolbar py-2 border-b">
                                <span class="text-gray-600">Bergabung</span>
                                <span class="font-medium">
                                    <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                </span>
                            </div>
                            <div class="dashboard-page-toolbar py-2 border-b">
                                <span class="text-gray-600">Verifikasi Email</span>
                                <span class="font-medium <?= $user['email_verified'] ? 'text-green-600' : 'text-yellow-600' ?>">
                                    <?= $user['email_verified'] ? 'Terverifikasi' : 'Belum diverifikasi' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistik Berdasarkan Role -->
                    <div class="dashboard-panel dashboard-section">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">
                            <i class="fas fa-chart-bar mr-2 text-blue-600"></i>
                            Statistik
                        </h3>
                        <?php
                        $stats = [];
                        if ($role == 'mahasiswa') {
                            $total_logbook = $conn->query("SELECT COUNT(*) as total FROM logbook WHERE id_mahasiswa = $user_id")->fetch_assoc()['total'] ?? 0;
                            $approved_logbook = $conn->query("SELECT COUNT(*) as total FROM logbook WHERE id_mahasiswa = $user_id AND status_dospem_pkl = 'disetujui' AND status_dospem_lapangan = 'disetujui'")->fetch_assoc()['total'] ?? 0;
                            $pending_logbook = $conn->query("SELECT COUNT(*) as total FROM logbook WHERE id_mahasiswa = $user_id AND (status_dospem_pkl = 'pending' OR status_dospem_lapangan = 'pending')")->fetch_assoc()['total'] ?? 0;
                            
                            $stats = [
                                ['label' => 'Total Logbook', 'value' => $total_logbook, 'color' => 'blue'],
                                ['label' => 'Disetujui', 'value' => $approved_logbook, 'color' => 'green'],
                                ['label' => 'Pending', 'value' => $pending_logbook, 'color' => 'yellow']
                            ];
                        } elseif ($role == 'dosen_pkl' || $role == 'dosen_lapangan') {
                            // Sesuaikan dengan nama kolom yang benar
                            $status_field = ($role == 'dosen_pkl') ? 'status_dospem_pkl' : 'status_dospem_lapangan';
                            
                            // Tentukan kolom dosen pembimbing berdasarkan role
                            $dospem_column = ($role == 'dosen_pkl') ? 'id_dospem_pkl' : 'id_dospem_lapangan';
                            
                            // Hitung total mahasiswa bimbingan dengan query yang aman
                            $total_mahasiswa_query = $conn->prepare("SELECT COUNT(*) as total FROM bimbingan WHERE $dospem_column = ?");
                            $total_mahasiswa_query->bind_param("i", $user_id);
                            $total_mahasiswa_query->execute();
                            $total_mahasiswa_result = $total_mahasiswa_query->get_result();
                            $total_mahasiswa = $total_mahasiswa_result->fetch_assoc()['total'] ?? 0;
                            $total_mahasiswa_query->close();
                            
                            // Hitung pending approvals dengan query yang aman
                            // Pertama, dapatkan semua mahasiswa bimbingan
                            $mahasiswa_ids = [];
                            $mahasiswa_query = $conn->prepare("SELECT id_mahasiswa FROM bimbingan WHERE $dospem_column = ?");
                            $mahasiswa_query->bind_param("i", $user_id);
                            $mahasiswa_query->execute();
                            $mahasiswa_result = $mahasiswa_query->get_result();
                            
                            while ($row = $mahasiswa_result->fetch_assoc()) {
                                $mahasiswa_ids[] = $row['id_mahasiswa'];
                            }
                            $mahasiswa_query->close();
                            
                            $pending_approvals = 0;
                            $total_approved = 0;
                            
                            if (!empty($mahasiswa_ids)) {
                                $ids_str = implode(',', array_map('intval', $mahasiswa_ids));
                                
                                // Hitung pending
                                $pending_result = $conn->query("SELECT COUNT(*) as total FROM logbook WHERE id_mahasiswa IN ($ids_str) AND $status_field = 'pending'");
                                if ($pending_result) {
                                    $pending_approvals = $pending_result->fetch_assoc()['total'] ?? 0;
                                }
                                
                                // Hitung disetujui
                                $approved_result = $conn->query("SELECT COUNT(*) as total FROM logbook WHERE id_mahasiswa IN ($ids_str) AND $status_field = 'disetujui'");
                                if ($approved_result) {
                                    $total_approved = $approved_result->fetch_assoc()['total'] ?? 0;
                                }
                            }
                            
                            $stats = [
                                ['label' => 'Mahasiswa Bimbingan', 'value' => $total_mahasiswa, 'color' => 'blue'],
                                ['label' => 'Pending Approval', 'value' => $pending_approvals, 'color' => 'yellow'],
                                ['label' => 'Total Disetujui', 'value' => $total_approved, 'color' => 'green']
                            ];
                        } elseif ($role == 'admin') {
                            $total_users = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'] ?? 0;
                            $total_mahasiswa = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'mahasiswa'")->fetch_assoc()['total'] ?? 0;
                            $total_logbooks = $conn->query("SELECT COUNT(*) as total FROM logbook")->fetch_assoc()['total'] ?? 0;
                            
                            $stats = [
                                ['label' => 'Total Pengguna', 'value' => $total_users, 'color' => 'blue'],
                                ['label' => 'Total Mahasiswa', 'value' => $total_mahasiswa, 'color' => 'green'],
                                ['label' => 'Total Logbook', 'value' => $total_logbooks, 'color' => 'purple']
                            ];
                        }
                        ?>
                        
                        <div class="space-y-4">
                            <?php foreach ($stats as $stat): ?>
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm text-gray-600"><?= $stat['label'] ?></span>
                                        <span class="text-sm font-bold text-<?= $stat['color'] ?>-600"><?= $stat['value'] ?></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-<?= $stat['color'] ?>-500 h-2 rounded-full" 
                                             style="width: <?= min($stat['value'] * 10, 100) ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="dashboard-panel dashboard-section">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">
                            <i class="fas fa-bolt mr-2 text-blue-600"></i>
                            Aksi Cepat
                        </h3>
                        <div class="space-y-3">
                            <a href="change_password.php" 
                               class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-key text-gray-400 mr-3"></i>
                                <span>Ubah Password</span>
                            </a>
                            
                            <?php if ($role == 'mahasiswa'): ?>
                                <a href="mahasiswa/input_logbook.php" 
                                   class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                    <i class="fas fa-plus text-blue-400 mr-3"></i>
                                    <span>Tambah Logbook Baru</span>
                                </a>
                                <a href="mahasiswa/riwayat.php" 
                                   class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                    <i class="fas fa-history text-green-400 mr-3"></i>
                                    <span>Lihat Riwayat</span>
                                </a>
                            <?php endif; ?>
                            
                            <a href="logout.php" 
                               class="flex items-center p-3 border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition">
                                <i class="fas fa-sign-out-alt mr-3"></i>
                                <span>Keluar</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
