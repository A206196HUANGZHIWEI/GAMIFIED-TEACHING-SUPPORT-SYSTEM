<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$course = require_course_access($courseId);
$user = current_user();
$canManage = can_manage_course($course);
$errors = array();
$xpRules = course_xp_rules($courseId);
$questTypeLabels = quest_type_labels();
$externalToolLabels = external_tool_labels();
$externalToolLinks = course_external_tool_links($courseId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if (!$errors && $action === 'create_quest') {
        if (!$canManage) {
            $errors[] = 'Only course teachers can create quests.';
        }

        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $link = isset($_POST['link']) ? trim($_POST['link']) : '';
        $xpRaw = isset($_POST['XPValue']) ? trim($_POST['XPValue']) : '';
        $deadline = isset($_POST['deadline']) ? trim($_POST['deadline']) : '';
        $questType = isset($_POST['questType']) ? $_POST['questType'] : 'individual';
        $isCompulsory = isset($_POST['isCompulsory']) ? 1 : 0;
        $xpValue = $xpRaw === '' ? course_default_xp_for_type($courseId, $questType) : (int) $xpRaw;

        if ($title === '' || strlen($title) > 150) {
            $errors[] = 'Quest title is required and must be 150 characters or fewer.';
        }

        if ($xpValue < 0 || $xpValue > 10000) {
            $errors[] = 'XP value must be between 0 and 10000.';
        }

        if (!in_array($questType, array('individual', 'group', 'external', 'offline'), true)) {
            $errors[] = 'Invalid quest type.';
        }

        $deadlineSql = 'NULL';
        if ($deadline !== '') {
            $deadlineSql = "'" . db_escape($deadline . ' 23:59:59') . "'";
        }

        if (!$errors) {
            db_exec(
                "INSERT INTO quests (questId, courseId, title, description, link, XPValue, deadline, questType, isCompulsory, status, createdAt, updatedAt)
                 VALUES ('" . db_escape(uuid_v4()) . "', '" . db_escape($courseId) . "', '" . db_escape($title) . "', '" . db_escape($description) . "', '" . db_escape($link) . "', " . (int) $xpValue . ", " . $deadlineSql . ", '" . db_escape($questType) . "', " . (int) $isCompulsory . ", 'active', NOW(), NOW())"
            );

            $students = enrolled_students($courseId);
            foreach ($students as $student) {
                create_notification($student['userId'], $courseId, 'New quest: ' . $title, 'A new quest has been added to ' . $course['title'] . '.', 'quest');
            }

            flash('success', 'Quest created.');
            redirect_to('quests.php?courseId=' . urlencode($courseId));
        }
    }

    if (!$errors && $action === 'submit_evidence') {
        if ($user['role'] !== 'student') {
            $errors[] = 'Only students can submit quest evidence.';
        }

        $questId = isset($_POST['questId']) ? $_POST['questId'] : '';
        $evidenceLink = isset($_POST['evidenceLink']) ? trim($_POST['evidenceLink']) : '';
        $reflectionText = isset($_POST['reflectionText']) ? trim($_POST['reflectionText']) : '';
        $evidenceFile = '';

        $quest = db_one(
            "SELECT * FROM quests
             WHERE questId = '" . db_escape($questId) . "'
               AND courseId = '" . db_escape($courseId) . "'
               AND status = 'active'
             LIMIT 1"
        );

        if (!$quest) {
            $errors[] = 'Quest was not found.';
        }

        if (!$errors) {
            $evidenceFile = save_uploaded_evidence('evidenceFile', $courseId, $questId, $user['userId'], $errors);
        }

        if (!$errors) {
            $existingResultId = db_value(
                "SELECT resultId FROM results
                 WHERE questId = '" . db_escape($questId) . "'
                   AND studentId = '" . db_escape($user['userId']) . "'
                 LIMIT 1",
                ''
            );

            if ($existingResultId) {
                $fileSql = $evidenceFile !== '' ? ", evidenceFile = '" . db_escape($evidenceFile) . "'" : '';
                db_exec(
                    "UPDATE results
                     SET evidenceLink = '" . db_escape($evidenceLink) . "'" . $fileSql . ", submissionTime = NOW()
                     WHERE resultId = '" . db_escape($existingResultId) . "'"
                );
            } else {
                db_exec(
                    "INSERT INTO results (resultId, questId, studentId, score, completionStatus, awardedXP, evidenceLink, evidenceFile, rubricRating, teacherComment, submissionTime)
                     VALUES ('" . db_escape(uuid_v4()) . "', '" . db_escape($questId) . "', '" . db_escape($user['userId']) . "', 0, 'pending', 0, '" . db_escape($evidenceLink) . "', '" . db_escape($evidenceFile) . "', NULL, '', NOW())"
                );
            }

            if ($reflectionText !== '') {
                db_exec(
                    "INSERT INTO reflections (reflectionId, questId, studentId, text, teacherComment, timestamp)
                     VALUES ('" . db_escape(uuid_v4()) . "', '" . db_escape($questId) . "', '" . db_escape($user['userId']) . "', '" . db_escape($reflectionText) . "', '', NOW())"
                );
            }

            create_notification($course['teacherId'], $courseId, 'Quest evidence submitted', $user['name'] . ' submitted evidence for ' . $quest['title'] . '.', 'quest');
            flash('success', 'Evidence submitted. Your teacher can now review it.');
            redirect_to('quests.php?courseId=' . urlencode($courseId));
        }
    }
}

