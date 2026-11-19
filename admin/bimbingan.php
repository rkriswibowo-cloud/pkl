<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$bimbingan_feedback = '';

// Proses submit form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_mahasiswa = intval($_POST['mahasiswa']);
    $id_dospem_pkl = intval($_POST['dospem_pkl']);
    $id_dospem_lapangan = intval($_POST['dospem_lapangan']);

    // Cek apakah sudah ada entri untuk mahasiswa ini
    $cek = $conn->query("SELECT * FROM bimbingan WHERE id_mahasiswa='$id_mahasiswa'");

    if ($cek->num_rows > 0) {
        // Update jika sudah ada
        $stmt = $conn->prepare("UPDATE bimbingan SET id_dospem_pkl=?, id_dospem_lapangan=? WHERE id_mahasiswa=?");
        $stmt->bind_param("iii", $id_dospem_pkl, $id_dospem_lapangan, $id_mahasiswa);
    } else {
        // Insert baru jika belum ada
        $stmt = $conn->prepare("INSERT INTO bimbingan (id_mahasiswa, id_dospem_pkl, id_dospem_lapangan) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $id_mahasiswa, $id_dospem_pkl, $id_dospem_lapangan);
    }

    if ($stmt->execute()) {
        $bimbingan_feedback = "<script>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: 'Bimbingan berhasil diset.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.href = 'bimbingan.php';
            });
        </script>";
    } else {
        $bimbingan_feedback = "<script>
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Gagal menyimpan data bimbingan.'
            });
        </script>";
    }
}

