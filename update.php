<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_login();

$action = $_GET['action'] ?? '';

try {
    ensure_schema_columns();

    if ($action === 'settings') {
        save_settings_from_post($_POST);
        flash_set('success', 'Header settings saved.');
        redirect_to('index.php');
    }

    if ($action === 'bulk') {
        $ids = $_POST['record_ids'] ?? [];
        $stmt = db()->prepare('UPDATE class_records SET
            cronasia_pmvgo_checks = ?,
            class_material_1_2 = ?,
            class_material_3_4 = ?,
            activity_1_2 = ?,
            activity_3_4 = ?,
            total_classes = ?,
            present = ?,
            absent = ?,
            remarks = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ?');

        $fetch = db()->prepare('SELECT day, total_classes FROM class_records WHERE id = ?');

        foreach ($ids as $rawId) {
            $id = (int)$rawId;
            if ($id <= 0) continue;

            $fetch->execute([$id]);
            $current = $fetch->fetch() ?: ['day' => '', 'total_classes' => 0];
            $total = to_int($current['total_classes'] ?? 0, 0);
            if ($total === 0) {
                $total = guess_total_classes($current['day'] ?? '');
            }

            $pmvgo = isset($_POST['pmvgo_check_' . $id]) ? '1' : '';
            $classMaterial12 = pmvgo_checks_to_string($_POST['class_material_1_2_' . $id] ?? []);
            $classMaterial34 = pmvgo_checks_to_string($_POST['class_material_3_4_' . $id] ?? []);
            $activity12 = pmvgo_checks_to_string($_POST['activity_1_2_' . $id] ?? []);
            $activity34 = pmvgo_checks_to_string($_POST['activity_3_4_' . $id] ?? []);
            [$computedPresent, $computedAbsent] = attendance_from_materials_and_activities($total, $classMaterial12, $classMaterial34, $activity12, $activity34);

            $postedPresent = clean_text($_POST['present_' . $id] ?? '');
            $postedAbsent = clean_text($_POST['absent_' . $id] ?? '');

            $present = $postedPresent !== '' ? to_int($postedPresent, $computedPresent) : $computedPresent;
            $absent = $postedAbsent !== '' ? to_int($postedAbsent, $computedAbsent) : $computedAbsent;

            $present = max(0, $present);
            $absent = max(0, $absent);

            if ($total > 0) {
                $present = min($present, $total);
                $absent = min($absent, $total);
            }

            $manualRemarks = clean_text($_POST['remarks_' . $id] ?? '');
            $remarks = $manualRemarks !== '' ? $manualRemarks : attendance_remarks($present, $total);

            $stmt->execute([
                $pmvgo,
                $classMaterial12,
                $classMaterial34,
                $activity12,
                $activity34,
                $total,
                $present,
                $absent,
                $remarks,
                $id,
            ]);
        }
        flash_set('success', 'Monitoring checklist saved. Present and absent were saved. You may manually edit them, or leave them to auto-compute from the checkboxes.');
        $redirectParams = [
            'q' => $_POST['q'] ?? '',
            'program' => $_POST['program'] ?? '',
            'course' => $_POST['course'] ?? '',
            'status' => $_POST['status'] ?? '',
            'college' => $_POST['college'] ?? '',
        ];
        $years = normalize_year_filters($_POST['years'] ?? []);
        if ($years) {
            $redirectParams['years'] = $years;
        }
        $qs = http_build_query($redirectParams);
        redirect_to('index.php' . ($qs ? '?' . $qs : ''));
    }

    if ($action === 'delete') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = db()->prepare('DELETE FROM class_records WHERE id = ?');
            $stmt->execute([$id]);
            flash_set('success', 'Record deleted.');
        }
        redirect_to('index.php');
    }

    if ($action === 'clear') {
        if (clean_text($_POST['confirm'] ?? '') !== 'YES') {
            flash_set('error', 'Type YES to confirm clearing all records.');
            redirect_to('index.php');
        }
        $college = active_college_for_request($_POST ?: $_GET);
        if (user_is_admin() && $college === 'ALL') {
            db()->exec('DELETE FROM class_records');
        } else {
            $delete = db()->prepare('DELETE FROM class_records WHERE college_account = ?');
            $delete->execute([$college]);
        }
        flash_set('success', 'Monitoring records were cleared for the selected account.');
        redirect_to('upload.php');
    }

    flash_set('warning', 'No action was selected.');
    redirect_to('index.php');
} catch (Throwable $e) {
    flash_set('error', $e->getMessage());
    redirect_to('index.php');
}
