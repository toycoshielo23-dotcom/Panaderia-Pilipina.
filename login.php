<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $user = user_by_username($username);
    if ($user && password_verify($password, (string)($user['password_hash'] ?? ''))) {
        $_SESSION['user'] = $user;
        if (($user['role'] ?? '') === 'admin') {
            redirect('admin.php');
        }

        redirect('tenant.php');
    }

    $_SESSION['login_error'] = 'Invalid username or password.';
}

$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Golden Haven</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <p class="eyebrow">Secure access</p>
            <h1>Boarding House Portal</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="auth-form">
            <label>
                Username
                <input type="text" name="username" placeholder="Enter username" required>
            </label>
            <label>
                Password
                <input type="password" name="password" placeholder="Enter password" required>
            </label>
            <button type="submit" class="btn btn-primary full-width">Login</button>
        </form>

        <div class="demo-box">
            <p><strong>Demo credentials</strong></p>
            <p>Admin: admin / admin123</p>
            <p>Tenant: maria / tenant123</p>
        </div>

        <p class="back-link"><a href="index.php">Return to landing page</a></p>
    </div>
</body>
</html>
