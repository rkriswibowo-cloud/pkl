<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'mahasiswa') {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';
$id_mahasiswa = $_SESSION['user']['id'];

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$start = ($page - 1) * $limit;

// Hitung total halaman
$total_result = $conn->query("SELECT COUNT(*) as total FROM logbook WHERE id_mahasiswa='$id_mahasiswa'");
$total_row = $total_result->fetch_assoc();
$total_pages = ceil($total_row['total'] / $limit);

// Ambil logbook sesuai halaman
$result = $conn->query("
    SELECT * FROM logbook 
    WHERE id_mahasiswa = '$id_mahasiswa' 
    ORDER BY tanggal DESC
    LIMIT $start, $limit
");

// Hapus data
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM logbook WHERE id='$id'");
    header("Location: riwayat.php");
    exit();
}
$page_title = 'Riwayat Logbook - Logbook PKL';
include '../includes/header.php';
?>


<div class="dashboard-page">
    <h2 class="dashboard-title text-2xl font-bold text-gray-800 mb-6">Riwayat Logbook</h2>

    <!-- Tombol Export -->
    <div class="dashboard-page-toolbar mb-4">
        <a href="export_pdf.php" target="_blank" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded shadow transition">
            <i class="fas fa-file-pdf mr-2"></i>Export ke PDF
        </a>
    </div>

    <!-- Tabel Riwayat -->
    <div class="dashboard-panel overflow-hidden mb-6">
        <div class="dashboard-table-scroll" tabindex="0" role="region" aria-label="Tabel riwayat logbook">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hari/Tanggal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jam Kerja</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kegiatan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status PKL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">TTD Dosen PKL</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status Lapangan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">TTD Dosen Lap.</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($log = $result->fetch_assoc()): 
                            // Format jam jika ada
                            $jam_datang = !empty($log['jam_datang']) ? date('H:i', strtotime($log['jam_datang'])) : '-';
                            $jam_pulang = !empty($log['jam_pulang']) ? date('H:i', strtotime($log['jam_pulang'])) : '-';
                        ?>
                        <tr class="hover:bg-gray-50">
                            <!-- Hari/Tanggal -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($log['hari']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($log['tanggal']) ?></div>
                            </td>
                            
                            <!-- Unit Penempatan -->
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?= htmlspecialchars($log['unit_penempatan'] ?? 'Belum diisi') ?>
                                </span>
                            </td>
                            
                            <!-- Jam Kerja -->
                            <td class="px-6 py-4">
                                <div class="text-sm">
                                    <div class="flex items-center">
                                        <i class="fas fa-sign-in-alt text-green-500 mr-2 text-xs"></i>
                                        <span class="font-medium"><?= $jam_datang ?></span>
                                    </div>
                                    <div class="flex items-center mt-1">
                                        <i class="fas fa-sign-out-alt text-red-500 mr-2 text-xs"></i>
                                        <span class="font-medium"><?= $jam_pulang ?></span>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Kegiatan -->
                            <td class="px-6 py-4">
                                <div class="max-w-xs">
                                    <div class="text-sm text-gray-900 line-clamp-2" title="<?= htmlspecialchars($log['kegiatan']) ?>">
                                        <?= substr(htmlspecialchars($log['kegiatan']), 0, 120) ?>
                                        <?= strlen($log['kegiatan']) > 120 ? '...' : '' ?>
                                    </div>
                                    <?php if (!empty($log['catatan_dospem_pkl']) || !empty($log['catatan_dospem_lapangan'])): ?>
                                        <div class="mt-2">
                                            <?php if (!empty($log['catatan_dospem_pkl'])): ?>
                                                <div class="text-xs text-blue-600">
                                                    <i class="fas fa-comment-alt mr-1"></i>
                                                    <span class="font-medium">Dosen PKL:</span> 
                                                    <?= substr(htmlspecialchars($log['catatan_dospem_pkl']), 0, 50) ?>...
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($log['catatan_dospem_lapangan'])): ?>
                                                <div class="text-xs text-green-600">
                                                    <i class="fas fa-comment-alt mr-1"></i>
                                                    <span class="font-medium">Dosen Lap.:</span> 
                                                    <?= substr(htmlspecialchars($log['catatan_dospem_lapangan']), 0, 50) ?>...
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <!-- Status Dosen PKL -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                $status_pkl = $log['status_dospem_pkl'];
                                $status_class = '';
                                $status_icon = '';
                                
                                if ($status_pkl == 'disetujui') {
                                    $status_class = 'bg-green-100 text-green-800';
                                    $status_icon = 'fa-check-circle';
                                } elseif ($status_pkl == 'ditolak') {
                                    $status_class = 'bg-red-100 text-red-800';
                                    $status_icon = 'fa-times-circle';
                                } else {
                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                    $status_icon = 'fa-clock';
                                }
                                ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $status_class ?>">
                                    <i class="fas <?= $status_icon ?> mr-2"></i>
                                    <?= ucfirst(htmlspecialchars($status_pkl)) ?>
                                </span>
                            </td>
                            
                            <!-- TTD Dosen PKL -->
                            <td class="px-6 py-4">
                                <?php if (!empty($log['ttd_dospem_pkl'])): ?>
                                    <div class="flex flex-col items-center">
                                        <img src="<?= htmlspecialchars($log['ttd_dospem_pkl']) ?>" 
                                             alt="TTD Dosen PKL" 
                                             class="w-24 h-auto border rounded">
                                        <span class="text-xs text-gray-500 mt-1">TTD</span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 italic text-sm">–</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Status Dosen Lapangan -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                $status_lapangan = $log['status_dospem_lapangan'];
                                $status_class = '';
                                $status_icon = '';
                                
                                if ($status_lapangan == 'disetujui') {
                                    $status_class = 'bg-green-100 text-green-800';
                                    $status_icon = 'fa-check-circle';
                                } elseif ($status_lapangan == 'ditolak') {
                                    $status_class = 'bg-red-100 text-red-800';
                                    $status_icon = 'fa-times-circle';
                                } else {
                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                    $status_icon = 'fa-clock';
                                }
                                ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $status_class ?>">
                                    <i class="fas <?= $status_icon ?> mr-2"></i>
                                    <?= ucfirst(htmlspecialchars($status_lapangan)) ?>
                                </span>
                            </td>
                            
                            <!-- TTD Dosen Lapangan -->
                            <td class="px-6 py-4">
                                <?php if (!empty($log['ttd_dospem_lapangan'])): ?>
                                    <div class="flex flex-col items-center">
                                        <img src="<?= htmlspecialchars($log['ttd_dospem_lapangan']) ?>" 
                                             alt="TTD Dosen Lapangan" 
                                             class="w-24 h-auto border rounded">
                                        <span class="text-xs text-gray-500 mt-1">TTD</span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 italic text-sm">–</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Aksi -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($log['status_dospem_pkl'] == 'pending' && $log['status_dospem_lapangan'] == 'pending'): ?>
                                    <div class="dashboard-filter">
                                        <a href="edit_logbook.php?id=<?= $log['id'] ?>" 
                                           title="Edit" 
                                           class="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 p-2 rounded">
                                            <i class="fas fa-pen-to-square"></i>
                                        </a>
                                        <a href="?delete=<?= $log['id'] ?>" 
                                           onclick="return confirm('Yakin menghapus logbook ini?')" 
                                           title="Hapus" 
                                           class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 p-2 rounded">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 italic text-sm" title="Tidak bisa diedit/dihapus">
                                        <i class="fas fa-lock mr-1"></i> Terkunci
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-book-open text-gray-300 text-4xl mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-700 mb-2">Belum ada logbook</h3>
                                    <p class="text-gray-500 mb-4">Mulai dengan mengisi kegiatan harian Anda</p>
                                    <a href="input_logbook.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                                        <i class="fas fa-plus mr-2"></i>Tambah Logbook Pertama
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php $pagination_filters = []; include '../includes/pagination.php'; ?>

    <!-- Action Buttons -->
    <div class="dashboard-page-toolbar mt-6">
        <div class="dashboard-page-toolbar">
            <a href="input_logbook.php" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i>Tambah Logbook Baru
            </a>
            <a href="dashboard.php" class="inline-flex items-center bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
                <i class="fas fa-home mr-2"></i>Dashboard
            </a>
        </div>
        
        <?php if ($result->num_rows > 0): ?>
        <div class="text-sm text-gray-500">
            <i class="fas fa-lightbulb mr-1"></i>
            Logbook hanya bisa diedit/dihapus jika status masih <span class="font-medium">Pending</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Tooltip untuk kegiatan yang dipotong
document.addEventListener('DOMContentLoaded', function() {
    const kegiatanCells = document.querySelectorAll('.line-clamp-2');
    kegiatanCells.forEach(cell => {
        cell.addEventListener('mouseenter', function() {
            if (this.scrollWidth > this.clientWidth) {
                this.setAttribute('title', this.textContent);
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
