<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'dosen_pkl') {
    header("Location: ../../index.php");
    exit();
}

include '../config/db.php';

$feedback = "";

// Proses submit form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['logbook_id'])) {
    $id_logbook = intval($_POST['logbook_id']);
    $review = htmlspecialchars($_POST['review'] ?? '');
    $ttd_data = $_POST['signature'] ?? '';

    // Update status dan review
    $stmt = $conn->prepare("UPDATE logbook SET status_dospem_pkl='disetujui', catatan_dospem_pkl=? WHERE id=?");
    $stmt->bind_param("si", $review, $id_logbook);

    if ($stmt->execute()) {
        // Simpan TTD jika ada
        if (!empty($ttd_data) && strpos($ttd_data, 'data:image/png;base64,') === 0) {
            $imageData = explode(',', $ttd_data)[1];
            $decodedImage = base64_decode($imageData);

            if ($decodedImage === false) {
                $feedback = "Gagal decode base64.";
            } else {
                $filename = "../signatures/sign_".$id_logbook."_".time().".png";

                if (file_put_contents($filename, $decodedImage)) {
                    $conn->query("UPDATE logbook SET ttd_dospem_pkl='$filename' WHERE id='$id_logbook'");
                    $feedback = "Logbook berhasil disetujui dan TTD tersimpan.";
                } else {
                    $feedback = "Gagal menyimpan file TTD.";
                }
            }
        } else {
            $feedback = "TTD kosong atau format salah.";
        }
    } else {
        $feedback = "Gagal memperbarui logbook.";
    }
}

// Pagination setup
$limit = 5;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$start = ($page - 1) * $limit;

// Hitung total logbook milik mahasiswa bimbingan dosen ini
$id_dosen_pkl = $_SESSION['user']['id'];
$total_result = $conn->query("
    SELECT COUNT(*) as total FROM logbook l
    JOIN bimbingan b ON l.id_mahasiswa = b.id_mahasiswa
    WHERE b.id_dospem_pkl = '$id_dosen_pkl' AND l.status_dospem_pkl = 'pending'
");

$total_row = $total_result->fetch_assoc();
$total_pages = ceil($total_row['total'] / $limit);

// Ambil logbook milik mahasiswa bimbingan dosen pkl ini
$result = $conn->query("
    SELECT l.*, u.nama 
    FROM logbook l
    JOIN bimbingan b ON l.id_mahasiswa = b.id_mahasiswa
    JOIN users u ON l.id_mahasiswa = u.id
    WHERE b.id_dospem_pkl = '$id_dosen_pkl' AND l.status_dospem_pkl = 'pending'
    ORDER BY l.tanggal DESC
    LIMIT $start, $limit
");
?>


<?php
$page_title = 'Dashboard Dosen Pembimbing PKL';
include '../includes/header.php';
?>

<div class="min-w-0">
    <h2 class="dashboard-title text-2xl font-bold text-gray-800 mb-6"><i class="fas fa-graduation-cap" aria-hidden="true"></i>Dashboard Dosen Pembimbing PKL</h2>

    <?php if (!empty($feedback)): ?>
        <div class="dashboard-feedback mb-4 px-4 py-3 rounded"><?= $feedback ?></div>
    <?php endif; ?>

    <!-- Daftar Logbook -->
    <div id="persetujuan-logbook" class="dashboard-panel dashboard-table-scroll" tabindex="0" role="region" aria-label="Tabel persetujuan logbook">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mahasiswa</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kegiatan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status Lapangan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Review</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">TTD Approval</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php while ($log = $result->fetch_assoc()): ?>
            <tr>
                <td class="px-6 py-4"><?= htmlspecialchars($log['nama']) ?></td>
                <td class="px-6 py-4"><?= htmlspecialchars($log['tanggal']) ?></td>
                <td class="px-6 py-4"><?= substr(htmlspecialchars($log['kegiatan']), 0, 100) ?>...</td>
                <td class="px-6 py-4"><span class="dashboard-status" data-status="<?= htmlspecialchars((string) $log['status_dospem_lapangan']) ?>"><?= ucfirst($log['status_dospem_lapangan']) ?></span></td>
                <td class="px-6 py-4">
                    <textarea rows="2" name="review_<?= $log['id'] ?>" id="review_<?= $log['id'] ?>" class="dashboard-review w-full border rounded px-3 py-1" placeholder="Masukkan komentar..."></textarea>
                </td>
                <td class="px-6 py-4">
                    <canvas id="signatureCanvas_<?= $log['id'] ?>" class="signature-pad mb-2"></canvas>
                    <input type="hidden" id="signatureData_<?= $log['id'] ?>">
                    <button type="button" onclick="clearSignature(<?= $log['id'] ?>)" class="dashboard-clear text-red-600 text-sm">Clear</button>
                </td>
                <td class="px-6 py-4 space-x-2">
                    <form method="post">
                        <input type="hidden" name="logbook_id" value="<?= $log['id'] ?>">
                        <input type="hidden" name="review" id="reviewInput_<?= $log['id'] ?>">
                        <input type="hidden" name="signature" id="signatureInput_<?= $log['id'] ?>">
                        <button type="submit" class="dashboard-approve px-3 py-1 rounded">
                            <i class="fas fa-check-circle"></i> Setujui
                        </button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>

    <?php $pagination_filters = []; include '../includes/pagination.php'; ?>

    
</div>

<!-- Script Signature Pad -->
<script>
    const canvases = document.querySelectorAll('canvas.signature-pad');

    canvases.forEach(canvas => {
        const ctx = canvas.getContext('2d');
        let drawing = false;

        canvas.width = 300;
        canvas.height = 150;

        function startDrawing(e) {
            drawing = true;
            draw(e);
        }

        function stopDrawing() {
            drawing = false;
            ctx.beginPath();
        }

        function draw(e) {
            if (!drawing) return;
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000';
            ctx.lineTo(x, y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        // Mouse Events
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);

        // Touch Events
        canvas.addEventListener('touchstart', (e) => {
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent("mousedown", {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });

        canvas.addEventListener('touchmove', (e) => {
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent("mousemove", {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });

        canvas.addEventListener('touchend', () => {
            const mouseEvent = new MouseEvent("mouseup");
            canvas.dispatchEvent(mouseEvent);
        });

        // Reset warna putih
        ctx.fillStyle = "#fff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = "#000";
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
    });

    function clearSignature(id) {
        const canvas = document.getElementById("signatureCanvas_"+id);
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = "#fff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.beginPath();
        document.getElementById("signatureData_"+id).value = '';
    }

    // Saat submit form
    document.querySelectorAll("form").forEach(form => {
        form.addEventListener("submit", function(e) {
            const id = this.querySelector("[name=logbook_id]").value;
            const canvas = document.getElementById("signatureCanvas_" + id);
            const signatureData = canvas.toDataURL(); // Base64

            document.getElementById("signatureInput_"+id).value = signatureData;
            document.getElementById("reviewInput_"+id).value = document.getElementById("review_"+id).value;
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
