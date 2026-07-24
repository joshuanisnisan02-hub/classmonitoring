<?php
function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash_set(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_render(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['flash'])) {
        return;
    }
    foreach ($_SESSION['flash'] as $item) {
        $type = $item['type'] ?? 'info';
        $message = $item['message'] ?? '';
        echo '<div class="alert alert-' . h($type) . '">' . h($message) . '</div>';
    }
    unset($_SESSION['flash']);
}

function app_url(string $path = ''): string
{
    $base = defined('APP_BASE_URL') ? trim(APP_BASE_URL) : '';
    if ($base === '') {
        return $path;
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}


function clean_query_params(array $query): array
{
    $out = [];
    foreach ($query as $key => $value) {
        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                if (clean_text($item) !== '') {
                    $items[] = $item;
                }
            }
            if ($items) {
                $out[$key] = $items;
            }
            continue;
        }
        if (clean_text($value) !== '') {
            $out[$key] = $value;
        }
    }
    return $out;
}

function start_app_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function college_profiles(): array
{
    return [
        'CBIT' => [
            'key' => 'CBIT',
            'label' => 'CBIT',
            'college_name' => 'COLLEGE OF BUSINESS AND INFORMATION TECHNOLOGY',
            'monitoring_title' => 'CBIT CLASS MONITORING',
            'header_color' => '#ffc000',
            'logo' => 'assets/img/cbit_logo.p   ng',
        ],
        'CSSH' => [
            'key' => 'CSSH',
            'label' => 'CSSH',
            'college_name' => 'COLLEGE OF SOCIAL SCIENCES AND HUMANITIES',
            'monitoring_title' => 'CSSH CLASS MONITORING',
            'header_color' => '#6d28d9',
            'logo' => 'assets/img/cssh_logo.svg',
        ],
    ];
}

function normalize_college_key($value): string
{
    $key = strtoupper(clean_text($value));
    return array_key_exists($key, college_profiles()) ? $key : 'CBIT';
}

function college_profile(string $key = ''): array
{
    $profiles = college_profiles();
    $key = normalize_college_key($key ?: 'CBIT');
    return $profiles[$key];
}

function ensure_auth_schema(?PDO $pdo = null): void
{
    $pdo = $pdo ?: db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) NOT NULL UNIQUE,
        display_name VARCHAR(160) NOT NULL DEFAULT '',
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'Coordinator',
        college_account VARCHAR(20) NOT NULL DEFAULT 'CBIT',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_users_college (college_account),
        INDEX idx_users_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $defaults = [
        ['admin', 'System Administrator', 'Admin', 'ALL', 'admin123'],
        ['cbit', 'CBIT Account', 'CBIT', 'CBIT', 'cbit123'],
        ['cssh', 'CSSH Account', 'CSSH', 'CSSH', 'cssh123'],
    ];
    $select = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO users (username, display_name, password_hash, role, college_account, is_active) VALUES (?, ?, ?, ?, ?, 1)');
    $update = $pdo->prepare('UPDATE users SET display_name = ?, password_hash = ?, role = ?, college_account = ?, is_active = 1 WHERE username = ?');
    foreach ($defaults as [$username, $display, $role, $college, $password]) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $select->execute([$username]);
        if (!$select->fetchColumn()) {
            $insert->execute([$username, $display, $passwordHash, $role, $college]);
        } else {
            $update->execute([$display, $passwordHash, $role, $college, $username]);
        }
    }
}

function auth_user(): ?array
{
    start_app_session();
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return auth_user() !== null;
}

function user_is_admin(): bool
{
    $user = auth_user();
    return $user && strtoupper($user['role'] ?? '') === 'ADMIN';
}

function require_login(): void
{
    start_app_session();
    if (empty($_SESSION['user'])) {
        $next = basename($_SERVER['PHP_SELF'] ?? 'index.php');
        $query = $_SERVER['QUERY_STRING'] ?? '';
        if ($query !== '') $next .= '?' . $query;
        redirect_to('login.php?next=' . urlencode($next));
    }
}

