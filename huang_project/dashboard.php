<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

require_login();

$user = current_user();
$courses = array();

if ($user['role'] === 'teacher') {
    $courses = teacher_courses($user['userId']);
    $courseCount = count($courses);
    $studentCount = (int) db_value(
        "SELECT COUNT(*)
         FROM enrollments e
         INNER JOIN courses c ON c.courseId = e.courseId
         WHERE c.teacherId = '" . db_escape($user['userId']) . "' AND e.roleInCourse = 'student'",
        0
    );
    $questCount = (int) db_value(
        "SELECT COUNT(*)
         FROM quests q
         INNER JOIN courses c ON c.courseId = q.courseId
         WHERE c.teacherId = '" . db_escape($user['userId']) . "' AND q.status = 'active'",
        0
    );
} elseif ($user['role'] === 'student') {
    $courses = student_courses($user['userId']);
    send_due_quest_reminders_for_user($user['userId']);
    $courseCount = count($courses);
    $studentCount = (int) db_value("SELECT COALESCE(SUM(totalXP), 0) FROM xp_records WHERE studentId = '" . db_escape($user['userId']) . "'", 0);
    $questCount = (int) db_value("SELECT COUNT(*) FROM student_badges WHERE studentId = '" . db_escape($user['userId']) . "'", 0);
} else {
    $courses = admin_courses();
    $courseCount = (int) db_value("SELECT COUNT(*) FROM courses", 0);
    $studentCount = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'student'", 0);
    $questCount = (int) db_value("SELECT COUNT(*) FROM quests WHERE status = 'active'", 0);
}

$notifications = db_all(
    "SELECT * FROM notifications
     WHERE userId = '" . db_escape($user['userId']) . "'
     ORDER BY createdAt DESC
     LIMIT 5"
);

page_header('Dashboard');
?>
<section class="panel">
    <p class="eyebrow"><?php echo h(role_label($user['role'])); ?> Dashboard</p>
    <h1>Welcome, <?php echo h($user['name']); ?></h1>
    <p class="muted">Use this dashboard to access courses, quests, rewards, and progress data.</p>

    <div class="stats">
        <div class="stat">
            <strong><?php echo h($courseCount); ?></strong>
            <span><?php echo $user['role'] === 'student' ? 'Joined courses' : 'Courses'; ?></span>
        </div>
        <div class="stat">
            <strong><?php echo h($studentCount); ?></strong>
            <span><?php echo $user['role'] === 'student' ? 'Total XP' : 'Students'; ?></span>
        </div>
        <div class="stat">
            <strong><?php echo h($questCount); ?></strong>
            <span><?php echo $user['role'] === 'student' ? 'Badges earned' : 'Active quests'; ?></span>
        </div>
    </div>
</section>

<section class="grid two-col mt">
    <div class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Courses</p>
                <h2>Recent courses</h2>
            </div>
            <a class="button secondary" href="courses.php">Manage</a>
        </div>

        <?php if (!$courses): ?>
            <p class="muted">No courses yet.</p>
        <?php else: ?>
            <div class="list">
                <?php foreach (array_slice($courses, 0, 5) as $course): ?>
                    <a class="list-item" href="course.php?courseId=<?php echo h($course['courseId']); ?>">
                        <strong><?php echo h($course['title']); ?></strong>
                        <span><?php echo h(isset($course['academicLevel']) ? $course['academicLevel'] : ''); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <p class="eyebrow">Notifications</p>
        <h2>Latest updates</h2>
        <?php if (!$notifications): ?>
            <p class="muted">No notifications yet.</p>
        <?php else: ?>
            <div class="list">
                <?php foreach ($notifications as $notice): ?>
                    <div class="list-item">
                        <strong><?php echo h($notice['title']); ?></strong>
                        <span><?php echo h($notice['message']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php page_footer(); ?>
