<?php
session_start();
require_once __DIR__ . '/../includes/impersonation.php';
require_writable_session();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'dosen_pkl') {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$feedback = "";

// Helper format tanggal Indonesia
if (!function_exists('formatTanggalIndo')) {
    function formatTanggalIndo($datetime_str) {
        if (empty($datetime_str)) return '-';
        $time = strtotime($datetime_str);
        if (!$time) return htmlspecialchars($datetime_str);
        $bulan = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
        ];
        $d = date('d', $time);
        $m = (int)date('m', $time);
        $y = date('Y', $time);
        $his = date('H:i', $time);
        $namaBulan = $bulan[$m] ?? date('M', $time);
        return "$d $namaBulan $y, $his WIB";
    }
}

// Proses submit form
if (($_SERVER['REQUEST_METHOD'] ?? '') == 'POST' && isset($_POST['logbook_id'])) {
    $id_logbook = intval($_POST['logbook_id']);
    $review = htmlspecialchars($_POST['review'] ?? '');
    $ttd_data = $_POST['signature'] ?? '';

    // Update status, review, dan tanggal approval
    $stmt = $conn->prepare("UPDATE logbook SET status_dospem_pkl='disetujui', catatan_dospem_pkl=?, tgl_approval_dospem_pkl=NOW() WHERE id=?");
    $stmt->bind_param("si", $review, $id_logbook);

    if ($stmt->execute()) {
        // Simpan TTD jika ada
        if (!empty($ttd_data) && strpos($ttd_data, 'data:image/png;base64,') === 0) {
            $imageData = explode(',', $ttd_data)[1];
            $decodedImage = base64_decode($imageData);

            if ($decodedImage === false) {
                $feedback = "Logbook disetujui, namun gagal decode base64 TTD.";
            } else {
                $filename = "../signatures/sign_".$id_logbook."_".time().".png";

                if (file_put_contents($filename, $decodedImage)) {
                    $conn->query("UPDATE logbook SET ttd_dospem_pkl='$filename' WHERE id='$id_logbook'");
                    $feedback = "Logbook berhasil disetujui dan TTD tersimpan.";
                } else {
                    $feedback = "Logbook disetujui, namun gagal menyimpan file TTD.";
                }
            }
        } else {
            $feedback = "Logbook berhasil disetujui.";
        }
    } else {
        $feedback = "Gagal memperbarui logbook.";
    }
}

// Setup pagination
$limit = 5;
$page = max(1, (int) ($_GET['page'] ?? 1));

$h_limit = 5;
$h_page = max(1, (int) ($_GET['h_page'] ?? 1));

$id_dosen_pkl = $_SESSION['user']['id'];

