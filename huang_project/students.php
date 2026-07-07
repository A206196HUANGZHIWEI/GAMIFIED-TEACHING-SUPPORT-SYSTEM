<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

$courseId = isset($_GET['courseId']) ? $_GET['courseId'] : '';
$course = require_course_manager($courseId);
$errors = array();
$messages = array();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = array();
    foreach (enrolled_students($courseId) as $student) {
        $rows[] = array(
            $student['name'],
            $student['email'],
            (int) $student['totalXP'],
            (int) $student['level'],
        );
    }
    csv_download('students-' . $courseId . '.csv', array('Name', 'Email', 'Total XP', 'Level'), $rows);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if (!$errors && $action === 'enroll_email') {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid student email.';
        }

        if (!$errors) {
            $student = db_one("SELECT * FROM users WHERE email = '" . db_escape($email) . "' AND role = 'student' LIMIT 1");

            if (!$student) {
                $errors[] = 'No student account found for that email.';
            } else {
                $exists = db_value(
                    "SELECT COUNT(*) FROM enrollments WHERE userId = '" . db_escape($student['userId']) . "' AND courseId = '" . db_escape($courseId) . "'",
                    0
                );

                if ((int) $exists === 0) {
                    db_exec(
                        "INSERT INTO enrollments (enrollmentId, userId, courseId, roleInCourse, createdAt)
                         VALUES ('" . db_escape(uuid_v4()) . "', '" . db_escape($student['userId']) . "', '" . db_escape($courseId) . "', 'student', NOW())"
                    );
                    ensure_xp_record($courseId, $student['userId']);
                    create_notification($student['userId'], $courseId, 'Course enrolment', 'You have been enrolled in ' . $course['title'] . '.', 'course');
                }

                flash('success', 'Student enrolled.');
                redirect_to('students.php?courseId=' . urlencode($courseId));
            }
        }
    }

    if (!$errors && $action === 'remove_student') {
        $studentId = isset($_POST['studentId']) ? $_POST['studentId'] : '';
        db_exec(
            "DELETE FROM enrollments
             WHERE courseId = '" . db_escape($courseId) . "'
               AND userId = '" . db_escape($studentId) . "'
               AND roleInCourse = 'student'"
        );
        flash('success', 'Student removed from course.');
        redirect_to('students.php?courseId=' . urlencode($courseId));
    }

    if (!$errors && $action === 'import_emails') {
        $emailsText = isset($_POST['emails']) ? trim($_POST['emails']) : '';
        $lines = preg_split('/\r\n|\r|\n/', $emailsText);
        $added = 0;
        $missing = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = str_getcsv($line);
            $email = trim(count($parts) > 1 ? $parts[1] : $parts[0]);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $missing[] = $line;
                continue;
            }

            $student = db_one("SELECT * FROM users WHERE email = '" . db_escape($email) . "' AND role = 'student' LIMIT 1");
            if (!$student) {
                $missing[] = $email;
                continue;
            }

            $exists = db_value(
                "SELECT COUNT(*) FROM enrollments WHERE userId = '" . db_escape($student['userId']) . "' AND courseId = '" . db_escape($courseId) . "'",
                0
            );

            if ((int) $exists === 0) {
                db_exec(
                    "INSERT INTO enrollments (enrollmentId, userId, courseId, roleInCourse, createdAt)
                     VALUES ('" . db_escape(uuid_v4()) . "', '" . db_escape($student['userId']) . "', '" . db_escape($courseId) . "', 'student', NOW())"
                );
                ensure_xp_record($courseId, $student['userId']);
                create_notification($student['userId'], $courseId, 'Course enrolment', 'You have been enrolled in ' . $course['title'] . '.', 'course');
                $added++;
            }
        }

        $message = $added . ' student(s) enrolled.';
        if ($missing) {
            $message .= ' Missing or invalid: ' . implode(', ', array_slice($missing, 0, 8));
        }
        flash('success', $message);
        redirect_to('students.php?courseId=' . urlencode($courseId));
    }
}

$students = enrolled_students($courseId);

page_header('Students');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Student Management</p>
            <h1><?php echo h($course['title']); ?> Students</h1>
            <p class="muted">Manage enrolment by code, individual email, or email list import.</p>
        </div>
        <a class="button secondary" href="course.php?courseId=<?php echo h($courseId); ?>">Back to course</a>
    </div>
    <?php render_errors($errors); ?>
</section>

<section class="grid two-col mt">
    <div class="panel">
        <h2>Add existing student</h2>
        <form method="post" action="students.php?courseId=<?php echo h($courseId); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="enroll_email">
            <label for="email">Student email</label>
            <input id="email" name="email" type="email" maxlength="190" required>
            <div class="form-footer">
                <button type="submit">Enroll student</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>Import email list</h2>
        <form method="post" action="students.php?courseId=<?php echo h($courseId); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="import_emails">
            <label for="emails">One email per line</label>
            <textarea id="emails" name="emails" rows="6" placeholder="student1@example.com&#10;student2@example.com"></textarea>
            <div class="form-footer">
                <button type="submit">Import students</button>
            </div>
        </form>
    </div>
</section>

<section class="panel mt">
    <div class="section-head">
        <h2>Current students</h2>
        <a class="button secondary" href="students.php?courseId=<?php echo h($courseId); ?>&export=csv">Export CSV</a>
    </div>

    <?php if (!$students): ?>
        <p class="muted">No students have joined yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>XP</th>
                        <th>Level</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo h($student['name']); ?></td>
                            <td><?php echo h($student['email']); ?></td>
                            <td><?php echo h((int) $student['totalXP']); ?></td>
                            <td><?php echo h((int) $student['level']); ?></td>
                            <td>
                                <form method="post" action="students.php?courseId=<?php echo h($courseId); ?>" class="inline-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="remove_student">
                                    <input type="hidden" name="studentId" value="<?php echo h($student['userId']); ?>">
                                    <button type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
