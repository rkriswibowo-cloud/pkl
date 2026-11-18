<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user']['id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Semua field harus diisi!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Password baru dan konfirmasi tidak cocok!";
    } elseif (strlen($new_password) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        // Cek password saat ini
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();
        
        if (password_verify($current_password, $hashed_password)) {
            // Update password baru
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $success = "Password berhasil diubah!";
            } else {
                $error = "Gagal mengubah password: " . $conn->error;
            }
            $update_stmt->close();
        } else {
            $error = "Password saat ini salah!";
        }
    }
}
$page_title = 'Ubah Password - Logbook PKL';
include 'includes/header.php';
?>

    
    <div class="dashboard-page">
        <div>
            <div class="dashboard-panel dashboard-section">
                <h1 class="dashboard-title text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-key mr-2 text-blue-600"></i>
                    Ubah Password
                </h1>
                
                <?php if ($success): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= $success ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="dashboard-form-grid dashboard-password-form">
                    <!-- Password Saat Ini -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-1"></i> Password Saat Ini
                        </label>
                        <input type="password" 
                               name="current_password" 
                               required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>
                    
                    <!-- Password Baru -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-1"></i> Password Baru
                        </label>
                        <input type="password" 
                               name="new_password" 
                               required
                               minlength="6"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        <p class="text-xs text-gray-500 mt-2">Minimal 6 karakter</p>
                    </div>
                    
                    <!-- Konfirmasi Password Baru -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-1"></i> Konfirmasi Password Baru
                        </label>
                        <input type="password" 
                               name="confirm_password" 
                               required
                               minlength="6"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>
                    
                    <!-- Tombol -->
                    <div class="pt-4">
                        <button type="submit" 
                                class="w-full bg-blue-600 text-white py-3 rounded-lg font-medium hover:bg-blue-700 transition">
                            <i class="fas fa-save mr-2"></i> Ubah Password
                        </button>
                        
                        <a href="profile.php" 
                           class="block text-center mt-4 text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali ke Profil
                        </a>
                    </div>
                </form>
                
                <div class="mt-8 pt-6 border-t">
                    <h3 class="text-sm font-medium text-gray-700 mb-2">Tips Password Aman:</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li><i class="fas fa-check text-green-500 mr-2"></i> Gunakan kombinasi huruf, angka, dan simbol</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i> Minimal 8 karakter untuk keamanan maksimal</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i> Jangan gunakan password yang mudah ditebak</li>
                        <li><i class="fas fa-check text-green-500 mr-2"></i> Ubah password secara berkala</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
