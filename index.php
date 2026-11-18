<?php
session_start();
include 'config/db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Ambil data user berdasarkan username
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Verifikasi password
        if (password_verify($password, $row['password'])) {
            session_regenerate_id(true);
            unset($_SESSION['impersonator_id'], $_SESSION['impersonation_token']);
            $_SESSION['user'] = $row;

            // Remember me hanya menyimpan username, bukan password.
            if (!empty($_POST['remember'])) {
                setcookie('username', $username, time() + (86400 * 30), "/");
            } else {
                setcookie('username', '', time() - 1, "/");
            }

            // Hapus cookie password lama jika sebelumnya pernah tersimpan.
            setcookie('password', '', time() - 1, "/");

            // Redirect sesuai role
            header("Location: " . $row['role'] . "/dashboard.php");
            exit();
        } else {
            $error = "Username atau password salah.";
        }
    } else {
        $error = "Username atau password salah.";
    }
}

// Ambil nilai cookie jika ada
$remembered_username = isset($_COOKIE['username']) ? $_COOKIE['username'] : '';
$remember_checked = !empty($remembered_username);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Sistem Logbook PKL</title>

    <!-- Favicon -->
    <link rel="icon" href="assets/images/logo.png" type="image/x-icon">

    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --bg-color: #f8f9fc;
            --text-color: #2d3748;
            --text-muted: #718096;
            --primary-color: #5d73ea;
            --primary-hover: #4a5ed1;
            --input-bg: #edf2f9;
            --input-focus-border: #a3b2fd;
            --input-focus-bg: #ffffff;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.03);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Logos Section */
        .logos-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 24px;
            margin-bottom: 24px;
        }

        .logo-img {
            height: 85px;
            width: auto;
            object-fit: contain;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 28px;
            text-align: center;
        }

        /* Form Card */
        .login-card {
            width: 100%;
            background-color: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-header-banner {
            background-color: #f8fafc;
            padding: 16px 24px;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .card-body {
            padding: 32px 36px;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-input {
            width: 100%;
            height: 52px;
            padding: 0 16px;
            font-size: 0.95rem;
            color: var(--text-color);
            background-color: var(--input-bg);
            border: 1px solid transparent;
            border-radius: 8px;
            outline: none;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .form-input:focus {
            background-color: var(--input-focus-bg);
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px rgba(93, 115, 234, 0.15);
        }

        .form-input::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }

        /* Password Input Toggle */
        .password-input {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .password-toggle:hover {
            color: var(--primary-color);
            background-color: #f1f5f9;
        }

        /* Error Alert */
        .error-alert {
            background-color: #fef2f2;
            border: 1px solid #fee2e2;
            color: #ef4444;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.88rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Remember Me & Options */
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .remember-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
            user-select: none;
        }

        .remember-checkbox input {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-color);
            cursor: pointer;
        }

        /* Buttons */
        .btn-submit {
            width: 100%;
            height: 52px;
            background-color: var(--primary-color);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 6px -1px rgba(93, 115, 234, 0.25), 0 2px 4px -1px rgba(93, 115, 234, 0.15);
            transition: all 0.2s ease;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(93, 115, 234, 0.3), 0 4px 6px -2px rgba(93, 115, 234, 0.15);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Support Link */
        .support-link-container {
            text-align: center;
            margin-top: 20px;
        }

        .support-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: color 0.2s;
        }

        .support-link:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            color: #94a3b8;
            font-size: 0.85rem;
            line-height: 1.6;
            font-weight: 500;
        }

        .login-footer p {
            margin-bottom: 4px;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <!-- Two Logos Section -->
        <div class="logos-container">
            <img src="assets/images/dikti.png" alt="Logo DIKTI" class="logo-img">
            <img src="assets/images/uam1.png" alt="Logo Universitas Anwar Medika" class="logo-img">
        </div>

        <h2 class="page-title">Sign In</h2>

        <!-- Card Container -->
        <div class="login-card">
            <!-- Card Header Banner -->
            <div class="card-header-banner">
                Sistem Logbook PKL - Universitas Anwar Medika
            </div>

            <div class="card-body">
                <!-- Error Alert -->
                <?php if (!empty($error)): ?>
                <div class="error-alert" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php endif; ?>

                <form method="post" autocomplete="on">
                    <!-- Username/Email Field -->
                    <div class="form-group">
                        <label for="username" class="form-label">Username/Email</label>
                        <div class="input-wrapper">
                            <input type="text"
                                   name="username"
                                   id="username"
                                   value="<?= htmlspecialchars($remembered_username, ENT_QUOTES, 'UTF-8') ?>"
                                   required
                                   autocomplete="username"
                                   placeholder="rkriswibowo@gmail.com"
                                   class="form-input">
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrapper">
                            <input type="password"
                                   name="password"
                                   id="password"
                                   required
                                   autocomplete="current-password"
                                   placeholder="••••••••••••"
                                   class="form-input password-input">
                            <button type="button" class="password-toggle" id="togglePassword" aria-label="Tampilkan password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Options (Remember me) -->
                    <div class="form-options">
                        <label class="remember-checkbox" for="remember">
                            <input type="checkbox" name="remember" id="remember" value="1" <?= $remember_checked ? 'checked' : '' ?>>
                            <span>Ingat username</span>
                        </label>
                    </div>

                    <!-- Login Button -->
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </button>
                </form>

                <!-- Help Link -->
                <div class="support-link-container">
                    <a class="support-link" href="#" id="helpLink">Kendala login?</a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="login-footer">
            <p>Universitas Anwar Medika</p>
            <p>Sistem Logbook PKL &copy; <?= date('Y') ?></p>
        </div>
    </div>

    <script>
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const icon = this.querySelector('i');
                const isHidden = passwordInput.type === 'password';

                passwordInput.type = isHidden ? 'text' : 'password';
                icon.classList.toggle('fa-eye', !isHidden);
                icon.classList.toggle('fa-eye-slash', isHidden);
                this.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
            });
        }

        // Form submit behavior
        const form = document.querySelector('form');
        const submitBtn = document.querySelector('.btn-submit');

        if (form && submitBtn) {
            form.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value.trim();

                if (!username || !password) {
                    e.preventDefault();
                    alert('Harap isi username dan password!');
                    return false;
                }

                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Memproses...</span>';
                submitBtn.disabled = true;

                // Let the natural form submission proceed. Disable state will be reset upon reload/redirect.
            });
        }

        // Auto-focus username if empty
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            if (usernameField && !usernameField.value) {
                usernameField.focus();
            }
        });

        // Simple Help Dialog Alert
        const helpLink = document.getElementById('helpLink');
        if (helpLink) {
            helpLink.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Jika Anda mengalami kendala login, silakan hubungi administrator Sistem Logbook PKL Universitas Anwar Medika.');
            });
        }
    </script>
</body>
</html>
