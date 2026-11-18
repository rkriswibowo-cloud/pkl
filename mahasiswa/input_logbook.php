<?php
session_start();
require_once __DIR__ . '/../includes/impersonation.php';
require_writable_session();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'mahasiswa') {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';

$error = "";
$hari_options = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $hari = $_POST['hari'] ?? '';
    $tanggal = htmlspecialchars($_POST['tanggal']);
    $jam_datang = htmlspecialchars($_POST['jam_datang']);
    $jam_pulang = htmlspecialchars($_POST['jam_pulang']);
    $unit_penempatan = htmlspecialchars($_POST['unit_penempatan']);
    $kegiatan = htmlspecialchars($_POST['kegiatan']);
    $id_mahasiswa = $_SESSION['user']['id'];

    // Validasi jam
    if (!in_array($hari, $hari_options, true)) {
        $error = 'Pilih hari Senin sampai Minggu.';
    } elseif (strtotime($jam_pulang) <= strtotime($jam_datang)) {
        $error = "Jam pulang harus lebih besar dari jam datang";
    } else {
        $stmt = $conn->prepare("INSERT INTO logbook (id_mahasiswa, hari, tanggal, jam_datang, jam_pulang, unit_penempatan, kegiatan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $id_mahasiswa, $hari, $tanggal, $jam_datang, $jam_pulang, $unit_penempatan, $kegiatan);

        if ($stmt->execute()) {
            // Redirect ke halaman riwayat setelah sukses
            header("Location: riwayat.php");
            exit();
        } else {
            $error = "Gagal menyimpan logbook.";
        }
    }
}
$page_title = 'Input Kegiatan Harian - Logbook PKL';
include '../includes/header.php';
?>


<div class="dashboard-page">
    <header class="admin-list-header">
        <div>
            <a href="dashboard.php" class="admin-list-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Dashboard
            </a>
            <h2 class="dashboard-title">Input Kegiatan Harian</h2>
        </div>
        <a href="riwayat.php" class="admin-button">
            <i class="fas fa-clock-rotate-left" aria-hidden="true"></i> Riwayat Logbook
        </a>
    </header>

    <div class="dashboard-panel dashboard-section">
        <?php if ($error): ?>
            <p class="profile-feedback bg-red-50" role="alert"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <fieldset class="logbook-fields"<?= $is_impersonating ? ' disabled' : '' ?>>
                <div class="dashboard-fields dashboard-fields-three">
                    <div>
                        <label class="dashboard-field-label" for="logbook-hari">Hari</label>
                        <select id="logbook-hari" name="hari" class="dashboard-field-control" required>
                            <option value="">Pilih hari</option>
                            <?php foreach ($hari_options as $hari_option): ?>
                                <option value="<?= $hari_option ?>"<?= ($hari ?? '') === $hari_option ? ' selected' : '' ?>>
                                    <?= $hari_option ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="dashboard-field-label" for="logbook-tanggal">Tanggal</label>
                        <input id="logbook-tanggal" type="date" name="tanggal" value="<?= $tanggal ?? '' ?>"
                               class="dashboard-field-control" required>
                    </div>
                    <div>
                        <label class="dashboard-field-label" for="logbook-unit">Unit Penempatan</label>
                        <input id="logbook-unit" type="text" name="unit_penempatan" value="<?= $unit_penempatan ?? '' ?>"
                               placeholder="Contoh: Divisi IT" class="dashboard-field-control" required>
                    </div>
                </div>

                <div class="dashboard-fields">
                    <div>
                        <label class="dashboard-field-label" for="logbook-datang">Jam Datang</label>
                        <input id="logbook-datang" type="time" name="jam_datang" value="<?= $jam_datang ?? '' ?>"
                               class="dashboard-field-control" required>
                    </div>
                    <div>
                        <label class="dashboard-field-label" for="logbook-pulang">Jam Pulang</label>
                        <input id="logbook-pulang" type="time" name="jam_pulang" value="<?= $jam_pulang ?? '' ?>"
                               class="dashboard-field-control" required>
                    </div>
                </div>

                <div>
                    <label class="dashboard-field-label" for="logbook-kegiatan">Uraian Kegiatan</label>
                    <textarea id="logbook-kegiatan" name="kegiatan" rows="4" class="dashboard-field-control"
                              placeholder="Tuliskan pekerjaan dan hasilnya." required><?= $kegiatan ?? '' ?></textarea>
                </div>
            </fieldset>

            <?php if (!$is_impersonating): ?>
                <div class="logbook-form-footer">
                    <p>Logbook dapat diedit selama masih menunggu persetujuan kedua pembimbing.</p>
                    <button type="submit" class="admin-button admin-button-primary">
                        <i class="fas fa-paper-plane" aria-hidden="true"></i> Ajukan Logbook
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
