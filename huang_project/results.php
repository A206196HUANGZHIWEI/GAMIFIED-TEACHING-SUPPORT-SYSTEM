<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$course = require_course_manager($courseId);
$errors = array();

$quests = db_all(
    "SELECT * FROM quests
     WHERE courseId = '" . db_escape($courseId) . "' AND status = 'active'
     ORDER BY createdAt DESC"
);

$selectedQuestId = isset($_GET['questId']) ? $_GET['questId'] : '';
if ($selectedQuestId === '' && $quests) {
    $selectedQuestId = $quests[0]['questId'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    $action = isset($_POST['action']) ? $_POST['action'] : 'save_results';
    $selectedQuestId = isset($_POST['questId']) ? $_POST['questId'] : '';
    $quest = db_one(
        "SELECT * FROM quests
         WHERE questId = '" . db_escape($selectedQuestId) . "'
           AND courseId = '" . db_escape($courseId) . "'
           AND status = 'active'
         LIMIT 1"
    );

    if (!$quest) {
        $errors[] = 'Quest was not found.';
    }

    if (!$errors && $action === 'import_csv') {
        if (!isset($_FILES['results_csv']) || $_FILES['results_csv']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please upload a valid CSV file.';
        }

        if (!$errors) {
            $handle = fopen($_FILES['results_csv']['tmp_name'], 'r');
            if (!$handle) {
                $errors[] = 'CSV file could not be opened.';
            }
        }

        if (!$errors) {
            $header = fgetcsv($handle);
            $imported = 0;
            $skipped = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $email = isset($row[0]) ? trim($row[0]) : '';
                $score = isset($row[1]) ? (int) $row[1] : 0;
                $status = isset($row[2]) ? strtolower(trim($row[2])) : 'completed';
                $xp = isset($row[3]) && trim($row[3]) !== '' ? (int) $row[3] : (int) $quest['XPValue'];
                $comment = isset($row[4]) ? trim($row[4]) : '';
                $rubricRating = isset($row[5]) && trim($row[5]) !== '' ? (int) $row[5] : 0;

                if (!in_array($status, array('pending', 'completed'), true)) {
                    $status = 'completed';
                }

                $student = db_one(
                    "SELECT u.*
                     FROM users u
                     INNER JOIN enrollments e ON e.userId = u.userId
                     WHERE u.email = '" . db_escape($email) . "'
                       AND e.courseId = '" . db_escape($courseId) . "'
                       AND e.roleInCourse = 'student'
                     LIMIT 1"
                );

                if (!$student) {
                    $skipped++;
                    continue;
                }

                if ($status === 'pending') {
                    $xp = 0;
                }

                $existing = db_value(
                    "SELECT resultId FROM results
                     WHERE questId = '" . db_escape($selectedQuestId) . "'
                       AND studentId = '" . db_escape($student['userId']) . "'
                     LIMIT 1",
                    ''
                );

                if ($existing) {
                    db_exec(
                        "UPDATE results
                         SET score = " . (int) $score . ",
                             completionStatus = '" . db_escape($status) . "',
                             awardedXP = " . (int) $xp . ",
                             rubricRating = " . ($rubricRating > 0 ? (int) $rubricRating : 'NULL') . ",
                             teacherComment = '" . db_escape($comment) . "',
                             submissionTime = NOW()
                         WHERE resultId = '" . db_escape($existing) . "'"
                    );
                } else {
                    db_exec(
                        "INSERT INTO results (resultId, questId, studentId, score, completionStatus, awardedXP, evidenceLink, evidenceFile, rubricRating, teacherComment, submissionTime)
                         VALUES ('" . db_escape(uuid_v4()) . "', '" . db_escape($selectedQuestId) . "', '" . db_escape($student['userId']) . "', " . (int) $score . ", '" . db_escape($status) . "', " . (int) $xp . ", '', '', " . ($rubricRating > 0 ? (int) $rubricRating : 'NULL') . ", '" . db_escape($comment) . "', NOW())"
                    );
                }

                recalculate_student_progress($courseId, $student['userId']);
                create_notification($student['userId'], $courseId, 'Quest result imported', 'Your result for ' . $quest['title'] . ' has been updated.', 'result');
                $imported++;
            }

            fclose($handle);
            flash('success', $imported . ' result(s) imported. ' . $skipped . ' row(s) skipped.');
            redirect_to('results.php?courseId=' . urlencode($courseId) . '&questId=' . urlencode($selectedQuestId));
        }
    }

    if (!$errors && $action === 'save_results') {
        $students = enrolled_students($courseId);
        $scores = isset($_POST['score']) && is_array($_POST['score']) ? $_POST['score'] : array();
        $statuses = isset($_POST['completionStatus']) && is_array($_POST['completionStatus']) ? $_POST['completionStatus'] : array();
        $awarded = isset($_POST['awardedXP']) && is_array($_POST['awardedXP']) ? $_POST['awardedXP'] : array();
        $ratings = isset($_POST['rubricRating']) && is_array($_POST['rubricRating']) ? $_POST['rubricRating'] : array();
        $comments = isset($_POST['teacherComment']) && is_array($_POST['teacherComment']) ? $_POST['teacherComment'] : array();

        foreach ($students as $student) {
            $studentId = $student['userId'];
            $status = isset($statuses[$studentId]) ? $statuses[$studentId] : 'pending';
            $score = isset($scores[$studentId]) ? (int) $scores[$studentId] : 0;
            $xp = isset($awarded[$studentId]) ? (int) $awarded[$studentId] : 0;
            $rubricRating = isset($ratings[$studentId]) ? (int) $ratings[$studentId] : 0;
            $comment = isset($comments[$studentId]) ? trim($comments[$studentId]) : '';

            if (!in_array($status, array('pending', 'completed'), true)) {
                $status = 'pending';
            }

            if ($status === 'completed' && $xp <= 0) {
                $xp = (int) $quest['XPValue'];
            }

            if ($status === 'pending') {
                $xp = 0;
            }

            $existing = db_value(
                "SELECT resultId FROM results
                 WHERE questId = '" . db_escape($selectedQuestId) . "'
                   AND studentId = '" . db_escape($studentId) . "'
                 LIMIT 1",
                ''
            );

            if ($existing) {
                db_exec(
                    "UPDATE results
                         SET score = " . (int) $score . ",
                             completionStatus = '" . db_escape($status) . "',
                             awardedXP = " . (int) $xp . ",
                             rubricRating = " . ($rubricRating > 0 ? (int) $rubricRating : 'NULL') . ",
                             teacherComment = '" . db_escape($comment) . "',
                             submissionTime = NOW()
                     WHERE resultId = '" . db_escape($existing) . "'"
                );
            } else {
                db_exec(
                    "INSERT INTO results (resultId, questId, studentId, score, completionStatus, awardedXP, evidenceLink, evidenceFile, rubricRating, teacherComment, submissionTime)
                     VALUES ('" . db_escape(uuid_v4()) . "', '" . db_escape($selectedQuestId) . "', '" . db_escape($studentId) . "', " . (int) $score . ", '" . db_escape($status) . "', " . (int) $xp . ", '', '', " . ($rubricRating > 0 ? (int) $rubricRating : 'NULL') . ", '" . db_escape($comment) . "', NOW())"
                );
            }

            recalculate_student_progress($courseId, $studentId);

            if ($status === 'completed') {
                create_notification($studentId, $courseId, 'Quest result updated', 'Your result for ' . $quest['title'] . ' has been updated.', 'result');
            }
        }

        flash('success', 'Results saved and XP records recalculated.');
        redirect_to('results.php?courseId=' . urlencode($courseId) . '&questId=' . urlencode($selectedQuestId));
    }
}

