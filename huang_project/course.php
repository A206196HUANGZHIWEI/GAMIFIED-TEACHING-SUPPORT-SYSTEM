<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$course = require_course_access($courseId);
$user = current_user();
$canManage = can_manage_course($course);
$counts = course_counts($courseId);
$averageXP = course_average_xp($courseId);

$quests = db_all(
    "SELECT * FROM quests
     WHERE courseId = '" . db_escape($courseId) . "' AND status = 'active'
     ORDER BY deadline ASC, createdAt DESC
     LIMIT 5"
);

$announcements = db_all(
    "SELECT a.*, u.name AS authorName
     FROM announcements a
     LEFT JOIN users u ON u.userId = a.createdBy
     WHERE a.courseId = '" . db_escape($courseId) . "'
     ORDER BY a.createdAt DESC
     LIMIT 3"
);

$leaderboard = db_all(
    "SELECT u.name, xr.totalXP, xr.level
     FROM xp_records xr
     INNER JOIN users u ON u.userId = xr.studentId
     WHERE xr.courseId = '" . db_escape($courseId) . "'
     ORDER BY xr.totalXP DESC, u.name ASC
     LIMIT 5"
);

page_header($course['title']);
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Course</p>
            <h1><?php echo h($course['title']); ?></h1>
            <p class="muted"><?php echo h($course['description']); ?></p>
        </div>
        <?php if ($canManage): ?>
            <div class="code-box">
                <span>Enrolment code</span>
                <strong><?php echo h($course['enrollmentCode']); ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <div class="actions">
        <a class="button secondary" href="quests.php?courseId=<?php echo h($courseId); ?>">Quests</a>
        <a class="button secondary" href="badges.php?courseId=<?php echo h($courseId); ?>">Badges</a>
        <a class="button secondary" href="progress.php?courseId=<?php echo h($courseId); ?>">Progress</a>
        <a class="button secondary" href="announcements.php?courseId=<?php echo h($courseId); ?>">Announcements</a>
        <a class="button secondary" href="reflections.php?courseId=<?php echo h($courseId); ?>">Reflections</a>
        <a class="button secondary" href="reward_store.php?courseId=<?php echo h($courseId); ?>">XP Store</a>
        <?php if ($canManage): ?>
            <a class="button secondary" href="students.php?courseId=<?php echo h($courseId); ?>">Students</a>
            <a class="button secondary" href="course_settings.php?courseId=<?php echo h($courseId); ?>">Settings</a>
            <a class="button secondary" href="report.php?courseId=<?php echo h($courseId); ?>">Report</a>
            <a class="button" href="results.php?courseId=<?php echo h($courseId); ?>">Record results</a>
        <?php endif; ?>
    </div>

    <div class="stats">
        <div class="stat">
            <strong><?php echo h($counts['students']); ?></strong>
            <span>Students</span>
        </div>
        <div class="stat">
            <strong><?php echo h($counts['quests']); ?></strong>
            <span>Active quests</span>
        </div>
        <div class="stat">
            <strong><?php echo h($averageXP); ?></strong>
            <span>Average XP</span>
        </div>
    </div>
</section>

<section class="grid three-col mt">
    <div class="panel">
        <h2>Latest quests</h2>
        <?php if (!$quests): ?>
            <p class="muted">No quests yet.</p>
        <?php else: ?>
            <div class="list">
                <?php foreach ($quests as $quest): ?>
                    <a class="list-item" href="quests.php?courseId=<?php echo h($courseId); ?>">
                        <strong><?php echo h($quest['title']); ?></strong>
                        <span><?php echo h($quest['XPValue']); ?> XP</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Leaderboard</h2>
        <?php if (!$leaderboard): ?>
            <p class="muted">No progress data yet.</p>
        <?php else: ?>
            <div class="list">
                <?php foreach ($leaderboard as $rank => $row): ?>
                    <div class="list-item">
                        <strong><?php echo h(($rank + 1) . '. ' . $row['name']); ?></strong>
                        <span><?php echo h($row['totalXP']); ?> XP - Level <?php echo h($row['level']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Announcements</h2>
        <?php if (!$announcements): ?>
            <p class="muted">No announcements yet.</p>
        <?php else: ?>
            <div class="list">
                <?php foreach ($announcements as $item): ?>
                    <div class="list-item">
                        <strong><?php echo h($item['title']); ?></strong>
                        <span><?php echo h($item['createdAt']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php page_footer(); ?>