function current_user_college(): string
{
    $user = auth_user();
    $college = strtoupper(clean_text($user['college_account'] ?? 'CBIT'));
    if ($college === 'ALL') return 'ALL';
    return normalize_college_key($college);
}

function active_college_for_request(array $source = []): string
{
    $userCollege = current_user_college();
    if ($userCollege !== 'ALL') return $userCollege;
    $requested = strtoupper(clean_text($source['college'] ?? 'CBIT'));
    if ($requested === 'ALL') return 'ALL';
    return normalize_college_key($requested);
}

function college_filter_tabs(): array
{
    if (!user_is_admin()) return [];
    return ['CBIT' => 'CBIT', 'CSSH' => 'CSSH', 'ALL' => 'All Colleges'];
}

function clean_text($value): string
{
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    $text = trim((string)$value);
    $text = preg_replace('/\s+/u', ' ', $text);
    return $text ?? '';
}


function subject_code_only($value): string
{
    $text = clean_text($value);
    if ($text === '') {
        return '';
    }

    // Keep only the subject code, like the Excel summary format.
    // Examples: "PROFEL 3 - Fundamentals of Database Systems" -> "PROFEL 3";
    // "CC 106 APPLICATION DEVELOPMENT AND EMERGING TECHNOLOGIES" -> "CC 106".
    if (preg_match('/^(.{2,30}?)\s+[-–—:]\s+.+$/u', $text, $m)) {
        return strtoupper(trim($m[1]));
    }

    $tokens = preg_split('/\s+/', $text) ?: [];
    $code = [];
    foreach ($tokens as $i => $token) {
        $clean = strtoupper(trim($token, " \t\n\r\0\x0B.,;()[]{}"));
        if ($clean === '') {
            continue;
        }
        if ($i === 0) {
            $code[] = $clean;
            continue;
        }
        $isNumber = (bool)preg_match('/^\d+[A-Z]?$/', $clean);
        $isRoman = (bool)preg_match('/^(I|II|III|IV|V|VI|VII|VIII|IX|X)$/', $clean);
        $isShortCodeWord = strlen($clean) <= 4 && preg_match('/^[A-Z]+$/', $clean) && !in_array($clean, ['AND', 'THE', 'FOR', 'OF', 'WITH', 'IN'], true);
        if ($isNumber || $isRoman || $isShortCodeWord) {
            $code[] = $clean;
            if (count($code) >= 4) {
                break;
            }
            continue;
        }
        break;
    }

    $result = trim(implode(' ', $code));
    return $result !== '' ? $result : strtoupper($text);
}
function norm_text($value): string
{
    $text = strtolower(clean_text($value));
    $text = str_replace('&', ' and ', $text);
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text ?? '');
    return trim($text ?? '');
}

function to_int($value, int $default = 0): int
{
    $text = clean_text($value);
    if ($text === '') {
        return $default;
    }
    if (is_numeric($text)) {
        return (int)round((float)$text);
    }
    return $default;
}

function status_options(): array
{
    return ['Pending', 'Monitored', 'Not Monitored', 'No Class', 'Late', 'For Follow-up'];
}

function is_checked_value($value): bool
{
    $raw = clean_text($value);
    $n = norm_text($raw);
    if ($raw === '✓' || $raw === '✔' || $raw === '☑' || $raw === 'X' || $raw === 'x') {
        return true;
    }
    return in_array($n, ['1', 'yes', 'y', 'true', 'checked', 'check', 'complete', 'completed', 'done', 'present', 'ok', 'submitted'], true);
}

function checkbox_attr($value): string
{
    return is_checked_value($value) ? ' checked' : '';
}

function checkbox_mark($value): string
{
    return is_checked_value($value) ? '☑' : '☐';
}

function single_checkbox_value($value): string
{
    return is_checked_value($value) ? '1' : '';
}

function checkbox_attr_single($value): string
{
    return is_checked_value($value) ? ' checked' : '';
}

