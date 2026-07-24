<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
start_app_session();
ensure_schema_columns();

if (is_logged_in()) {
    redirect_to('index.php');
}

$error = '';
$next = clean_text($_GET['next'] ?? $_POST['next'] ?? 'index.php');
if ($next === '' || str_contains($next, '://') || str_starts_with($next, '/')) {
    $next = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean_text($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    try {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'display_name' => $user['display_name'],
                'role' => $user['role'],
                'college_account' => $user['college_account'],
            ];
            flash_set('success', 'Welcome, ' . ($user['display_name'] ?: $user['username']) . '.');
            redirect_to($next);
        }
        $error = 'Invalid username or password.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<main class="login-shell">
    <section class="login-card">
        <div class="login-logos">
            <img class="logo" src="assets/img/cfc_logo.png" alt="CFCI Logo">
            <img class="logo" src="assets/img/cbit_logo.png" alt="CBIT Logo">
            <img class="logo" src="assets/img/cssh_logo.svg" alt="CSSH Logo">
        </div>
        <h1><?= h(APP_NAME) ?></h1>
        <p class="helper">Sign in using your assigned college monitoring account.</p>
        <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
        <?php flash_render(); ?>
        <form method="post" class="login-form">
            <input type="hidden" name="next" value="<?= h($next) ?>">
            <label>Username</label>
            <input name="username" autocomplete="username" required autofocus>
            <label>Password</label>
            <input name="password" type="password" autocomplete="current-password" required>
            <button class="btn btn-primary" type="submit">Login</button>
        </form>
        <div class="demo-accounts">
            <b>Default accounts after setup:</b><br>
            Admin: <code>admin</code> / <code>admin123</code><br>
            CBIT: <code>cbit</code> / <code>cbit123</code> · Role: <b>CBIT</b><br>
            CSSH: <code>cssh</code> / <code>cssh123</code> · Role: <b>CSSH</b>
        </div>
        <p class="helper">If login does not work yet, open <a href="setup.php">setup.php</a> first and click Create / Update Database.</p>
    </section>
</main>
</body>
</html>