// 1. Data logbook pending (Persetujuan Logbook)
$total_result = $conn->query("
    SELECT COUNT(*) as total FROM logbook l
    JOIN bimbingan b ON l.id_mahasiswa = b.id_mahasiswa
    WHERE b.id_dospem_pkl = '$id_dosen_pkl' AND l.status_dospem_pkl = 'pending'
");

$total_row = $total_result->fetch_assoc();
$total_pages = ceil($total_row['total'] / $limit);
$page = min($page, max(1, (int) $total_pages));
$start = ($page - 1) * $limit;

$result = $conn->query("
    SELECT l.*, u.nama 
    FROM logbook l
    JOIN bimbingan b ON l.id_mahasiswa = b.id_mahasiswa
    JOIN users u ON l.id_mahasiswa = u.id
    WHERE b.id_dospem_pkl = '$id_dosen_pkl' AND l.status_dospem_pkl = 'pending'
    ORDER BY l.tanggal DESC, l.id DESC
    LIMIT $start, $limit
");

// 2. Data logbook disetujui (Riwayat Approval)
$h_total_result = $conn->query("
    SELECT COUNT(*) as total FROM logbook l
    JOIN bimbingan b ON l.id_mahasiswa = b.id_mahasiswa
    WHERE b.id_dospem_pkl = '$id_dosen_pkl' AND l.status_dospem_pkl = 'disetujui'
");

$h_total_row = $h_total_result->fetch_assoc();
$h_total_pages = ceil($h_total_row['total'] / $h_limit);
$h_page = min($h_page, max(1, (int) $h_total_pages));
$h_start = ($h_page - 1) * $h_limit;

$h_result = $conn->query("
    SELECT l.*, u.nama 
    FROM logbook l
    JOIN bimbingan b ON l.id_mahasiswa = b.id_mahasiswa
    JOIN users u ON l.id_mahasiswa = u.id
    WHERE b.id_dospem_pkl = '$id_dosen_pkl' AND l.status_dospem_pkl = 'disetujui'
    ORDER BY COALESCE(l.tgl_approval_dospem_pkl, CONCAT(l.tanggal, ' 00:00:00')) DESC, l.id DESC
    LIMIT $h_start, $h_limit
");
?>

<?php
$page_title = 'Dashboard Dosen Pembimbing PKL';
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">
    <header class="admin-list-header">
        <h2 class="dashboard-title">Dashboard Dosen Pembimbing PKL</h2>
    </header>

    <?php if ($feedback !== ''): ?>
        <p class="dashboard-feedback mb-4 px-4 py-3" role="status"><?= htmlspecialchars($feedback) ?></p>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════
         SECTION 1: PERSETUJUAN LOGBOOK (PENDING)
    ══════════════════════════════════════════════════ -->
    <section id="persetujuan-logbook" tabindex="0" aria-labelledby="pkl-review-title">
        <h3 class="pkl-review-heading" id="pkl-review-title">
            Persetujuan Logbook
            <?php if ((int)$total_row['total'] > 0): ?>
                <span class="dashboard-status" style="margin-left: 0.5rem;"><?= (int)$total_row['total'] ?> Menunggu</span>
            <?php endif; ?>
        </h3>
        <?php if ($result->num_rows === 0): ?>
            <p class="dashboard-panel dashboard-section">Tidak ada logbook yang menunggu persetujuan.</p>
        <?php endif; ?>

        <div class="pkl-review-list">
            <?php while ($log = $result->fetch_assoc()): ?>
                <?php
                $id = (int) $log['id'];
                $status = htmlspecialchars((string) $log['status_dospem_lapangan']);
                ?>
                <details name="pkl-approval-list" class="dashboard-panel pkl-review-card"<?= (int) $total_row['total'] === 1 ? ' open' : '' ?>>
                    <summary class="pkl-review-meta">
                        <span class="pkl-review-student">
                            <strong><?= htmlspecialchars($log['nama']) ?></strong>
                            <time datetime="<?= htmlspecialchars($log['tanggal']) ?>"><?= htmlspecialchars($log['tanggal']) ?></time>
                        </span>
                        <span class="pkl-review-status">
                            <span>Status Lapangan</span>
                            <span class="dashboard-status" data-status="<?= $status ?>"><?= $status !== '' ? ucfirst($status) : '&ndash;' ?></span>
                        </span>
                        <i class="fas fa-chevron-down pkl-review-chevron" aria-hidden="true"></i>
                    </summary>

                    <div class="dashboard-section">
                        <div class="pkl-review-activity">
                            <h5>Kegiatan</h5>
                            <p><?= nl2br(htmlspecialchars((string) $log['kegiatan'])) ?></p>
                        </div>

                        <?php if (!$is_impersonating): ?>
                            <form method="post" class="pkl-review-form">
                                <input type="hidden" name="logbook_id" value="<?= $id ?>">
                                <input type="hidden" name="signature">
                                <div class="pkl-review-fields">
                                    <div>
                                        <label class="dashboard-field-label" for="review_<?= $id ?>">Catatan Pembimbing</label>
                                        <textarea id="review_<?= $id ?>" name="review" rows="4" class="dashboard-field-control"
                                                  placeholder="Tulis catatan jika diperlukan."></textarea>
                                    </div>
                                    <div>
                                        <p class="dashboard-field-label" id="signature-label-<?= $id ?>">Tanda Tangan Digital</p>
                                        <div class="pkl-signature-frame">
                                            <canvas class="signature-pad" width="300" height="150"
                                                    aria-labelledby="signature-label-<?= $id ?>" aria-describedby="signature-help-<?= $id ?>"></canvas>
                                        </div>
                                        <div class="pkl-signature-tools">
                                            <p id="signature-help-<?= $id ?>">Tanda tangan di dalam kotak.</p>
                                            <button type="button" class="admin-button admin-button-quiet pkl-signature-clear">
                                                <i class="fas fa-eraser" aria-hidden="true"></i> Bersihkan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="profile-form-actions">
                                    <button type="submit" class="admin-button admin-button-primary">
                                        <i class="fas fa-check" aria-hidden="true"></i> Setujui Logbook
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endwhile; ?>
        </div>

        <?php $pagination_filters = ['h_page' => $h_page]; include __DIR__ . '/../includes/pagination.php'; ?>
    </section>

    <!-- ══════════════════════════════════════════════════
         SECTION 2: RIWAYAT APPROVAL (DISETUJUI)
    ══════════════════════════════════════════════════ -->
    <section id="riwayat-approval" tabindex="0" aria-labelledby="pkl-history-title" style="margin-top: 2.25rem;">
        <h3 class="pkl-review-heading" id="pkl-history-title">
            Riwayat Approval
            <?php if ((int)$h_total_row['total'] > 0): ?>
                <span class="dashboard-status" data-status="disetujui" style="margin-left: 0.5rem;"><?= (int)$h_total_row['total'] ?> Disetujui</span>
            <?php endif; ?>
        </h3>
        <?php if ($h_result->num_rows === 0): ?>
            <p class="dashboard-panel dashboard-section">Belum ada riwayat logbook yang disetujui.</p>
        <?php endif; ?>

        <div class="pkl-review-list">
            <?php while ($h = $h_result->fetch_assoc()): ?>
                <?php
                $h_id = (int) $h['id'];
                $h_status_lapangan = htmlspecialchars((string) $h['status_dospem_lapangan']);
                
                // Ambil tanggal approval atau fallback ke timestamp signature / tanggal logbook
                $tgl_approve = $h['tgl_approval_dospem_pkl'];
                if (empty($tgl_approve) && !empty($h['ttd_dospem_pkl']) && preg_match('/_(\d{10})\.png$/', $h['ttd_dospem_pkl'], $m)) {
                    $tgl_approve = date('Y-m-d H:i:s', (int)$m[1]);
                }
                $tgl_approve_text = !empty($tgl_approve) ? formatTanggalIndo($tgl_approve) : formatTanggalIndo($h['tanggal']);
                ?>
                <details name="pkl-history-list" class="dashboard-panel pkl-review-card">
                    <summary class="pkl-review-meta">
                        <span class="pkl-review-student">
                            <strong><?= htmlspecialchars($h['nama']) ?></strong>
                            <time datetime="<?= htmlspecialchars($h['tanggal']) ?>"><?= htmlspecialchars($h['tanggal']) ?></time>
                        </span>
                        <span class="pkl-review-status">
                            <span class="dashboard-status" data-status="disetujui">
                                <i class="fas fa-check" aria-hidden="true"></i> Sudah Approve
                            </span>
                            <span style="font-size: 0.8125rem; color: var(--dashboard-muted); display: inline-flex; align-items: center; gap: 0.25rem;">
                                <i class="far fa-calendar-check" aria-hidden="true"></i> <?= $tgl_approve_text ?>
                            </span>
                        </span>
                        <i class="fas fa-chevron-down pkl-review-chevron" aria-hidden="true"></i>
                    </summary>

                    <div class="dashboard-section">
                        <div class="pkl-review-activity">
                            <h5>Kegiatan</h5>
                            <p><?= nl2br(htmlspecialchars((string) $h['kegiatan'])) ?></p>
                        </div>

                        <?php if (!empty($h['jam_datang']) || !empty($h['jam_pulang']) || !empty($h['unit_penempatan'])): ?>
                            <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.75rem; font-size: 0.8125rem; color: var(--dashboard-muted);">
                                <?php if (!empty($h['jam_datang']) || !empty($h['jam_pulang'])): ?>
                                    <span><i class="far fa-clock" aria-hidden="true"></i> Jam: <?= htmlspecialchars($h['jam_datang'] ?? '-') ?> &ndash; <?= htmlspecialchars($h['jam_pulang'] ?? '-') ?></span>
                                <?php endif; ?>
                                <?php if (!empty($h['unit_penempatan'])): ?>
                                    <span><i class="far fa-building" aria-hidden="true"></i> Unit: <?= htmlspecialchars($h['unit_penempatan']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($h['catatan_dospem_pkl'])): ?>
                            <div style="margin-top: 0.75rem;">
                                <h5 style="margin-bottom: 0.25rem; font-weight: 650; font-size: 0.875rem;">Catatan Pembimbing</h5>
                                <p style="margin: 0; color: var(--dashboard-text); font-size: 0.875rem; background: var(--dashboard-raised); padding: 0.625rem 0.875rem; border-radius: 6px; border: 1px solid var(--dashboard-border);">
                                    <?= nl2br(htmlspecialchars($h['catatan_dospem_pkl'])) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--dashboard-border); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 0.75rem;">
                            <div>
                                <p style="margin: 0 0 0.25rem; font-size: 0.8125rem; color: var(--dashboard-muted);">
                                    <strong>Tanggal Approve:</strong> <?= $tgl_approve_text ?>
                                </p>
                                <p style="margin: 0; font-size: 0.8125rem; color: var(--dashboard-muted);">
                                    <strong>Status Pembimbing Lapangan:</strong>
                                    <span class="dashboard-status" data-status="<?= $h_status_lapangan ?>">
                                        <?= $h_status_lapangan !== '' ? ucfirst($h_status_lapangan) : '&ndash;' ?>
                                    </span>
                                </p>
                            </div>
                            <?php if (!empty($h['ttd_dospem_pkl'])): ?>
                                <div style="text-align: right;">
                                    <p style="margin: 0 0 0.25rem; font-size: 0.75rem; color: var(--dashboard-muted);">Tanda Tangan Dosen PKL:</p>
                                    <img src="<?= htmlspecialchars($h['ttd_dospem_pkl']) ?>" alt="Tanda Tangan Dosen PKL"
                                         style="max-height: 50px; max-width: 140px; object-fit: contain; background: #fff; padding: 4px; border: 1px solid var(--dashboard-border); border-radius: 6px;">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
            <?php endwhile; ?>
        </div>

        <!-- Pagination Riwayat Approval -->
        <?php
        $h_pagination_total = (int) $h_total_row['total'];
        $h_pagination_last = (int) $h_total_pages;
        $h_pagination_start = $h_start < $h_pagination_total ? $h_start + 1 : 0;
        $h_pagination_end = min($h_start + $h_limit, $h_pagination_total);
        $h_pagination_numbers = [];

        if ($h_pagination_last <= 7 && $h_pagination_last > 0) {
            $h_pagination_numbers = range(1, $h_pagination_last);
        } elseif ($h_pagination_last > 7 && $h_page <= 3) {
            $h_pagination_numbers = [...range(1, max(3, $h_page + 1)), $h_pagination_last - 1, $h_pagination_last];
        } elseif ($h_pagination_last > 7 && $h_page >= $h_pagination_last - 2) {
            $h_pagination_numbers = [1, 2, ...range(min($h_page - 1, $h_pagination_last - 2), $h_pagination_last)];
        } elseif ($h_pagination_last > 7) {
            $h_pagination_numbers = [1, $h_page - 1, $h_page, $h_page + 1, $h_pagination_last];
        }

        $h_pagination_href = static function (int $number) use ($page): string {
            return htmlspecialchars('?' . http_build_query(['page' => $page, 'h_page' => $number]) . '#riwayat-approval', ENT_QUOTES, 'UTF-8');
        };
        ?>
        <?php if ($h_pagination_total > 0): ?>
            <div class="dashboard-pagination">
                <p class="dashboard-pagination-summary">
                    Menampilkan
                    <?php if ($h_pagination_start === 0): ?>
                        0
                    <?php elseif ($h_pagination_start === $h_pagination_end): ?>
                        <?= $h_pagination_start ?>
                    <?php else: ?>
                        <?= $h_pagination_start ?>&ndash;<?= $h_pagination_end ?>
                    <?php endif; ?>
                    dari <?= $h_pagination_total ?>
                </p>
                <?php if ($h_pagination_last > 1): ?>
                    <nav class="dashboard-pagination-links" aria-label="Navigasi halaman riwayat">
                        <?php if ($h_page > 1): ?>
                            <a class="dashboard-pagination-direction" href="<?= $h_pagination_href(min($h_page - 1, $h_pagination_last)) ?>"
                               rel="prev" aria-label="Halaman sebelumnya">
                                <i class="fas fa-chevron-left" aria-hidden="true"></i><span>Sebelumnya</span>
                            </a>
                        <?php endif; ?>
                        <?php $h_pagination_previous = 0; ?>
                        <?php foreach ($h_pagination_numbers as $number): ?>
                            <?php if ($number - $h_pagination_previous > 1): ?>
                                <span class="dashboard-pagination-gap" aria-hidden="true">&hellip;</span>
                            <?php endif; ?>
                            <a href="<?= $h_pagination_href($number) ?>" aria-label="Halaman <?= $number ?>"
                                <?= $number === $h_page ? 'aria-current="page"' : '' ?>><?= $number ?></a>
                            <?php $h_pagination_previous = $number; ?>
                        <?php endforeach; ?>
                        <?php if ($h_page < $h_pagination_last): ?>
                            <a class="dashboard-pagination-direction" href="<?= $h_pagination_href($h_page + 1) ?>"
                               rel="next" aria-label="Halaman selanjutnya">
                                <span>Selanjutnya</span><i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
    document.querySelectorAll('.pkl-review-form').forEach(form => {
        const canvas = form.querySelector('.signature-pad');
        const context = canvas.getContext('2d');
        let activePointer = null;

        function clearSignature() {
            context.fillStyle = '#fff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.beginPath();
        }

        function point(event) {
            const rect = canvas.getBoundingClientRect();
            return [
                (event.clientX - rect.left) * canvas.width / rect.width,
                (event.clientY - rect.top) * canvas.height / rect.height,
            ];
        }

        clearSignature();
        context.strokeStyle = '#000';
        context.lineWidth = 2;
        context.lineCap = 'round';

        canvas.addEventListener('pointerdown', event => {
            if (activePointer !== null || event.button !== 0) return;
            activePointer = event.pointerId;
            canvas.setPointerCapture(activePointer);
            context.beginPath();
            context.moveTo(...point(event));
        });

        canvas.addEventListener('pointermove', event => {
            if (event.pointerId !== activePointer) return;
            const position = point(event);
            context.lineTo(...position);
            context.stroke();
            context.beginPath();
            context.moveTo(...position);
        });

        for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) {
            canvas.addEventListener(type, event => {
                if (event.pointerId === activePointer) activePointer = null;
            });
        }

        form.querySelector('.pkl-signature-clear').addEventListener('click', clearSignature);
        form.addEventListener('submit', () => {
            form.elements.signature.value = canvas.toDataURL('image/png');
        });
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
