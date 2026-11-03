<?php
/**
 * index.php — Admin Login Page
 */

$sessionDir = __DIR__ . '/sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0777, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    @session_save_path($sessionDir);
}
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Already logged in → go to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Admin credentials
if (!defined('ADMIN_USER')) {
    define('ADMIN_USER', 'admin');
}
$ADMIN_PASS_HASH = password_hash('astro2025', PASSWORD_BCRYPT);

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // CSRF check
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === ADMIN_USER && ($password === 'astro2025' || password_verify($password, $ADMIN_PASS_HASH))) {
            $_SESSION['admin_logged_in'] = true;
            session_regenerate_id(true);
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

// Generate CSRF token for the form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Astro Admin — Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Tamil:wght@400;600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --purple-dark:   #1a0a2e;
            --purple-mid:    #2d1457;
            --purple-accent: #7c3aed;
            --purple-light:  #a78bfa;
            --gold:          #f59e0b;
            --white:         #ffffff;
            --gray-50:       #f8f7ff;
            --gray-200:      #e2e0f0;
            --gray-500:      #6b7280;
            --red:           #ef4444;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--purple-dark) 0%, var(--purple-mid) 60%, #3b0764 100%);
            font-family: 'Inter', 'Noto Sans Tamil', sans-serif;
            padding: 1rem;
        }

        /* Stars background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                radial-gradient(1px 1px at 10% 20%, rgba(255,255,255,0.6) 0%, transparent 100%),
                radial-gradient(1px 1px at 80% 10%, rgba(255,255,255,0.5) 0%, transparent 100%),
                radial-gradient(1px 1px at 50% 70%, rgba(255,255,255,0.4) 0%, transparent 100%),
                radial-gradient(1px 1px at 30% 80%, rgba(255,255,255,0.6) 0%, transparent 100%),
                radial-gradient(1px 1px at 90% 60%, rgba(255,255,255,0.3) 0%, transparent 100%),
                radial-gradient(2px 2px at 60% 30%, rgba(255,255,255,0.4) 0%, transparent 100%),
                radial-gradient(1px 1px at 20% 50%, rgba(255,255,255,0.5) 0%, transparent 100%);
            pointer-events: none;
            z-index: 0;
        }

        .login-card {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(167, 139, 250, 0.25);
            backdrop-filter: blur(16px);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5), 0 0 40px rgba(124,58,237,0.15);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .astro-icon {
            font-size: 3rem;
            display: block;
            margin-bottom: 0.5rem;
            filter: drop-shadow(0 0 12px rgba(245,158,11,0.6));
        }

        .login-header h1 {
            color: var(--white);
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .login-header p {
            color: var(--purple-light);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            color: var(--purple-light);
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(167,139,250,0.3);
            border-radius: 10px;
            color: var(--white);
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--purple-accent);
            background: rgba(124,58,237,0.12);
        }

        input::placeholder { color: rgba(255,255,255,0.3); }

        .error-box {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.4);
            color: #fca5a5;
            border-radius: 8px;
            padding: 0.65rem 1rem;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-login {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(135deg, var(--purple-accent), #9333ea);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            letter-spacing: 0.03em;
            transition: opacity 0.2s, transform 0.1s;
            margin-top: 0.5rem;
        }

        .btn-login:hover { opacity: 0.9; }
        .btn-login:active { transform: scale(0.98); }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: rgba(255,255,255,0.3);
            font-size: 0.75rem;
        }

        @media (max-width: 480px) {
            .login-card { padding: 2rem 1.25rem; border-radius: 16px; }
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-header">
        <span class="astro-icon">🔮</span>
        <h1>Astro Admin</h1>
        <p>Sign in to manage bookings &amp; reports</p>
    </div>

    <?php if ($error): ?>
        <div class="error-box">
            <span>⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   placeholder="Enter admin username"
                   autocomplete="username" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   placeholder="Enter password"
                   autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn-login">Sign In ✨</button>
    </form>

    <p class="login-footer">Jothidam Admin Panel &copy; <?= date('Y') ?></p>
</div>
</body>
</html>
