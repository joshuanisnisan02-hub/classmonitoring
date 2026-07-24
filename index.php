<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';
require_login();
ensure_directories();

$settings = fetch_settings();
$activeProgram = normalize_program_tab($_GET['program'] ?? '');
$activeCollege = active_college_for_request($_GET);
$activeYears = normalize_year_filters($_GET['years'] ?? []);
$records = fetch_records($_GET);

function render_check_pair(string $name, string $value, array $labels): void
{
    echo '<div class="check-pair">';
    foreach ($labels as $number => $label) {
        $n = (int)$number;
        echo '<label><input type="checkbox" name="' . h($name) . '[]" value="' . $n . '"' . checkbox_attr_in_list($value, $n) . '> ' . h($label) . '</label>';
    }
    echo '</div>';
}

function render_single_check(string $name, string $value): void
{
    echo '<label class="single-check"><input type="checkbox" name="' . h($name) . '" value="1"' . checkbox_attr_single($value) . '></label>';
}

$teacherCounts = [];
foreach ($records as $r) {
    $key = clean_text($r['instructor'] ?? '');
    $teacherCounts[$key] = ($teacherCounts[$key] ?? 0) + 1;
}
$teacherPrinted = [];

render_page_header('Dashboard - ' . APP_NAME);
?>

<section class="exact-actions no-print">
    <a class="btn btn-primary" href="upload.php">Upload / Import Excel</a>
    <a class="btn btn-outline" href="print.php?<?= h(http_build_query($_GET)) ?>" target="_blank">Print</a>
    <a class="btn btn-gold" href="export.php?<?= h(http_build_query($_GET)) ?>">Export Excel</a>
    <form method="post" action="update.php?action=clear" onsubmit="return confirm('Clear all imported records?')" class="inline-clear">
        <input name="confirm" value="YES" type="hidden">
        <input name="college" value="<?= h($activeCollege) ?>" type="hidden">
        <button class="btn btn-danger" type="submit">Clear Records</button>
    </form>
</section>

<section class="filter-bar no-print">
    <?php if (college_filter_tabs()): ?>
    <div class="college-tabs" role="tablist" aria-label="College account tabs">
        <?php foreach (college_filter_tabs() as $collegeKey => $collegeLabel):
            $collegeQuery = $_GET;
            $collegeQuery['college'] = $collegeKey;
            if ($collegeKey === 'ALL') { $collegeQuery['college'] = 'ALL'; }
            $collegeQuery = clean_query_params($collegeQuery);
            $collegeActive = $activeCollege === $collegeKey;
        ?>
            <a class="college-tab <?= $collegeActive ? 'active' : '' ?>" href="index.php?<?= h(http_build_query($collegeQuery)) ?>"><?= h($collegeLabel) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="program-tabs" role="tablist" aria-label="Class monitoring program tabs">
        <?php foreach (program_tabs() as $programKey => $programLabel):
            $tabQuery = $_GET;
            if ($programKey === '') {
                unset($tabQuery['program']);
            } else {
                $tabQuery['program'] = $programKey;
            }
            $tabQuery['q'] = $_GET['q'] ?? '';
            $tabQuery = clean_query_params($tabQuery);
            $isActive = $activeProgram === $programKey || ($activeProgram === '' && $programKey === '');
        ?>
            <a class="program-tab <?= $isActive ? 'active' : '' ?>" href="index.php<?= $tabQuery ? '?' . h(http_build_query($tabQuery)) : '' ?>"><?= h($programLabel) ?></a>
        <?php endforeach; ?>
    </div>
    <form class="search-form" method="get" action="index.php">
        <?php if ($activeProgram !== ''): ?><input type="hidden" name="program" value="<?= h($activeProgram) ?>"><?php endif; ?>
        <input type="hidden" name="college" value="<?= h($activeCollege) ?>">
        <details class="year-filter">
            <summary>Section Year <?= $activeYears ? '(' . count($activeYears) . ')' : '' ?></summary>
            <div class="year-menu">
                <?php foreach (year_filter_options() as $yearValue => $yearLabel): ?>
                    <label><input type="checkbox" name="years[]" value="<?= h($yearValue) ?>" <?= in_array($yearValue, $activeYears, true) ? 'checked' : '' ?>> <?= h($yearLabel) ?></label>
                <?php endforeach; ?>
                <p class="year-hint">F = 1st, S = 2nd, T = 3rd, G = 4th</p>
            </div>
        </details>
        <input type="search" name="q" value="<?= h($_GET['q'] ?? '') ?>" placeholder="Search instructor name, section, or subject code">
        <button class="btn btn-primary" type="submit">Search</button>
        <a class="btn btn-outline" href="index.php<?= h(($activeProgram !== '' ? '?program=' . urlencode($activeProgram) . '&' : '?') . 'college=' . urlencode($activeCollege)) ?>">Clear</a>
    </form>
