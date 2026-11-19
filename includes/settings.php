<?php
// includes/settings.php - Pengelolaan Pengaturan Sistem (Logo, Favicon, dll.)

if (!function_exists('get_app_settings')) {
    function get_app_settings() {
        global $conn;
        static $settings_cache = null;

        if ($settings_cache !== null) {
            return $settings_cache;
        }

        $defaults = [
            'app_logo'           => 'assets/images/dashboard-logo-uam.png',
            'app_favicon'        => 'assets/images/dashboard-logo-uam.png',
            'app_logo_login'     => 'assets/images/uam1.png',
            'app_logo_secondary' => 'assets/images/dikti.png',
            'app_name'           => 'Logbook PKL',
            'app_desc'           => 'Sistem Monitoring Praktik Kerja Lapangan'
        ];

        if (!isset($conn) || !($conn instanceof mysqli)) {
            $settings_cache = $defaults;
            return $settings_cache;
        }

        // Cek dan buat tabel jika belum tersedia
        $check = $conn->query("SHOW TABLES LIKE 'settings'");
        if (!$check || $check->num_rows === 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS `settings` (
                `setting_key` varchar(50) NOT NULL,
                `setting_value` text NOT NULL,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            foreach ($defaults as $k => $v) {
                $stmt = $conn->prepare("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ss", $k, $v);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        $res = $conn->query("SELECT setting_key, setting_value FROM settings");
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $defaults[$row['setting_key']] = $row['setting_value'];
            }
        }

        $settings_cache = $defaults;
        return $settings_cache;
    }
}

if (!function_exists('get_setting')) {
    function get_setting($key, $default = '') {
        $settings = get_app_settings();
        return $settings[$key] ?? $default;
    }
}

if (!function_exists('update_setting')) {
    function update_setting($key, $value) {
        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) {
            return false;
        }

        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        if ($stmt) {
            $stmt->bind_param("sss", $key, $value, $value);
            $res = $stmt->execute();
            $stmt->close();
            return $res;
        }
        return false;
    }
}
