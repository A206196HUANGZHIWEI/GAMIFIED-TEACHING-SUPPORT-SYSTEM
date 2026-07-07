<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$course = require_course_access($courseId);
$user = current_user();
$canManage = can_manage_course($course);
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    if (!$canManage) {
        $errors[] = 'Only course teachers can manage badges.';
    }

    $action = isset($_POST['action']) ? $_POST['action'] : 'create_badge';

    if (!$errors && $action === 'award_badge') {
        $badgeId = isset($_POST['badgeId']) ? $_POST['badgeId'] : '';
        $studentId = isset($_POST['studentId']) ? $_POST['studentId'] : '';

        $badge = db_one(
            "SELECT * FROM badges
             WHERE badgeId = '" . db_escape($badgeId) . "'
               AND courseId = '" . db_escape($courseId) . "'
             LIMIT 1"
        );
        $student = db_one(
            "SELECT u.*
             FROM users u
             INNER JOIN enrollments e ON e.userId = u.userId
             WHERE u.userId = '" . db_escape($studentId) . "'
               AND e.courseId = '" . db_escape($courseId) . "'
               AND e.roleInCourse = 'student'
             LIMIT 1"
        );

        if (!$badge || !$student) {
            $errors[] = 'Please select a valid badge and student.';
        }

        if (!$errors) {
            $exists = db_value(
                "SELECT COUNT(*) FROM student_badges
                 WHERE badgeId = '" . db_escape($badgeId) . "'
                   AND studentId = '" . db_escape($studentId) . "'",
                0
            );

            if ((int) $exists === 0) {
                db_exec(
                    "INSERT INTO student_badges (studentBadgeId, badgeId, studentId, courseId, awardedAt)
                     VALUES ('" . db_escape(uuid_v4()) . "', '" . db_escape($badgeId) . "', '" . db_escape($studentId) . "', '" . db_escape($courseId) . "', NOW())"
                );
                create_notification($studentId, $courseId, 'Badge awarded: ' . $badge['name'], 'You earned a badge in ' . $course['title'] . '.', 'badge');
            }

            flash('success', 'Badge awarded.');
            redirect_to('badges.php?courseId=' . urlencode($courseId));
        }
    }

    if (!$errors && $action === 'create_badge') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $iconPath = isset($_POST['iconPath']) ? trim($_POST['iconPath']) : '';
        $criteriaType = isset($_POST['criteriaType']) ? $_POST['criteriaType'] : 'xp';
        $threshold = isset($_POST['threshold']) ? (int) $_POST['threshold'] : 0;
        $badgeId = uuid_v4();

        if ($name === '' || strlen($name) > 100) {
            $errors[] = 'Badge name is required and must be 100 characters or fewer.';
        }

        if (!in_array($criteriaType, array('xp', 'manual'), true)) {
            $errors[] = 'Invalid badge criteria.';
        }

        if ($threshold < 0 || $threshold > 100000) {
            $errors[] = 'Threshold must be between 0 and 100000.';
        }

        if (!$errors) {
            $uploadedIcon = save_uploaded_badge_icon('badgeImage', $courseId, $badgeId, $errors);
            if (!$errors && $uploadedIcon !== '') {
                $iconPath = $uploadedIcon;
            }
        }

        if (!$errors) {
            db_exec(
                "INSERT INTO badges (badgeId, courseId, name, description, iconPath, criteriaType, threshold, createdAt, updatedAt)
                 VALUES ('" . db_escape($badgeId) . "', '" . db_escape($courseId) . "', '" . db_escape($name) . "', '" . db_escape($description) . "', '" . db_escape($iconPath) . "', '" . db_escape($criteriaType) . "', " . (int) $threshold . ", NOW(), NOW())"
            );

            if ($criteriaType === 'xp') {
                $students = enrolled_students($courseId);
                foreach ($students as $student) {
                    recalculate_student_progress($courseId, $student['userId']);
                }
            }

            flash('success', 'Badge created.');
            redirect_to('badges.php?courseId=' . urlencode($courseId));
        }
    }
}

$badges = db_all(
    "SELECT b.*,
        (SELECT COUNT(*) FROM student_badges sb WHERE sb.badgeId = b.badgeId) AS awardedCount
     FROM badges b
     WHERE b.courseId = '" . db_escape($courseId) . "'
     ORDER BY b.threshold ASC, b.createdAt DESC"
);
$students = $canManage ? enrolled_students($courseId) : array();

