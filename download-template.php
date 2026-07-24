<?php
$path = __DIR__ . '/import_template.csv';
if (!file_exists($path)) {
    http_response_code(404);
    echo 'Template file not found.';
    exit;
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="cbit_class_monitoring_import_template.csv"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
