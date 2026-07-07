<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

require_role('admin');

$rows = db_all(
    "SELECT c.courseId, c.title, c.status, u.name AS teacherName,
        (SELECT COUNT(*) FROM enrollments e WHERE e.courseId = c.courseId AND e.roleInCourse = 'student') AS studentCount,
        (SELECT COUNT(*) FROM quests q WHERE q.courseId = c.courseId AND q.status = 'active') AS questCount,
        (SELECT COALESCE(AVG(xr.totalXP), 0) FROM xp_records xr WHERE xr.courseId = c.courseId) AS averageXP,
        (SELECT COUNT(*) FROM student_badges sb WHERE sb.courseId = c.courseId) AS badgeAwards
     FROM courses c
     LEFT JOIN users u ON u.userId = c.teacherId
     ORDER BY c.createdAt DESC"
);
$maxAverageXP = 0;
foreach ($rows as $row) {
    if ((int) $row['averageXP'] > $maxAverageXP) {
        $maxAverageXP = (int) $row['averageXP'];
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csvRows = array();
    foreach ($rows as $row) {
        $csvRows[] = array(
            $row['title'],
            $row['teacherName'],
            $row['status'],
            (int) $row['studentCount'],
            (int) $row['questCount'],
            round($row['averageXP']),
            (int) $row['badgeAwards'],
        );
    }

    csv_download(
        'admin-course-analytics.csv',
        array('Course', 'Teacher', 'Status', 'Students', 'Quests', 'Average XP', 'Badge Awards'),
        $csvRows
    );
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $lines = array('Course | Teacher | Status | Students | Quests | Average XP | Badge Awards');
    foreach ($rows as $row) {
        $lines[] = $row['title'] . ' | ' . $row['teacherName'] . ' | ' . $row['status'] . ' | ' . (int) $row['studentCount'] . ' | ' . (int) $row['questCount'] . ' | ' . round($row['averageXP']) . ' | ' . (int) $row['badgeAwards'];
    }
    if (!$rows) {
        $lines[] = 'No courses found.';
    }

    simple_pdf_download(
        'admin-course-analytics.pdf',
        'Administrator Course Analytics',
        array(array('title' => 'Aggregated Course Analytics', 'lines' => $lines))
    );
}

page_header('Admin Analytics');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Aggregated Analytics</p>
            <h1>Course Analytics</h1>
            <p class="muted">Cross-course engagement overview for administrators and researchers.</p>
        </div>
        <div class="actions compact-actions">
            <a class="button" href="admin_analytics.php?export=pdf">Download PDF</a>
            <a class="button secondary" href="admin_analytics.php?export=csv">Export CSV</a>
            <a class="button secondary" href="admin.php">Back to admin</a>
        </div>
    </div>
</section>

<section class="panel mt">
    <?php if (!$rows): ?>
        <p class="muted">No courses found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Teacher</th>
                        <th>Status</th>
                        <th>Students</th>
                        <th>Quests</th>
                        <th>Average XP</th>
                        <th>Badge awards</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo h($row['title']); ?></td>
                            <td><?php echo h($row['teacherName']); ?></td>
                            <td><?php echo h($row['status']); ?></td>
                            <td><?php echo h($row['studentCount']); ?></td>
                            <td><?php echo h($row['questCount']); ?></td>
                            <td>
                                <?php $xpWidth = $maxAverageXP > 0 ? round(((int) $row['averageXP'] / $maxAverageXP) * 100) : 0; ?>
                                <strong><?php echo h(round($row['averageXP'])); ?></strong>
                                <div class="mini-bar"><span style="width: <?php echo h($xpWidth); ?>%"></span></div>
                            </td>
                            <td><?php echo h($row['badgeAwards']); ?></td>
                            <td><a href="course.php?courseId=<?php echo h($row['courseId']); ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
