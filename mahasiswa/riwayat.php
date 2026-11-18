<?php
session_start();
require_once __DIR__ . '/../includes/impersonation.php';
require_writable_session(isset($_GET['delete']));

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
    <header class="admin-list-header">
        <div>
            <a href="dashboard.php" class="admin-list-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Dashboard
            </a>
            <h2 class="dashboard-title">Riwayat Logbook</h2>
        </div>
        <div class="history-actions">
            <a href="export_pdf.php" target="_blank" rel="noopener" class="admin-button">
                <i class="fas fa-file-pdf" aria-hidden="true"></i> Ekspor PDF
            </a>
            <?php if (!$is_impersonating): ?>
                <a href="input_logbook.php" class="admin-button admin-button-primary">
                    <i class="fas fa-plus" aria-hidden="true"></i> Tambah Logbook
                </a>
            <?php endif; ?>
        </div>
    </header>

    <div class="dashboard-panel history-panel">
        <?php if ($result->num_rows === 0): ?>
            <p class="history-empty">Belum ada logbook untuk ditampilkan.</p>
        <?php else: ?>
            <table class="history-table admin-list-table" role="table" aria-label="Riwayat Logbook">
                <colgroup>
                    <col class="history-date-column">
                    <col class="history-unit-column">
                    <col class="history-hours-column">
                    <col>
                    <col class="history-status-column" span="2">
                    <col class="history-action-column">
                </colgroup>
                <thead role="rowgroup">
                    <tr role="row">
                        <th scope="col" role="columnheader">Hari / Tanggal</th>
                        <th scope="col" role="columnheader">Unit</th>
                        <th scope="col" role="columnheader">Jam Kerja</th>
                        <th scope="col" role="columnheader">Kegiatan</th>
                        <th scope="col" role="columnheader">Pembimbing PKL</th>
                        <th scope="col" role="columnheader">Pembimbing Lapangan</th>
                        <th scope="col" role="columnheader">Aksi</th>
                    </tr>
                </thead>
                <tbody role="rowgroup">
                    <?php while ($log = $result->fetch_assoc()): ?>
                        <?php
                        $jam_datang = !empty($log['jam_datang']) ? date('H:i', strtotime($log['jam_datang'])) : '?';
                        $jam_pulang = !empty($log['jam_pulang']) ? date('H:i', strtotime($log['jam_pulang'])) : '?';
                        $activity = (string) $log['kegiatan'];
                        $preview = mb_strimwidth($activity, 0, 125, '?', 'UTF-8');
                        $has_notes = !empty($log['catatan_dospem_pkl']) || !empty($log['catatan_dospem_lapangan']);
                        $editable = $log['status_dospem_pkl'] === 'pending' && $log['status_dospem_lapangan'] === 'pending';
                        ?>
                        <tr role="row">
                            <td role="cell" class="history-date">
                                <strong><?= htmlspecialchars($log['hari']) ?></strong>
                                <time datetime="<?= htmlspecialchars($log['tanggal']) ?>"><?= htmlspecialchars($log['tanggal']) ?></time>
                            </td>
                            <td role="cell" class="history-unit">
                                <span class="history-cell-label" aria-hidden="true">Unit</span>
                                <?= htmlspecialchars($log['unit_penempatan'] ?? 'Belum diisi') ?>
                            </td>
                            <td role="cell" class="history-hours">
                                <span class="history-cell-label" aria-hidden="true">Jam Kerja</span>
                                <span><?= $jam_datang ?> <span aria-hidden="true">&ndash;</span> <?= $jam_pulang ?></span>
                            </td>
                            <td role="cell" class="history-activity">
                                <span class="history-cell-label" aria-hidden="true">Kegiatan</span>
                                <?php if ($has_notes || $preview !== $activity): ?>
                                    <details class="history-details">
                                        <summary>
                                            <span class="history-preview"><?= htmlspecialchars($preview) ?></span>
                                            <span class="history-details-label">Lihat detail</span>
                                        </summary>
                                        <p><?= nl2br(htmlspecialchars($activity)) ?></p>
                                        <?php if (!empty($log['catatan_dospem_pkl'])): ?>
                                            <p><strong>Catatan PKL:</strong> <?= nl2br(htmlspecialchars($log['catatan_dospem_pkl'])) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($log['catatan_dospem_lapangan'])): ?>
                                            <p><strong>Catatan Lapangan:</strong> <?= nl2br(htmlspecialchars($log['catatan_dospem_lapangan'])) ?></p>
                                        <?php endif; ?>
                                    </details>
                                <?php else: ?>
                                    <?= nl2br(htmlspecialchars($activity)) ?>
                                <?php endif; ?>
                            </td>
                            <?php foreach (['pkl' => 'Pembimbing PKL', 'lapangan' => 'Pembimbing Lapangan'] as $key => $label): ?>
                                <?php $status = htmlspecialchars((string) $log['status_dospem_' . $key]); ?>
                                <td role="cell" class="history-approval">
                                    <span class="history-cell-label" aria-hidden="true"><?= $label ?></span>
                                    <span class="dashboard-status" data-status="<?= $status ?>"><?= $status !== '' ? ucfirst($status) : '?' ?></span>
                                    <?php if (!empty($log['ttd_dospem_' . $key])): ?>
                                        <img src="<?= htmlspecialchars($log['ttd_dospem_' . $key]) ?>"
                                             alt="Tanda tangan <?= $label ?>" class="history-signature" loading="lazy">
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td role="cell" class="history-row-actions">
                                <?php if ($editable && !$is_impersonating): ?>
                                    <div class="history-actions">
                                        <a href="edit_logbook.php?id=<?= (int) $log['id'] ?>" title="Edit logbook" aria-label="Edit logbook"
                                           class="admin-button admin-button-quiet">
                                            <i class="fas fa-pen-to-square" aria-hidden="true"></i>
                                        </a>
                                        <a href="?delete=<?= (int) $log['id'] ?>" title="Hapus logbook" aria-label="Hapus logbook"
                                           onclick="return confirm('Yakin menghapus logbook ini?')" class="admin-button admin-button-danger">
                                            <i class="fas fa-trash-can" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                <?php elseif (!$editable): ?>
                                    <span class="history-locked"><i class="fas fa-lock" aria-hidden="true"></i> Terkunci</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
        $pagination_filters = [];
        include '../includes/pagination.php';
        ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
