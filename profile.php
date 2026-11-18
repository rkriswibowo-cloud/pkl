<?php
session_start();
require_once __DIR__ . '/includes/impersonation.php';
require_writable_session();
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

$password_error = '';
$password_success = '';
$password_submitted = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password']);
$password_open = $password_submitted || ($_GET['ubah_kata_sandi'] ?? null) === '1';

if ($password_submitted) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!is_string($current_password) || !is_string($new_password) || !is_string($confirm_password)
        || $current_password === '' || $new_password === '' || $confirm_password === '') {
        $password_error = 'Lengkapi semua kolom kata sandi.';
    } elseif ($new_password !== $confirm_password) {
        $password_error = 'Kata sandi baru dan konfirmasi tidak cocok.';
    } elseif (strlen($new_password) < 6) {
        $password_error = 'Kata sandi baru minimal 6 karakter.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $password_error = 'Kata sandi saat ini salah.';
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $password_stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $password_stmt->bind_param('si', $new_hash, $user_id);
        if ($password_stmt->execute()) {
            $password_success = 'Kata sandi berhasil diubah.';
        } else {
            $password_error = 'Kata sandi gagal diubah.';
        }
        $password_stmt->close();
    }
}

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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$password_submitted && !isset($_POST['upload_foto'])) {
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
    <header class="admin-list-header">
        <div>
            <a href="<?= htmlspecialchars($dashboard_url) ?>" class="admin-list-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Dashboard
            </a>
            <h1 class="dashboard-title">Profil</h1>
        </div>
    </header>

    <?php if ($success): ?>
        <p class="profile-feedback bg-green-50" role="status"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="profile-feedback bg-red-50" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <div class="profile-layout">
        <section class="dashboard-panel profile-panel" aria-labelledby="profile-details-title">
            <div class="profile-identity">
                <span class="profile-avatar" aria-hidden="true">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="assets/uploads/profiles/<?= htmlspecialchars($user['profile_image']) ?>" alt="">
                    <?php else: ?>
                        <?= htmlspecialchars(mb_strtoupper(mb_substr($user['nama'], 0, 1)) ?: 'U') ?>
                    <?php endif; ?>
                </span>
                <div>
                    <h2 id="profile-details-title"><?= htmlspecialchars($user['nama']) ?: 'Pengguna' ?></h2>
                    <p><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $role))) ?></p>
                </div>
            </div>

            <form method="POST" action="profile.php" class="profile-form">
                <div class="profile-fields">

                    <div>
                        <label for="profile-nama">
                            Nama Lengkap *
                        </label>
                        <input type="text" name="nama" id="profile-nama"
                               value="<?= htmlspecialchars($user['nama']) ?>"
                               required
                               class="dashboard-field-control">
                    </div>

                    <div>
                        <label for="profile-email">
                            Email *
                        </label>
                        <input type="email" name="email" id="profile-email"
                               value="<?= htmlspecialchars($user['username']) ?>"
                               required
                               class="dashboard-field-control">
                    </div>

                    <div>
                        <label for="profile-phone">
                            Nomor Telepon
                        </label>
                        <input type="tel" name="phone" id="profile-phone"
                               value="<?= htmlspecialchars($user['phone']) ?>"
                               placeholder="08xxxxxxxxxx"
                               pattern="[0-9]{10,13}"
                               class="dashboard-field-control">
                        <p class="text-xs text-gray-500 mt-1">Contoh: 081234567890</p>
                    </div>

                    <?php if ($role == 'mahasiswa'): ?>

                        <div>
                            <label for="profile-nim">
                                NIM *
                            </label>
                            <input type="text" name="nim" id="profile-nim"
                                   value="<?= htmlspecialchars($additional_data['nim']) ?>"
                                   required
                                   class="dashboard-field-control">
                        </div>

                        <div>
                            <label for="profile-prodi">
                                Program Studi *
                            </label>
                            <select name="prodi" id="profile-prodi"
                                    required
                                    class="dashboard-field-control">
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

                        <div>
                            <label for="profile-jurusan">
                                Jurusan/Fakultas
                            </label>
                            <input type="text" name="jurusan" id="profile-jurusan"
                                   value="<?= htmlspecialchars($additional_data['jurusan']) ?>"
                                   placeholder="Contoh: Fakultas Teknik"
                                   class="dashboard-field-control">
                        </div>
                    <?php endif; ?>

                    <?php if ($role == 'dosen_pkl' || $role == 'dosen_lapangan'): ?>

                        <div>
                            <label for="profile-nip">
                                NIP
                            </label>
                            <input type="text" name="nip" id="profile-nip"
                                   value="<?= htmlspecialchars($additional_data['nip']) ?>"
                                   placeholder="Contoh: 197001012000031001"
                                   class="dashboard-field-control">
                        </div>

                        <div>
                            <label for="profile-bidang">
                                Bidang Keahlian
                            </label>
                            <input type="text" name="bidang" id="profile-bidang"
                                   value="<?= htmlspecialchars($additional_data['bidang']) ?>"
                                   placeholder="Contoh: Pemrograman Web, Basis Data"
                                   class="dashboard-field-control">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="profile-form-actions">
                    <button type="submit" class="admin-button admin-button-primary">Simpan Perubahan</button>
                </div>
            </form>

            <?php if (!$is_impersonating): ?>
            <details id="ubah-kata-sandi" class="profile-password"<?= $password_open ? ' open' : '' ?>>
                <summary>
                    <span>Ubah Kata Sandi</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </summary>
                <form method="POST" action="profile.php#ubah-kata-sandi" class="profile-form">
                    <input type="hidden" name="change_password" value="1">
                    <?php if ($password_success !== ''): ?>
                        <p class="profile-feedback bg-green-50" role="status"><?= htmlspecialchars($password_success) ?></p>
                    <?php endif; ?>
                    <?php if ($password_error !== ''): ?>
                        <p class="profile-feedback bg-red-50" role="alert"><?= htmlspecialchars($password_error) ?></p>
                    <?php endif; ?>
                    <div class="profile-fields">
                        <div>
                            <label for="current-password">Kata Sandi Saat Ini</label>
                            <input type="password" id="current-password" name="current_password"
                                   autocomplete="current-password" class="dashboard-field-control" required>
                        </div>
                        <div>
                            <label for="new-password">Kata Sandi Baru</label>
                            <input type="password" id="new-password" name="new_password"
                                   autocomplete="new-password" class="dashboard-field-control" minlength="6" required>
                        </div>
                        <div>
                            <label for="confirm-password">Konfirmasi Kata Sandi Baru</label>
                            <input type="password" id="confirm-password" name="confirm_password"
                                   autocomplete="new-password" class="dashboard-field-control" minlength="6" required>
                        </div>
                    </div>
                    <div class="profile-form-actions">
                        <button type="submit" class="admin-button admin-button-primary">Simpan Kata Sandi</button>
                    </div>
                </form>
            </details>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel profile-panel profile-account" aria-labelledby="profile-account-title">
            <header class="profile-account-header">
                <h2 id="profile-account-title">Informasi Akun</h2>
            </header>
            <dl class="profile-details">
                <div>
                    <dt>Bergabung</dt>
                    <dd><?= date('d/m/Y', strtotime($user['created_at'])) ?></dd>
                </div>
                <div>
                    <dt>Verifikasi Email</dt>
                    <dd><?= $user['email_verified'] ? 'Terverifikasi' : 'Belum diverifikasi' ?></dd>
                </div>
            </dl>

            <?php
            $stats = [];
            if ($role == 'mahasiswa') {
                $total_logbook = $conn->query("SELECT COUNT(*) as total FROM logbook WHERE id_mahasiswa = $user_id")->fetch_assoc()['total'] ?? 0;
                $approved_logbook = $conn->query("SELECT COUNT(*) as total FROM logbook WHERE id_mahasiswa = $user_id AND status_dospem_pkl = 'disetujui' AND status_dospem_lapangan = 'disetujui'")->fetch_assoc()['total'] ?? 0;
                $pending_logbook = $conn->query("SELECT COUNT(*) as total FROM logbook WHERE id_mahasiswa = $user_id AND (status_dospem_pkl = 'pending' OR status_dospem_lapangan = 'pending')")->fetch_assoc()['total'] ?? 0;

                $stats = [
                    ['label' => 'Total Logbook', 'value' => $total_logbook],
                    ['label' => 'Disetujui', 'value' => $approved_logbook],
                    ['label' => 'Pending', 'value' => $pending_logbook]
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
                    ['label' => 'Mahasiswa Bimbingan', 'value' => $total_mahasiswa],
                    ['label' => 'Pending Approval', 'value' => $pending_approvals],
                    ['label' => 'Total Disetujui', 'value' => $total_approved]
                ];
            } elseif ($role == 'admin') {
                $total_users = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'] ?? 0;
                $total_mahasiswa = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'mahasiswa'")->fetch_assoc()['total'] ?? 0;
                $total_logbooks = $conn->query("SELECT COUNT(*) as total FROM logbook")->fetch_assoc()['total'] ?? 0;

                $stats = [
                    ['label' => 'Total Pengguna', 'value' => $total_users],
                    ['label' => 'Total Mahasiswa', 'value' => $total_mahasiswa],
                    ['label' => 'Total Logbook', 'value' => $total_logbooks]
                ];
            }
            ?>

            <?php if ($stats): ?>
                <h3 class="profile-stats-title">Statistik</h3>
                <dl class="profile-details profile-stats">
                    <?php foreach ($stats as $stat): ?>
                        <div>
                            <dt><?= htmlspecialchars($stat['label']) ?></dt>
                            <dd><?= (int) $stat['value'] ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
