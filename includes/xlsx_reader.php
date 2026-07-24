<?php
require_once __DIR__ . '/functions.php';

class SimpleXlsxReader
{
    public static function readFirstSheet(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive extension is required for .xlsx import. Enable zip extension in php.ini. In XAMPP, open php.ini and enable extension=zip, then restart Apache.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open Excel file. Please upload a valid .xlsx file.');
        }
        $sharedStrings = self::readSharedStrings($zip);
        $sheetPath = self::getFirstSheetPath($zip);
        $sheetXml = self::getFromNameCaseInsensitive($zip, $sheetPath);
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('Could not read the first worksheet from the Excel file.');
        }
        $xml = @simplexml_load_string($sheetXml);
        if (!$xml) {
            throw new RuntimeException('Invalid worksheet XML.');
        }

        $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $xml->xpath('//x:sheetData/x:row');
        if (!$rowNodes) {
            $rowNodes = $xml->sheetData->row;
        }

        $rows = [];
        foreach ($rowNodes as $rowNode) {
            $rowIndex = (int)$rowNode['r'];
            if ($rowIndex <= 0) {
                $rowIndex = count($rows) + 1;
            }
            $rowArray = [];
            foreach ($rowNode->c as $cell) {
                $ref = (string)$cell['r'];
                $colIndex = self::columnIndexFromRef($ref);
                $type = (string)$cell['t'];
                $value = '';
                if ($type === 'inlineStr') {
                    $value = self::inlineString($cell);
                } else {
                    $raw = isset($cell->v) ? (string)$cell->v : '';
                    if ($type === 's') {
                        $idx = (int)$raw;
                        $value = $sharedStrings[$idx] ?? '';
                    } else {
                        $value = $raw;
                    }
                }
                $rowArray[$colIndex] = clean_text($value);
            }
            if (!empty($rowArray)) {
                $max = max(array_keys($rowArray));
                $filled = [];
                for ($i = 0; $i <= $max; $i++) {
                    $filled[] = $rowArray[$i] ?? '';
                }
                $rows[$rowIndex] = $filled;
            }
        }
        ksort($rows);
        return array_values($rows);
    }

    private static function locateNameCaseInsensitive(ZipArchive $zip, string $wanted)
    {
        $idx = $zip->locateName($wanted);
        if ($idx !== false) {
            return $zip->getNameIndex($idx);
        }
        if (defined('ZipArchive::FL_NOCASE')) {
            $idx = $zip->locateName($wanted, ZipArchive::FL_NOCASE);
            if ($idx !== false) {
                return $zip->getNameIndex($idx);
            }
        }
        $wantedLower = strtolower($wanted);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strtolower($name) === $wantedLower) {
                return $name;
            }
        }
        return false;
    }

    private static function getFromNameCaseInsensitive(ZipArchive $zip, string $wanted)
    {
        $name = self::locateNameCaseInsensitive($zip, $wanted);
        if ($name === false) {
            return false;
        }
        return $zip->getFromName($name);
    }

    private static function readSharedStrings(ZipArchive $zip): array
    {
        $xmlText = self::getFromNameCaseInsensitive($zip, 'xl/sharedStrings.xml');
        if ($xmlText === false) {
            return [];
        }
        $xml = @simplexml_load_string($xmlText);
        if (!$xml) {
            return [];
        }
        $strings = [];
        foreach ($xml->si as $si) {
            $parts = [];
            if (isset($si->t)) {
                $parts[] = (string)$si->t;
            }
            if (isset($si->r)) {
                foreach ($si->r as $r) {
                    if (isset($r->t)) {
                        $parts[] = (string)$r->t;
                    }
                }
            }
            $strings[] = clean_text(implode('', $parts));
        }
        return $strings;
    }

    private static function getFirstSheetPath(ZipArchive $zip): string
    {
        // Some exported Excel files use xl/worksheets/Sheet1.xml instead of xl/worksheets/sheet1.xml.
        // The previous reader was case-sensitive, causing the "No worksheet found" error.
        $direct = self::locateNameCaseInsensitive($zip, 'xl/worksheets/sheet1.xml');
        if ($direct !== false) {
            return $direct;
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/[^/]+\.xml$#i', $name)) {
                return $name;
            }
        }
        throw new RuntimeException('No worksheet found in the Excel file. Please save the file again as .xlsx from Microsoft Excel and upload it again.');
    }

    private static function columnIndexFromRef(string $ref): int
    {
        preg_match('/^([A-Z]+)/i', $ref, $m);
        $letters = strtoupper($m[1] ?? 'A');
        $num = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $num = $num * 26 + (ord($letters[$i]) - 64);
        }
        return max(0, $num - 1);
    }

    private static function inlineString(SimpleXMLElement $cell): string
    {
        if (isset($cell->is->t)) {
            return clean_text((string)$cell->is->t);
        }
        $parts = [];
        if (isset($cell->is->r)) {
            foreach ($cell->is->r as $r) {
                if (isset($r->t)) {
                    $parts[] = (string)$r->t;
                }
            }
        }
        return clean_text(implode('', $parts));
    }
}

