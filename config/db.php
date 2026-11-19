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
?>