$earnedMap = array();
if ($user['role'] === 'student') {
    $earnedRows = db_all(
        "SELECT badgeId FROM student_badges
         WHERE courseId = '" . db_escape($courseId) . "'
           AND studentId = '" . db_escape($user['userId']) . "'"
    );
    foreach ($earnedRows as $row) {
        $earnedMap[$row['badgeId']] = true;
    }
}

page_header('Badges');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Reward System</p>
            <h1><?php echo h($course['title']); ?> Badges</h1>
            <p class="muted">Configure achievement badges and XP unlock thresholds.</p>
        </div>
        <a class="button secondary" href="course.php?courseId=<?php echo h($courseId); ?>">Back to course</a>
    </div>
    <?php render_errors($errors); ?>
</section>

<?php if ($canManage): ?>
<section class="panel mt">
    <h2>Create badge</h2>
    <form method="post" action="badges.php?courseId=<?php echo h($courseId); ?>" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="create_badge">
        <div class="grid two-col tight">
            <div>
                <label for="name">Badge name</label>
                <input id="name" name="name" type="text" maxlength="100" required>
            </div>
            <div>
                <label for="threshold">XP threshold</label>
                <input id="threshold" name="threshold" type="number" min="0" max="100000" value="100">
            </div>
        </div>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4"></textarea>

        <div class="grid two-col tight">
            <div>
                <label for="criteriaType">Criteria</label>
                <select id="criteriaType" name="criteriaType">
                    <option value="xp">XP threshold</option>
                    <option value="manual">Manual award later</option>
                </select>
            </div>
            <div>
                <label for="iconPath">Icon label or image URL</label>
                <input id="iconPath" name="iconPath" type="text" maxlength="255" placeholder="Star / Trophy / icon URL">
            </div>
        </div>

        <label for="badgeImage">Badge image</label>
        <input id="badgeImage" name="badgeImage" type="file" accept=".jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif">
        <p class="muted">Optional JPG, PNG, or GIF. Maximum 2 MB. Uploaded image replaces the label or URL above.</p>

        <div class="form-footer">
            <button type="submit">Create badge</button>
        </div>
    </form>
</section>
<?php endif; ?>

<?php if ($canManage): ?>
<section class="panel mt">
    <h2>Manual award</h2>
    <?php if (!$badges || !$students): ?>
        <p class="muted">Create badges and enrol students before using manual awards.</p>
    <?php else: ?>
        <form method="post" action="badges.php?courseId=<?php echo h($courseId); ?>" class="inline-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="award_badge">
            <select name="badgeId">
                <?php foreach ($badges as $badge): ?>
                    <option value="<?php echo h($badge['badgeId']); ?>"><?php echo h($badge['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="studentId">
                <?php foreach ($students as $student): ?>
                    <option value="<?php echo h($student['userId']); ?>"><?php echo h($student['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Award badge</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel mt">
    <h2>Badge list</h2>
    <?php if (!$badges): ?>
        <p class="muted">No badges yet.</p>
    <?php else: ?>
        <div class="quest-grid">
            <?php foreach ($badges as $badge): ?>
                <article class="item-card">
                    <div class="section-head">
                        <div class="badge-card-head">
                            <?php echo layout_badge_icon($badge['iconPath'], $badge['name'], 'badge-image'); ?>
                            <div>
                            <h3><?php echo h($badge['name']); ?></h3>
                            <p class="muted"><?php echo h($badge['description']); ?></p>
                            </div>
                        </div>
                        <?php if ($user['role'] === 'student'): ?>
                            <?php echo status_badge(isset($earnedMap[$badge['badgeId']]) ? 'Earned' : 'Locked'); ?>
                        <?php else: ?>
                            <?php echo status_badge($badge['awardedCount'] . ' earned'); ?>
                        <?php endif; ?>
                    </div>
                    <p class="meta">
                        Criteria: <?php echo h($badge['criteriaType']); ?> |
                        Threshold: <?php echo h($badge['threshold']); ?> XP
                    </p>
                    <?php if ($canManage): ?>
                        <p><a href="badge_edit.php?courseId=<?php echo h($courseId); ?>&badgeId=<?php echo h($badge['badgeId']); ?>">Edit badge</a></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
