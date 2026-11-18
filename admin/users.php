<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';

// Hapus user jika ada parameter delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Mulai transaction untuk memastikan konsistensi data
    $conn->begin_transaction();
    
    try {
        // 1. Cek role user untuk menentukan tabel mana yang perlu dihapus
        $role_query = $conn->query("SELECT role FROM users WHERE id = $id");
        if ($role_query->num_rows > 0) {
            $user_data = $role_query->fetch_assoc();
            $role = $user_data['role'];
            
            // 2. Hapus data berdasarkan role
            if ($role == 'mahasiswa') {
                // Hapus dari tabel bimbingan yang terkait
                $conn->query("DELETE FROM bimbingan WHERE id_mahasiswa = $id");
                // Hapus dari tabel logbook
                $conn->query("DELETE FROM logbook WHERE id_mahasiswa = $id");
                // Hapus dari tabel mahasiswa
                $conn->query("DELETE FROM mahasiswa WHERE id = $id");
            } 
            elseif ($role == 'dosen_pkl' || $role == 'dosen_lapangan') {
                // Hapus relasi bimbingan
                $conn->query("UPDATE bimbingan SET 
                    id_dospem_pkl = NULL WHERE id_dospem_pkl = $id");
                $conn->query("UPDATE bimbingan SET 
                    id_dospem_lapangan = NULL WHERE id_dospem_lapangan = $id");
                // Hapus dari tabel dosen
                $conn->query("DELETE FROM dosen WHERE id = $id");
            }
            
            // 3. Hapus dari tabel users
            $conn->query("DELETE FROM users WHERE id = $id");
            
            $conn->commit();
            $_SESSION['success'] = "User berhasil dihapus!";
        } else {
            $_SESSION['error'] = "User tidak ditemukan!";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Gagal menghapus user: " . $e->getMessage();
    }
    
    header("Location: users.php");
    exit();
}

// Pencarian
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where_clause = "";
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $where_clause = "WHERE nama LIKE '%$search%' OR username LIKE '%$search%'";
}

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$start = ($page - 1) * $limit;

// Hitung total halaman
$total_result = $conn->query("SELECT COUNT(*) as total FROM users $where_clause");
$total_row = $total_result->fetch_assoc();
$total_pages = ceil($total_row['total'] / $limit);

// Ambil data pengguna sesuai halaman dan pencarian
$result = $conn->query("
    SELECT * FROM users 
    $where_clause 
    ORDER BY role DESC 
    LIMIT $start, $limit
");
$page_title = 'Kelola Pengguna - Logbook PKL';
include '../includes/header.php';
?>


<div class="dashboard-page users-page">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="status">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="admin-list-header">
        <div>
            <a href="dashboard.php" class="admin-list-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Dashboard
            </a>
            <h2 class="dashboard-title">Kelola Pengguna</h2>
        </div>
        <a href="tambah_user.php" class="admin-button admin-button-primary">
            <i class="fas fa-user-plus" aria-hidden="true"></i> Tambah Pengguna
        </a>
    </div>

    <div class="dashboard-panel admin-list-panel">
        <form method="get" class="admin-list-filter" role="search" aria-label="Cari pengguna">
            <label class="users-search-field">
                <span class="sr-only">Nama atau username</span>
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama atau username">
            </label>
            <button type="submit" class="admin-button">Cari</button>
            <?php if (!empty($search)): ?>
                <a href="users.php" class="admin-button admin-button-quiet">Reset</a>
            <?php endif; ?>
        </form>

        <div class="dashboard-table-scroll" tabindex="0" role="region" aria-label="Tabel Kelola Pengguna">
            <table class="admin-list-table">
                <thead>
                    <tr>
                        <th scope="col">No</th>
                        <th scope="col">Nama</th>
                        <th scope="col">Username</th>
                        <th scope="col">Peran</th>
                        <th scope="col" class="users-action-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = $start + 1; ?>
                    <?php while ($user = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="users-row-number"><?= $no++ ?></td>
                            <td class="users-name"><?= htmlspecialchars($user['nama']) ?></td>
                            <td class="users-username"><?= htmlspecialchars($user['username']) ?></td>
                            <td><span class="users-role"><?= htmlspecialchars(str_replace('_', ' ', $user['role'])) ?></span></td>
                            <td>
                                <div class="users-actions">
                                    <?php if (in_array($user['role'], ['mahasiswa', 'dosen_pkl', 'dosen_lapangan'], true)): ?>
                                        <form method="post" action="../impersonate.php">
                                            <input type="hidden" name="token" value="<?= impersonation_token() ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                            <button type="submit" name="action" value="start" class="admin-button admin-button-quiet"
                                                    aria-label="Impersonate as <?= htmlspecialchars($user['nama']) ?>">
                                                <i class="fas fa-user-secret" aria-hidden="true"></i> Impersonate as
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="admin-button admin-button-quiet">
                                        <i class="fas fa-pen-to-square" aria-hidden="true"></i> Edit
                                    </a>
                                    <a href="reset_password.php?id=<?= (int) $user['id'] ?>" class="admin-button admin-button-quiet">
                                        <i class="fas fa-key" aria-hidden="true"></i> Reset Kata Sandi
                                    </a>
                                    <a href="?delete=<?= $user['id'] ?>" onclick="return confirm('Yakin hapus user ini? Semua data terkait juga akan dihapus.')" class="admin-button admin-button-danger">
                                        <i class="fas fa-trash-can" aria-hidden="true"></i> Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="5" class="admin-list-empty">
                                <?= empty($search) ? 'Belum ada pengguna.' : 'Pengguna tidak ditemukan.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        $pagination_filters = ['search' => $search];
        include '../includes/pagination.php';
        ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
