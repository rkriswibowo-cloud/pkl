<?php
session_start();
require_once __DIR__ . '/includes/impersonation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

$active_user = $_SESSION['user'] ?? null;
$token = $_POST['token'] ?? null;
if (!$active_user || !is_string($token) || !hash_equals(impersonation_token(), $token)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/config/db.php';

$actor_id = $_SESSION['impersonator_id'] ?? null;
$action = $_POST['action'] ?? null;

if ($action === 'stop' && $actor_id !== null) {
    $subject_id = (int) $active_user['id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
    $stmt->bind_param('i', $actor_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if (!$admin) {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php', true, 303);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION = ['user' => $admin];
    error_log(sprintf('Impersonation stopped: admin=%d subject=%d', $actor_id, $subject_id));
    header('Location: admin/users.php', true, 303);
    exit;
}

if ($action !== 'start' || $active_user['role'] !== 'admin' || $actor_id !== null) {
    http_response_code(403);
    exit;
}

$actor_id = (int) $active_user['id'];
$subject_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$subject_id || $subject_id === $actor_id) {
    http_response_code(400);
    exit;
}

$stmt = $conn->prepare(
    "SELECT subject.* FROM users subject
     JOIN users actor ON actor.id = ? AND actor.role = 'admin'
     WHERE subject.id = ? AND subject.role IN ('mahasiswa', 'dosen_pkl', 'dosen_lapangan')"
);
$stmt->bind_param('ii', $actor_id, $subject_id);
$stmt->execute();
$subject = $stmt->get_result()->fetch_assoc();

if (!$subject) {
    $_SESSION['error'] = 'Akun yang dipilih tidak dapat diimpersonasi.';
    header('Location: admin/users.php', true, 303);
    exit;
}

unset($subject['password'], $subject['remember_token']);
session_regenerate_id(true);
$_SESSION = ['user' => $subject, 'impersonator_id' => $actor_id];
error_log(sprintf('Impersonation started: admin=%d subject=%d', $actor_id, $subject_id));
header('Location: ' . $subject['role'] . '/dashboard.php', true, 303);
exit;