function read_csv_rows(string $path): array
{
    $rows = [];
    $handle = fopen($path, 'r');
    if (!$handle) {
        throw new RuntimeException('Unable to read CSV file.');
    }
    while (($data = fgetcsv($handle)) !== false) {
        $rows[] = array_map('clean_text', $data);
    }
    fclose($handle);
    return $rows;
}

function read_with_phpspreadsheet(string $path): array
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        return [];
    }
    require_once $autoload;
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
        return [];
    }
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($path);
    $sheet = $spreadsheet->getSheet(0);
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
    $rows = [];
    for ($r = 1; $r <= $highestRow; $r++) {
        $row = [];
        $hasAny = false;
        for ($c = 1; $c <= $highestColumnIndex; $c++) {
            $value = $sheet->getCellByColumnAndRow($c, $r)->getFormattedValue();
            $text = clean_text($value);
            if ($text !== '') {
                $hasAny = true;
            }
            $row[] = $text;
        }
        if ($hasAny) {
            $rows[] = $row;
        }
    }
    $spreadsheet->disconnectWorksheets();
    return $rows;
}

function read_import_file_rows(string $path): array
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['xls', 'xlsx', 'xlsm'], true)) {
        $viaComposer = read_with_phpspreadsheet($path);
        if ($viaComposer) {
            return $viaComposer;
        }
        if (in_array($ext, ['xlsx', 'xlsm'], true)) {
            return SimpleXlsxReader::readFirstSheet($path);
        }
        throw new RuntimeException('This is an old .xls file. For .xls import, install the optional PhpSpreadsheet library by running composer install in this folder, or open the file in Excel and Save As .xlsx.');
    }
    if ($ext === 'csv') {
        return read_csv_rows($path);
    }
    throw new RuntimeException('Unsupported file type. Please upload .xlsx, .xls, .xlsm, or .csv.');
}

function fill_forward(array $row): array
{
    $filled = [];
    $last = '';
    foreach ($row as $value) {
        $text = clean_text($value);
        if ($text !== '') {
            $last = $text;
        }
        $filled[] = $last;
    }
    return $filled;
}

function row_text(array $row): string
{
    $parts = [];
    foreach ($row as $v) {
        $t = clean_text($v);
        if ($t !== '') {
            $parts[] = $t;
        }
    }
    return implode(' | ', $parts);
}

function detect_schedule_header(array $row): array
{
    $found = [];
    foreach ($row as $idx => $value) {
        $h = norm_text($value);
        if ($h === '') continue;
        if ($h === 'room' || str_contains($h, 'room')) {
            $found['room'] = $idx;
        } elseif ($h === 'subject' || str_contains($h, 'subject')) {
            $found['subject'] = $idx;
        } elseif ($h === 'day' || str_starts_with($h, 'day')) {
            $found['day'] = $idx;
        } elseif (str_contains($h, 'time start') || $h === 'start' || $h === 'start time') {
            $found['time_start'] = $idx;
        } elseif (str_contains($h, 'time end') || $h === 'end' || $h === 'end time') {
            $found['time_end'] = $idx;
        } elseif (str_contains($h, 'instructor') || str_contains($h, 'teacher') || str_contains($h, 'faculty')) {
            $found['instructor'] = $idx;
        }
    }
    if (isset($found['subject'], $found['instructor']) && (isset($found['time_start']) || isset($found['room']) || isset($found['day']))) {
        return $found;
    }
    return [];
}

