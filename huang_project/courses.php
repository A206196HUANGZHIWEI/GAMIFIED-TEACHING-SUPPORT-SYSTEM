<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

require_login();

$user = current_user();
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if (!$errors && $action === 'create_course') {
        if (!in_array($user['role'], array('teacher', 'admin'), true)) {
            $errors[] = 'Only teachers and administrators can create courses.';
        }

        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $academicLevel = isset($_POST['academicLevel']) ? trim($_POST['academicLevel']) : '';

        if ($title === '' || strlen($title) > 150) {
            $errors[] = 'Course title is required and must be 150 characters or fewer.';
        }

        if (!$errors) {
            $courseId = uuid_v4();
            $code = generate_enrollment_code();

            db_exec(
                "INSERT INTO courses (courseId, teacherId, title, description, academicLevel, enrollmentCode, status, createdAt, updatedAt)
                 VALUES ('" . db_escape($courseId) . "', '" . db_escape($user['userId']) . "', '" . db_escape($title) . "', '" . db_escape($description) . "', '" . db_escape($academicLevel) . "', '" . db_escape($code) . "', 'active', NOW(), NOW())"
            );

            flash('success', 'Course created. Enrolment code: ' . $code);
            redirect_to('course.php?courseId=' . urlencode($courseId));
        }
    }

    if (!$errors && $action === 'join_course') {
        if ($user['role'] !== 'student') {
            $errors[] = 'Only student accounts can join courses with a code.';
        }

        $code = isset($_POST['enrollmentCode']) ? strtoupper(trim($_POST['enrollmentCode'])) : '';

        if ($code === '') {
            $errors[] = 'Please enter an enrolment code.';
        }

        $course = null;
        if (!$errors) {
            $course = db_one(
                "SELECT * FROM courses
                 WHERE enrollmentCode = '" . db_escape($code) . "' AND status = 'active'
                 LIMIT 1"
            );

            if (!$course) {
                $errors[] = 'Course code was not found.';
            }
        }

        if (!$errors) {
            $exists = db_value(
                "SELECT COUNT(*) FROM enrollments
                 WHERE userId = '" . db_escape($user['userId']) . "'
                   AND courseId = '" . db_escape($course['courseId']) . "'",
                0
            );

            if ((int) $exists === 0) {
                db_exec(
                    "INSERT INTO enrollments (enrollmentId, userId, courseId, roleInCourse, createdAt)
                     VALUES ('" . db_escape(uuid_v4()) . "', '" . db_escape($user['userId']) . "', '" . db_escape($course['courseId']) . "', 'student', NOW())"
                );
                ensure_xp_record($course['courseId'], $user['userId']);
            }

            flash('success', 'Course joined.');
            redirect_to('course.php?courseId=' . urlencode($course['courseId']));
        }
    }
}

if ($user['role'] === 'teacher') {
    $courses = teacher_courses($user['userId']);
} elseif ($user['role'] === 'student') {
    $courses = student_courses($user['userId']);
} else {
    $courses = admin_courses();
}

page_header('Courses');
?>
<section class="panel">
    <p class="eyebrow">Course Management</p>
    <h1>Courses</h1>
    <p class="muted">Teachers create gamified courses. Students join with an enrolment code.</p>
    <?php render_errors($errors); ?>
</section>

<section class="grid two-col mt">
    <?php if (in_array($user['role'], array('teacher', 'admin'), true)): ?>
        <div class="panel">
            <h2>Create course</h2>
            <form method="post" action="courses.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_course">

                <label for="title">Course title</label>
                <input id="title" name="title" type="text" maxlength="150" required>

                <label for="academicLevel">Academic level</label>
                <input id="academicLevel" name="academicLevel" type="text" maxlength="100" placeholder="Secondary / Tertiary">

                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5"></textarea>

                <div class="form-footer">
                    <button type="submit">Create course</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($user['role'] === 'student'): ?>
        <div class="panel">
            <h2>Join course</h2>
            <form method="post" action="courses.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="join_course">

                <label for="enrollmentCode">Enrolment code</label>
                <input id="enrollmentCode" name="enrollmentCode" type="text" maxlength="20" required>

                <div class="form-footer">
                    <button type="submit">Join course</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="panel <?php echo $user['role'] === 'student' ? '' : 'span-two'; ?>">
        <h2>Your courses</h2>
        <?php if (!$courses): ?>
            <p class="muted">No courses found.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Level</th>
                            <th>Status</th>
                            <th>Code</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><?php echo h($course['title']); ?></td>
                                <td><?php echo h($course['academicLevel']); ?></td>
                                <td><?php echo h($course['status']); ?></td>
                                <td><?php echo can_manage_course($course) ? h($course['enrollmentCode']) : '-'; ?></td>
                                <td><a href="course.php?courseId=<?php echo h($course['courseId']); ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php page_footer(); ?>

