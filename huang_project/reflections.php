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
        $errors[] = 'Only course teachers can respond to reflections.';
    }

    $reflectionId = isset($_POST['reflectionId']) ? $_POST['reflectionId'] : '';
    $teacherComment = isset($_POST['teacherComment']) ? trim($_POST['teacherComment']) : '';

    $reflection = null;
    if (!$errors) {
        $reflection = db_one(
            "SELECT r.*, q.title AS questTitle
             FROM reflections r
             INNER JOIN quests q ON q.questId = r.questId
             WHERE r.reflectionId = '" . db_escape($reflectionId) . "'
               AND q.courseId = '" . db_escape($courseId) . "'
             LIMIT 1"
        );

        if (!$reflection) {
            $errors[] = 'Reflection was not found.';
        }
    }

    if (!$errors) {
        db_exec(
            "UPDATE reflections
             SET teacherComment = '" . db_escape($teacherComment) . "'
             WHERE reflectionId = '" . db_escape($reflectionId) . "'"
        );
        create_notification($reflection['studentId'], $courseId, 'Reflection feedback', 'Your reflection for ' . $reflection['questTitle'] . ' has teacher feedback.', 'reflection');
        flash('success', 'Reflection feedback saved.');
        redirect_to('reflections.php?courseId=' . urlencode($courseId));
    }
}

if ($canManage) {
    $reflections = db_all(
        "SELECT r.*, q.title AS questTitle, u.name AS studentName, u.email AS studentEmail
         FROM reflections r
         INNER JOIN quests q ON q.questId = r.questId
         INNER JOIN users u ON u.userId = r.studentId
         WHERE q.courseId = '" . db_escape($courseId) . "'
         ORDER BY r.timestamp DESC"
    );
} else {
    $reflections = db_all(
        "SELECT r.*, q.title AS questTitle, u.name AS studentName, u.email AS studentEmail
         FROM reflections r
         INNER JOIN quests q ON q.questId = r.questId
         INNER JOIN users u ON u.userId = r.studentId
         WHERE q.courseId = '" . db_escape($courseId) . "'
           AND r.studentId = '" . db_escape($user['userId']) . "'
         ORDER BY r.timestamp DESC"
    );
}

page_header('Reflections');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Reflection Management</p>
            <h1><?php echo h($course['title']); ?> Reflections</h1>
            <p class="muted">Review exit tickets and teacher feedback linked to quests.</p>
        </div>
        <a class="button secondary" href="course.php?courseId=<?php echo h($courseId); ?>">Back to course</a>
    </div>
    <?php render_errors($errors); ?>
</section>

<section class="panel mt">
    <?php if (!$reflections): ?>
        <p class="muted">No reflections yet.</p>
    <?php else: ?>
        <div class="quest-grid">
            <?php foreach ($reflections as $reflection): ?>
                <article class="item-card">
                    <p class="eyebrow"><?php echo h($reflection['questTitle']); ?></p>
                    <h3><?php echo h($reflection['studentName']); ?></h3>
                    <p><?php echo h($reflection['text']); ?></p>
                    <p class="meta"><?php echo h($reflection['timestamp']); ?></p>

                    <?php if ($canManage): ?>
                        <form method="post" action="reflections.php?courseId=<?php echo h($courseId); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="reflectionId" value="<?php echo h($reflection['reflectionId']); ?>">
                            <label for="comment-<?php echo h($reflection['reflectionId']); ?>">Teacher feedback</label>
                            <textarea id="comment-<?php echo h($reflection['reflectionId']); ?>" name="teacherComment" rows="3"><?php echo h($reflection['teacherComment']); ?></textarea>
                            <div class="form-footer">
                                <button type="submit">Save feedback</button>
                            </div>
                        </form>
                    <?php elseif ($reflection['teacherComment']): ?>
                        <p class="muted">Teacher feedback: <?php echo h($reflection['teacherComment']); ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
