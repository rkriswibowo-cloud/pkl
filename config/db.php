<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "pkl-logbook";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

require_once __DIR__ . '/../includes/settings.php';

// Integrasi Shared SSO untuk akun su12345
if (session_status() === PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/../../sso_shared.php';
    if (isset($_SESSION['user']) && ($_SESSION['user']['username'] ?? '') === 'su12345') {
        if (!sso_is_active('su12345', $_SESSION['su_sso_token'] ?? null)) {
            $_SESSION = [];
            session_destroy();
            $redirect_base = (strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false || strpos($_SERVER['PHP_SELF'] ?? '', '/mahasiswa/') !== false || strpos($_SERVER['PHP_SELF'] ?? '', '/dosen_') !== false) ? '../index.php' : 'index.php';
            if (basename($_SERVER['PHP_SELF'] ?? '') !== 'index.php') {
                header("Location: " . $redirect_base);
                exit();
            }
        }
    } elseif (!isset($_SESSION['user']) && sso_can_auto_login('su12345')) {
        $stmt_sso = $conn->prepare("SELECT * FROM users WHERE username = 'su12345' LIMIT 1");
        if ($stmt_sso) {
            $stmt_sso->execute();
            $res_sso = $stmt_sso->get_result();
            if ($sso_user = $res_sso->fetch_assoc()) {
                $_SESSION['user'] = $sso_user;
                $_SESSION['su_sso_token'] = $_COOKIE[SSO_COOKIE_NAME] ?? '';
            }
        }
    }
}
?>
