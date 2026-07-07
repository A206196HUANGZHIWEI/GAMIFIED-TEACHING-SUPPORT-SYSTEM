<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$course = require_course_manager($courseId);

$counts = course_counts($courseId);
$averageXP = course_average_xp($courseId);
$leaderboardSettings = course_leaderboard_settings($courseId);
$analyticsRows = course_leaderboard_rows($courseId);
$maxReportXP = 0;
foreach ($analyticsRows as $row) {
    if ((int) $row['totalXP'] > $maxReportXP) {
        $maxReportXP = (int) $row['totalXP'];
    }
}
$quests = db_all(
    "SELECT q.title, q.XPValue, q.deadline,
        (SELECT COUNT(*) FROM results r WHERE r.questId = q.questId AND r.completionStatus = 'completed') AS completedCount
     FROM quests q
     WHERE q.courseId = '" . db_escape($courseId) . "' AND q.status = 'active'
     ORDER BY q.createdAt DESC"
);
$badges = db_all(
    "SELECT b.name, b.threshold,
        (SELECT COUNT(*) FROM student_badges sb WHERE sb.badgeId = b.badgeId) AS awardedCount
     FROM badges b
     WHERE b.courseId = '" . db_escape($courseId) . "'
     ORDER BY b.threshold ASC"
);

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $sections = array();
    $sections[] = array(
        'title' => 'Summary',
        'lines' => array(
            'Course: ' . $course['title'],
            'Students: ' . $counts['students'],
            'Active quests: ' . $counts['quests'],
            'Average XP: ' . $averageXP,
        ),
    );

    $studentLines = array('Rank | Student | Email | XP | Level | Badges | Completion');
    foreach ($analyticsRows as $rank => $row) {
        $totalQuests = (int) $row['totalQuests'];
        $completedQuests = (int) $row['completedQuests'];
        $completionRate = $totalQuests > 0 ? round(($completedQuests / $totalQuests) * 100) : 0;
        $displayName = leaderboard_display_name($row, $rank, $leaderboardSettings, true);
        $email = $leaderboardSettings['anonymity'] === 'all' ? '' : $row['email'];
        $studentLines[] = ($rank + 1) . ' | ' . $displayName . ' | ' . $email . ' | ' . (int) $row['totalXP'] . ' | Level ' . (int) $row['level'] . ' | ' . (int) $row['badgeCount'] . ' | ' . $completedQuests . '/' . $totalQuests . ' (' . $completionRate . '%)';
    }
    $sections[] = array('title' => 'Student Analytics', 'lines' => $studentLines);

    $questLines = array();
    foreach ($quests as $quest) {
        $rate = $counts['students'] > 0 ? round(((int) $quest['completedCount'] / $counts['students']) * 100) : 0;
        $questLines[] = $quest['title'] . ' | ' . (int) $quest['completedCount'] . ' completed | ' . $rate . '% | ' . (int) $quest['XPValue'] . ' XP';
    }
    if (!$questLines) {
        $questLines[] = 'No active quests.';
    }
    $sections[] = array('title' => 'Quest Completion', 'lines' => $questLines);

    $badgeLines = array();
    foreach ($badges as $badge) {
        $rate = $counts['students'] > 0 ? round(((int) $badge['awardedCount'] / $counts['students']) * 100) : 0;
        $badgeLines[] = $badge['name'] . ' | ' . (int) $badge['awardedCount'] . ' awarded | ' . $rate . '% | threshold ' . (int) $badge['threshold'] . ' XP';
    }
    if (!$badgeLines) {
        $badgeLines[] = 'No badges configured.';
    }
    $sections[] = array('title' => 'Badge Distribution', 'lines' => $badgeLines);

    simple_pdf_download('course-report-' . $courseId . '.pdf', 'Course Report - ' . $course['title'], $sections);
}

page_header('Course Report');
?>
<section class="panel print-actions">
    <div class="section-head">
        <div>
            <p class="eyebrow">Course Report</p>
            <h1><?php echo h($course['title']); ?></h1>
            <p class="muted">Download a server-generated PDF or use the browser print command for a visual page copy.</p>
        </div>
        <div class="actions compact-actions">
            <a class="button" href="report.php?courseId=<?php echo h($courseId); ?>&export=pdf">Download PDF</a>
            <button type="button" onclick="window.print()">Print / Save PDF</button>
            <a class="button secondary" href="course.php?courseId=<?php echo h($courseId); ?>">Back</a>
        </div>
    </div>
</section>

<section class="panel mt">
    <h2>Summary</h2>
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

<section class="panel mt">
    <h2>Student analytics</h2>
    <?php if (!$analyticsRows): ?>
        <p class="muted">No student analytics yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>XP</th>
                        <th>Level</th>
                        <th>Badges</th>
                        <th>Completion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analyticsRows as $rank => $row): ?>
                        <?php
                        $totalQuests = (int) $row['totalQuests'];
                        $completedQuests = (int) $row['completedQuests'];
                        $completionRate = $totalQuests > 0 ? round(($completedQuests / $totalQuests) * 100) : 0;
                        $displayName = leaderboard_display_name($row, $rank, $leaderboardSettings, true);
                        $email = $leaderboardSettings['anonymity'] === 'all' ? '' : $row['email'];
                        ?>
                        <tr>
                            <td><?php echo h($displayName); ?></td>
                            <td><?php echo h($email); ?></td>
                            <td>
                                <?php $xpWidth = $maxReportXP > 0 ? round(((int) $row['totalXP'] / $maxReportXP) * 100) : 0; ?>
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
</section>

<section class="grid two-col mt">
    <div class="panel">
        <h2>Quest completion</h2>
        <?php if (!$quests): ?>
            <p class="muted">No quests yet.</p>
        <?php else: ?>
            <div class="bar-list">
                <?php foreach ($quests as $quest): ?>
                    <?php $rate = $counts['students'] > 0 ? round(((int) $quest['completedCount'] / $counts['students']) * 100) : 0; ?>
                    <div class="bar-row">
                        <div class="bar-label">
                            <strong><?php echo h($quest['title']); ?></strong>
                            <span><?php echo h($quest['completedCount']); ?> completed | <?php echo h($quest['XPValue']); ?> XP</span>
                        </div>
                        <div class="bar-track"><span style="width: <?php echo h($rate); ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Badge distribution</h2>
        <?php if (!$badges): ?>
            <p class="muted">No badges yet.</p>
        <?php else: ?>
            <div class="bar-list">
                <?php foreach ($badges as $badge): ?>
                    <?php $rate = $counts['students'] > 0 ? round(((int) $badge['awardedCount'] / $counts['students']) * 100) : 0; ?>
                    <div class="bar-row">
                        <div class="bar-label">
                            <strong><?php echo h($badge['name']); ?></strong>
                            <span><?php echo h($badge['awardedCount']); ?> awarded | <?php echo h($badge['threshold']); ?> XP threshold</span>
                        </div>
                        <div class="bar-track"><span style="width: <?php echo h($rate); ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php page_footer(); ?>
