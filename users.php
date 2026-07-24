<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';
require_login();
if (!user_is_admin()) {
    flash_set('error', 'Only the Admin account can view the users list.');
    redirect_to('index.php');
}
ensure_schema_columns();
$stmt = db()->query('SELECT username, display_name, role, college_account, is_active FROM users ORDER BY id ASC');
$users = $stmt->fetchAll();
render_page_header('Users - ' . APP_NAME);
?>
<section class="card">
    <h2 class="card-title">User Accounts and Roles</h2>
    <p class="helper">Default accounts are created or updated when you run setup.php.</p>
    <div class="table-wrap">
        <table class="monitoring-table users-table">
            <thead><tr><th>Username</th><th>Display Name</th><th>Role</th><th>College Account</th><th>Status</th><th>Default Password</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u):
                $defaultPassword = '';
                if ($u['username'] === 'admin') $defaultPassword = 'admin123';
                if ($u['username'] === 'cbit') $defaultPassword = 'cbit123';
                if ($u['username'] === 'cssh') $defaultPassword = 'cssh123';
            ?>
                <tr>
                    <td><?= h($u['username']) ?></td>
                    <td><?= h($u['display_name']) ?></td>
                    <td><?= h($u['role']) ?></td>
                    <td><?= h($u['college_account']) ?></td>
                    <td><?= ((int)$u['is_active'] === 1) ? 'Active' : 'Inactive' ?></td>
                    <td><?= h($defaultPassword) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_page_footer(); ?>