function normalize_time_text(string $value): string
{
    if ($value === '') return '';
    if (is_numeric($value) && (float)$value >= 0 && (float)$value < 1) {
        $seconds = (int)round((float)$value * 86400);
        $h = intdiv($seconds, 3600) % 24;
        $m = intdiv($seconds % 3600, 60);
        $ampm = $h < 12 ? 'AM' : 'PM';
        $hh = $h % 12;
        if ($hh === 0) $hh = 12;
        return sprintf('%d:%02d %s', $hh, $m, $ampm);
    }
    return $value;
}

function infer_course_from_section(string $section, string $defaultCourse): string
{
    $section = clean_text($section);
    if (preg_match('/^([A-Za-z0-9]+)[\s\-]/', $section, $m)) {
        return strtoupper($m[1]);
    }
    return $defaultCourse;
}

function shifted_schedule_map(array $headerMap): array
{
    $shifted = [];
    foreach ($headerMap as $field => $idx) {
        $shifted[$field] = max(0, ((int)$idx) - 1);
    }
    return $shifted;
}

function schedule_map_score(array $row, array $map): int
{
    $get = function (string $field) use ($row, $map): string {
        $idx = $map[$field] ?? null;
        return $idx === null ? '' : clean_text($row[$idx] ?? '');
    };
    $score = 0;
    if ($get('subject') !== '') $score += 4;
    if ($get('instructor') !== '') $score += 4;
    if ($get('day') !== '') $score += 2;
    if ($get('time_start') !== '') $score += 1;
    if ($get('time_end') !== '') $score += 1;
    if ($get('room') !== '') $score += 1;
    $subjectNorm = norm_text($get('subject'));
    if (in_array($subjectNorm, ['m w', 't th', 'th', 'sat', 'sun', 'mw', 'tth'], true)) {
        $score -= 3;
    }
    return $score;
}

function best_schedule_map_for_row(array $row, array $headerMap): array
{
    $best = $headerMap;
    if (($headerMap['subject'] ?? 0) > 0) {
        $shifted = shifted_schedule_map($headerMap);
        if (schedule_map_score($row, $shifted) > schedule_map_score($row, $headerMap)) {
            $best = $shifted;
        }
    }
    return $best;
}

function parse_schedule_blocks(array $rows, string $defaultCourse): array
{
    $records = [];
    $currentSection = '';
    $headerMap = [];
    $sawClassLabel = false;
    foreach ($rows as $row) {
        $detected = detect_schedule_header($row);
        if ($detected) {
            $headerMap = $detected;
            continue;
        }

        $text = row_text($row);
        if (preg_match('/class\s*:\s*([^|]+)/i', $text, $m)) {
            $sawClassLabel = true;
            $currentSection = trim($m[1]);
            // Do not clear $headerMap. In the CFCI schedule export, the header appears once before all class blocks.
            continue;
        }
        if (!$headerMap || !$currentSection) continue;

        $activeMap = best_schedule_map_for_row($row, $headerMap);
        $get = function (string $field) use ($row, $activeMap): string {
            $idx = $activeMap[$field] ?? null;
            return $idx === null ? '' : clean_text($row[$idx] ?? '');
        };
        $subject = $get('subject');
        $instructor = $get('instructor');
        if ($subject === '' || $instructor === '') continue;
        if (in_array(norm_text($subject), ['subject', 'date', 'prepared by'], true)) continue;

        $start = normalize_time_text($get('time_start'));
        $end = normalize_time_text($get('time_end'));
        $room = $get('room');
        $scheduleParts = [];
        if ($start !== '' || $end !== '') {
            $scheduleParts[] = trim($start . ($start !== '' && $end !== '' ? ' - ' : '') . $end);
        }
        if ($room !== '') {
            $scheduleParts[] = $room;
        }
        $day = $get('day');
        $total = guess_total_classes($day);
        $records[] = [
            'instructor' => $instructor,
            'course' => infer_course_from_section($currentSection, $defaultCourse),
            'section' => $currentSection,
            'subject' => $subject,
            'day' => $day,
            'schedule' => implode(' / ', $scheduleParts),
            'cronasia_pmvgo_checks' => '',
            'class_material_1_2' => '',
            'class_material_3_4' => '',
            'activity_1_2' => '',
            'activity_3_4' => '',
            'total_classes' => $total,
            'present' => 0,
            'absent' => 0,
            'remarks' => '',
            'monitoring_status' => 'Pending',
        ];
    }
    return $sawClassLabel ? $records : [];
}

