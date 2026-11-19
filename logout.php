<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('PKL_SESSID');
    session_start();
}
require_once __DIR__ . '/../sso_shared.php';

$is_su = (($_SESSION['user']['username'] ?? '') === 'su12345');
if ($is_su || sso_is_active('su12345')) {
    sso_record_logout('su12345');
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Keluar Sesi Logbook PKL...</title>
    <?= sso_broadcast_logout_script() ?>
    <script>
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 120);
    </script>
</head>
<body style="font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f8fafc; color: #475569;">
    <p>Keluar sesi, mengalihkan...</p>
</body>
</html>
