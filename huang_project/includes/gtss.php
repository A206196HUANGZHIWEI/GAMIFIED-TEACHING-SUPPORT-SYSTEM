<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

function require_login()
{
    if (!current_user()) {
        flash('error', 'Please log in first.');
        redirect_to('login.php');
    }
}

function require_role($roles)
{
    require_login();
    $user = current_user();

    if (!is_array($roles)) {
        $roles = array($roles);
    }

    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function db_escape($value)
{
    return db()->real_escape_string((string) $value);
}

function db_all($sql)
{
    $result = db()->query($sql);

    if (!$result) {
        return array();
    }

    $rows = array();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $result->free();
    return $rows;
}

function db_one($sql)
{
    $result = db()->query($sql);

    if (!$result) {
        return null;
    }

    $row = $result->fetch_assoc();
    $result->free();

    return $row ? $row : null;
}

function db_value($sql, $default)
{
    $row = db_one($sql);

    if (!$row) {
        return $default;
    }

    foreach ($row as $value) {
        return $value;
    }

    return $default;
}

function db_exec($sql)
{
    return db()->query($sql);
}

function system_setting($key, $default)
{
    $value = db_value(
        "SELECT settingValue FROM system_settings WHERE settingKey = '" . db_escape($key) . "' LIMIT 1",
        null
    );

    if ($value === null || $value === '') {
        return $default;
    }

    return $value;
}

function save_system_setting($key, $value)
{
    $exists = db_value(
        "SELECT COUNT(*) FROM system_settings WHERE settingKey = '" . db_escape($key) . "'",
        0
    );

    if ((int) $exists > 0) {
        return db_exec(
            "UPDATE system_settings
             SET settingValue = '" . db_escape($value) . "', updatedAt = NOW()
             WHERE settingKey = '" . db_escape($key) . "'"
        );
    }

    return db_exec(
        "INSERT INTO system_settings (settingKey, settingValue, updatedAt)
         VALUES ('" . db_escape($key) . "', '" . db_escape($value) . "', NOW())"
    );
}

function course_setting_key($courseId, $name)
{
    return 'course_' . $courseId . '_' . $name;
}

function course_setting($courseId, $name, $default)
{
    return system_setting(course_setting_key($courseId, $name), $default);
}

function save_course_setting($courseId, $name, $value)
{
    return save_system_setting(course_setting_key($courseId, $name), $value);
}

function quest_type_labels()
{
    return array(
        'individual' => 'Individual',
        'group' => 'Group',
        'external' => 'External tool',
        'offline' => 'Offline activity',
    );
}

function external_tool_labels()
{
    return array(
        'kahoot' => 'Kahoot',
        'quizizz' => 'Quizizz',
        'google_classroom' => 'Google Classroom',
        'moodle' => 'Moodle',
    );
}

function course_external_tool_links($courseId)
{
    $links = array();
    foreach (external_tool_labels() as $key => $label) {
        $links[$key] = course_setting($courseId, 'tool_' . $key . '_url', '');
    }

    return $links;
}

function external_tool_name_for_link($url)
{
    $lower = strtolower((string) $url);
    if (strpos($lower, 'kahoot') !== false) {
        return 'Kahoot';
    }
    if (strpos($lower, 'quizizz') !== false) {
        return 'Quizizz';
    }
    if (strpos($lower, 'classroom.google') !== false || strpos($lower, 'google.com/classroom') !== false) {
        return 'Google Classroom';
    }
    if (strpos($lower, 'moodle') !== false) {
        return 'Moodle';
    }

    return 'External activity';
}

function course_xp_rules($courseId)
{
    return array(
        'individual' => (int) course_setting($courseId, 'xp_individual', '10'),
        'group' => (int) course_setting($courseId, 'xp_group', '15'),
        'external' => (int) course_setting($courseId, 'xp_external', '10'),
        'offline' => (int) course_setting($courseId, 'xp_offline', '10'),
    );
}

function course_default_xp_for_type($courseId, $questType)
{
    $rules = course_xp_rules($courseId);
    return isset($rules[$questType]) ? (int) $rules[$questType] : (int) $rules['individual'];
}

function leaderboard_sort_options()
{
    return array(
        'xp' => 'Total XP',
        'level' => 'Level',
        'badges' => 'Badge count',
        'completion' => 'Completion rate',
        'name' => 'Student name',
    );
}

function leaderboard_anonymity_options()
{
    return array(
        'none' => 'Show student names',
        'students' => 'Hide names from students',
        'all' => 'Hide names from everyone',
    );
}

function course_leaderboard_settings($courseId)
{
    $sort = course_setting($courseId, 'leaderboard_sort', 'xp');
    $anonymity = course_setting($courseId, 'leaderboard_anonymity', 'none');

    if (!array_key_exists($sort, leaderboard_sort_options())) {
        $sort = 'xp';
    }

    if (!array_key_exists($anonymity, leaderboard_anonymity_options())) {
        $anonymity = 'none';
    }

    return array(
        'sort' => $sort,
        'anonymity' => $anonymity,
    );
}

function course_leaderboard_rows($courseId)
{
    $settings = course_leaderboard_settings($courseId);
    $rows = course_analytics_rows($courseId);
    $sort = $settings['sort'];

    usort($rows, function ($a, $b) use ($sort) {
        $aTotalQuests = (int) $a['totalQuests'];
        $bTotalQuests = (int) $b['totalQuests'];
        $aCompletion = $aTotalQuests > 0 ? ((int) $a['completedQuests'] / $aTotalQuests) : 0;
        $bCompletion = $bTotalQuests > 0 ? ((int) $b['completedQuests'] / $bTotalQuests) : 0;

        if ($sort === 'name') {
            $primary = strcasecmp($a['name'], $b['name']);
        } elseif ($sort === 'level') {
            $primary = (int) $b['level'] - (int) $a['level'];
        } elseif ($sort === 'badges') {
            $primary = (int) $b['badgeCount'] - (int) $a['badgeCount'];
        } elseif ($sort === 'completion') {
            if ($aCompletion === $bCompletion) {
                $primary = 0;
            } else {
                $primary = $aCompletion < $bCompletion ? 1 : -1;
            }
        } else {
            $primary = (int) $b['totalXP'] - (int) $a['totalXP'];
        }

        if ($primary !== 0) {
            return $primary;
        }

        $xpCompare = (int) $b['totalXP'] - (int) $a['totalXP'];
        if ($xpCompare !== 0) {
            return $xpCompare;
        }

        return strcasecmp($a['name'], $b['name']);
    });

    return $rows;
}

function leaderboard_display_name($row, $rank, $settings, $canManage)
{
    $anonymity = isset($settings['anonymity']) ? $settings['anonymity'] : 'none';

    if ($anonymity === 'all' || ($anonymity === 'students' && !$canManage)) {
        return 'Student #' . (int) ($rank + 1);
    }

    return isset($row['name']) ? $row['name'] : 'Student';
}

function course_deadline_reminder_days($courseId)
{
    return (int) course_setting($courseId, 'deadline_reminder_days', '3');
}

function csv_download($filename, $headers, $rows)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);

    foreach ($rows as $row) {
        fputcsv($out, $row);
    }

    fclose($out);
    exit;
}

