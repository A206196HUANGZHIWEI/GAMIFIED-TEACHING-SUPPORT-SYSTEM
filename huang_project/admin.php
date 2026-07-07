<?php
require __DIR__ . '/includes/gtss.php';
require __DIR__ . '/includes/layout.php';

require_role('admin');

$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrfToken)) {
        $errors[] = 'Invalid form token. Please try again.';
    }

    $userId = isset($_POST['userId']) ? $_POST['userId'] : '';
    $role = isset($_POST['role']) ? $_POST['role'] : '';

    if (!in_array($role, array('student', 'teacher', 'admin'), true)) {
        $errors[] = 'Invalid role selected.';
    }

    if (!$errors) {
        db_exec(
            "UPDATE users
             SET role = '" . db_escape($role) . "', updatedAt = NOW()
             WHERE userId = '" . db_escape($userId) . "'"
        );

        flash('success', 'User role updated.');
        redirect_to('admin.php');
    }
}

$users = db_all("SELECT userId, name, email, role, createdAt FROM users ORDER BY createdAt DESC");
$counts = array(
    'users' => (int) db_value("SELECT COUNT(*) FROM users", 0),
    'teachers' => (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'teacher'", 0),
    'students' => (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'student'", 0),
    'courses' => (int) db_value("SELECT COUNT(*) FROM courses", 0),
);

page_header('Admin');
?>
<section class="panel">
    <p class="eyebrow">Administration</p>
    <h1>System Administration</h1>
    <p class="muted">Manage user roles and review basic system totals.</p>
    <?php render_errors($errors); ?>
    <div class="actions">
        <a class="button secondary" href="admin_analytics.php">Aggregated analytics</a>
        <a class="button secondary" href="reward_admin.php">Store review</a>
        <a class="button secondary" href="settings.php">System settings</a>
    </div>

    <div class="stats">
        <div class="stat">
            <strong><?php echo h($counts['users']); ?></strong>
            <span>Users</span>
        </div>
        <div class="stat">
            <strong><?php echo h($counts['teachers']); ?></strong>
            <span>Teachers</span>
        </div>
        <div class="stat">
            <strong><?php echo h($counts['courses']); ?></strong>
            <span>Courses</span>
        </div>
    </div>
</section>

<section class="panel mt">
    <h2>User accounts</h2>
    <?php if (!$users): ?>
        <p class="muted">No users found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td><?php echo h($row['name']); ?></td>
                            <td><?php echo h($row['email']); ?></td>
                            <td>
                                <form method="post" action="admin.php" class="inline-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="userId" value="<?php echo h($row['userId']); ?>">
                                    <select name="role">
                                        <option value="student" <?php echo $row['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                        <option value="teacher" <?php echo $row['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                        <option value="admin" <?php echo $row['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                    <button type="submit">Save</button>
                                </form>
                            </td>
                            <td><?php echo h($row['createdAt']); ?></td>
                            <td><?php echo status_badge(role_label($row['role'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
