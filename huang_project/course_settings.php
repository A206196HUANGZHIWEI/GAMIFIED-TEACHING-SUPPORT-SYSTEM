<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$course = require_course_manager($courseId);
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    $action = isset($_POST['action']) ? $_POST['action'] : 'update';

    if (!$errors && $action === 'regenerate_code') {
        $code = generate_enrollment_code();
        db_exec(
            "UPDATE courses
             SET enrollmentCode = '" . db_escape($code) . "', updatedAt = NOW()
             WHERE courseId = '" . db_escape($courseId) . "'"
        );
        flash('success', 'New enrolment code generated: ' . $code);
        redirect_to('course_settings.php?courseId=' . urlencode($courseId));
    }

    if (!$errors && $action === 'update') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $academicLevel = isset($_POST['academicLevel']) ? trim($_POST['academicLevel']) : '';
        $status = isset($_POST['status']) ? $_POST['status'] : 'active';

        if ($title === '' || strlen($title) > 150) {
            $errors[] = 'Course title is required and must be 150 characters or fewer.';
        }

        if (!in_array($status, array('active', 'inactive', 'archived'), true)) {
            $errors[] = 'Invalid course status.';
        }

        if (!$errors) {
            db_exec(
                "UPDATE courses
                 SET title = '" . db_escape($title) . "',
                     description = '" . db_escape($description) . "',
                     academicLevel = '" . db_escape($academicLevel) . "',
                     status = '" . db_escape($status) . "',
                     updatedAt = NOW()
                 WHERE courseId = '" . db_escape($courseId) . "'"
            );
            flash('success', 'Course settings saved.');
            redirect_to('course_settings.php?courseId=' . urlencode($courseId));
        }
    }

    if (!$errors && $action === 'gamification') {
        $xpRules = array(
            'individual' => isset($_POST['xp_individual']) ? (int) $_POST['xp_individual'] : 10,
            'group' => isset($_POST['xp_group']) ? (int) $_POST['xp_group'] : 15,
            'external' => isset($_POST['xp_external']) ? (int) $_POST['xp_external'] : 10,
            'offline' => isset($_POST['xp_offline']) ? (int) $_POST['xp_offline'] : 10,
        );
        $leaderboardSort = isset($_POST['leaderboard_sort']) ? $_POST['leaderboard_sort'] : 'xp';
        $leaderboardAnonymity = isset($_POST['leaderboard_anonymity']) ? $_POST['leaderboard_anonymity'] : 'none';
        $deadlineReminderDays = isset($_POST['deadline_reminder_days']) ? (int) $_POST['deadline_reminder_days'] : 3;
        $externalToolLinks = array();

        foreach (external_tool_labels() as $key => $label) {
            $field = 'tool_' . $key . '_url';
            $externalToolLinks[$key] = isset($_POST[$field]) ? trim($_POST[$field]) : '';
        }

        foreach ($xpRules as $xpValue) {
            if ($xpValue < 0 || $xpValue > 10000) {
                $errors[] = 'Default XP values must be between 0 and 10000.';
                break;
            }
        }

        if (!array_key_exists($leaderboardSort, leaderboard_sort_options())) {
            $errors[] = 'Invalid leaderboard ranking option.';
        }

        if (!array_key_exists($leaderboardAnonymity, leaderboard_anonymity_options())) {
            $errors[] = 'Invalid leaderboard privacy option.';
        }

        if ($deadlineReminderDays < 0 || $deadlineReminderDays > 30) {
            $errors[] = 'Deadline reminder days must be between 0 and 30.';
        }

        foreach ($externalToolLinks as $url) {
            if ($url !== '' && (strlen($url) > 255 || !filter_var($url, FILTER_VALIDATE_URL))) {
                $errors[] = 'External tool links must be valid URLs and 255 characters or fewer.';
                break;
            }
        }

        if (!$errors) {
            save_course_setting($courseId, 'xp_individual', $xpRules['individual']);
            save_course_setting($courseId, 'xp_group', $xpRules['group']);
            save_course_setting($courseId, 'xp_external', $xpRules['external']);
            save_course_setting($courseId, 'xp_offline', $xpRules['offline']);
            save_course_setting($courseId, 'leaderboard_sort', $leaderboardSort);
            save_course_setting($courseId, 'leaderboard_anonymity', $leaderboardAnonymity);
            save_course_setting($courseId, 'deadline_reminder_days', $deadlineReminderDays);
            foreach ($externalToolLinks as $key => $url) {
                save_course_setting($courseId, 'tool_' . $key . '_url', $url);
            }

            flash('success', 'Gamification rules saved.');
            redirect_to('course_settings.php?courseId=' . urlencode($courseId));
        }
    }
}

