<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$course = require_course_access($courseId);
$user = current_user();
$canManage = can_manage_course($course);
$errors = array();
$leaderboardSettings = course_leaderboard_settings($courseId);
$leaderboardSortOptions = leaderboard_sort_options();
$leaderboardAnonymityOptions = leaderboard_anonymity_options();

if ($canManage && isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = array();
    $analyticsRows = course_leaderboard_rows($courseId);

    foreach ($analyticsRows as $rank => $row) {
        $totalQuests = (int) $row['totalQuests'];
        $completedQuests = (int) $row['completedQuests'];
        $completionRate = $totalQuests > 0 ? round(($completedQuests / $totalQuests) * 100) : 0;
        $displayName = leaderboard_display_name($row, $rank, $leaderboardSettings, true);
        $email = $leaderboardSettings['anonymity'] === 'all' ? '' : $row['email'];
        $rows[] = array(
            $displayName,
            $email,
            (int) $row['totalXP'],
            (int) $row['level'],
            (int) $row['badgeCount'],
            $completedQuests,
            $totalQuests,
            $completionRate . '%',
        );
    }

    csv_download(
        'analytics-' . $courseId . '.csv',
        array('Name', 'Email', 'Total XP', 'Level', 'Badges', 'Completed Quests', 'Total Quests', 'Completion Rate'),
        $rows
    );
}