function find_header_row(array $rows): array
{
    $bestScore = -1;
    $bestIdx = 0;
    $bestUsesNext = false;
    $bestHeaders = [];
    $maxScan = min(count($rows), 60);
    for ($i = 0; $i < $maxScan; $i++) {
        $top = $rows[$i] ?? [];
        $next = $rows[$i + 1] ?? [];
        $width = max(count($top), count($next));
        $top = array_pad($top, $width, '');
        $next = array_pad($next, $width, '');
        $topFilled = fill_forward($top);
        $headersNext = [];
        for ($c = 0; $c < $width; $c++) {
            $a = clean_text($topFilled[$c]);
            $b = clean_text($next[$c]);
            if ($a !== '' && $b !== '' && !str_contains(norm_text($a), norm_text($b))) {
                $headersNext[] = $a . ' ' . $b;
            } else {
                $headersNext[] = $a !== '' ? $a : $b;
            }
        }
        foreach ([[false, $top], [true, $headersNext]] as $set) {
            [$usesNext, $headers] = $set;
            $joined = implode(' | ', array_map('norm_text', $headers));
            $score = 0;
            foreach (['instructor', 'teacher', 'faculty'] as $token) {
                if (str_contains($joined, $token)) { $score += 4; break; }
            }
            foreach (['subject', 'section', 'course', 'program', 'day', 'schedule', 'remarks'] as $token) {
                if (str_contains($joined, $token)) $score += 2;
            }
            if (str_contains($joined, 'week 1') || str_contains($joined, 'week 3')) $score += 2;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIdx = $i;
                $bestUsesNext = $usesNext;
                $bestHeaders = $headers;
            }
        }
    }
    if ($bestScore < 4) {
        throw new RuntimeException('Could not detect the header row. Please use the sample template or ensure the file has Teacher/Instructor and Subject columns.');
    }
    return [$bestIdx, $bestUsesNext, $bestHeaders];
}