function checkbox_mark_list($value, array $labels): string
{
    $checked = array_flip(normalize_pmvgo_checks($value));
    $parts = [];
    foreach ($labels as $number => $label) {
        $n = is_int($number) ? (int)$number : to_int($number, 0);
        if ($n <= 0) {
            $n = to_int($label, 0);
        }
        $parts[] = (isset($checked[$n]) ? '☑' : '☐') . ' ' . $label;
    }
    return implode('  ', $parts);
}

function checkbox_attr_in_list($value, int $number): string
{
    $checked = array_flip(normalize_pmvgo_checks($value));
    return isset($checked[$number]) ? ' checked' : '';
}


function guess_total_classes(string $dayText): int
{
    $raw = strtoupper(clean_text($dayText));
    if ($raw === '') {
        return 0;
    }

    $compact = preg_replace('/[^A-Z]/', '', $raw);
    $compactMap = [
        'MWF' => 3,
        'MTWTHF' => 5,
        'MTWTF' => 5,
        'MW' => 2,
        'TTH' => 2,
        'TH' => 1,
        'SAT' => 1,
        'SUN' => 1,
    ];
    if (isset($compactMap[$compact])) {
        return $compactMap[$compact];
    }

    $normalized = preg_replace('/[^A-Z]+/', ' ', $raw);
    preg_match_all('/\b(MON|TUE|WED|THU|FRI|SAT|SUN|TH|M|T|W|F)\b/', $normalized, $matches);
    if (!empty($matches[1])) {
        $days = [];
        foreach ($matches[1] as $token) {
            $key = match ($token) {
                'MON', 'M' => 'M',
                'TUE', 'T' => 'T',
                'WED', 'W' => 'W',
                'THU', 'TH' => 'TH',
                'FRI', 'F' => 'F',
                'SAT' => 'SAT',
                'SUN' => 'SUN',
                default => $token,
            };
            $days[$key] = true;
        }
        return count($days);
    }

    $tokens = array_values(array_filter(explode(' ', norm_text($dayText))));
    return count($tokens) >= 2 ? count($tokens) : 1;
}

function normalize_pmvgo_checks($value): array
{
    if (is_array($value)) {
        $items = $value;
    } else {
        $text = clean_text($value);
        if ($text === '') {
            return [];
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = preg_split('/[,;|\s]+/', $text) ?: [];
        }
    }
    $checked = [];
    foreach ($items as $item) {
        $n = to_int($item, 0);
        if ($n > 0) {
            $checked[$n] = true;
        }
    }
    ksort($checked);
    return array_keys($checked);
}

function pmvgo_checks_to_string(array $checks): string
{
    $clean = [];
    foreach ($checks as $check) {
        $n = to_int($check, 0);
        if ($n > 0) {
            $clean[$n] = true;
        }
    }
    ksort($clean);
    return implode(',', array_keys($clean));
}

function attendance_from_pmvgo($checksValue, int $totalClasses): array
{
    $total = max(0, $totalClasses);
    $checks = normalize_pmvgo_checks($checksValue);
    $present = count($checks);
    if ($total > 0) {
        $present = min($present, $total);
    }
    if ($total === 0) {
        return [$present, 0];
    }
    return [$present, max(0, $total - $present)];
}

function attendance_from_materials_and_activities(int $totalClasses, $classMaterial12, $classMaterial34, $activity12, $activity34): array
{
    $total = max(0, $totalClasses);
    $presentWeeks = [];

    foreach (normalize_pmvgo_checks($classMaterial12) as $week) {
        $presentWeeks[$week] = true;
    }
    foreach (normalize_pmvgo_checks($classMaterial34) as $week) {
        $presentWeeks[$week] = true;
    }
    foreach (normalize_pmvgo_checks($activity12) as $week) {
        $presentWeeks[$week] = true;
    }
    foreach (normalize_pmvgo_checks($activity34) as $week) {
        $presentWeeks[$week] = true;
    }

    $present = count($presentWeeks);
    if ($total > 0) {
        $present = min($present, $total);
        return [$present, max(0, $total - $present)];
    }
    return [$present, 0];
}