</section>

<?php render_report_header($settings); ?>

<?php if (!$records): ?>
    <section class="card no-records no-print">
        <h3>No class records yet</h3>
        <p class="helper">Upload your Excel file first to generate the monitoring checklist.</p>
        <a class="btn btn-primary" href="upload.php">Upload Excel Now</a>
    </section>
<?php else: ?>
<form method="post" action="update.php?action=bulk" id="monitoringForm">
    <input type="hidden" name="q" value="<?= h($_GET['q'] ?? '') ?>">
    <input type="hidden" name="program" value="<?= h($_GET['program'] ?? '') ?>">
    <input type="hidden" name="college" value="<?= h($activeCollege) ?>">
    <input type="hidden" name="course" value="<?= h($_GET['course'] ?? '') ?>">
    <input type="hidden" name="status" value="<?= h($_GET['status'] ?? '') ?>">
    <?php foreach ($activeYears as $yearValue): ?><input type="hidden" name="years[]" value="<?= h($yearValue) ?>"><?php endforeach; ?>
    <div class="table-wrap exact-wrap">
        <table class="monitoring-table exact-table">
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
                $id = (int)$r['id'];
                $teacherKey = clean_text($r['instructor'] ?? '');
                $isFirstTeacherRow = empty($teacherPrinted[$teacherKey]);
                $teacherPrinted[$teacherKey] = true;
                $total = max(0, (int)$r['total_classes']);
            ?>
                <tr data-total="<?= $total ?>">
                    <?php if ($isFirstTeacherRow): ?>
                        <td class="instructor-cell" rowspan="<?= (int)$teacherCounts[$teacherKey] ?>"><?= h($r['instructor']) ?></td>
                    <?php endif; ?>
                    <td class="section-cell"><?= h(clean_text($r['section']) !== '' ? $r['section'] : display_course_section($r)) ?><input type="hidden" name="record_ids[]" value="<?= $id ?>"></td>
                    <td class="subject-cell"><?= h(subject_code_only($r['subject'])) ?></td>
                    <td class="day-cell"><?= h($r['day']) ?></td>
                    <td class="check-cell pmvgo-cell"><?php render_single_check('pmvgo_check_' . $id, $r['cronasia_pmvgo_checks'] ?? ''); ?></td>
                    <td class="check-cell attendance-source"><?php render_check_pair('class_material_1_2_' . $id, $r['class_material_1_2'] ?? '', [1 => 'W1', 2 => 'W2']); ?></td>
                    <td class="check-cell attendance-source"><?php render_check_pair('class_material_3_4_' . $id, $r['class_material_3_4'] ?? '', [3 => 'W3', 4 => 'W4']); ?></td>
                    <td class="check-cell attendance-source"><?php render_check_pair('activity_1_2_' . $id, $r['activity_1_2'] ?? '', [1 => 'W1', 2 => 'W2']); ?></td>
                    <td class="check-cell attendance-source"><?php render_check_pair('activity_3_4_' . $id, $r['activity_3_4'] ?? '', [3 => 'W3', 4 => 'W4']); ?></td>
                    <td class="number-cell total-cell"><?= h($r['total_classes']) ?></td>
                    <td class="number-cell present-cell"><input class="attendance-number-input present-input" type="number" name="present_<?= $id ?>" value="<?= h($r['present']) ?>" min="0" max="<?= $total ?>" step="1" title="You can manually edit present count."></td>
                    <td class="number-cell absent-cell"><input class="attendance-number-input absent-input" type="number" name="absent_<?= $id ?>" value="<?= h($r['absent']) ?>" min="0" max="<?= $total ?>" step="1" title="You can manually edit absent count."></td>
                    <td class="remarks-cell"><input class="remarks-input" name="remarks_<?= $id ?>" value="<?= h($r['remarks']) ?>" title="You can edit this remarks field manually."></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php render_signature_block(); ?>
    <div class="form-actions exact-save no-print">
        <button class="btn btn-primary" type="submit">Save Monitoring Updates</button>
    </div>
