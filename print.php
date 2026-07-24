<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';
require_login();
ensure_directories();
$settings = fetch_settings();
$records = fetch_records($_GET);

$teacherCounts = [];
foreach ($records as $r) {
    $key = clean_text($r['instructor'] ?? '');
    $teacherCounts[$key] = ($teacherCounts[$key] ?? 0) + 1;
}
$teacherPrinted = [];

render_page_header('Print View - ' . APP_NAME);
?>

<div class="print-toolbar no-print">
    <a class="btn btn-outline" href="index.php?<?= h(http_build_query($_GET)) ?>">Back to Dashboard</a>
    <button class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button>
</div>

<?php render_report_header($settings); ?>

<?php if (!$records): ?>
    <section class="card no-print"><h3>No records to print.</h3></section>
<?php else: ?>
<div class="table-wrap exact-wrap print-wrap">
    <table class="monitoring-table exact-table print-exact-table">
        <thead>
            <tr>
                <th rowspan="2" class="col-instructor">INSTRUCTORS</th>
                <th rowspan="2" class="col-section">SECTION</th>
                <th rowspan="2" class="col-subject">Subject</th>
                <th rowspan="2" class="col-day">Day</th>
                <th rowspan="2" class="col-pmvgo">CRONASIA<br>PMVGO</th>
                <th colspan="2" class="col-material">Class Material</th>
                <th colspan="2" class="col-activity">ACTIVITY / QUIZZES</th>
                <th rowspan="2" class="col-total">Total<br>Classes<br>(Week)</th>
                <th rowspan="2" class="col-present">Present</th>
                <th rowspan="2" class="col-absent">Absent</th>
                <th rowspan="2" class="col-remarks">Remarks</th>
            </tr>
            <tr>
                <th class="col-week">Week 1 &amp; 2</th>
                <th class="col-week">Week 3 &amp; 4</th>
                <th class="col-week">Week 1 &amp; 2</th>
                <th class="col-week">Week 3 &amp; 4</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($records as $r):
            $teacherKey = clean_text($r['instructor'] ?? '');
            $isFirstTeacherRow = empty($teacherPrinted[$teacherKey]);
            $teacherPrinted[$teacherKey] = true;
        ?>
            <tr>
                <?php if ($isFirstTeacherRow): ?>
                    <td class="instructor-cell" rowspan="<?= (int)$teacherCounts[$teacherKey] ?>"><?= h($r['instructor']) ?></td>
                <?php endif; ?>
                <td class="section-cell"><?= h(clean_text($r['section']) !== '' ? $r['section'] : display_course_section($r)) ?></td>
                <td class="subject-cell"><?= h(subject_code_only($r['subject'])) ?></td>
                <td class="day-cell"><?= h($r['day']) ?></td>
                <td class="mark-cell"><?= h(checkbox_mark($r['cronasia_pmvgo_checks'] ?? '')) ?></td>
                <td class="mark-cell"><?= h(checkbox_mark_list($r['class_material_1_2'] ?? '', [1 => 'W1', 2 => 'W2'])) ?></td>
                <td class="mark-cell"><?= h(checkbox_mark_list($r['class_material_3_4'] ?? '', [3 => 'W3', 4 => 'W4'])) ?></td>
                <td class="mark-cell"><?= h(checkbox_mark_list($r['activity_1_2'] ?? '', [1 => 'W1', 2 => 'W2'])) ?></td>
                <td class="mark-cell"><?= h(checkbox_mark_list($r['activity_3_4'] ?? '', [3 => 'W3', 4 => 'W4'])) ?></td>
                <td class="number-cell"><?= h($r['total_classes']) ?></td>
                <td class="number-cell"><?= h($r['present']) ?></td>
                <td class="number-cell"><?= h($r['absent']) ?></td>
                <td class="remarks-cell print-remarks"><?= h($r['remarks']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php render_signature_block(); ?>
<?php endif; ?>

<script>
window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 300); });
</script>

<?php render_page_footer(); ?>