if ($canManage) {
    send_due_quest_reminders_for_course($courseId);
} elseif ($user['role'] === 'student') {
    send_due_quest_reminders_for_user($user['userId']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    if (!$canManage) {
        $errors[] = 'Only course teachers can manage milestones.';
    }

    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $requiredXP = isset($_POST['requiredXP']) ? (int) $_POST['requiredXP'] : 0;

    if ($name === '' || strlen($name) > 100) {
        $errors[] = 'Milestone name is required and must be 100 characters or fewer.';
    }

    if ($requiredXP < 0 || $requiredXP > 100000) {
        $errors[] = 'Required XP must be between 0 and 100000.';
    }

    if (!$errors) {
        db_exec(
            "INSERT INTO milestones (milestoneId, courseId, name, description, requiredXP, createdAt)
             VALUES ('" . db_escape(uuid_v4()) . "', '" . db_escape($courseId) . "', '" . db_escape($name) . "', '" . db_escape($description) . "', " . (int) $requiredXP . ", NOW())"
        );

        flash('success', 'Milestone created.');
        redirect_to('progress.php?courseId=' . urlencode($courseId));
    }
}

$students = enrolled_students($courseId);
$leaderboard = course_leaderboard_rows($courseId);
$maxXP = 0;
foreach ($leaderboard as $row) {
    if ((int) $row['totalXP'] > $maxXP) {
        $maxXP = (int) $row['totalXP'];
    }
}

$quests = db_all(
    "SELECT q.title, q.XPValue,
        (SELECT COUNT(*) FROM results r WHERE r.questId = q.questId AND r.completionStatus = 'completed') AS completedCount
     FROM quests q
     WHERE q.courseId = '" . db_escape($courseId) . "' AND q.status = 'active'
     ORDER BY q.createdAt DESC"
);

$milestones = db_all(
    "SELECT * FROM milestones
     WHERE courseId = '" . db_escape($courseId) . "'
     ORDER BY requiredXP ASC"
);

$myProgress = null;
if ($user['role'] === 'student') {
    ensure_xp_record($courseId, $user['userId']);
    $myProgress = db_one(
        "SELECT * FROM xp_records
         WHERE courseId = '" . db_escape($courseId) . "'
           AND studentId = '" . db_escape($user['userId']) . "'
         LIMIT 1"
    );
}

page_header('Progress');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Progress and Analytics</p>
            <h1><?php echo h($course['title']); ?> Progress</h1>
            <p class="muted">Track XP, levels, badge counts, completion rates, and milestone progress.</p>
        </div>
        <div class="actions compact-actions">
            <?php if ($canManage): ?>
                <a class="button secondary" href="progress.php?courseId=<?php echo h($courseId); ?>&export=csv">Export CSV</a>
            <?php endif; ?>
            <a class="button secondary" href="course.php?courseId=<?php echo h($courseId); ?>">Back to course</a>
        </div>
    </div>
    <?php render_errors($errors); ?>
</section>

<?php if ($user['role'] === 'student' && $myProgress): ?>
<section class="panel mt">
    <p class="eyebrow">My Progress</p>
    <h2><?php echo h($myProgress['totalXP']); ?> XP - Level <?php echo h($myProgress['level']); ?></h2>
    <div class="progress-bar">
        <span style="width: <?php echo h(min(100, (int) $myProgress['totalXP'] % 100)); ?>%"></span>
    </div>
    <p class="muted">Level progress resets every 100 XP.</p>
</section>
<?php endif; ?>

<?php if ($canManage): ?>
<section class="panel mt">
    <h2>Create milestone</h2>
    <form method="post" action="progress.php?courseId=<?php echo h($courseId); ?>">
        <?php echo csrf_field(); ?>
        <div class="grid two-col tight">
            <div>
                <label for="name">Milestone name</label>
                <input id="name" name="name" type="text" maxlength="100" required>
            </div>
            <div>
                <label for="requiredXP">Required XP</label>
                <input id="requiredXP" name="requiredXP" type="number" min="0" max="100000" value="100">
            </div>
        </div>
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3"></textarea>
        <div class="form-footer">
            <button type="submit">Create milestone</button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="grid two-col mt">
    <div class="panel">
        <h2>Leaderboard</h2>
        <p class="muted">Ranking: <?php echo h($leaderboardSortOptions[$leaderboardSettings['sort']]); ?>. Privacy: <?php echo h($leaderboardAnonymityOptions[$leaderboardSettings['anonymity']]); ?>.</p>
        <?php if (!$leaderboard): ?>
            <p class="muted">No progress data yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>XP</th>
                            <th>Level</th>
                            <th>Badges</th>
                            <th>Completion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard as $rank => $row): ?>
                            <?php
                            $xpWidth = $maxXP > 0 ? round(((int) $row['totalXP'] / $maxXP) * 100) : 0;
                            $totalQuests = (int) $row['totalQuests'];
                            $completedQuests = (int) $row['completedQuests'];
                            $completionRate = $totalQuests > 0 ? round(($completedQuests / $totalQuests) * 100) : 0;
                            ?>
                            <tr>
                                <td class="rank-cell"><?php echo h($rank + 1); ?></td>
                                <td><?php echo h(leaderboard_display_name($row, $rank, $leaderboardSettings, $canManage)); ?></td>
                                <td>
                                    <strong><?php echo h($row['totalXP']); ?></strong>
                                    <div class="mini-bar"><span style="width: <?php echo h($xpWidth); ?>%"></span></div>
                                </td>
                                <td><?php echo h($row['level']); ?></td>
                                <td><?php echo h($row['badgeCount']); ?></td>
                                <td><?php echo h($completedQuests . '/' . $totalQuests . ' (' . $completionRate . '%)'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Quest completion</h2>
        <?php if (!$quests): ?>
            <p class="muted">No quests yet.</p>
        <?php else: ?>
            <div class="bar-list">
                <?php foreach ($quests as $quest): ?>
                    <?php
                    $rate = count($students) > 0 ? round(((int) $quest['completedCount'] / count($students)) * 100) : 0;
                    ?>
                    <div class="bar-row">
                        <div class="bar-label">
                            <strong><?php echo h($quest['title']); ?></strong>
                            <span><?php echo h($quest['completedCount']); ?> completed - <?php echo h($rate); ?>%</span>
                        </div>
                        <div class="bar-track"><span style="width: <?php echo h($rate); ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="panel mt">
    <h2>Milestones</h2>
    <?php if (!$milestones): ?>
        <p class="muted">No milestones yet.</p>
    <?php else: ?>
        <div class="quest-grid">
            <?php foreach ($milestones as $milestone): ?>
                <article class="item-card">
                    <h3><?php echo h($milestone['name']); ?></h3>
                    <p class="muted"><?php echo h($milestone['description']); ?></p>
                    <p><?php echo status_badge($milestone['requiredXP'] . ' XP required'); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