</form>
<?php endif; ?>

<script>
function clampAttendanceValue(value, total) {
    let number = parseInt(value || '0', 10);
    if (Number.isNaN(number) || number < 0) number = 0;
    if (total > 0) number = Math.min(number, total);
    return number;
}

function updateAutoRemarks(row, present) {
    const total = parseInt(row.dataset.total || '0', 10);
    const remarksInput = row.querySelector('.remarks-input');
    const autoRemark = present >= total ? 'Complete' : 'Incomplete';

    if (remarksInput) {
        const current = (remarksInput.value || '').trim();
        if (remarksInput.dataset.manual !== '1' || current === '' || current === 'Complete' || current === 'Incomplete') {
            remarksInput.value = autoRemark;
            remarksInput.dataset.manual = '0';
        }
    }
}

document.addEventListener('change', function (event) {
    if (!event.target.matches('.attendance-source input[type="checkbox"]')) return;

    const row = event.target.closest('tr');
    if (!row) return;

    const total = parseInt(row.dataset.total || '0', 10);
    const presentWeeks = new Set();

    row.querySelectorAll('.attendance-source input[type="checkbox"]:checked').forEach(function (box) {
        const week = parseInt(box.value || '0', 10);
        if (week > 0) presentWeeks.add(week);
    });

    const computedPresent = total > 0 ? Math.min(presentWeeks.size, total) : presentWeeks.size;
    const computedAbsent = total > 0 ? Math.max(0, total - computedPresent) : 0;
    const presentInput = row.querySelector('.present-input');
    const absentInput = row.querySelector('.absent-input');

    if (presentInput && presentInput.dataset.manual !== '1') {
        presentInput.value = String(computedPresent);
    }

    if (absentInput && absentInput.dataset.manual !== '1') {
        absentInput.value = String(computedAbsent);
    }

    const currentPresent = presentInput ? clampAttendanceValue(presentInput.value, total) : computedPresent;
    updateAutoRemarks(row, currentPresent);
});

document.addEventListener('input', function (event) {
    if (event.target.matches('.remarks-input')) {
        event.target.dataset.manual = '1';
    }

    if (event.target.matches('.attendance-number-input')) {
        const input = event.target;
        const row = input.closest('tr');
        if (!row) return;

        const total = parseInt(row.dataset.total || '0', 10);
        const value = clampAttendanceValue(input.value, total);
        input.value = String(value);
        input.dataset.manual = '1';

        const presentInput = row.querySelector('.present-input');
        const absentInput = row.querySelector('.absent-input');

        if (input.classList.contains('present-input') && absentInput && absentInput.dataset.manual !== '1') {
            absentInput.value = String(total > 0 ? Math.max(0, total - value) : 0);
        }

        const present = presentInput ? clampAttendanceValue(presentInput.value, total) : 0;
        updateAutoRemarks(row, present);
    }
});
</script>

<?php render_page_footer(); ?>
