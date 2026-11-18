<?php
session_start();

if (($_SESSION['user']['role'] ?? null) !== 'admin' || isset($_SESSION['impersonator_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$admin_id = (int) $_SESSION['user']['id'];
$target_id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$target = null;
if ($target_id !== false) {
    $stmt = $conn->prepare(
        "SELECT target.nama, target.username FROM users target
         JOIN users actor ON actor.id = ? AND actor.role = 'admin'
         WHERE target.id = ?"
    );
    $stmt->bind_param('ii', $admin_id, $target_id);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$target) {
    http_response_code(404);
}

$token = $_SESSION['admin_password_reset_token'] ??= bin2hex(random_bytes(32));
$error = '';
if ($target && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirmation = $_POST['confirm_password'] ?? '';

    if (!is_string($submitted_token) || !hash_equals($token, $submitted_token)) {
        http_response_code(403);
        $error = 'Permintaan tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif (!is_string($new_password) || mb_strlen($new_password, 'UTF-8') < 6) {
        $error = 'Kata sandi baru minimal 6 karakter.';
    } elseif (strlen($new_password) > 72 || str_contains($new_password, "\0")) {
        $error = 'Kata sandi terlalu panjang atau mengandung karakter yang tidak didukung.';
    } elseif ($new_password !== $confirmation) {
        $error = 'Konfirmasi kata sandi tidak cocok.';
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->bind_param('si', $hash, $target_id);
        $stmt->execute();
        if ($stmt->affected_rows === 1) {
            unset($_SESSION['admin_password_reset_token']);
            $_SESSION['success'] = 'Kata sandi pengguna berhasil direset.';
            error_log(sprintf('Admin password reset: admin=%d target=%d', $admin_id, $target_id));
            header('Location: users.php', true, 303);
            exit;
        }
        $error = 'Kata sandi tidak dapat direset. Akun mungkin sudah dihapus.';
    }
}

$page_title = 'Reset Kata Sandi - Logbook PKL';
require '../includes/header.php';
?>

<div class="dashboard-page">
    <header class="admin-list-header">
        <div>
            <a href="users.php" class="admin-list-back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Kelola Pengguna
            </a>
            <h2 class="dashboard-title">Reset Kata Sandi</h2>
        </div>
    </header>

    <section class="dashboard-panel dashboard-section">
        <?php if (!$target): ?>
            <p>Pengguna tidak ditemukan.</p>
        <?php else: ?>
            <div class="profile-identity">
                <div>
                    <h2><?= htmlspecialchars($target['nama']) ?></h2>
                    <p><?= htmlspecialchars($target['username']) ?></p>
                </div>
            </div>

            <form method="post" action="reset_password.php?id=<?= $target_id ?>" class="profile-form">
                <input type="hidden" name="token" value="<?= $token ?>">
                <?php if ($error !== ''): ?>
                    <p class="profile-feedback bg-red-50" role="alert"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <div class="dashboard-fields">
                    <div>
                        <label class="dashboard-field-label" for="reset-new-password">Kata Sandi Baru</label>
                        <input id="reset-new-password" type="password" name="new_password" minlength="6"
                               maxlength="72" autocomplete="new-password" required class="dashboard-field-control">
                    </div>
                    <div>
                        <label class="dashboard-field-label" for="reset-confirm-password">Konfirmasi Kata Sandi</label>
                        <input id="reset-confirm-password" type="password" name="confirm_password" minlength="6"
                               maxlength="72" autocomplete="new-password" required class="dashboard-field-control">
                    </div>
                </div>
                <div class="profile-form-actions">
                    <button type="submit" class="admin-button admin-button-primary">Simpan Kata Sandi Baru</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>

<?php require '../includes/footer.php'; ?>