$quests = db_all(
    "SELECT q.*,
        (SELECT COUNT(*) FROM results r WHERE r.questId = q.questId AND r.completionStatus = 'completed') AS completedCount
     FROM quests q
     WHERE q.courseId = '" . db_escape($courseId) . "' AND q.status = 'active'
     ORDER BY q.deadline ASC, q.createdAt DESC"
);

page_header('Quests');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Quest Management</p>
            <h1><?php echo h($course['title']); ?> Quests</h1>
            <p class="muted">Create interactive tasks, external activity links, offline activities, and XP rewards.</p>
        </div>
        <a class="button secondary" href="course.php?courseId=<?php echo h($courseId); ?>">Back to course</a>
    </div>
    <?php render_errors($errors); ?>
</section>

<?php if ($canManage): ?>
<section class="panel mt">
    <h2>Create quest</h2>
    <form method="post" action="quests.php?courseId=<?php echo h($courseId); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="create_quest">

        <div class="xp-rule-strip">
            <?php foreach ($questTypeLabels as $type => $label): ?>
                <span><?php echo h($label); ?>: <strong><?php echo h(isset($xpRules[$type]) ? $xpRules[$type] : 0); ?> XP</strong></span>
            <?php endforeach; ?>
            <a href="course_settings.php?courseId=<?php echo h($courseId); ?>">Edit rules</a>
        </div>

        <?php
        $hasExternalToolLinks = false;
        foreach ($externalToolLinks as $toolUrl) {
            if ($toolUrl !== '') {
                $hasExternalToolLinks = true;
                break;
            }
        }
        ?>
        <?php if ($hasExternalToolLinks): ?>
            <div class="tool-shortcuts">
                <?php foreach ($externalToolLabels as $key => $label): ?>
                    <?php if (isset($externalToolLinks[$key]) && $externalToolLinks[$key] !== ''): ?>
                        <a href="<?php echo h($externalToolLinks[$key]); ?>" target="_blank"><?php echo h($label); ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="grid two-col tight">
            <div>
                <label for="title">Quest title</label>
                <input id="title" name="title" type="text" maxlength="150" required>
            </div>
            <div>
                <label for="XPValue">XP value</label>
                <input id="XPValue" name="XPValue" type="number" min="0" max="10000" placeholder="Use course default">
            </div>
        </div>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4"></textarea>

        <div class="grid three-col tight">
            <div>
                <label for="link">External link</label>
                <input id="link" name="link" type="text" maxlength="255" placeholder="Kahoot / Quizizz / Google Form URL">
            </div>
            <div>
                <label for="deadline">Deadline</label>
                <input id="deadline" name="deadline" type="date">
            </div>
            <div>
                <label for="questType">Type</label>
                <select id="questType" name="questType">
                    <?php foreach ($questTypeLabels as $type => $label): ?>
                        <option value="<?php echo h($type); ?>"><?php echo h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <label class="check">
            <input type="checkbox" name="isCompulsory" value="1" checked>
            Compulsory quest
        </label>

        <div class="form-footer">
            <button type="submit">Create quest</button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="panel mt">
    <h2>Quest list</h2>
    <?php if (!$quests): ?>
        <p class="muted">No quests yet.</p>
    <?php else: ?>
        <div class="quest-grid">
            <?php foreach ($quests as $quest): ?>
                <?php
                $studentResult = null;
                if ($user['role'] === 'student') {
                    $studentResult = db_one(
                        "SELECT * FROM results
                         WHERE questId = '" . db_escape($quest['questId']) . "'
                           AND studentId = '" . db_escape($user['userId']) . "'
                         LIMIT 1"
                    );
                }
                ?>
                <article class="item-card">
                    <div class="section-head">
                        <div>
                            <h3><?php echo h($quest['title']); ?></h3>
                            <p class="muted"><?php echo h($quest['description']); ?></p>
                        </div>
                        <?php echo status_badge($quest['XPValue'] . ' XP'); ?>
                    </div>
                    <p class="meta">
                        <?php echo h($quest['questType']); ?> |
                        <?php echo $quest['isCompulsory'] ? 'Compulsory' : 'Optional'; ?> |
                        Deadline: <?php echo h($quest['deadline'] ? $quest['deadline'] : 'None'); ?>
                    </p>
                    <?php if ($quest['link']): ?>
                        <p><a href="<?php echo h($quest['link']); ?>" target="_blank">Open <?php echo h(external_tool_name_for_link($quest['link'])); ?></a></p>
                    <?php endif; ?>

                    <?php if ($canManage): ?>
                        <p class="muted"><?php echo h($quest['completedCount']); ?> completed submissions.</p>
                        <p><a href="quest_edit.php?courseId=<?php echo h($courseId); ?>&questId=<?php echo h($quest['questId']); ?>">Edit quest</a></p>
                    <?php elseif ($user['role'] === 'student'): ?>
                        <?php if ($studentResult): ?>
                            <p><?php echo status_badge($studentResult['completionStatus']); ?> <?php echo h($studentResult['awardedXP']); ?> XP awarded</p>
                            <?php if ($studentResult['teacherComment']): ?>
                                <p class="muted">Teacher feedback: <?php echo h($studentResult['teacherComment']); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <form method="post" action="quests.php?courseId=<?php echo h($courseId); ?>" enctype="multipart/form-data">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="submit_evidence">
                            <input type="hidden" name="questId" value="<?php echo h($quest['questId']); ?>">

                            <label for="evidence-<?php echo h($quest['questId']); ?>">Evidence link</label>
                            <input id="evidence-<?php echo h($quest['questId']); ?>" name="evidenceLink" type="text" maxlength="255" value="<?php echo h($studentResult ? $studentResult['evidenceLink'] : ''); ?>">

                            <?php if ($studentResult && isset($studentResult['evidenceFile']) && $studentResult['evidenceFile']): ?>
                                <p class="muted">Uploaded file: <a href="<?php echo h($studentResult['evidenceFile']); ?>" target="_blank">Open evidence file</a></p>
                            <?php endif; ?>

                            <label for="file-<?php echo h($quest['questId']); ?>">Evidence file</label>
                            <input id="file-<?php echo h($quest['questId']); ?>" name="evidenceFile" type="file">

                            <label for="reflection-<?php echo h($quest['questId']); ?>">Reflection</label>
                            <textarea id="reflection-<?php echo h($quest['questId']); ?>" name="reflectionText" rows="3"></textarea>

                            <div class="form-footer">
                                <button type="submit">Submit evidence</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