$selectedQuest = null;
if ($selectedQuestId !== '') {
    $selectedQuest = db_one(
        "SELECT * FROM quests
         WHERE questId = '" . db_escape($selectedQuestId) . "'
           AND courseId = '" . db_escape($courseId) . "'
         LIMIT 1"
    );
}

$students = enrolled_students($courseId);

if ($selectedQuest && isset($_GET['template']) && $_GET['template'] === 'csv') {
    $rows = array();
    foreach ($students as $student) {
        $rows[] = array(
            $student['email'],
            '',
            'completed',
            $selectedQuest['XPValue'],
            '',
            '',
        );
    }
    csv_download(
        'result-template-' . $selectedQuest['questId'] . '.csv',
        array('email', 'score', 'status', 'awardedXP', 'feedback', 'rubricRating'),
        $rows
    );
}

$resultMap = array();

if ($selectedQuest) {
    $rows = db_all(
        "SELECT * FROM results
         WHERE questId = '" . db_escape($selectedQuest['questId']) . "'"
    );

    foreach ($rows as $row) {
        $resultMap[$row['studentId']] = $row;
    }
}

page_header('Record Results');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Result Recording</p>
            <h1><?php echo h($course['title']); ?> Results</h1>
            <p class="muted">Record scores, mark quest completion, award XP, and send feedback.</p>
        </div>
        <a class="button secondary" href="course.php?courseId=<?php echo h($courseId); ?>">Back to course</a>
    </div>
    <?php render_errors($errors); ?>