function pdf_clean_text($text)
{
    $text = html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
    $text = str_replace(array("\r\n", "\r"), "\n", $text);

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\xFF]/', '', $text);
    return $text;
}

function pdf_escape_text($text)
{
    $text = pdf_clean_text($text);
    return str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $text);
}

function pdf_wrapped_lines($text, $style, $limit)
{
    $text = pdf_clean_text($text);
    $parts = explode("\n", $text);
    $lines = array();

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            $lines[] = array('text' => '', 'style' => 'blank');
            continue;
        }

        $wrapped = explode("\n", wordwrap($part, $limit, "\n", true));
        foreach ($wrapped as $line) {
            $lines[] = array('text' => $line, 'style' => $style);
        }
    }

    return $lines;
}

function pdf_add_object(&$pdf, &$offsets, $id, $body)
{
    $offsets[$id] = strlen($pdf);
    $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
}

function simple_pdf_download($filename, $title, $sections)
{
    $lines = array();
    $lines[] = array('text' => $title, 'style' => 'title');
    $lines[] = array('text' => 'Generated at ' . date('Y-m-d H:i'), 'style' => 'small');

    foreach ($sections as $section) {
        $lines[] = array('text' => '', 'style' => 'blank');
        $lines = array_merge($lines, pdf_wrapped_lines($section['title'], 'heading', 78));

        foreach ($section['lines'] as $line) {
            $lines = array_merge($lines, pdf_wrapped_lines($line, 'normal', 98));
        }
    }

    $pages = array();
    $page = array();
    $remaining = 760;
    foreach ($lines as $line) {
        $height = 14;
        if ($line['style'] === 'title') {
            $height = 24;
        } elseif ($line['style'] === 'heading') {
            $height = 18;
        } elseif ($line['style'] === 'blank') {
            $height = 8;
        }

        if ($remaining < $height && $page) {
            $pages[] = $page;
            $page = array();
            $remaining = 760;
        }

        $page[] = $line;
        $remaining -= $height;
    }

    if ($page) {
        $pages[] = $page;
    }

    $pdf = "%PDF-1.4\n";
    $offsets = array();
    $objectCount = 3 + (count($pages) * 2);
    $kids = array();

    pdf_add_object($pdf, $offsets, 1, "<< /Type /Catalog /Pages 2 0 R >>");
    pdf_add_object($pdf, $offsets, 3, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");

    foreach ($pages as $index => $pageLines) {
        $pageObjectId = 4 + ($index * 2);
        $contentObjectId = $pageObjectId + 1;
        $kids[] = $pageObjectId . ' 0 R';

        $stream = '';
        $y = 800;
        foreach ($pageLines as $line) {
            if ($line['style'] === 'blank') {
                $y -= 8;
                continue;
            }

            $fontSize = 10;
            $lineHeight = 14;
            if ($line['style'] === 'title') {
                $fontSize = 16;
                $lineHeight = 24;
            } elseif ($line['style'] === 'heading') {
                $fontSize = 12;
                $lineHeight = 18;
            } elseif ($line['style'] === 'small') {
                $fontSize = 9;
                $lineHeight = 14;
            }

            $stream .= "BT /F1 " . $fontSize . " Tf 50 " . $y . " Td (" . pdf_escape_text($line['text']) . ") Tj ET\n";
            $y -= $lineHeight;
        }

        $contentBody = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        pdf_add_object($pdf, $offsets, $contentObjectId, $contentBody);

        $pageBody = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents " . $contentObjectId . " 0 R >>";
        pdf_add_object($pdf, $offsets, $pageObjectId, $pageBody);
    }

    pdf_add_object($pdf, $offsets, 2, "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($pages) . " >>");

    $xrefPosition = strlen($pdf);
    $pdf .= "xref\n0 " . ($objectCount + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= $objectCount; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", isset($offsets[$i]) ? $offsets[$i] : 0);
    }

    $pdf .= "trailer\n<< /Size " . ($objectCount + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPosition . "\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

function safe_file_extension($filename)
{
    $parts = explode('.', strtolower($filename));
    return count($parts) > 1 ? end($parts) : '';
}

function save_uploaded_evidence($field, $courseId, $questId, $studentId, &$errors)
{
    if (!isset($_FILES[$field]) || !isset($_FILES[$field]['error']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Evidence file upload failed.';
        return '';
    }

    $maxBytes = 5 * 1024 * 1024;
    if ($_FILES[$field]['size'] > $maxBytes) {
        $errors[] = 'Evidence file must be 5 MB or smaller.';
        return '';
    }

    $extension = safe_file_extension($_FILES[$field]['name']);
    $allowed = array('pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip');

    if (!in_array($extension, $allowed, true)) {
        $errors[] = 'Evidence file type is not allowed.';
        return '';
    }

    $uploadDir = __DIR__ . '/../uploads/evidence';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $courseId . '-' . $questId . '-' . $studentId);
    $filename .= '-' . substr(md5(uniqid('', true)), 0, 10) . '.' . $extension;
    $target = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        $errors[] = 'Evidence file could not be saved. Please check upload folder permissions.';
        return '';
    }

    return 'uploads/evidence/' . $filename;
}

function save_uploaded_avatar($field, $userId, &$errors)
{
    if (!isset($_FILES[$field]) || !isset($_FILES[$field]['error']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Avatar upload failed.';
        return '';
    }

    $maxBytes = 2 * 1024 * 1024;
    if ($_FILES[$field]['size'] > $maxBytes) {
        $errors[] = 'Avatar image must be 2 MB or smaller.';
        return '';
    }

    $extension = safe_file_extension($_FILES[$field]['name']);
    $allowed = array('jpg', 'jpeg', 'png', 'gif');

    if (!in_array($extension, $allowed, true)) {
        $errors[] = 'Avatar must be a JPG, PNG, or GIF image.';
        return '';
    }

    $uploadDir = __DIR__ . '/../uploads/avatars';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
    $filename .= '-' . substr(md5(uniqid('', true)), 0, 10) . '.' . $extension;
    $target = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        $errors[] = 'Avatar image could not be saved. Please check upload folder permissions.';
        return '';
    }

    return 'uploads/avatars/' . $filename;
}

function save_uploaded_badge_icon($field, $courseId, $badgeId, &$errors)
{
    if (!isset($_FILES[$field]) || !isset($_FILES[$field]['error']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Badge image upload failed.';
        return '';
    }

    $maxBytes = 2 * 1024 * 1024;
    if ($_FILES[$field]['size'] > $maxBytes) {
        $errors[] = 'Badge image must be 2 MB or smaller.';
        return '';
    }

    $extension = safe_file_extension($_FILES[$field]['name']);
    $allowed = array('jpg', 'jpeg', 'png', 'gif');

    if (!in_array($extension, $allowed, true)) {
        $errors[] = 'Badge image must be a JPG, PNG, or GIF image.';
        return '';
    }

    $uploadDir = __DIR__ . '/../uploads/badges';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $courseId . '-' . $badgeId);
    $filename .= '-' . substr(md5(uniqid('', true)), 0, 10) . '.' . $extension;
    $target = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        $errors[] = 'Badge image could not be saved. Please check upload folder permissions.';
        return '';
    }

    return 'uploads/badges/' . $filename;
}

function save_uploaded_system_logo($field, &$errors)
{
    if (!isset($_FILES[$field]) || !isset($_FILES[$field]['error']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Logo upload failed.';
        return '';
    }

    $maxBytes = 2 * 1024 * 1024;
    if ($_FILES[$field]['size'] > $maxBytes) {
        $errors[] = 'Logo image must be 2 MB or smaller.';
        return '';
    }

    $extension = safe_file_extension($_FILES[$field]['name']);
    $allowed = array('jpg', 'jpeg', 'png', 'gif');

    if (!in_array($extension, $allowed, true)) {
        $errors[] = 'Logo image must be a JPG, PNG, or GIF image.';
        return '';
    }

    $uploadDir = __DIR__ . '/../uploads/system';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'system-logo-' . substr(md5(uniqid('', true)), 0, 10) . '.' . $extension;
    $target = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        $errors[] = 'Logo image could not be saved. Please check upload folder permissions.';
        return '';
    }

    return 'uploads/system/' . $filename;
}

function save_uploaded_reward_item_image($field, $courseId, $itemId, &$errors)
{
    if (!isset($_FILES[$field]) || !isset($_FILES[$field]['error']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Item image upload failed.';
        return '';
    }

    $maxBytes = 2 * 1024 * 1024;
    if ($_FILES[$field]['size'] > $maxBytes) {
        $errors[] = 'Item image must be 2 MB or smaller.';
        return '';
    }

    $extension = safe_file_extension($_FILES[$field]['name']);
    $allowed = array('jpg', 'jpeg', 'png', 'gif');

    if (!in_array($extension, $allowed, true)) {
        $errors[] = 'Item image must be a JPG, PNG, or GIF image.';
        return '';
    }

    $uploadDir = __DIR__ . '/../uploads/rewards';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $courseId . '-' . $itemId);
    $filename .= '-' . substr(md5(uniqid('', true)), 0, 10) . '.' . $extension;
    $target = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        $errors[] = 'Item image could not be saved. Please check upload folder permissions.';
        return '';
    }

    return 'uploads/rewards/' . $filename;
}

function user_profile($userId)
{
    return db_one(
        "SELECT u.*, b.name AS equippedBadgeName, b.iconPath AS equippedBadgeIcon
         FROM users u
         LEFT JOIN badges b ON b.badgeId = u.equippedBadgeId
         WHERE u.userId = '" . db_escape($userId) . "'
         LIMIT 1"
    );
}

function user_earned_badges($userId)
{
    return db_all(
        "SELECT b.badgeId, b.name, b.description, b.iconPath, c.title AS courseTitle, sb.awardedAt
         FROM student_badges sb
         INNER JOIN badges b ON b.badgeId = sb.badgeId
         INNER JOIN courses c ON c.courseId = sb.courseId
         WHERE sb.studentId = '" . db_escape($userId) . "'
         ORDER BY sb.awardedAt DESC, b.name ASC"
    );
}

function user_can_equip_badge($userId, $badgeId)
{
    if ($badgeId === '') {
        return true;
    }

    $exists = db_value(
        "SELECT COUNT(*) FROM student_badges
         WHERE studentId = '" . db_escape($userId) . "'
           AND badgeId = '" . db_escape($badgeId) . "'",
        0
    );

    return (int) $exists > 0;
}

function chat_unread_count($userId)
{
    return (int) db_value(
        "SELECT COUNT(*) FROM chat_messages
         WHERE receiverId = '" . db_escape($userId) . "'
           AND isRead = 0",
        0
    );
}

function chat_users($currentUserId)
{
    return db_all(
        "SELECT u.userId, u.name, u.email, u.role, u.avatarPath,
            (SELECT COUNT(*) FROM chat_messages cm WHERE cm.senderId = u.userId AND cm.receiverId = '" . db_escape($currentUserId) . "' AND cm.isRead = 0) AS unreadCount,
            (SELECT MAX(cm2.createdAt) FROM chat_messages cm2
             WHERE (cm2.senderId = u.userId AND cm2.receiverId = '" . db_escape($currentUserId) . "')
                OR (cm2.receiverId = u.userId AND cm2.senderId = '" . db_escape($currentUserId) . "')) AS lastMessageAt
         FROM users u
         WHERE u.userId <> '" . db_escape($currentUserId) . "'
         ORDER BY lastMessageAt DESC, u.role ASC, u.name ASC"
    );
}

function get_user_by_id($userId)
{
    return db_one("SELECT * FROM users WHERE userId = '" . db_escape($userId) . "' LIMIT 1");
}

function send_email_notification($userId, $title, $message)
{
    if (system_setting('email_notifications_enabled', '0') !== '1') {
        return false;
    }

    $recipient = db_one("SELECT email, name FROM users WHERE userId = '" . db_escape($userId) . "' LIMIT 1");
    if (!$recipient || !filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $from = system_setting('notification_email_from', '');
    $headers = '';
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $headers = 'From: ' . $from . "\r\n";
    }

    if (!function_exists('mail')) {
        return false;
    }

    $appName = system_setting('institution_name', app_config('app_name', 'GTSS'));
    $subject = '[' . $appName . '] ' . $title;
    $body = $message . "\n\nThis is an automatic notification from " . $appName . ".";

    return @mail($recipient['email'], $subject, wordwrap($body, 70), $headers);
}

function current_user_id()
{
    $user = current_user();
    return $user ? $user['userId'] : '';
}

function generate_enrollment_code()
{
    for ($i = 0; $i < 20; $i++) {
        $code = strtoupper(substr(bin2hex(secure_random_bytes(4)), 0, 8));
        $exists = db_value("SELECT COUNT(*) FROM courses WHERE enrollmentCode = '" . db_escape($code) . "'", 0);

        if ((int) $exists === 0) {
            return $code;
        }
    }

    return strtoupper(substr(md5(uniqid('', true)), 0, 8));
}

function get_course($courseId)
{
    return db_one("SELECT * FROM courses WHERE courseId = '" . db_escape($courseId) . "' LIMIT 1");
}

function user_course_role($courseId, $userId)
{
    $role = db_value(
        "SELECT roleInCourse FROM enrollments WHERE courseId = '" . db_escape($courseId) . "' AND userId = '" . db_escape($userId) . "' LIMIT 1",
        ''
    );

    return $role;
}

function can_view_course($course)
{
    $user = current_user();

    if (!$user || !$course) {
        return false;
    }

    if ($user['role'] === 'admin') {
        return true;
    }

    if ($course['teacherId'] === $user['userId']) {
        return true;
    }

    return user_course_role($course['courseId'], $user['userId']) !== '';
}

function can_manage_course($course)
{
    $user = current_user();

    if (!$user || !$course) {
        return false;
    }

    if ($user['role'] === 'admin') {
        return true;
    }

    return $user['role'] === 'teacher' && $course['teacherId'] === $user['userId'];
}

function require_course_access($courseId)
{
    require_login();
    $course = get_course($courseId);

    if (!$course || !can_view_course($course)) {
        http_response_code(404);
        exit('Course not found.');
    }

    return $course;
}

function require_course_manager($courseId)
{
    $course = require_course_access($courseId);

    if (!can_manage_course($course)) {
        http_response_code(403);
        exit('Access denied.');
    }

    return $course;
}

function teacher_courses($teacherId)
{
    return db_all(
        "SELECT c.*, COUNT(e.enrollmentId) AS studentCount
         FROM courses c
         LEFT JOIN enrollments e ON e.courseId = c.courseId AND e.roleInCourse = 'student'
         WHERE c.teacherId = '" . db_escape($teacherId) . "'
         GROUP BY c.courseId
         ORDER BY c.createdAt DESC"
    );
}

function student_courses($studentId)
{
    return db_all(
        "SELECT c.*, e.createdAt AS joinedAt, u.name AS teacherName
         FROM enrollments e
         INNER JOIN courses c ON c.courseId = e.courseId
         INNER JOIN users u ON u.userId = c.teacherId
         WHERE e.userId = '" . db_escape($studentId) . "' AND e.roleInCourse = 'student'
         ORDER BY e.createdAt DESC"
    );
}

function admin_courses()
{
    return db_all(
        "SELECT c.*, u.name AS teacherName, COUNT(e.enrollmentId) AS studentCount
         FROM courses c
         LEFT JOIN users u ON u.userId = c.teacherId
         LEFT JOIN enrollments e ON e.courseId = c.courseId AND e.roleInCourse = 'student'
         GROUP BY c.courseId
         ORDER BY c.createdAt DESC"
    );
}

function enrolled_students($courseId)
{
    return db_all(
        "SELECT u.userId, u.name, u.email, xr.totalXP, xr.level
         FROM enrollments e
         INNER JOIN users u ON u.userId = e.userId
         LEFT JOIN xp_records xr ON xr.studentId = u.userId AND xr.courseId = e.courseId
         WHERE e.courseId = '" . db_escape($courseId) . "' AND e.roleInCourse = 'student'
         ORDER BY u.name ASC"
    );
}

function ensure_xp_record($courseId, $studentId)
{
    $exists = db_value(
        "SELECT COUNT(*) FROM xp_records WHERE courseId = '" . db_escape($courseId) . "' AND studentId = '" . db_escape($studentId) . "'",
        0
    );

    if ((int) $exists === 0) {
        db_exec(
            "INSERT INTO xp_records (studentId, courseId, totalXP, level, updatedAt)
             VALUES ('" . db_escape($studentId) . "', '" . db_escape($courseId) . "', 0, 1, NOW())"
        );
    }
}

function recalculate_student_progress($courseId, $studentId)
{
    ensure_xp_record($courseId, $studentId);

    $totalXP = db_value(
        "SELECT COALESCE(SUM(r.awardedXP), 0)
         FROM results r
         INNER JOIN quests q ON q.questId = r.questId
         WHERE q.courseId = '" . db_escape($courseId) . "'
           AND r.studentId = '" . db_escape($studentId) . "'
           AND r.completionStatus = 'completed'",
        0
    );

    $totalXP = (int) $totalXP;
    $level = max(1, (int) floor($totalXP / 100) + 1);

    db_exec(
        "UPDATE xp_records
         SET totalXP = " . $totalXP . ", level = " . $level . ", updatedAt = NOW()
         WHERE courseId = '" . db_escape($courseId) . "' AND studentId = '" . db_escape($studentId) . "'"
    );

    award_eligible_badges($courseId, $studentId, $totalXP);
}

function award_eligible_badges($courseId, $studentId, $totalXP)
{
    $badges = db_all(
        "SELECT * FROM badges
         WHERE courseId = '" . db_escape($courseId) . "'
           AND criteriaType = 'xp'
           AND threshold <= " . (int) $totalXP
    );

    foreach ($badges as $badge) {
        $exists = db_value(
            "SELECT COUNT(*) FROM student_badges
             WHERE badgeId = '" . db_escape($badge['badgeId']) . "'
               AND studentId = '" . db_escape($studentId) . "'",
            0
        );

        if ((int) $exists === 0) {
            db_exec(
                "INSERT INTO student_badges (studentBadgeId, badgeId, studentId, courseId, awardedAt)
                 VALUES ('" . db_escape(uuid_v4()) . "', '" . db_escape($badge['badgeId']) . "', '" . db_escape($studentId) . "', '" . db_escape($courseId) . "', NOW())"
            );
        }
    }
}

function reward_item_status_label($status)
{
    $labels = array(
        'pending' => 'Pending review',
        'approved' => 'Open for redemption',
        'delisted' => 'Delisted',
        'archived' => 'Archived',
    );

    return isset($labels[$status]) ? $labels[$status] : 'Pending review';
}

function reward_redemption_status_label($status)
{
    $labels = array(
        'pending' => 'Waiting for handover',
        'fulfilled' => 'Fulfilled',
        'cancelled' => 'Cancelled',
    );

    return isset($labels[$status]) ? $labels[$status] : 'Waiting for handover';
}

function student_spent_xp($courseId, $studentId)
{
    return (int) db_value(
        "SELECT COALESCE(SUM(xpCost), 0)
         FROM reward_redemptions
         WHERE courseId = '" . db_escape($courseId) . "'
           AND studentId = '" . db_escape($studentId) . "'
           AND status <> 'cancelled'",
        0
    );
}

function student_available_xp($courseId, $studentId)
{
    ensure_xp_record($courseId, $studentId);
    $earned = (int) db_value(
        "SELECT totalXP FROM xp_records
         WHERE courseId = '" . db_escape($courseId) . "'
           AND studentId = '" . db_escape($studentId) . "'
         LIMIT 1",
        0
    );

    return max(0, $earned - student_spent_xp($courseId, $studentId));
}

function reward_item_image($imagePath, $name, $class)
{
    $imagePath = trim((string) $imagePath);
    if ($imagePath !== '' && preg_match('/\.(jpg|jpeg|png|gif)(\?.*)?$/i', $imagePath)) {
        return '<span class="' . h($class) . '"><img src="' . h($imagePath) . '" alt="' . h($name) . '"></span>';
    }

    $label = strtoupper(substr(trim((string) $name), 0, 1));
    if ($label === '') {
        $label = 'R';
    }

    return '<span class="' . h($class) . '">' . h($label) . '</span>';
}

function normalize_notification_type($type, $title, $message)
{
    $allowed = array('general', 'course', 'quest', 'result', 'badge', 'announcement', 'reflection', 'chat', 'store', 'system');
    $type = strtolower(trim((string) $type));

    if (in_array($type, $allowed, true)) {
        return $type;
    }

    $text = strtolower((string) $title . ' ' . (string) $message);

    if (strpos($text, 'announcement') !== false) {
        return 'announcement';
    }

    if (strpos($text, 'redeem') !== false || strpos($text, 'redemption') !== false || strpos($text, 'store') !== false || strpos($text, 'item') !== false) {
        return 'store';
    }

    if (strpos($text, 'chat') !== false || strpos($text, 'message') !== false) {
        return 'chat';
    }

    if (strpos($text, 'badge') !== false) {
        return 'badge';
    }

    if (strpos($text, 'reflection') !== false || strpos($text, 'feedback') !== false) {
        return 'reflection';
    }

    if (strpos($text, 'result') !== false || strpos($text, 'score') !== false || strpos($text, 'xp') !== false) {
        return 'result';
    }

    if (strpos($text, 'quest') !== false || strpos($text, 'evidence') !== false) {
        return 'quest';
    }

    if (strpos($text, 'course') !== false || strpos($text, 'enrol') !== false || strpos($text, 'enroll') !== false) {
        return 'course';
    }

    return 'general';
}

function notification_type_label($type)
{
    $labels = array(
        'general' => 'General',
        'course' => 'Course',
        'quest' => 'Quest',
        'result' => 'Result',
        'badge' => 'Badge',
        'announcement' => 'Announcement',
        'reflection' => 'Reflection',
        'chat' => 'Chat',
        'store' => 'XP Store',
        'system' => 'System',
    );

    return isset($labels[$type]) ? $labels[$type] : 'General';
}

function notification_type_options()
{
    return array(
        'all' => 'All',
        'course' => 'Courses',
        'quest' => 'Quests',
        'result' => 'Results',
        'badge' => 'Badges',
        'announcement' => 'Announcements',
        'reflection' => 'Reflections',
        'chat' => 'Chat',
        'store' => 'XP Store',
        'system' => 'System',
        'general' => 'General',
    );
}

function create_notification($userId, $courseId, $title, $message, $type = 'general')
{
    $type = normalize_notification_type($type, $title, $message);

    db_exec(
        "INSERT INTO notifications (notificationId, userId, courseId, notificationType, title, message, isRead, createdAt)
         VALUES ('" . db_escape(uuid_v4()) . "', '" . db_escape($userId) . "', '" . db_escape($courseId) . "', '" . db_escape($type) . "', '" . db_escape($title) . "', '" . db_escape($message) . "', 0, NOW())"
    );

    send_email_notification($userId, $title, $message);
}

function due_quest_reminder_exists($userId, $courseId, $title)
{
    $count = db_value(
        "SELECT COUNT(*) FROM notifications
         WHERE userId = '" . db_escape($userId) . "'
           AND courseId = '" . db_escape($courseId) . "'
           AND notificationType = 'quest'
           AND title = '" . db_escape($title) . "'",
        0
    );

    return (int) $count > 0;
}

function send_due_quest_reminder_to_student($course, $quest, $studentId)
{
    $title = 'Upcoming deadline: ' . $quest['title'];

    if (due_quest_reminder_exists($studentId, $course['courseId'], $title)) {
        return false;
    }

    $completed = db_value(
        "SELECT COUNT(*) FROM results
         WHERE questId = '" . db_escape($quest['questId']) . "'
           AND studentId = '" . db_escape($studentId) . "'
           AND completionStatus = 'completed'",
        0
    );

    if ((int) $completed > 0) {
        return false;
    }

    $deadlineText = $quest['deadline'] ? date('Y-m-d H:i', strtotime($quest['deadline'])) : 'soon';
    $message = $course['title'] . ' quest "' . $quest['title'] . '" is due on ' . $deadlineText . '.';
    create_notification($studentId, $course['courseId'], $title, $message, 'quest');

    return true;
}

function due_quests_for_course($courseId, $days)
{
    return db_all(
        "SELECT * FROM quests
         WHERE courseId = '" . db_escape($courseId) . "'
           AND status = 'active'
           AND deadline IS NOT NULL
           AND deadline >= NOW()
           AND deadline <= DATE_ADD(NOW(), INTERVAL " . (int) $days . " DAY)
         ORDER BY deadline ASC"
    );
}

function send_due_quest_reminders_for_course($courseId)
{
    $days = course_deadline_reminder_days($courseId);
    if ($days <= 0) {
        return 0;
    }

    $course = get_course($courseId);
    if (!$course) {
        return 0;
    }

    $sent = 0;
    $quests = due_quests_for_course($courseId, $days);
    $students = enrolled_students($courseId);

    foreach ($quests as $quest) {
        foreach ($students as $student) {
            if (send_due_quest_reminder_to_student($course, $quest, $student['userId'])) {
                $sent++;
            }
        }
    }

    return $sent;
}

function send_due_quest_reminders_for_user($userId)
{
    $sent = 0;
    $courses = student_courses($userId);

    foreach ($courses as $course) {
        $days = course_deadline_reminder_days($course['courseId']);
        if ($days <= 0) {
            continue;
        }

        $quests = due_quests_for_course($course['courseId'], $days);
        foreach ($quests as $quest) {
            if (send_due_quest_reminder_to_student($course, $quest, $userId)) {
                $sent++;
            }
        }
    }

    return $sent;
}

function unread_notification_count($userId)
{
    return (int) db_value(
        "SELECT COUNT(*) FROM notifications WHERE userId = '" . db_escape($userId) . "' AND isRead = 0",
        0
    );
}

function course_counts($courseId)
{
    return array(
        'students' => (int) db_value("SELECT COUNT(*) FROM enrollments WHERE courseId = '" . db_escape($courseId) . "' AND roleInCourse = 'student'", 0),
        'quests' => (int) db_value("SELECT COUNT(*) FROM quests WHERE courseId = '" . db_escape($courseId) . "' AND status = 'active'", 0),
        'badges' => (int) db_value("SELECT COUNT(*) FROM badges WHERE courseId = '" . db_escape($courseId) . "'", 0),
        'announcements' => (int) db_value("SELECT COUNT(*) FROM announcements WHERE courseId = '" . db_escape($courseId) . "'", 0),
    );
}

function course_average_xp($courseId)
{
    return (int) db_value(
        "SELECT COALESCE(AVG(totalXP), 0) FROM xp_records WHERE courseId = '" . db_escape($courseId) . "'",
        0
    );
}

function course_analytics_rows($courseId)
{
    return db_all(
        "SELECT u.userId AS studentId, u.name, u.email, COALESCE(xr.totalXP, 0) AS totalXP, COALESCE(xr.level, 1) AS level,
            (SELECT COUNT(*) FROM student_badges sb WHERE sb.courseId = e.courseId AND sb.studentId = u.userId) AS badgeCount,
            (SELECT COUNT(*) FROM results r INNER JOIN quests q ON q.questId = r.questId WHERE q.courseId = e.courseId AND r.studentId = u.userId AND r.completionStatus = 'completed') AS completedQuests,
            (SELECT COUNT(*) FROM quests q2 WHERE q2.courseId = e.courseId AND q2.status = 'active') AS totalQuests
         FROM enrollments e
         INNER JOIN users u ON u.userId = e.userId
         LEFT JOIN xp_records xr ON xr.studentId = u.userId AND xr.courseId = e.courseId
         WHERE e.courseId = '" . db_escape($courseId) . "' AND e.roleInCourse = 'student'
         ORDER BY COALESCE(xr.totalXP, 0) DESC, u.name ASC"
    );
}
