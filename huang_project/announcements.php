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
        $errors[] = 'Only course teachers can post announcements.';
    }

    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';

    if ($title === '' || strlen($title) > 150) {
        $errors[] = 'Announcement title is required and must be 150 characters or fewer.';
    }

    if ($message === '') {
        $errors[] = 'Announcement message is required.';
    }

    if (!$errors) {
        db_exec(
            "INSERT INTO announcements (announcementId, courseId, title, message, createdBy, createdAt)
             VALUES ('" . db_escape(uuid_v4()) . "', '" . db_escape($courseId) . "', '" . db_escape($title) . "', '" . db_escape($message) . "', '" . db_escape($user['userId']) . "', NOW())"
        );

        $students = enrolled_students($courseId);
        foreach ($students as $student) {
            create_notification($student['userId'], $courseId, 'New announcement: ' . $title, $message, 'announcement');
        }

        flash('success', 'Announcement posted.');
        redirect_to('announcements.php?courseId=' . urlencode($courseId));
    }
}

$announcements = db_all(
    "SELECT a.*, u.name AS authorName
     FROM announcements a
     LEFT JOIN users u ON u.userId = a.createdBy
     WHERE a.courseId = '" . db_escape($courseId) . "'
     ORDER BY a.createdAt DESC"
);

page_header('Announcements');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Communication</p>
            <h1><?php echo h($course['title']); ?> Announcements</h1>
            <p class="muted">Post course updates, reminders, and challenge notices.</p>
        </div>
        <a class="button secondary" href="course.php?courseId=<?php echo h($courseId); ?>">Back to course</a>
    </div>
    <?php render_errors($errors); ?>
</section>

<?php if ($canManage): ?>
<section class="panel mt">
    <h2>Post announcement</h2>
    <form method="post" action="announcements.php?courseId=<?php echo h($courseId); ?>">
        <?php echo csrf_field(); ?>

        <label for="title">Title</label>
        <input id="title" name="title" type="text" maxlength="150" required>

        <label for="message">Message</label>
        <textarea id="message" name="message" rows="5" required></textarea>

        <div class="form-footer">
            <button type="submit">Post announcement</button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="panel mt">
    <h2>Announcement history</h2>
    <?php if (!$announcements): ?>
        <p class="muted">No announcements yet.</p>
    <?php else: ?>
        <div class="list">
            <?php foreach ($announcements as $item): ?>
                <article class="list-item">
                    <strong><?php echo h($item['title']); ?></strong>
                    <span><?php echo h($item['message']); ?></span>
                    <span class="muted">Posted by <?php echo h($item['authorName']); ?> on <?php echo h($item['createdAt']); ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