// Ambil semua mahasiswa dan dosen
$mahasiswa_list = $conn->query("
    SELECT u.id, u.nama, u.username, u.prodi 
    FROM users u 
    WHERE u.role = 'mahasiswa' 
    ORDER BY u.prodi ASC, u.nama ASC
");

$dosen_pkl_list = $conn->query("
    SELECT u.id, u.nama, u.username, u.prodi, d.nip, d.bidang
    FROM users u
    LEFT JOIN dosen d ON u.id = d.id
    WHERE u.role = 'dosen_pkl'
    ORDER BY u.prodi ASC, u.nama ASC
");

$dosen_lapangan_list = $conn->query("
    SELECT u.id, u.nama, u.username, u.phone 
    FROM users u 
    WHERE u.role = 'dosen_lapangan' 
    ORDER BY u.nama ASC
");

$bimbingan_list = $conn->query("
    SELECT 
        b.id_mahasiswa, 
        u.nama AS mahasiswa, 
        u.prodi AS prodi_mahasiswa,
        u.username AS nim_mahasiswa,
        pkl.nama AS dospem_pkl, 
        pkl.prodi AS prodi_dospem,
        lapangan.nama AS dospem_lapangan
    FROM bimbingan b
    JOIN users u ON b.id_mahasiswa = u.id
    LEFT JOIN users pkl ON b.id_dospem_pkl = pkl.id
    LEFT JOIN users lapangan ON b.id_dospem_lapangan = lapangan.id
    ORDER BY u.prodi ASC, u.nama ASC
");
$page_title = 'Pengaturan Bimbingan Mahasiswa - Logbook PKL';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($bimbingan_feedback !== ''): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?= $bimbingan_feedback ?>
<?php endif; ?>


<div class="dashboard-page">
    <div class="admin-list-header">
        <div>
            <a href="dashboard.php" class="admin-list-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Dashboard
            </a>
            <h2 class="dashboard-title">Setting Bimbingan Mahasiswa</h2>
        </div>
    </div>

    <!-- Informasi Dosen Pembimbing -->
    <div class="mb-6 flex justify-between items-center">
        <a href="dosen_pembimbing.php" class="admin-button">
            <i class="fas fa-id-badge" aria-hidden="true"></i> Informasi Dosen Pembimbing
        </a>
    </div>

    <!-- Form Setting Bimbingan -->
    <div class="dashboard-panel dashboard-section mb-8">
        <form method="post" class="dashboard-form-grid dashboard-form-assignments">
            <div>
                <label class="block text-sm font-medium text-gray-700">Pilih Mahasiswa</label>
                <select name="mahasiswa" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                    <option value="">-- Pilih Mahasiswa --</option>
                    <?php 
                    $curr_prodi_mhs = null;
                    while ($mhs = $mahasiswa_list->fetch_assoc()): 
                        $grp = $mhs['prodi'] ?: 'Umum / Belum Diisi';
                        if ($curr_prodi_mhs !== $grp):
                            if ($curr_prodi_mhs !== null) echo '</optgroup>';
                            $curr_prodi_mhs = $grp;
                            echo '<optgroup label="' . htmlspecialchars($curr_prodi_mhs) . '">';
                        endif;
                    ?>
                        <option value="<?= $mhs['id'] ?>">
                            <?= htmlspecialchars($mhs['nama']) ?> (NIM: <?= htmlspecialchars($mhs['username']) ?>)
                        </option>
                    <?php endwhile; ?>
                    <?php if ($curr_prodi_mhs !== null) echo '</optgroup>'; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Dosen Pembimbing PKL (Kampus)</label>
                <select name="dospem_pkl" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                    <option value="">-- Pilih Dosen Pembimbing PKL --</option>
                    <?php 
                    $curr_prodi_dosen = null;
                    while ($dosen = $dosen_pkl_list->fetch_assoc()): 
                        $grp = $dosen['prodi'] ?: 'Umum / Lainnya';
                        if ($curr_prodi_dosen !== $grp):
                            if ($curr_prodi_dosen !== null) echo '</optgroup>';
                            $curr_prodi_dosen = $grp;
                            echo '<optgroup label="Program Studi: ' . htmlspecialchars($curr_prodi_dosen) . '">';
                        endif;
                    ?>
                        <option value="<?= $dosen['id'] ?>">
                            <?= htmlspecialchars($dosen['nama']) ?> <?= !empty($dosen['nip']) ? ' (NIDN: ' . htmlspecialchars($dosen['nip']) . ')' : '' ?>
                        </option>
                    <?php endwhile; ?>
                    <?php if ($curr_prodi_dosen !== null) echo '</optgroup>'; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Dosen Pembimbing Lapangan</label>
                <select name="dospem_lapangan" required class="w-full px-4 py-2 border border-gray-300 rounded-md">
                    <option value="">-- Pilih Pembimbing Lapangan --</option>
                    <?php while ($dosen = $dosen_lapangan_list->fetch_assoc()): ?>
                        <option value="<?= $dosen['id'] ?>">
                            <?= htmlspecialchars($dosen['nama']) ?> <?= !empty($dosen['phone']) ? ' (' . htmlspecialchars($dosen['phone']) . ')' : '' ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="dashboard-page-toolbar dashboard-form-actions">
                <button type="submit" class="admin-button admin-button-primary">
                    <i class="fas fa-check" aria-hidden="true"></i> Simpan Pengaturan
                </button>
                <button type="reset" class="admin-button admin-button-quiet">
                    <i class="fas fa-rotate-left" aria-hidden="true"></i> Reset Pilihan
                </button>
            </div>
        </form>
    </div>

    <!-- Daftar Bimbingan -->
    <h3 class="text-xl font-semibold text-gray-800 mb-4">Daftar Bimbingan Saat Ini</h3>
    <div class="dashboard-panel dashboard-table-scroll" tabindex="0" role="region" aria-label="Tabel Pengaturan Bimbingan Mahasiswa">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mahasiswa</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dosen PKL (Kampus)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pembimbing Lapangan</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($bimbingan_list && $bimbingan_list->num_rows > 0): ?>
                    <?php while ($row = $bimbingan_list->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-user-graduate text-blue-500"></i>
                                    <div>
                                        <div><?= htmlspecialchars($row['mahasiswa']) ?></div>
                                        <div class="text-xs text-gray-500">
                                            <?= htmlspecialchars($row['nim_mahasiswa']) ?>
                                            <?php if (!empty($row['prodi_mahasiswa'])): ?>
                                                &bull; <span class="text-blue-600 font-semibold"><?= htmlspecialchars($row['prodi_mahasiswa']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?php if (!empty($row['dospem_pkl'])): ?>
                                    <div><?= htmlspecialchars($row['dospem_pkl']) ?></div>
                                    <?php if (!empty($row['prodi_dospem'])): ?>
                                        <span class="inline-block px-2 py-0.5 text-[11px] font-semibold bg-blue-50 text-blue-700 rounded-full border border-blue-200">
                                            <?= htmlspecialchars($row['prodi_dospem']) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400 italic">Belum diset</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?php if (!empty($row['dospem_lapangan'])): ?>
                                    <div class="flex items-center gap-1.5">
                                        <i class="fas fa-building text-emerald-600 text-xs"></i>
                                        <span><?= htmlspecialchars($row['dospem_lapangan']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 italic">Belum diset</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="px-6 py-6 text-center text-sm text-gray-500">
                            Belum ada plotting bimbingan.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
