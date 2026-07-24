<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $sql = file_get_contents(__DIR__ . '/database/install.sql');
        if ($sql === false) {
            throw new RuntimeException('database/install.sql not found.');
        }
        $pdo->exec($sql);
        $pdo->exec('USE `' . str_replace('`', '``', DB_NAME) . '`');
        ensure_auth_schema($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([DB_NAME, 'class_records', 'cronasia_pmvgo_checks']);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE class_records ADD COLUMN cronasia_pmvgo_checks VARCHAR(100) NOT NULL DEFAULT '' AFTER schedule_text");
        }
        $message = 'Database, tables, and default user accounts were created/updated successfully. You may now open the dashboard.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Setup - <?= h(APP_NAME) ?></title><link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="container" style="max-width:850px">
    <section class="card" style="margin-top:30px">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px">
            <img class="logo" src="assets/img/cfc_logo.png" alt="CFCI"><img class="logo" src="assets/img/cbit_logo.png" alt="CBIT"><img class="logo" src="assets/img/cssh_logo.svg" alt="CSSH"><div><h2 style="margin:0">CBIT Class Monitoring Setup</h2><p class="helper">This will create the MySQL database and default tables.</p></div>
        </div>
        <?php if ($message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
        <p><b>Current database config:</b></p>
        <ul>
            <li>Host: <code><?= h(DB_HOST) ?></code></li>
            <li>Database: <code><?= h(DB_NAME) ?></code></li>
            <li>User: <code><?= h(DB_USER) ?></code></li>
        </ul>
        <form method="post" onsubmit="return confirm('Create or update the database tables and default users?')">
            <button class="btn btn-primary" type="submit">Create / Update Database</button>
            <a class="btn btn-outline" href="index.php">Open Dashboard</a>
        </form>
        <div class="demo-accounts" style="margin-top:18px">
            <b>Default users and roles:</b><br>
            Admin: <code>admin</code> / <code>admin123</code> · Role: <b>Admin</b> · Access: All<br>
            CBIT: <code>cbit</code> / <code>cbit123</code> · Role: <b>CBIT</b> · Yellow header<br>
            CSSH: <code>cssh</code> / <code>cssh123</code> · Role: <b>CSSH</b> · Purple header
        </div>
        <p class="helper" style="margin-top:18px">For XAMPP, start Apache and MySQL first. If your MySQL password is not blank, edit <code>includes/config.php</code>.</p>
    </section>
</main>
</body>
</html>