</section>

<section class="panel mt">
    <h2>Select quest</h2>
    <?php if (!$quests): ?>
        <p class="muted">Create a quest before recording results.</p>
    <?php else: ?>
        <form method="get" action="results.php" class="inline-form">
            <input type="hidden" name="courseId" value="<?php echo h($courseId); ?>">
            <select name="questId">
                <?php foreach ($quests as $quest): ?>
                    <option value="<?php echo h($quest['questId']); ?>" <?php echo $quest['questId'] === $selectedQuestId ? 'selected' : ''; ?>>
                        <?php echo h($quest['title']); ?> (<?php echo h($quest['XPValue']); ?> XP)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Open</button>
        </form>
    <?php endif; ?>
</section>

<?php if ($selectedQuest): ?>
<section class="panel mt">
    <h2>Import CSV results</h2>
    <p class="muted">CSV columns: email, score, status, awardedXP, feedback, rubricRating. Status should be pending or completed.</p>
    <form method="post" action="results.php?courseId=<?php echo h($courseId); ?>" enctype="multipart/form-data" class="inline-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="import_csv">
        <input type="hidden" name="questId" value="<?php echo h($selectedQuest['questId']); ?>">
        <input name="results_csv" type="file" accept=".csv,text/csv" required>
        <button type="submit">Import CSV</button>
        <a class="button secondary" href="results.php?courseId=<?php echo h($courseId); ?>&questId=<?php echo h($selectedQuest['questId']); ?>&template=csv">Download template</a>
    </form>
</section>

<section class="panel mt">
    <h2><?php echo h($selectedQuest['title']); ?></h2>

    <?php if (!$students): ?>
        <p class="muted">No students have joined this course yet.</p>
    <?php else: ?>
        <form method="post" action="results.php?courseId=<?php echo h($courseId); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_results">
            <input type="hidden" name="questId" value="<?php echo h($selectedQuest['questId']); ?>">

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Evidence</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Awarded XP</th>
                            <th>Rubric</th>
                            <th>Feedback</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <?php
                            $result = isset($resultMap[$student['userId']]) ? $resultMap[$student['userId']] : null;
                            $score = $result ? $result['score'] : 0;
                            $status = $result ? $result['completionStatus'] : 'pending';
                            $xp = $result ? $result['awardedXP'] : $selectedQuest['XPValue'];
                            $rubricRating = $result ? $result['rubricRating'] : '';
                            $comment = $result ? $result['teacherComment'] : '';
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo h($student['name']); ?></strong><br>
                                    <span class="muted"><?php echo h($student['email']); ?></span>
                                </td>
                                <td>
                                    <?php if ($result && $result['evidenceLink']): ?>
                                        <a href="<?php echo h($result['evidenceLink']); ?>" target="_blank">Open link</a><br>
                                    <?php endif; ?>
                                    <?php if ($result && isset($result['evidenceFile']) && $result['evidenceFile']): ?>
                                        <a href="<?php echo h($result['evidenceFile']); ?>" target="_blank">Open file</a>
                                    <?php endif; ?>
                                    <?php if (!$result || (!$result['evidenceLink'] && (!isset($result['evidenceFile']) || !$result['evidenceFile']))): ?>
                                        <span class="muted">None</span>
                                    <?php else: ?>
                                    <?php endif; ?>
                                </td>
                                <td><input name="score[<?php echo h($student['userId']); ?>]" type="number" value="<?php echo h($score); ?>"></td>
                                <td>
                                    <select name="completionStatus[<?php echo h($student['userId']); ?>]">
                                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </td>
                                <td><input name="awardedXP[<?php echo h($student['userId']); ?>]" type="number" value="<?php echo h($xp); ?>"></td>
                                <td><input name="rubricRating[<?php echo h($student['userId']); ?>]" type="number" min="1" max="5" value="<?php echo h($rubricRating); ?>"></td>
                                <td><textarea name="teacherComment[<?php echo h($student['userId']); ?>]" rows="2"><?php echo h($comment); ?></textarea></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-footer">
                <button type="submit">Save results</button>
            </div>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php page_footer(); ?>