$course = get_course($courseId);
$xpRules = course_xp_rules($courseId);
$questTypeLabels = quest_type_labels();
$leaderboardSettings = course_leaderboard_settings($courseId);
$leaderboardSortOptions = leaderboard_sort_options();
$leaderboardAnonymityOptions = leaderboard_anonymity_options();
$deadlineReminderDays = course_deadline_reminder_days($courseId);
$externalToolLabels = external_tool_labels();
$externalToolLinks = course_external_tool_links($courseId);

page_header('Course Settings');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Course Settings</p>
            <h1><?php echo h($course['title']); ?></h1>
            <p class="muted">Edit course details, archive a course, or regenerate the enrolment code.</p>
        </div>
        <a class="button secondary" href="course.php?courseId=<?php echo h($courseId); ?>">Back to course</a>
    </div>
    <?php render_errors($errors); ?>
</section>

<section class="grid two-col mt">
    <div class="panel">
        <h2>Edit details</h2>
        <form method="post" action="course_settings.php?courseId=<?php echo h($courseId); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update">

            <label for="title">Course title</label>
            <input id="title" name="title" type="text" maxlength="150" value="<?php echo h($course['title']); ?>" required>

            <label for="academicLevel">Academic level</label>
            <input id="academicLevel" name="academicLevel" type="text" maxlength="100" value="<?php echo h($course['academicLevel']); ?>">

            <label for="description">Description</label>
            <textarea id="description" name="description" rows="5"><?php echo h($course['description']); ?></textarea>

            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="active" <?php echo $course['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $course['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="archived" <?php echo $course['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
            </select>

            <div class="form-footer">
                <button type="submit">Save settings</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>Enrolment code</h2>
        <div class="code-box wide">
            <span>Current code</span>
            <strong><?php echo h($course['enrollmentCode']); ?></strong>
        </div>
        <p class="muted mt">Regenerating the code prevents new students from joining with the old code. Existing students remain enrolled.</p>
        <form method="post" action="course_settings.php?courseId=<?php echo h($courseId); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="regenerate_code">
            <div class="form-footer">
                <button type="submit">Regenerate code</button>
            </div>
        </form>
    </div>
</section>

<section class="panel mt">
    <h2>Gamification rules</h2>
    <form method="post" action="course_settings.php?courseId=<?php echo h($courseId); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="gamification">

        <div class="grid four-col tight">
            <?php foreach ($questTypeLabels as $type => $label): ?>
                <div>
                    <label for="xp-<?php echo h($type); ?>"><?php echo h($label); ?> XP</label>
                    <input id="xp-<?php echo h($type); ?>" name="xp_<?php echo h($type); ?>" type="number" min="0" max="10000" value="<?php echo h(isset($xpRules[$type]) ? $xpRules[$type] : 0); ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="grid three-col tight">
            <div>
                <label for="leaderboard_sort">Leaderboard ranking</label>
                <select id="leaderboard_sort" name="leaderboard_sort">
                    <?php foreach ($leaderboardSortOptions as $value => $label): ?>
                        <option value="<?php echo h($value); ?>" <?php echo $leaderboardSettings['sort'] === $value ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="leaderboard_anonymity">Leaderboard privacy</label>
                <select id="leaderboard_anonymity" name="leaderboard_anonymity">
                    <?php foreach ($leaderboardAnonymityOptions as $value => $label): ?>
                        <option value="<?php echo h($value); ?>" <?php echo $leaderboardSettings['anonymity'] === $value ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="deadline_reminder_days">Deadline reminder days</label>
                <input id="deadline_reminder_days" name="deadline_reminder_days" type="number" min="0" max="30" value="<?php echo h($deadlineReminderDays); ?>">
            </div>
        </div>

        <p class="muted mt">Leave the reminder value as 0 to disable automatic in-system deadline reminders for this course.</p>

        <h3 class="form-subhead">External tool shortcuts</h3>
        <div class="grid two-col tight">
            <?php foreach ($externalToolLabels as $key => $label): ?>
                <div>
                    <label for="tool-<?php echo h($key); ?>"><?php echo h($label); ?> URL</label>
                    <input id="tool-<?php echo h($key); ?>" name="tool_<?php echo h($key); ?>_url" type="text" maxlength="255" value="<?php echo h(isset($externalToolLinks[$key]) ? $externalToolLinks[$key] : ''); ?>" placeholder="https://">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="form-footer">
            <button type="submit">Save gamification rules</button>
        </div>
    </form>
</section>
<?php page_footer(); ?>
