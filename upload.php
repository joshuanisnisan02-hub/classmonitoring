<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/xlsx_reader.php';
require_login();
ensure_directories();

$settings = fetch_settings();
$activeCollege = active_college_for_request($_POST ?: $_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Please choose a valid Excel or CSV file.');
        }
        if ($_FILES['excel_file']['size'] > MAX_UPLOAD_BYTES) {
            throw new RuntimeException('File is too large. Maximum allowed size is 20 MB.');
        }
        $originalName = basename($_FILES['excel_file']['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xlsm', 'csv', 'xls'], true)) {
            throw new RuntimeException('Unsupported file type. Please upload .xlsx, .xlsm, .xls, or .csv.');
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName);
        $target = __DIR__ . '/uploads/' . date('Ymd_His') . '_' . $safeName;
        if (!move_uploaded_file($_FILES['excel_file']['tmp_name'], $target)) {
            throw new RuntimeException('Could not save uploaded file. Check folder permission for uploads/.');
        }

        $defaultCourse = clean_text($_POST['default_course'] ?? setting_value($settings, 'course_title', 'CBIT'));
        $replaceExisting = isset($_POST['replace_existing']);
        $records = import_class_records_from_file($target, $defaultCourse);
        if (!$records) {
            throw new RuntimeException('No class records were found in the uploaded file.');
        }
        $collegeAccount = user_is_admin() ? normalize_college_key($_POST['college_account'] ?? $activeCollege) : current_user_college();
        $count = insert_records($records, $replaceExisting, $collegeAccount);
        flash_set('success', 'Imported ' . $count . ' class records successfully.');
        redirect_to('index.php');
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
        redirect_to('upload.php');
    }
}

render_page_header('Upload Excel - ' . APP_NAME);
?>

<section class="card">
    <h2 class="card-title">Upload Excel / CSV</h2>
    <p class="helper">Upload a file with instructor, section, subject, day, and schedule columns. The system will automatically generate the monitoring checklist using the exact summary format.</p>

    <form method="post" enctype="multipart/form-data" class="upload-box">
        <h3>Attach your class schedule or monitoring Excel file</h3>
        <p class="helper">Supported directly: <b>.xlsx</b>, <b>.xlsm</b>, and <b>.csv</b>. Old <b>.xls</b> files are also accepted if you run <b>composer install</b> first; otherwise, save the .xls as .xlsx before uploading.</p>
        <input type="file" name="excel_file" accept=".xlsx,.xlsm,.csv,.xls" required>
        <div class="grid grid-3" style="margin-top:18px;text-align:left">
            <div>
                <label>Default Course / Program if missing in Excel</label>
                <input name="default_course" value="<?= h(setting_value($settings, 'course_title', 'CBIT')) ?>" placeholder="Example: CBIT, BSIS, BSAIS">
            </div>
            <div>
                <label>Account / Header Color</label>
                <?php if (user_is_admin()): ?>
                    <select name="college_account">
                        <option value="CBIT" <?= $activeCollege === 'CBIT' ? 'selected' : '' ?>>CBIT - Yellow Header</option>
                        <option value="CSSH" <?= $activeCollege === 'CSSH' ? 'selected' : '' ?>>CSSH - Purple Header</option>
                    </select>
                <?php else: ?>
                    <input value="<?= h(current_user_college()) ?>" readonly>
                    <input type="hidden" name="college_account" value="<?= h(current_user_college()) ?>">
                <?php endif; ?>
            </div>
            <div style="display:flex;align-items:end;gap:10px">
                <label style="display:flex;align-items:center;gap:8px;margin:0;font-size:14px">
                    <input type="checkbox" name="replace_existing" style="width:auto"> Replace existing records
                </label>
            </div>
        </div>
        <div class="form-actions" style="justify-content:center">
            <button class="btn btn-primary" type="submit">Import File</button>
            <a class="btn btn-outline" href="download-template.php">Download CSV Template</a>
            <a class="btn btn-outline" href="index.php">Back to Dashboard</a>
        </div>
    </form>
</section>

<section class="card">
    <h3 class="card-title">Expected Columns</h3>
    <div class="table-wrap">
        <table class="monitoring-table" style="min-width:900px">
            <thead><tr><th>Required</th><th>Optional but useful</th><th>Monitoring fields</th></tr></thead>
            <tbody>
            <tr>
                <td>Instructor / Teacher<br>Subject</td>
                <td>Course / Program<br>Section<br>Day<br>Schedule / Time / Room / GMeet</td>
                <td>CRONASIA PMVGO<br>CRONASIA PMVGO<br>Class Material Week 1 &amp; 2<br>Class Material Week 3 &amp; 4<br>Activity / Quizzes Week 1 &amp; 2<br>Activity / Quizzes Week 3 &amp; 4<br>Total Classes (Week)<br>Present<br>Absent<br>Remarks</td>
            </tr>
            </tbody>
        </table>
    </div>
    <p class="helper">Tip: The importer also recognizes your CFCI schedule format with headers like Room, Subject, Day, Time Start, Time End, and Instructor. Total Classes is automatically based on the Day column, e.g., T TH = 2.</p>
</section>

<?php render_page_footer(); ?>
