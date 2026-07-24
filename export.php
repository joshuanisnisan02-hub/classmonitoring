<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
ensure_directories();
$settings = fetch_settings();
$records = fetch_records($_GET);
$collegeKey = active_college_for_request($_GET);
if ($collegeKey === 'ALL') { $collegeKey = 'CBIT'; }
$profile = college_profile($collegeKey);
$filename = $profile['key'] . '_Class_Monitoring_' . date('Ymd_His') . '.xls';

$teacherCounts = [];
foreach ($records as $r) {
    $key = clean_text($r['instructor'] ?? '');
    $teacherCounts[$key] = ($teacherCounts[$key] ?? 0) + 1;
}
$teacherPrinted = [];

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
echo "\xEF\xBB\xBF";
?>
<html>
<head>
<meta charset="UTF-8">
<style>
body{font-family:Arial,Helvetica,sans-serif;color:#000;margin:0}table{border-collapse:collapse;width:100%}th,td{border:1px solid #000;padding:2px 4px;font-size:9px;vertical-align:middle}th{text-align:center;font-weight:bold;background:#fff}.headerbox{background:<?= h($profile['header_color']) ?>;border:1px solid #000;text-align:center;font-weight:bold}.headerbox h2{font-size:18px;margin:2px 0 4px 0;letter-spacing:.5px}.headerbox p{font-size:11px;margin:1px 0}.headerbox .program{font-size:14px;margin-top:8px}.headerbox .date{font-size:11px}.instructor{font-weight:normal;text-align:left}.center{text-align:center}.remarks{text-align:center}.mark{font-size:9px;text-align:center;white-space:nowrap}.signature-export{table-layout:fixed;width:100%;margin-top:0}.signature-export td{border:1px solid #d9d9d9;font-size:10px;padding:2px 4px;height:18px}.signature-export .space{height:42px}.signature-export .name{font-weight:bold;text-decoration:underline;text-align:center;white-space:normal;font-size:9px}.signature-export .title{text-align:center}.signature-export .label{text-align:left}
</style>
</head>
<body>
<table>
<tr>
<td class="headerbox" colspan="13">
<h2><?= h($profile['college_name']) ?></h2>
<p>ACADEMIC YEAR <?= h(setting_value($settings, 'academic_year', '2026-2027')) ?></p>
<p><?= h(setting_value($settings, 'semester', 'FIRST SEMESTER')) ?></p>
<p class="program"><?= h($profile['monitoring_title']) ?></p>
<p><?= h(setting_value($settings, 'report_title', "TEACHER'S MONITORING SUMMARY")) ?></p>
<p class="date"><?= h(setting_value($settings, 'covered_dates', 'JULY 6-12, 2026')) ?></p>
</td>
</tr>
</table>
<table>
<thead>
<tr>
<th rowspan="2">INSTRUCTORS</th>
<th rowspan="2">SECTION</th>
<th rowspan="2">Subject</th>
<th rowspan="2">Day</th>
<th rowspan="2">CRONASIA<br>PMVGO</th>
<th colspan="2">Class Material</th>
<th colspan="2">ACTIVITY / QUIZZES</th>
<th rowspan="2">Total<br>Classes<br>(Week)</th>
<th rowspan="2">Present</th>
<th rowspan="2">Absent</th>
<th rowspan="2">Remarks</th>
</tr>
<tr>
<th>Week 1 &amp; 2</th><th>Week 3 &amp; 4</th><th>Week 1 &amp; 2</th><th>Week 3 &amp; 4</th>
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
<td class="instructor" rowspan="<?= (int)$teacherCounts[$teacherKey] ?>"><?= h($r['instructor']) ?></td>
<?php endif; ?>
<td class="center"><?= h(clean_text($r['section']) !== '' ? $r['section'] : display_course_section($r)) ?></td>
<td><?= h(subject_code_only($r['subject'])) ?></td>
<td class="center"><?= h($r['day']) ?></td>
<td class="mark"><?= h(checkbox_mark($r['cronasia_pmvgo_checks'] ?? '')) ?></td>
<td class="mark"><?= h(checkbox_mark_list($r['class_material_1_2'] ?? '', [1 => 'W1', 2 => 'W2'])) ?></td>
<td class="mark"><?= h(checkbox_mark_list($r['class_material_3_4'] ?? '', [3 => 'W3', 4 => 'W4'])) ?></td>
<td class="mark"><?= h(checkbox_mark_list($r['activity_1_2'] ?? '', [1 => 'W1', 2 => 'W2'])) ?></td>
<td class="mark"><?= h(checkbox_mark_list($r['activity_3_4'] ?? '', [3 => 'W3', 4 => 'W4'])) ?></td>
<td class="center"><?= h($r['total_classes']) ?></td>
<td class="center"><?= h($r['present']) ?></td>
<td class="center"><?= h($r['absent']) ?></td>
<td class="remarks"><?= h($r['remarks']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php $blocks = signature_blocks(); ?>
<table class="signature-export">
<colgroup><col style="width:25%"><col style="width:25%"><col style="width:25%"><col style="width:25%"></colgroup>
<tr>
<?php foreach ($blocks as $block): ?>
<td class="label"><?= h($block['label']) ?></td>
<?php endforeach; ?>
</tr>
<tr>
<?php foreach ($blocks as $block): ?>
<td class="space">&nbsp;</td>
<?php endforeach; ?>
</tr>
<tr>
<?php foreach ($blocks as $block): ?>
<td class="name"><?= h($block['name']) ?></td>
<?php endforeach; ?>
</tr>
<tr>
<?php foreach ($blocks as $block): ?>
<td class="title"><?= h($block['title']) ?></td>
<?php endforeach; ?>
</tr>
</table>
</body>
</html>