function map_headers(array $headers): array
{
    $mapped = [];
    $set = function (string $field, int $idx) use (&$mapped) {
        if (!isset($mapped[$field])) $mapped[$field] = $idx;
    };
    foreach ($headers as $idx => $header) {
        $h = norm_text($header);
        if ($h === '') continue;
        if ($h === 'no' || $h === 'number') {
            continue;
        } elseif (str_contains($h, 'class material') && (str_contains($h, 'week 2') || str_contains($h, 'week 3') || str_contains($h, 'week 4') || str_contains($h, '3 4'))) {
            $set('class_material_3_4', $idx);
        } elseif (str_contains($h, 'class material') && (str_contains($h, 'week 1') || str_contains($h, '1 2'))) {
            $set('class_material_1_2', $idx);
        } elseif ((str_contains($h, 'activity') || str_contains($h, 'quiz')) && (str_contains($h, 'week 2') || str_contains($h, 'week 3') || str_contains($h, 'week 4') || str_contains($h, '3 4'))) {
            $set('activity_3_4', $idx);
        } elseif ((str_contains($h, 'activity') || str_contains($h, 'quiz')) && (str_contains($h, 'week 1') || str_contains($h, '1 2'))) {
            $set('activity_1_2', $idx);
        } elseif (str_contains($h, 'cronasia') || str_contains($h, 'pmvgo')) {
            $set('cronasia_pmvgo_checks', $idx);
        } elseif (str_contains($h, 'total') && str_contains($h, 'class')) {
            $set('total_classes', $idx);
        } elseif (str_contains($h, 'present')) {
            $set('present', $idx);
        } elseif (str_contains($h, 'absent')) {
            $set('absent', $idx);
        } elseif (str_contains($h, 'remark')) {
            $set('remarks', $idx);
        } elseif (str_contains($h, 'status')) {
            $set('monitoring_status', $idx);
        } elseif (str_contains($h, 'instructor') || str_contains($h, 'teacher') || str_contains($h, 'faculty') || str_contains($h, 'professor')) {
            $set('instructor', $idx);
        } elseif (str_contains($h, 'course') || str_contains($h, 'program') || str_contains($h, 'department') || str_contains($h, 'college')) {
            $set('course', $idx);
        } elseif (str_contains($h, 'section') || $h === 'class') {
            $set('section', $idx);
        } elseif (str_contains($h, 'subject')) {
            $set('subject', $idx);
        } elseif ($h === 'day' || str_starts_with($h, 'day ') || str_contains($h, 'days')) {
            $set('day', $idx);
        } elseif (str_contains($h, 'schedule') || str_contains($h, 'time') || str_contains($h, 'room') || str_contains($h, 'pmvgo') || str_contains($h, 'gmeet') || str_contains($h, 'modality') || str_contains($h, 'platform')) {
            $set('schedule', $idx);
        }
    }
    if (!isset($mapped['instructor']) || !isset($mapped['subject'])) {
        throw new RuntimeException('The file must include at least Instructor/Teacher and Subject columns.');
    }
    return $mapped;
}

function import_class_records_from_file(string $path, string $defaultCourse): array
{
    $rows = read_import_file_rows($path);
    if (!$rows) return [];
    $scheduleRecords = parse_schedule_blocks($rows, $defaultCourse);
    if ($scheduleRecords) return $scheduleRecords;
    [$headerIdx, $usesNext, $headers] = find_header_row($rows);
    $mapping = map_headers($headers);
    $dataStart = $headerIdx + ($usesNext ? 2 : 1);
    $records = [];
    $lastInstructor = '';
    for ($i = $dataStart; $i < count($rows); $i++) {
        $row = $rows[$i];
        $hasAny = false;
        foreach ($row as $v) {
            if (clean_text($v) !== '') { $hasAny = true; break; }
        }
        if (!$hasAny) continue;
        $get = function (string $field) use ($mapping, $row): string {
            $idx = $mapping[$field] ?? null;
            return $idx === null ? '' : clean_text($row[$idx] ?? '');
        };
        $instructor = $get('instructor');
        if ($instructor !== '') $lastInstructor = $instructor;
        else $instructor = $lastInstructor;
        $subject = $get('subject');
        $section = $get('section');
        if ($instructor === '' && $subject === '' && $section === '') continue;
        if (in_array(norm_text($instructor), ['instructors', 'instructor', 'teacher'], true)) continue;
        $total = to_int($get('total_classes'), 0);
        if ($total === 0) $total = guess_total_classes($get('day'));
        $present = to_int($get('present'), 0);
        $absent = to_int($get('absent'), 0);
        $remarks = $get('remarks');
        $status = $get('monitoring_status');
        if ($status === '') {
            if (str_contains(norm_text($remarks), 'complete') || ($total > 0 && $present >= $total && $absent === 0)) $status = 'Monitored';
            else $status = 'Pending';
        }
        $records[] = [
            'instructor' => $instructor,
            'course' => $get('course') ?: infer_course_from_section($section, $defaultCourse),
            'section' => $section,
            'subject' => $subject,
            'day' => $get('day'),
            'schedule' => $get('schedule'),
            'cronasia_pmvgo_checks' => $get('cronasia_pmvgo_checks'),
            'class_material_1_2' => $get('class_material_1_2'),
            'class_material_3_4' => $get('class_material_3_4'),
            'activity_1_2' => $get('activity_1_2'),
            'activity_3_4' => $get('activity_3_4'),
            'total_classes' => $total,
            'present' => $present,
            'absent' => $absent,
            'remarks' => $remarks,
            'monitoring_status' => $status,
        ];
    }
    return $records;
}