function attendance_remarks(int $present, int $totalClasses): string
{
    $total = max(0, $totalClasses);
    if ($present >= $total) {
        return 'Complete';
    }
    return 'Incomplete';
}

function signature_blocks(): array
{
    return [
        ['label' => 'PREPARED BY', 'name' => 'JOSHUA E. NISNISAN', 'title' => 'BSIS/BSAIS/ACT Coordinator'],
        ['label' => 'NOTED BY', 'name' => 'MARIA CRESANTA G. HITALIA, MBM', 'title' => 'CBIT Director'],
        ['label' => 'CHECKED BY', 'name' => 'DR. ELLAH JESSA G. TOMAMPOS, LPT', 'title' => 'Quality Assurance Officer'],
        ['label' => 'APPROVED BY', 'name' => 'DR. MAY MAEH V. TOBATO, LPT', 'title' => 'Academic Director'],
    ];
}

function make_checks_from_present(int $present, int $total): string
{
    $checks = [];
    $limit = max(0, min($present, $total));
    for ($i = 1; $i <= $limit; $i++) {
        $checks[] = $i;
    }
    return pmvgo_checks_to_string($checks);
}

function display_course_section(array $record): string
{
    $course = clean_text($record['course'] ?? '');
    $section = clean_text($record['section'] ?? '');
    if ($course === '') return $section;
    if ($section === '') return $course;
    return $course . ' / ' . $section;
}

