<?php
require_once __DIR__ . '/functions.php';

function render_page_header(string $title = APP_NAME): void
{
    start_app_session();
    $user = auth_user();
    $college = $user ? active_college_for_request($_GET) : 'CBIT';
    $profile = college_profile($college === 'ALL' ? 'CBIT' : $college);
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="college-<?= h(strtolower($profile['key'])) ?>" style="--header-color: <?= h($profile['header_color']) ?>;">
<header class="topbar no-print" style="--header-color: <?= h($profile['header_color']) ?>;">
    <div class="topbar-inner">
        <img class="logo" src="assets/img/cfc_logo.png" alt="CFCI Logo">
        <div class="brand">
            <h1><?= h(APP_NAME) ?></h1>
            <p>Excel import, class monitoring checklist, print report, and Excel export.</p>
            <?php if ($user): ?><p class="user-line">Logged in as <b><?= h($user['display_name'] ?: $user['username']) ?></b> · Role: <?= h($user['role']) ?> · Account: <?= h($user['college_account']) ?></p><?php endif; ?>
        </div>
        <?php if ($user): ?>
        <nav class="nav">
            <a class="btn btn-outline" href="index.php">Dashboard</a>
            <a class="btn btn-outline" href="upload.php">Upload Excel</a>
            <a class="btn btn-outline" href="print.php" target="_blank">Print View</a>
            <a class="btn btn-gold" href="export.php">Export Excel</a>
            <?php if (user_is_admin()): ?><a class="btn btn-outline" href="users.php">Users</a><?php endif; ?>
            <a class="btn btn-danger" href="logout.php">Logout</a>
        </nav>
        <?php endif; ?>
        <img class="logo" src="<?= h($profile['logo']) ?>" alt="<?= h($profile['label']) ?> Logo">
    </div>
</header>
<main class="container">
<?php flash_render(); ?>
    <?php
}

function render_page_footer(): void
{
    ?>
    <p class="footer-note no-print">Class Monitoring System - PHP and MySQL local version</p>
</main>
</body>
</html>
    <?php
}

function render_report_header(array $settings, ?string $collegeKey = null): void
{
    $collegeKey = $collegeKey ?: active_college_for_request($_GET);
    if ($collegeKey === 'ALL') $collegeKey = 'CBIT';
    $profile = college_profile($collegeKey);
    $collegeName = $profile['college_name'];
    $monitoringTitle = $profile['monitoring_title'];
    ?>
    <div class="report-header exact-header" style="--header-color: <?= h($profile['header_color']) ?>;">
        <div class="header-logo left-logo"><img src="assets/img/cfc_logo.png" alt="CFCI Logo"></div>
        <div class="header-text">
            <h2><?= h($collegeName) ?></h2>
            <p>ACADEMIC YEAR <?= h(setting_value($settings, 'academic_year', '2026-2027')) ?></p>
            <p><?= h(setting_value($settings, 'semester', 'FIRST SEMESTER')) ?></p>
            <p class="program-title"><?= h($monitoringTitle) ?></p>
            <p><?= h(setting_value($settings, 'report_title', "TEACHER'S MONITORING SUMMARY")) ?></p>
            <p class="covered-date"><?= h(setting_value($settings, 'covered_dates', 'JULY 6-12, 2026')) ?></p>
        </div>
        <div class="header-logo right-logo"><img src="<?= h($profile['logo']) ?>" alt="<?= h($profile['label']) ?> Logo"></div>
    </div>
    <?php
}

function render_signature_block(): void
{
    $blocks = signature_blocks();
    ?>
    <table class="signature-table exact-signature-table">
        <colgroup>
            <col style="width:25%"><col style="width:25%"><col style="width:25%"><col style="width:25%">
        </colgroup>
        <tr>
            <?php foreach ($blocks as $block): ?>
                <td class="signature-label"><?= h($block['label']) ?></td>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($blocks as $block): ?>
                <td class="signature-space">&nbsp;</td>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($blocks as $block): ?>
                <td class="signature-name"><?= h($block['name']) ?></td>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($blocks as $block): ?>
                <td class="signature-title"><?= h($block['title']) ?></td>
            <?php endforeach; ?>
        </tr>
    </table>
    <?php
}
function status_class(string $status): string
{
    $n = strtolower($status);
    if ($n === 'monitored') return 'st-monitored';
    if ($n === 'pending') return 'st-pending';
    if ($n === 'not monitored') return 'st-not';
    return 'st-other';
}