function ensure_directories(): void
{
    foreach (['uploads', 'exports'] as $dir) {
        $path = dirname(__DIR__) . '/' . $dir;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}

function column_exists(string $table, string $column): bool
{
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function ensure_schema_columns(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        ensure_auth_schema();
        if (!column_exists('class_records', 'college_account')) {
            db()->exec("ALTER TABLE class_records ADD COLUMN college_account VARCHAR(20) NOT NULL DEFAULT 'CBIT' AFTER id");
        }
        if (!column_exists('class_records', 'cronasia_pmvgo_checks')) {
            db()->exec("ALTER TABLE class_records ADD COLUMN cronasia_pmvgo_checks VARCHAR(100) NOT NULL DEFAULT '' AFTER schedule_text");
        }
    } catch (Throwable $e) {
        // setup.php will create the table. Existing installs can run setup.php to apply this update.
    }
}

function fetch_settings(): array
{
    ensure_schema_columns();
    $stmt = db()->query('SELECT setting_key, setting_value FROM settings');
    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function setting_value(array $settings, string $key, string $default = ''): string
{
    return clean_text($settings[$key] ?? $default);
}

function fetch_stats(): array
{
    ensure_schema_columns();
    $row = db()->query("SELECT
        COUNT(*) AS total_records,
        COUNT(DISTINCT instructor) AS total_instructors,
        COALESCE(SUM(total_classes),0) AS total_classes,
        COALESCE(SUM(present),0) AS total_present,
        COALESCE(SUM(absent),0) AS total_absent,
        SUM(CASE WHEN monitoring_status = 'Pending' THEN 1 ELSE 0 END) AS pending
        FROM class_records")->fetch();
    return $row ?: ['total_records' => 0, 'total_instructors' => 0, 'total_classes' => 0, 'total_present' => 0, 'total_absent' => 0, 'pending' => 0];
}

function fetch_courses(): array
{
    ensure_schema_columns();
    $stmt = db()->query("SELECT DISTINCT course FROM class_records WHERE course <> '' ORDER BY course ASC");
    return array_column($stmt->fetchAll(), 'course');
}

function program_tabs(): array
{
    return [
        '' => 'All CBIT',
        'BSIS' => 'BSIS Class Monitoring',
        'ACT' => 'ACT Class Monitoring',
        'BSAIS' => 'BSAIS Class Monitoring',
    ];
}

function normalize_program_tab($value): string
{
    $program = strtoupper(clean_text($value));
    return in_array($program, ['BSIS', 'ACT', 'BSAIS'], true) ? $program : '';
}

function program_title_for_tab(string $program): string
{
    $program = normalize_program_tab($program);
    if ($program === 'BSIS') return 'BSIS Class Monitoring';
    if ($program === 'ACT') return 'ACT Class Monitoring';
    if ($program === 'BSAIS') return 'BSAIS Class Monitoring';
    return 'All CBIT';
}

function program_filter_sql(string $program): array
{
    $program = normalize_program_tab($program);
    if ($program === 'BSIS') {
        return ["(UPPER(section) LIKE 'IS-%' OR UPPER(section) LIKE 'BSIS%' OR UPPER(course) LIKE '%BSIS%' OR UPPER(course) LIKE '%INFORMATION SYSTEM%')", []];
    }
    if ($program === 'ACT') {
        return ["(UPPER(section) LIKE 'ACT-%' OR UPPER(section) LIKE 'ACT%' OR UPPER(course) LIKE '%ACT%' OR UPPER(course) LIKE '%ASSOCIATE%')", []];
    }
    if ($program === 'BSAIS') {
        return ["(UPPER(section) LIKE 'AIS-%' OR UPPER(section) LIKE 'BSAIS%' OR UPPER(section) LIKE 'BAIS%' OR UPPER(course) LIKE '%BSAIS%' OR UPPER(course) LIKE '%ACCOUNTING INFORMATION%')", []];
    }
    return ['', []];
}

function year_filter_options(): array
{
    return [
        '1' => '1st Year',
        '2' => '2nd Year',
        '3' => '3rd Year',
        '4' => '4th Year',
    ];
}

function normalize_year_filters($value): array
{
    $items = is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);
    $valid = array_keys(year_filter_options());
    $years = [];
    foreach ($items as $item) {
        $key = clean_text($item);
        if (in_array($key, $valid, true)) {
            $years[$key] = true;
        }
    }
    return array_keys($years);
}

function year_filter_sql(array $years): array
{
    $years = normalize_year_filters($years);
    if (!$years) {
        return ['', []];
    }
    $patterns = [
        '1' => '(^|[^A-Z0-9])F[0-9]+',
        '2' => '(^|[^A-Z0-9])S[0-9]+',
        '3' => '(^|[^A-Z0-9])T[0-9]+',
        '4' => '(^|[^A-Z0-9])G[0-9]+',
    ];
    $parts = [];
    foreach ($years as $year) {
        if (isset($patterns[$year])) {
            $parts[] = 'UPPER(section) REGEXP ?';
        }
    }
    if (!$parts) {
        return ['', []];
    }
    $params = [];
    foreach ($years as $year) {
        if (isset($patterns[$year])) {
            $params[] = $patterns[$year];
        }
    }
    return ['(' . implode(' OR ', $parts) . ')', $params];
}

function build_filter_clause(array $source): array
{
    $where = [];
    $params = [];
    $q = clean_text($source['q'] ?? '');
    $course = clean_text($source['course'] ?? '');
    $status = clean_text($source['status'] ?? '');
    $program = normalize_program_tab($source['program'] ?? '');
    $years = normalize_year_filters($source['years'] ?? []);
    $college = active_college_for_request($source);

    if ($college !== 'ALL') {
        $where[] = 'college_account = ?';
        $params[] = $college;
    }

    if ($q !== '') {
        $where[] = '(instructor LIKE ? OR subject LIKE ? OR section LIKE ? OR course LIKE ? OR remarks LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ($program !== '') {
        [$programSql, $programParams] = program_filter_sql($program);
        if ($programSql !== '') {
            $where[] = $programSql;
            foreach ($programParams as $p) $params[] = $p;
        }
    }
    if ($course !== '') {
        $where[] = 'course = ?';
        $params[] = $course;
    }
    if ($years) {
        [$yearSql, $yearParams] = year_filter_sql($years);
        if ($yearSql !== '') {
            $where[] = $yearSql;
            foreach ($yearParams as $p) $params[] = $p;
        }
    }
    if ($status !== '') {
        $where[] = 'monitoring_status = ?';
        $params[] = $status;
    }

    return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
}

function fetch_records(array $source = []): array
{
    ensure_schema_columns();
    [$clause, $params] = build_filter_clause($source);
    $sql = "SELECT * FROM class_records $clause ORDER BY instructor ASC, section ASC, subject ASC, id ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
    foreach ($records as &$record) {
        [$present, $absent] = attendance_from_materials_and_activities(
            to_int($record['total_classes'] ?? 0),
            $record['class_material_1_2'] ?? '',
            $record['class_material_3_4'] ?? '',
            $record['activity_1_2'] ?? '',
            $record['activity_3_4'] ?? ''
        );
        $record['present'] = $present;
        $record['absent'] = $absent;
        // Keep manually encoded remarks. If remarks is empty, show the automatic Complete/Incomplete value.
        $storedRemarks = clean_text($record['remarks'] ?? '');
        $record['remarks'] = $storedRemarks !== '' ? $storedRemarks : attendance_remarks($present, to_int($record['total_classes'] ?? 0));
    }
    unset($record);
    return $records;
}

function save_settings_from_post(array $post): void
{
    $keys = ['college_name', 'academic_year', 'semester', 'course_title', 'report_title', 'covered_dates'];
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    foreach ($keys as $key) {
        $stmt->execute([$key, clean_text($post[$key] ?? '')]);
    }
}

function normalize_imported_checkbox($value): string
{
    return is_checked_value($value) ? '1' : '';
}

function normalize_imported_checkbox_list($value, array $defaultChecks): string
{
    $checks = normalize_pmvgo_checks($value);
    if ($checks) {
        return pmvgo_checks_to_string($checks);
    }
    if (is_checked_value($value)) {
        return pmvgo_checks_to_string($defaultChecks);
    }
    return '';
}

function insert_records(array $records, bool $replaceExisting = false, ?string $collegeAccount = null): int
{
    ensure_schema_columns();
    $collegeAccount = normalize_college_key($collegeAccount ?: (current_user_college() === 'ALL' ? 'CBIT' : current_user_college()));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($replaceExisting) {
            $delete = $pdo->prepare('DELETE FROM class_records WHERE college_account = ?');
            $delete->execute([$collegeAccount]);
        }
        $stmt = $pdo->prepare('INSERT INTO class_records
            (college_account, instructor, course, section, subject, day, schedule_text, cronasia_pmvgo_checks, class_material_1_2, class_material_3_4, activity_1_2, activity_3_4, total_classes, present, absent, remarks, monitoring_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $count = 0;
        foreach ($records as $r) {
            $day = clean_text($r['day'] ?? '');
            $total = to_int($r['total_classes'] ?? 0);
            if ($total === 0) {
                $total = guess_total_classes($day);
            }
            $checks = clean_text($r['cronasia_pmvgo_checks'] ?? '');
            $classMaterial12 = normalize_imported_checkbox_list($r['class_material_1_2'] ?? '', [1, 2]);
            $classMaterial34 = normalize_imported_checkbox_list($r['class_material_3_4'] ?? '', [3, 4]);
            $activity12 = normalize_imported_checkbox_list($r['activity_1_2'] ?? '', [1, 2]);
            $activity34 = normalize_imported_checkbox_list($r['activity_3_4'] ?? '', [3, 4]);
            [$present, $absent] = attendance_from_materials_and_activities($total, $classMaterial12, $classMaterial34, $activity12, $activity34);
            $computedRemarks = attendance_remarks($present, $total);
            $stmt->execute([
                $collegeAccount,
                clean_text($r['instructor'] ?? ''),
                clean_text($r['course'] ?? ''),
                clean_text($r['section'] ?? ''),
                subject_code_only($r['subject'] ?? ''),
                $day,
                clean_text($r['schedule'] ?? ''),
                single_checkbox_value($checks),
                $classMaterial12,
                $classMaterial34,
                $activity12,
                $activity34,
                $total,
                $present,
                $absent,
                $computedRemarks,
                clean_text($r['monitoring_status'] ?? 'Pending') ?: 'Pending',
            ]);
            $count++;
        }
        $pdo->commit();
        return $count;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
